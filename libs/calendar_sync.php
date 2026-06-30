<?php
/**
 * Two-way calendar sync for Google Calendar and Microsoft Outlook
 * Calendar, built on plain cURL — no SDK / Composer dependency, same
 * philosophy as the rest of this app.
 *
 * "Push" sends a local task to the provider as an event.
 * "Pull" reads events from the provider and either updates the matching
 * local task (matched via a stored extended property holding the task
 * id) or, if the event has no such property, imports it as a brand new
 * task — that's the "someone added it straight into Google/Outlook"
 * half of two-way sync.
 *
 * Every function here returns a result array rather than throwing, so a
 * sync failure never takes the rest of the app down with it.
 */

const MS_EXT_PROP_NAMESPACE = '8f2a6e2e-4b8a-4d8a-9b8a-6a2e2e8f2a6e'; // arbitrary, fixed namespace for our extended property

// ---------------------------------------------------------------------
// Generic HTTP helper
// ---------------------------------------------------------------------

function cal_http(string $method, string $url, array $headers = [], $body = null): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = is_array($body) ? http_build_query($body) : $body;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno) {
        return ['ok' => false, 'status' => 0, 'error' => $error, 'data' => null];
    }

    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    return [
        'ok' => $status >= 200 && $status < 300,
        'status' => $status,
        'error' => $status >= 400 ? ($decoded['error']['message'] ?? $decoded['error_description'] ?? $raw) : null,
        'data' => $decoded,
    ];
}

function cal_http_json(string $method, string $url, string $bearer, ?array $jsonBody = null): array
{
    $headers = ['Authorization: Bearer ' . $bearer, 'Content-Type: application/json'];
    $body = $jsonBody !== null ? json_encode($jsonBody) : null;
    return cal_http($method, $url, $headers, $body);
}

// ---------------------------------------------------------------------
// OAuth — Google
// ---------------------------------------------------------------------

