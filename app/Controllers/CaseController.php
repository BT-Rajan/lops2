<?php

namespace Lops2\Controllers;

class CaseController extends BaseController
{
    private const STATUSES   = ['open', 'pending', 'closed'];
    private const PRIORITIES = ['low', 'medium', 'high'];

    private const INTEL_TEXT_FIELDS = [
        'court_type', 'court_name', 'bench', 'court_hall', 'judge_name',
        'jurisdiction', 'opposite_counsel', 'police_station', 'fir_number',
        'crime_number', 'case_stage', 'next_hearing_purpose',
    ];
    private const INTEL_LONGTEXT_FIELDS = ['acts_involved', 'sections_involved', 'prayer', 'reliefs_sought', 'result'];
    private const INTEL_DATE_FIELDS     = ['limitation_date', 'filing_date', 'service_date', 'disposal_date'];

    public function index(): void
    {
        $this->requireLogin();

        $statusFilter = $_GET['status'] ?? 'all';
        $search       = trim($_GET['q'] ?? '');
        $practiceAreaFilter = trim($_GET['practice_area'] ?? '');
        $openedMonthFilter  = trim($_GET['opened_month'] ?? ''); // YYYY-MM
        $closedMonthFilter  = trim($_GET['closed_month'] ?? ''); // YYYY-MM

        $sql    = 'SELECT c.*, (SELECT COUNT(*) FROM legalops_case_documents WHERE case_id=c.id) AS doc_count FROM legalops_cases c WHERE 1=1';
        $params = [];
        if (in_array($statusFilter, self::STATUSES, true)) {
            $sql .= ' AND c.status=?'; $params[] = $statusFilter;
        }
        if ($practiceAreaFilter !== '') {
            $sql .= ' AND c.practice_area = ?'; $params[] = $practiceAreaFilter;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $openedMonthFilter)) {
            $sql .= ' AND DATE_FORMAT(c.opened_on, "%Y-%m") = ?'; $params[] = $openedMonthFilter;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $closedMonthFilter)) {
            $sql .= ' AND DATE_FORMAT(c.disposal_date, "%Y-%m") = ?'; $params[] = $closedMonthFilter;
        }
        if ($search !== '') {
            $sql .= ' AND (c.title LIKE ? OR c.client_name LIKE ? OR c.case_number LIKE ? OR c.judge_name LIKE ? OR c.court_name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY c.created_at DESC';
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        $cases = $stmt->fetchAll();

        $clients = $this->pdo->query('SELECT id, display_name, entity_type FROM legalops_clients ORDER BY display_name')->fetchAll();

        $this->view('cases/index', [
            'pageTitle'     => 'Cases',
            'activeNav'     => 'cases',
            'cases'         => $cases,
            'clients'       => $clients,
            'statusFilter'  => $statusFilter,
            'search'        => $search,
            'practiceAreaFilter' => $practiceAreaFilter,
            'openedMonthFilter'  => $openedMonthFilter,
            'closedMonthFilter'  => $closedMonthFilter,
        ]);
    }

    public function store(): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('cases'); }

        ['error' => $err, 'data' => $data] = $this->parseForm();
        if ($err) { flash('error', $err); $this->redirect('cases'); }

        $data['created_by'] = $user['uid'];
        $cols = array_keys($data);
        $sql  = 'INSERT INTO legalops_cases (' . implode(',', $cols) . ') VALUES (:' . implode(',:', $cols) . ')';

        try {
            $this->pdo->prepare($sql)->execute($data);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1062')) {
                flash('error', 'That matter number already exists — use a different one.');
            } else {
                flash('error', 'Could not save the matter: ' . $e->getMessage());
            }
            $this->redirect('cases');
        }
        $newId = (int)$this->pdo->lastInsertId();
        log_activity($this->pdo, (int)$user['uid'], 'case_created', 'Opened case ' . $data['case_number'] . ' — ' . $data['title'], ['case_id' => $newId]);
        flash('success', 'Matter opened.');
        $this->redirect('cases/' . $newId);
    }

    public function show(array $params): void
    {
        $this->requireLogin();
        $case = $this->findCase((int)$params['id']);

        $docs     = $this->pdo->prepare('SELECT d.*,u.full_name AS uploader FROM legalops_case_documents d LEFT JOIN phpauth_users u ON u.id=d.uploaded_by WHERE d.case_id=? ORDER BY d.uploaded_at DESC');
        $docs->execute([$case['id']]); $docs = $docs->fetchAll();

        $tasks = $this->pdo->prepare('SELECT t.*,u.full_name AS assignee_name,u.avatar_color AS assignee_color FROM legalops_tasks t LEFT JOIN phpauth_users u ON u.id=t.assigned_to WHERE t.case_id=? ORDER BY t.due_on ASC');
        $tasks->execute([$case['id']]); $tasks = $tasks->fetchAll();

        $activity = $this->pdo->prepare('SELECT a.*,u.full_name FROM legalops_activity a LEFT JOIN phpauth_users u ON u.id=a.uid WHERE a.case_id=? ORDER BY a.created_at DESC LIMIT 20');
        $activity->execute([$case['id']]); $activity = $activity->fetchAll();

        $team = $this->pdo->query('SELECT id,full_name,email,avatar_color FROM phpauth_users ORDER BY full_name')->fetchAll();

        $links = $this->fetchLinks($case['id']);

        $client = null;
        if ($case['client_id']) {
            $stmt = $this->pdo->prepare(
                'SELECT c.*, (SELECT COUNT(*) FROM legalops_client_documents WHERE client_id=c.id) AS doc_count
                 FROM legalops_clients c WHERE c.id = ?'
            );
            $stmt->execute([$case['client_id']]);
            $client = $stmt->fetch() ?: null;
        }
        $clients = $this->pdo->query('SELECT id, display_name FROM legalops_clients ORDER BY display_name')->fetchAll();

        $this->view('cases/show', [
            'pageTitle' => $case['case_number'] . ' — ' . $case['title'],
            'activeNav' => 'cases',
            'case'      => $case,
            'client'    => $client,
            'clients'   => $clients,
            'docs'      => $docs,
            'tasks'     => $tasks,
            'activity'  => $activity,
            'team'      => $team,
            'connected' => $links['connected'],
            'appeals'   => $links['appeals'],
        ]);
    }

    public function update(array $params): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('cases/' . $params['id']); }

        $case   = $this->findCase((int)$params['id']);
        $action = $_POST['_action'] ?? 'edit';

        if ($action === 'upload_doc') {
            require_once dirname(__DIR__, 2) . '/libs/client_types.php';
            $docType = trim($_POST['doc_type'] ?? 'Other');
            $result  = handle_case_upload($this->pdo, $case['id'], $docType, $_FILES['document'] ?? [], (int)$user['uid'], trim($_POST['doc_notes'] ?? '') ?: null);
            flash($result['ok'] ? 'success' : 'error', $result['message']);
            if ($result['ok']) log_activity($this->pdo, (int)$user['uid'], 'doc_uploaded', 'Uploaded ' . $docType . ' on case ' . $case['case_number'], ['case_id' => $case['id']]);
        } elseif ($action === 'delete_doc') {
            $docId = (int)($_POST['doc_id'] ?? 0);
            $d = $this->pdo->prepare('SELECT * FROM legalops_case_documents WHERE id=? AND case_id=?');
            $d->execute([$docId, $case['id']]);
            if ($doc = $d->fetch()) {
                $path = rtrim(STORAGE_PATH, '/') . '/uploads/cases/' . $case['id'] . '/' . $doc['stored_name'];
                if (is_file($path)) @unlink($path);
                $this->pdo->prepare('DELETE FROM legalops_case_documents WHERE id=?')->execute([$docId]);
                flash('success', 'Document removed.');
            }
        } elseif ($action === 'add_link') {
            $this->addLink($case, $user);
        } elseif ($action === 'remove_link') {
            $this->pdo->prepare('DELETE FROM legalops_case_links WHERE id=? AND (case_id=? OR linked_case_id=?)')
                ->execute([(int)($_POST['link_id'] ?? 0), $case['id'], $case['id']]);
            flash('success', 'Link removed.');
        } else {
            ['error' => $err, 'data' => $data] = $this->parseForm();
            if ($err) { flash('error', $err); $this->redirect('cases/' . $params['id']); }

            $set = implode(',', array_map(fn($c) => "$c=:$c", array_keys($data)));
            $data['id'] = $case['id'];
            $this->pdo->prepare("UPDATE legalops_cases SET $set WHERE id=:id")->execute($data);
            log_activity($this->pdo, (int)$user['uid'], 'case_updated', 'Updated case ' . $data['case_number'], ['case_id' => $case['id']]);
            flash('success', 'Matter updated.');
        }

        $this->redirect('cases/' . $case['id']);
    }

    public function destroy(array $params): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('cases'); }

        $case = $this->findCase((int)$params['id']);

        $dir = rtrim(STORAGE_PATH, '/') . '/uploads/cases/' . $case['id'];
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
            @rmdir($dir);
        }
        $this->pdo->prepare('DELETE FROM legalops_case_documents WHERE case_id=?')->execute([$case['id']]);
        $this->pdo->prepare('DELETE FROM legalops_tasks WHERE case_id=?')->execute([$case['id']]);
        $this->pdo->prepare('DELETE FROM legalops_case_links WHERE case_id=? OR linked_case_id=?')->execute([$case['id'], $case['id']]);
        $this->pdo->prepare('DELETE FROM legalops_cases WHERE id=?')->execute([$case['id']]);
        log_activity($this->pdo, (int)$user['uid'], 'case_deleted', 'Removed case ' . $case['case_number']);
        flash('success', 'Matter removed.');
        $this->redirect('cases');
    }

    private function findCase(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM legalops_cases WHERE id=?');
        $stmt->execute([$id]);
        $case = $stmt->fetch();
        if (!$case) {
            flash('error', 'That matter was not found.'); $this->redirect('cases');
        }
        return $case;
    }

    private function addLink(array $case, array $user): void
    {
        require_once dirname(__DIR__, 2) . '/libs/litigation_types.php';

        $otherNumber = trim($_POST['linked_case_number'] ?? '');
        $linkType    = array_key_exists($_POST['link_type'] ?? '', case_link_types()) ? $_POST['link_type'] : 'connected';

        if ($otherNumber === '') { flash('error', 'Enter a matter number to link.'); return; }

        $stmt = $this->pdo->prepare('SELECT id, case_number, title FROM legalops_cases WHERE case_number=?');
        $stmt->execute([$otherNumber]);
        $other = $stmt->fetch();

        if (!$other) { flash('error', "No matter found with number \"{$otherNumber}\"."); return; }
        if ((int)$other['id'] === $case['id']) { flash('error', 'A matter cannot be linked to itself.'); return; }

        // "Connected" is a symmetric relationship — A linked to B is the same
        // fact as B linked to A. The DB unique key only blocks an exact
        // (case_id, linked_case_id) repeat, not the reverse pairing, so check
        // for that here or the same link ends up listed twice on both sides.
        if ($linkType === 'connected') {
            $dupe = $this->pdo->prepare(
                "SELECT 1 FROM legalops_case_links
                 WHERE link_type='connected'
                   AND ((case_id=? AND linked_case_id=?) OR (case_id=? AND linked_case_id=?))"
            );
            $dupe->execute([$case['id'], $other['id'], $other['id'], $case['id']]);
            if ($dupe->fetch()) {
                flash('error', 'That link already exists.');
                return;
            }
        }

        try {
            $this->pdo->prepare('INSERT INTO legalops_case_links (case_id,linked_case_id,link_type,created_by) VALUES (?,?,?,?)')
                ->execute([$case['id'], $other['id'], $linkType, $user['uid']]);
            log_activity($this->pdo, (int)$user['uid'], 'case_linked', 'Linked ' . $case['case_number'] . ' to ' . $other['case_number'] . ' (' . case_link_type_label($linkType) . ')', ['case_id' => $case['id']]);
            flash('success', 'Matter linked.');
        } catch (\PDOException $e) {
            flash('error', str_contains($e->getMessage(), '1062') ? 'That link already exists.' : 'Could not save the link.');
        }
    }

    private function fetchLinks(int $caseId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT l.id, l.link_type, l.created_at,
                    CASE WHEN l.case_id=? THEN l.linked_case_id ELSE l.case_id END AS other_id
             FROM legalops_case_links l
             WHERE l.link_type='connected' AND (l.case_id=? OR l.linked_case_id=?)"
        );
        $stmt->execute([$caseId, $caseId, $caseId]);
        $connected = $this->hydrateLinks($stmt->fetchAll());

        $stmt = $this->pdo->prepare(
            "SELECT l.id, 'appealed_from' AS direction, l.linked_case_id AS other_id
             FROM legalops_case_links l WHERE l.link_type='appeal_of' AND l.case_id=?
             UNION ALL
             SELECT l.id, 'appealed_to' AS direction, l.case_id AS other_id
             FROM legalops_case_links l WHERE l.link_type='appeal_of' AND l.linked_case_id=?"
        );
        $stmt->execute([$caseId, $caseId]);
        $appeals = $this->hydrateLinks($stmt->fetchAll(), true);

        return ['connected' => $connected, 'appeals' => $appeals];
    }

    private function hydrateLinks(array $rows, bool $keepDirection = false): array
    {
        if (!$rows) return [];
        $ids  = array_column($rows, 'other_id');
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, case_number, title, status FROM legalops_cases WHERE id IN ($in)");
        $stmt->execute($ids);
        $byId = [];
        foreach ($stmt->fetchAll() as $c) { $byId[$c['id']] = $c; }

        $out = [];
        foreach ($rows as $r) {
            if (!isset($byId[$r['other_id']])) continue;
            $out[] = [
                'link_id'   => $r['id'],
                'case'      => $byId[$r['other_id']],
                'direction' => $keepDirection ? $r['direction'] : null,
            ];
        }
        return $out;
    }

    private function parseForm(): array
    {
        $caseNumber  = trim($_POST['case_number'] ?? '');
        $title       = trim($_POST['title'] ?? '');
        $clientId    = (int)($_POST['client_id'] ?? 0);
        $client      = trim($_POST['client_name'] ?? '');
        $area        = trim($_POST['practice_area'] ?? '');
        $status      = $this->postEnum('status', self::STATUSES, 'open');
        $priority    = $this->postEnum('priority', self::PRIORITIES, 'medium');

        if ($clientId > 0) {
            $stmt = $this->pdo->prepare('SELECT display_name FROM legalops_clients WHERE id = ?');
            $stmt->execute([$clientId]);
            $linkedName = $stmt->fetchColumn();
            if ($linkedName === false) {
                return ['error' => 'The selected client record could not be found.', 'data' => []];
            }
            // Linked matters always display the client's real name — never
            // a stale or hand-edited copy of it — so the link actually
            // means something instead of being a decoration next to a
            // free-text field nobody keeps in sync.
            $client = $linkedName;
        } else {
            $clientId = null;
        }

        if (!$caseNumber || !$title || !$client) {
            return ['error' => 'Matter number, title and client are required.', 'data' => []];
        }

        $data = [
            'case_number'   => $caseNumber,
            'title'         => $title,
            'client_name'   => $client,
            'client_id'     => $clientId,
            'practice_area' => $area ?: null,
            'status'        => $status,
            'priority'      => $priority,
            'opened_on'          => ($_POST['opened_on'] ?? '') ?: null,
            'due_on'             => ($_POST['due_on'] ?? '') ?: null,
            'next_hearing_date'  => ($_POST['next_hearing_date'] ?? '') ?: null,
            'next_hearing_time'  => ($_POST['next_hearing_time'] ?? '') ?: null,
        ];

        foreach (self::INTEL_TEXT_FIELDS as $f)     { $data[$f] = trim($_POST[$f] ?? '') ?: null; }
        foreach (self::INTEL_LONGTEXT_FIELDS as $f) { $data[$f] = trim($_POST[$f] ?? '') ?: null; }
        foreach (self::INTEL_DATE_FIELDS as $f)     { $data[$f] = ($_POST[$f] ?? '') ?: null; }

        return ['error' => null, 'data' => $data];
    }
}