function google_oauth_url(PDO $pdo, string $redirectUri, string $state): string
{
    $clientId = get_setting($pdo, 'google_client_id');
    $params = [
        'client_id' => $clientId,
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'access_type' => 'offline',
        'prompt' => 'consent',
        'scope' => 'https://www.googleapis.com/auth/calendar.events https://www.googleapis.com/auth/calendar.readonly',
        'state' => $state,
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function google_exchange_code(PDO $pdo, string $code, string $redirectUri): array
{
    return cal_http('POST', 'https://oauth2.googleapis.com/token', [], [
        'client_id' => get_setting($pdo, 'google_client_id'),
        'client_secret' => get_setting($pdo, 'google_client_secret'),
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirectUri,
    ]);
}

function google_refresh_token(PDO $pdo, string $refreshToken): array
{
    return cal_http('POST', 'https://oauth2.googleapis.com/token', [], [
        'client_id' => get_setting($pdo, 'google_client_id'),
        'client_secret' => get_setting($pdo, 'google_client_secret'),
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
    ]);
}

// ---------------------------------------------------------------------
// OAuth — Microsoft
// ---------------------------------------------------------------------

function microsoft_oauth_url(PDO $pdo, string $redirectUri, string $state): string
{
    $params = [
        'client_id' => get_setting($pdo, 'microsoft_client_id'),
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'response_mode' => 'query',
        'scope' => 'offline_access Calendars.ReadWrite',
        'state' => $state,
    ];
    return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query($params);
}

function microsoft_exchange_code(PDO $pdo, string $code, string $redirectUri): array
{
    return cal_http('POST', 'https://login.microsoftonline.com/common/oauth2/v2.0/token', [], [
        'client_id' => get_setting($pdo, 'microsoft_client_id'),
        'client_secret' => get_setting($pdo, 'microsoft_client_secret'),
        'code' => $code,
        'grant_type' => 'authorization_code',
        'redirect_uri' => $redirectUri,
        'scope' => 'offline_access Calendars.ReadWrite',
    ]);
}

function microsoft_refresh_token(PDO $pdo, string $refreshToken): array
{
    return cal_http('POST', 'https://login.microsoftonline.com/common/oauth2/v2.0/token', [], [
        'client_id' => get_setting($pdo, 'microsoft_client_id'),
        'client_secret' => get_setting($pdo, 'microsoft_client_secret'),
        'refresh_token' => $refreshToken,
        'grant_type' => 'refresh_token',
        'scope' => 'offline_access Calendars.ReadWrite',
    ]);
}

// ---------------------------------------------------------------------
// Token upkeep
// ---------------------------------------------------------------------

/** Refreshes the access token if it's expired/near-expiry. Returns the (possibly updated) account row, or null on failure. */
function ensure_fresh_token(PDO $pdo, array $account): ?array
{
    $expiresAt = $account['token_expires_at'] ? strtotime($account['token_expires_at']) : 0;
    if ($expiresAt > time() + 60) {
        return $account; // still valid for at least another minute
    }
    if (empty($account['refresh_token'])) {
        return null;
    }

    $result = $account['provider'] === 'google'
        ? google_refresh_token($pdo, $account['refresh_token'])
        : microsoft_refresh_token($pdo, $account['refresh_token']);

    if (!$result['ok'] || empty($result['data']['access_token'])) {
        return null;
    }

    $newAccessToken = $result['data']['access_token'];
    $expiresIn = (int)($result['data']['expires_in'] ?? 3600);
    $newExpiry = date('Y-m-d H:i:s', time() + $expiresIn);
    // Google only returns a new refresh_token sometimes; Microsoft usually rotates it.
    $newRefreshToken = $result['data']['refresh_token'] ?? $account['refresh_token'];

    $pdo->prepare('UPDATE legalops_calendar_accounts SET access_token = ?, refresh_token = ?, token_expires_at = ? WHERE id = ?')
        ->execute([$newAccessToken, $newRefreshToken, $newExpiry, $account['id']]);

    $account['access_token'] = $newAccessToken;
    $account['refresh_token'] = $newRefreshToken;
    $account['token_expires_at'] = $newExpiry;
    return $account;
}

// ---------------------------------------------------------------------
// Push: local task -> provider event
// ---------------------------------------------------------------------

function task_to_google_event(array $task): array
{
    $event = [
        'summary' => $task['title'],
        'description' => $task['notes'] ?? '',
        'extendedProperties' => ['private' => ['legalops_task_id' => (string)$task['id']]],
    ];
    if (!empty($task['due_time'])) {
        $start = $task['due_on'] . 'T' . $task['due_time'];
        $end = date('Y-m-d\TH:i:s', strtotime($start . ' +30 minutes'));
        $event['start'] = ['dateTime' => $start, 'timeZone' => APP_TIMEZONE];
        $event['end'] = ['dateTime' => $end, 'timeZone' => APP_TIMEZONE];
    } else {
        $event['start'] = ['date' => $task['due_on']];
        $event['end'] = ['date' => date('Y-m-d', strtotime($task['due_on'] . ' +1 day'))];
    }
    return $event;
}

function task_to_microsoft_event(array $task): array
{
    $event = [
        'subject' => $task['title'],
        'body' => ['contentType' => 'text', 'content' => $task['notes'] ?? ''],
        'singleValueExtendedProperties' => [[
            'id' => 'String {' . MS_EXT_PROP_NAMESPACE . '} Name legalops_task_id',
            'value' => (string)$task['id'],
        ]],
    ];
    if (!empty($task['due_time'])) {
        $start = $task['due_on'] . 'T' . $task['due_time'];
        $end = date('Y-m-d\TH:i:s', strtotime($start . ' +30 minutes'));
        $event['isAllDay'] = false;
        $event['start'] = ['dateTime' => $start, 'timeZone' => APP_TIMEZONE];
        $event['end'] = ['dateTime' => $end, 'timeZone' => APP_TIMEZONE];
    } else {
        $event['isAllDay'] = true;
        $event['start'] = ['dateTime' => $task['due_on'] . 'T00:00:00', 'timeZone' => APP_TIMEZONE];
        $event['end'] = ['dateTime' => date('Y-m-d', strtotime($task['due_on'] . ' +1 day')) . 'T00:00:00', 'timeZone' => APP_TIMEZONE];
    }
    return $event;
}

/** Push one task to one connected account. Updates the task's stored event id. */
function push_task_to_account(PDO $pdo, array $account, array $task): array
{
    if (empty($task['due_on'])) {
        return ['ok' => false, 'error' => 'Task has no due date, nothing to sync.'];
    }
    $account = ensure_fresh_token($pdo, $account);
    if (!$account) {
        return ['ok' => false, 'error' => 'Could not refresh access token.'];
    }

    if ($account['provider'] === 'google') {
        $calendarId = rawurlencode($account['calendar_id']);
        $body = task_to_google_event($task);
        $eventId = $task['google_event_id'] ?? null;
        $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events" . ($eventId ? "/{$eventId}" : '');
        $result = cal_http_json($eventId ? 'PATCH' : 'POST', $url, $account['access_token'], $body);

        // The stored event id might be stale (deleted on the Google side) — recreate it.
        if (!$result['ok'] && $eventId && in_array($result['status'], [404, 410], true)) {
            $result = cal_http_json('POST', "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events", $account['access_token'], $body);
        }

        if ($result['ok'] && !empty($result['data']['id'])) {
            $pdo->prepare('UPDATE legalops_tasks SET google_event_id = ? WHERE id = ?')->execute([$result['data']['id'], $task['id']]);
        }
        return ['ok' => $result['ok'], 'error' => $result['error']];
    }

    // Microsoft
    $body = task_to_microsoft_event($task);
    $eventId = $task['microsoft_event_id'] ?? null;
    $url = 'https://graph.microsoft.com/v1.0/me/events' . ($eventId ? "/{$eventId}" : '');
    $result = cal_http_json($eventId ? 'PATCH' : 'POST', $url, $account['access_token'], $body);

    if (!$result['ok'] && $eventId && $result['status'] === 404) {
        $result = cal_http_json('POST', 'https://graph.microsoft.com/v1.0/me/events', $account['access_token'], $body);
    }

    if ($result['ok'] && !empty($result['data']['id'])) {
        $pdo->prepare('UPDATE legalops_tasks SET microsoft_event_id = ? WHERE id = ?')->execute([$result['data']['id'], $task['id']]);
    }
    return ['ok' => $result['ok'], 'error' => $result['error']];
}

/** Delete the remote event for a task on one account (e.g. task deleted locally). */
function delete_task_event(PDO $pdo, array $account, array $task): void
{
    $account = ensure_fresh_token($pdo, $account);
    if (!$account) {
        return;
    }
    if ($account['provider'] === 'google' && !empty($task['google_event_id'])) {
        $calendarId = rawurlencode($account['calendar_id']);
        cal_http_json('DELETE', "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$task['google_event_id']}", $account['access_token']);
    } elseif ($account['provider'] === 'microsoft' && !empty($task['microsoft_event_id'])) {
        cal_http_json('DELETE', "https://graph.microsoft.com/v1.0/me/events/{$task['microsoft_event_id']}", $account['access_token']);
    }
}

// ---------------------------------------------------------------------
// Pull: provider events -> local tasks
// ---------------------------------------------------------------------

function pull_google_events(PDO $pdo, array $account, int $uid): array
{
    $calendarId = rawurlencode($account['calendar_id']);
    $timeMin = date('c', strtotime('-7 days'));
    $timeMax = date('c', strtotime('+60 days'));
    $url = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events?" . http_build_query([
        'timeMin' => $timeMin, 'timeMax' => $timeMax, 'singleEvents' => 'true', 'maxResults' => 250,
    ]);
    $result = cal_http_json('GET', $url, $account['access_token']);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'imported' => 0, 'updated' => 0];
    }

    [$imported, $updated] = sync_pulled_events($pdo, $uid, 'google', $result['data']['items'] ?? [], function ($e) {
        return [
            'remote_id' => $e['id'],
            'task_id' => $e['extendedProperties']['private']['legalops_task_id'] ?? null,
            'title' => $e['summary'] ?? '(untitled)',
            'notes' => $e['description'] ?? null,
            'due_on' => substr($e['start']['date'] ?? $e['start']['dateTime'] ?? '', 0, 10),
            'due_time' => isset($e['start']['dateTime']) ? substr($e['start']['dateTime'], 11, 8) : null,
            'cancelled' => ($e['status'] ?? '') === 'cancelled',
        ];
    });

    return ['ok' => true, 'error' => null, 'imported' => $imported, 'updated' => $updated];
}

function pull_microsoft_events(PDO $pdo, array $account, int $uid): array
{
    $start = date('c', strtotime('-7 days'));
    $end = date('c', strtotime('+60 days'));
    $url = 'https://graph.microsoft.com/v1.0/me/calendarView?' . http_build_query([
        'startDateTime' => $start, 'endDateTime' => $end, '$top' => 250,
        '$select' => 'id,subject,body,start,end,isAllDay',
    ]);
    $headers = ['Authorization: Bearer ' . $account['access_token'], 'Prefer: outlook.timezone="' . APP_TIMEZONE . '"'];
    $result = cal_http('GET', $url, $headers);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'imported' => 0, 'updated' => 0];
    }

    [$imported, $updated] = sync_pulled_events($pdo, $uid, 'microsoft', $result['data']['value'] ?? [], function ($e) {
        return [
            'remote_id' => $e['id'],
            'task_id' => null, // Microsoft extended properties need a separate $expand call; matched by event id instead (see sync_pulled_events)
            'title' => $e['subject'] ?? '(untitled)',
            'notes' => $e['body']['content'] ?? null,
            'due_on' => substr($e['start']['dateTime'] ?? '', 0, 10),
            'due_time' => !($e['isAllDay'] ?? false) ? substr($e['start']['dateTime'] ?? '', 11, 8) : null,
            'cancelled' => false,
        ];
    });

    return ['ok' => true, 'error' => null, 'imported' => $imported, 'updated' => $updated];
}

/**
 * Shared logic for both providers: match each remote event to a local
 * task (by the task id we tagged it with, falling back to the stored
 * remote event id for providers where re-fetching that tag is
 * expensive), update it if the remote side changed, or import it as a
 * new task if it's genuinely new.
 */
function sync_pulled_events(PDO $pdo, int $uid, string $provider, array $events, callable $normalize): array
{
    $eventIdCol = $provider === 'google' ? 'google_event_id' : 'microsoft_event_id';
    $imported = 0;
    $updated = 0;

    foreach ($events as $raw) {
        $e = $normalize($raw);
        if (empty($e['due_on'])) {
            continue; // can't place it on the calendar without a date
        }

        $task = null;
        if ($e['task_id']) {
            $stmt = $pdo->prepare("SELECT * FROM legalops_tasks WHERE id = ? AND assigned_to = ?");
            $stmt->execute([(int)$e['task_id'], $uid]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$task) {
            $stmt = $pdo->prepare("SELECT * FROM legalops_tasks WHERE {$eventIdCol} = ? AND assigned_to = ?");
            $stmt->execute([$e['remote_id'], $uid]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($e['cancelled']) {
            if ($task) {
                $pdo->prepare("UPDATE legalops_tasks SET status = 'done' WHERE id = ?")->execute([$task['id']]);
                $updated++;
            }
            continue;
        }

        if ($task) {
            // Only overwrite if it actually differs, so we don't spam updated_at every sync.
            if ($task['title'] !== $e['title'] || $task['due_on'] !== $e['due_on'] || $task['due_time'] !== $e['due_time']) {
                $pdo->prepare("UPDATE legalops_tasks SET title = ?, due_on = ?, due_time = ?, {$eventIdCol} = ? WHERE id = ?")
                    ->execute([$e['title'], $e['due_on'], $e['due_time'], $e['remote_id'], $task['id']]);
                $updated++;
            } elseif (empty($task[$eventIdCol])) {
                $pdo->prepare("UPDATE legalops_tasks SET {$eventIdCol} = ? WHERE id = ?")->execute([$e['remote_id'], $task['id']]);
            }
        } else {
            // Brand new event created directly in Google/Outlook — import it.
            $stmt = $pdo->prepare(
                "INSERT INTO legalops_tasks (title, notes, due_on, due_time, assigned_to, created_by, source, {$eventIdCol})
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$e['title'], $e['notes'], $e['due_on'], $e['due_time'], $uid, $uid, "{$provider}_import", $e['remote_id']]);
            $imported++;
        }
    }

    return [$imported, $updated];
}

// ---------------------------------------------------------------------
// Orchestration
// ---------------------------------------------------------------------

/** Push every task assigned to this account's user that's changed since the last sync, then pull. */
function sync_account(PDO $pdo, array $account): array
{
    $uid = (int)$account['uid'];
    $eventIdCol = $account['provider'] === 'google' ? 'google_event_id' : 'microsoft_event_id';
    $since = $account['last_synced_at'] ?? '1970-01-01 00:00:00';

    $stmt = $pdo->prepare(
        "SELECT * FROM legalops_tasks
         WHERE assigned_to = ? AND due_on IS NOT NULL AND status != 'done'
         AND (updated_at > ? OR {$eventIdCol} IS NULL)"
    );
    $stmt->execute([$uid, $since]);
    $toPush = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $pushed = 0;
    $pushErrors = 0;
    foreach ($toPush as $task) {
        $result = push_task_to_account($pdo, $account, $task);
        $result['ok'] ? $pushed++ : $pushErrors++;
    }

    $pullFn = $account['provider'] === 'google' ? 'pull_google_events' : 'pull_microsoft_events';
    $pullResult = $pullFn($pdo, $account, $uid);

    $pdo->prepare('UPDATE legalops_calendar_accounts SET last_synced_at = NOW() WHERE id = ?')->execute([$account['id']]);

    return [
        'ok' => $pullResult['ok'] && $pushErrors === 0,
        'pushed' => $pushed,
        'push_errors' => $pushErrors,
        'imported' => $pullResult['imported'],
        'updated' => $pullResult['updated'],
        'error' => $pullResult['error'],
    ];
}
