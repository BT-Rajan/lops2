<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/includes/client_types.php';
$current_user = require_login($auth);

$clientId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM legalops_clients WHERE id = ?');
$stmt->execute([$clientId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    flash('error', 'That client could not be found.');
    header('Location: ' . base_url('clients.php'));
    exit;
}

$meta = client_type_meta($client['entity_type']);
$uid = (int)$current_user['uid'];

// ---- POST actions -----------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_core') {
        $displayName = trim($_POST['display_name'] ?? '');
        $pan = strtoupper(trim($_POST['pan'] ?? ''));
        $registration = trim($_POST['registration_number'] ?? '');

        if ($displayName === '') {
            flash('error', 'Client / entity name is required.');
        } elseif ($meta['registration_required'] && $registration === '') {
            flash('error', $meta['label'] . ' clients need a ' . strtolower($meta['registration_label']) . '.');
        } else {
            $stmt = $pdo->prepare(
                'UPDATE legalops_clients SET display_name=?, pan=?, registration_number=?, email=?, phone=?,
                 address_line1=?, address_line2=?, city=?, state=?, pincode=? WHERE id=?'
            );
            $stmt->execute([
                $displayName, $pan ?: null, $registration ?: null,
                trim($_POST['email'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null,
                trim($_POST['address_line1'] ?? '') ?: null, trim($_POST['address_line2'] ?? '') ?: null,
                trim($_POST['city'] ?? '') ?: null, trim($_POST['state'] ?? '') ?: null, trim($_POST['pincode'] ?? '') ?: null,
                $clientId,
            ]);
            log_activity($pdo, $uid, 'client_updated', 'Updated details for client — ' . $displayName);
            flash('success', 'Client details updated.');
        }
    } elseif ($action === 'set_onboarding') {
        $status = $_POST['onboarding_status'] ?? '';
        if (in_array($status, CLIENT_ONBOARDING_STAGES, true)) {
            $pdo->prepare('UPDATE legalops_clients SET onboarding_status = ? WHERE id = ?')->execute([$status, $clientId]);
            log_activity($pdo, $uid, 'client_onboarding', $client['display_name'] . ' moved to onboarding stage: ' . str_replace('_', ' ', $status));
            flash('success', 'Onboarding stage updated.');
        }
    } elseif ($action === 'set_kyc') {
        $status = $_POST['kyc_status'] ?? '';
        if (in_array($status, CLIENT_KYC_STAGES, true)) {
            $pdo->prepare('UPDATE legalops_clients SET kyc_status = ? WHERE id = ?')->execute([$status, $clientId]);
            log_activity($pdo, $uid, 'client_kyc', $client['display_name'] . ' KYC marked ' . $status);
            flash('success', 'KYC status updated.');
        }
    } elseif ($action === 'add_leader') {
        $role = trim($_POST['role'] ?? '');
        $name = trim($_POST['full_name'] ?? '');

        if ($role === '' || $name === '') {
            flash('error', 'Role and full name are required for leadership KYC.');
        } else {
            // Singular roles (individual / family / proprietorship) can only
            // have one active leader — adding a new one ends the old one.
            // This is how "change leadership" works for those types.
            if ($meta['leadership_singular']) {
                $pdo->prepare(
                    "UPDATE legalops_client_leadership SET status='removed', effective_to=CURDATE()
                     WHERE client_id = ? AND status = 'active'"
                )->execute([$clientId]);
            }

            $stmt = $pdo->prepare(
                'INSERT INTO legalops_client_leadership
                 (client_id, role, full_name, pan, id_proof_type, id_proof_number, din_or_membership_no, email, phone, address, kyc_verified, effective_from, created_by)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $clientId, $role, $name,
                strtoupper(trim($_POST['pan'] ?? '')) ?: null,
                trim($_POST['id_proof_type'] ?? '') ?: null,
                trim($_POST['id_proof_number'] ?? '') ?: null,
                trim($_POST['din_or_membership_no'] ?? '') ?: null,
                trim($_POST['email'] ?? '') ?: null,
                trim($_POST['phone'] ?? '') ?: null,
                trim($_POST['address'] ?? '') ?: null,
                isset($_POST['kyc_verified']) ? 1 : 0,
                $_POST['effective_from'] ?: date('Y-m-d'),
                $uid,
            ]);
            log_activity($pdo, $uid, 'leadership_changed', 'Added ' . $role . ' ' . $name . ' for ' . $client['display_name']);
            flash('success', 'Leadership updated.');
        }
    } elseif ($action === 'end_leader') {
        $leaderId = (int)($_POST['leader_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT role, full_name FROM legalops_client_leadership WHERE id = ? AND client_id = ?');
        $stmt->execute([$leaderId, $clientId]);
        if ($leader = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare("UPDATE legalops_client_leadership SET status='removed', effective_to=CURDATE() WHERE id = ?")->execute([$leaderId]);
            log_activity($pdo, $uid, 'leadership_changed', 'Ended ' . $leader['role'] . ' role for ' . $leader['full_name'] . ' at ' . $client['display_name']);
            flash('success', 'Leadership entry closed out.');
        }
    } elseif ($action === 'toggle_leader_kyc') {
        $leaderId = (int)($_POST['leader_id'] ?? 0);
        $pdo->prepare('UPDATE legalops_client_leadership SET kyc_verified = NOT kyc_verified WHERE id = ? AND client_id = ?')->execute([$leaderId, $clientId]);
        flash('success', 'Leader KYC status toggled.');
    } elseif ($action === 'add_contact') {
        $name = trim($_POST['full_name'] ?? '');
        if ($name === '') {
            flash('error', 'Contact name is required.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO legalops_client_contacts (client_id, full_name, designation, email, phone, notes, created_by) VALUES (?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $clientId, $name, trim($_POST['designation'] ?? '') ?: null,
                trim($_POST['email'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null,
                trim($_POST['notes'] ?? '') ?: null, $uid,
            ]);
            log_activity($pdo, $uid, 'contact_added', 'Added secondary contact ' . $name . ' for ' . $client['display_name']);
            flash('success', 'Contact added.');
        }
    } elseif ($action === 'delete_contact') {
        $pdo->prepare('DELETE FROM legalops_client_contacts WHERE id = ? AND client_id = ?')->execute([(int)($_POST['contact_id'] ?? 0), $clientId]);
        flash('success', 'Contact removed.');
    } elseif ($action === 'upload_doc') {
        $docType = trim($_POST['doc_type'] ?? 'Other');
        $leadershipId = (int)($_POST['leadership_id'] ?? 0) ?: null;
        $result = handle_client_upload($pdo, $clientId, $leadershipId, $docType, $_FILES['document'] ?? [], $uid);
        flash($result['ok'] ? 'success' : 'error', $result['message']);
        if ($result['ok']) {
            log_activity($pdo, $uid, 'document_uploaded', 'Uploaded ' . $docType . ' for ' . $client['display_name']);
        }
    } elseif ($action === 'delete_doc') {
        $docId = (int)($_POST['doc_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM legalops_client_documents WHERE id = ? AND client_id = ?');
        $stmt->execute([$docId, $clientId]);
        if ($doc = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $path = rtrim(UPLOAD_DIR, '/') . '/' . $clientId . '/' . $doc['stored_name'];
            if (is_file($path)) { @unlink($path); }
            $pdo->prepare('DELETE FROM legalops_client_documents WHERE id = ?')->execute([$docId]);
            flash('success', 'Document removed.');
        }
    } elseif ($action === 'delete_client') {
        $dir = rtrim(UPLOAD_DIR, '/') . '/' . $clientId;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') as $f) { @unlink($f); }
            @rmdir($dir);
        }
        $pdo->prepare('DELETE FROM legalops_client_documents WHERE client_id = ?')->execute([$clientId]);
        $pdo->prepare('DELETE FROM legalops_client_contacts WHERE client_id = ?')->execute([$clientId]);
        $pdo->prepare('DELETE FROM legalops_client_leadership WHERE client_id = ?')->execute([$clientId]);
        $pdo->prepare('DELETE FROM legalops_clients WHERE id = ?')->execute([$clientId]);
        log_activity($pdo, $uid, 'client_deleted', 'Removed client — ' . $client['display_name']);
        flash('success', 'Client removed.');
        header('Location: ' . base_url('clients.php'));
        exit;
    }

    header('Location: ' . base_url('client-view.php?id=' . $clientId));
    exit;
}

// ---- Re-fetch fresh data for rendering -------------------------------------
$stmt = $pdo->prepare('SELECT * FROM legalops_clients WHERE id = ?');
$stmt->execute([$clientId]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM legalops_client_leadership WHERE client_id = ? AND status = 'active' ORDER BY effective_from ASC");
$stmt->execute([$clientId]);
$activeLeaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM legalops_client_leadership WHERE client_id = ? AND status = 'removed' ORDER BY effective_to DESC");
$stmt->execute([$clientId]);
$pastLeaders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT * FROM legalops_client_contacts WHERE client_id = ? ORDER BY created_at DESC');
$stmt->execute([$clientId]);
$contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    'SELECT d.*, l.role AS leader_role, l.full_name AS leader_name FROM legalops_client_documents d
     LEFT JOIN legalops_client_leadership l ON l.id = d.leadership_id
     WHERE d.client_id = ? ORDER BY d.uploaded_at DESC'
);
$stmt->execute([$clientId]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allLeadersForDocs = array_merge($activeLeaders, $pastLeaders);

$page_title = $client['display_name'];
$active_nav = 'clients';
$breadcrumb = [
    ['label' => 'Clients', 'href' => 'clients.php'],
    ['label' => $client['display_name']],
];

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold"><?= htmlspecialchars($meta['label']) ?></span>
    <h1><?= htmlspecialchars($client['display_name']) ?></h1>
    <p class="sub">
      <span class="badge badge-onboard-<?= htmlspecialchars($client['onboarding_status']) ?>"><?= htmlspecialchars(str_replace('_', ' ', $client['onboarding_status'])) ?></span>
      &nbsp;<span class="badge badge-kyc-<?= htmlspecialchars($client['kyc_status']) ?>">KYC <?= htmlspecialchars($client['kyc_status']) ?></span>
      &nbsp;Client since <?= date('d M Y', strtotime($client['created_at'])) ?>
    </p>
  </div>
  <div style="display:flex;gap:10px">
    <a class="btn btn-ghost" href="<?= base_url('clients.php') ?>">← All clients</a>
    <button class="btn btn-primary" type="button" id="edit-toggle-btn"><?= icon('edit') ?> Edit details</button>
  </div>
</div>

<!-- Edit core details (inline, not a modal) -->
<div class="card inline-panel" id="edit-panel">
  <form method="post">
    <div class="card-head" style="padding:20px 24px 0">
      <h3>Edit client details</h3>
      <span class="modal-close" id="edit-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_core">
      <div class="field">
        <label><?= $client['entity_type'] === 'individual' ? 'Full name' : 'Entity / registered name' ?></label>
        <input class="input" type="text" name="display_name" value="<?= htmlspecialchars($client['display_name']) ?>" required>
      </div>
      <div class="input-row">
        <div class="field">
          <label>PAN</label>
          <input class="input mono" type="text" name="pan" maxlength="10" value="<?= htmlspecialchars($client['pan'] ?? '') ?>" style="text-transform:uppercase">
        </div>
        <?php if ($meta['registration_label']): ?>
        <div class="field">
          <label><?= htmlspecialchars($meta['registration_label']) ?><?= $meta['registration_required'] ? ' *' : ' (optional)' ?></label>
          <input class="input mono" type="text" name="registration_number" value="<?= htmlspecialchars($client['registration_number'] ?? '') ?>" <?= $meta['registration_required'] ? 'required' : '' ?>>
        </div>
        <?php endif; ?>
      </div>
      <div class="input-row">
        <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= htmlspecialchars($client['email'] ?? '') ?>"></div>
        <div class="field"><label>Phone</label><input class="input" type="text" name="phone" value="<?= htmlspecialchars($client['phone'] ?? '') ?>"></div>
      </div>
      <div class="field"><label>Address line 1</label><input class="input" type="text" name="address_line1" value="<?= htmlspecialchars($client['address_line1'] ?? '') ?>"></div>
      <div class="field"><label>Address line 2</label><input class="input" type="text" name="address_line2" value="<?= htmlspecialchars($client['address_line2'] ?? '') ?>"></div>
      <div class="input-row">
        <div class="field"><label>City</label><input class="input" type="text" name="city" value="<?= htmlspecialchars($client['city'] ?? '') ?>"></div>
        <div class="field"><label>State</label><input class="input" type="text" name="state" value="<?= htmlspecialchars($client['state'] ?? '') ?>"></div>
        <div class="field"><label>PIN code</label><input class="input" type="text" name="pincode" value="<?= htmlspecialchars($client['pincode'] ?? '') ?>"></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="btn btn-ghost" id="edit-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </form>
</div>

<div class="grid-2">
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Summary -->
    <div class="card card-pad">
      <div class="card-head"><h3>Client summary</h3></div>
      <table class="table">
        <tr><td style="width:160px;color:var(--text-muted)">PAN</td><td class="mono"><?= htmlspecialchars($client['pan'] ?: '—') ?></td></tr>
        <?php if ($meta['registration_label']): ?>
        <tr><td style="color:var(--text-muted)"><?= htmlspecialchars($meta['registration_label']) ?></td><td class="mono"><?= htmlspecialchars($client['registration_number'] ?: '—') ?></td></tr>
        <?php endif; ?>
        <tr><td style="color:var(--text-muted)">Email</td><td><?= htmlspecialchars($client['email'] ?: '—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">Phone</td><td><?= htmlspecialchars($client['phone'] ?: '—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">Address</td><td><?= htmlspecialchars(trim(implode(', ', array_filter([$client['address_line1'], $client['address_line2'], $client['city'], $client['state'], $client['pincode']]))) ?: '—') ?></td></tr>
      </table>
    </div>

    <!-- Leadership -->
    <div class="card card-pad">
      <div class="card-head">
        <h3><?= htmlspecialchars($meta['leadership_label']) ?> — KYC</h3>
        <button class="btn btn-sm btn-primary" type="button" id="leader-toggle-btn"><?= icon('plus') ?> <?= $meta['leadership_singular'] ? 'Change' : 'Add' ?></button>
      </div>

      <div class="inline-panel" id="leader-panel">
        <form method="post" style="padding:4px 0 14px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_leader">
          <?php if ($meta['leadership_singular'] && $activeLeaders): ?>
            <div class="alert alert-info">Adding a new <?= strtolower($meta['leadership_label']) ?> will end the current one (<?= htmlspecialchars($activeLeaders[0]['full_name']) ?>) as of today — that's how leadership changes are tracked.</div>
          <?php endif; ?>
          <div class="input-row">
            <div class="field">
              <label>Role</label>
              <select class="input" name="role">
                <?php foreach ($meta['leadership_roles'] as $role): ?>
                  <option value="<?= htmlspecialchars($role) ?>"><?= htmlspecialchars($role) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label>Full name</label><input class="input" type="text" name="full_name" required></div>
          </div>
          <div class="input-row">
            <div class="field"><label>PAN</label><input class="input mono" type="text" name="pan" maxlength="10" style="text-transform:uppercase"></div>
            <div class="field">
              <label>ID proof type</label>
              <select class="input" name="id_proof_type">
                <option value="">— Select —</option>
                <option>Aadhaar</option><option>Passport</option><option>Voter ID</option>
                <option>Driving Licence</option><option>Other</option>
              </select>
            </div>
          </div>
          <div class="input-row">
            <div class="field"><label>ID proof number</label><input class="input" type="text" name="id_proof_number"></div>
            <div class="field"><label>DIN / membership no. <span style="color:var(--text-muted);font-weight:400">(if applicable)</span></label><input class="input" type="text" name="din_or_membership_no"></div>
          </div>
          <div class="input-row">
            <div class="field"><label>Email</label><input class="input" type="email" name="email"></div>
            <div class="field"><label>Phone</label><input class="input" type="text" name="phone"></div>
          </div>
          <div class="field"><label>Address</label><input class="input" type="text" name="address"></div>
          <div class="input-row">
            <div class="field"><label>Effective from</label><input class="input" type="date" name="effective_from" value="<?= date('Y-m-d') ?>"></div>
            <div class="field" style="display:flex;align-items:flex-end;padding-bottom:11px">
              <label style="display:flex;align-items:center;gap:8px;font-weight:500"><input type="checkbox" name="kyc_verified"> KYC already verified</label>
            </div>
          </div>
          <div style="display:flex;justify-content:flex-end;gap:10px">
            <button type="button" class="btn btn-ghost" id="leader-cancel-btn">Cancel</button>
            <button type="submit" class="btn btn-primary">Save</button>
          </div>
        </form>
      </div>

      <?php if ($activeLeaders): ?>
      <table class="table">
        <thead><tr><th>Role</th><th>Name</th><th>PAN / ID proof</th><th>KYC</th><th>Since</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($activeLeaders as $l): ?>
          <tr>
            <td><span class="badge badge-open"><?= htmlspecialchars($l['role']) ?></span></td>
            <td>
              <div class="case-title"><?= htmlspecialchars($l['full_name']) ?></div>
              <div class="case-client"><?= htmlspecialchars($l['email'] ?: $l['phone'] ?: '') ?></div>
            </td>
            <td>
              <div class="mono case-client"><?= htmlspecialchars($l['pan'] ?: '—') ?></div>
              <div class="case-client"><?= htmlspecialchars($l['id_proof_type'] ? $l['id_proof_type'] . ' · ' . $l['id_proof_number'] : '') ?></div>
            </td>
            <td>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_leader_kyc">
                <input type="hidden" name="leader_id" value="<?= (int)$l['id'] ?>">
                <button type="submit" class="badge badge-kyc-<?= $l['kyc_verified'] ? 'verified' : 'pending' ?>" style="border:none;cursor:pointer"><?= $l['kyc_verified'] ? 'Verified' : 'Pending' ?></button>
              </form>
            </td>
            <td class="case-client"><?= $l['effective_from'] ? date('d M Y', strtotime($l['effective_from'])) : '—' ?></td>
            <td style="text-align:right">
              <form method="post" onsubmit="return confirm('End this leadership role? It will move to history.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="end_leader">
                <input type="hidden" name="leader_id" value="<?= (int)$l['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm">End</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
        <div class="empty-state"><?= icon('clients') ?><p>No leadership / KYC details on file yet.</p></div>
      <?php endif; ?>

      <?php if ($pastLeaders): ?>
      <details style="margin-top:16px">
        <summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--text-muted)">Leadership history (<?= count($pastLeaders) ?>)</summary>
        <table class="table" style="margin-top:10px">
          <thead><tr><th>Role</th><th>Name</th><th>From</th><th>To</th></tr></thead>
          <tbody>
            <?php foreach ($pastLeaders as $l): ?>
            <tr>
              <td><span class="badge badge-closed"><?= htmlspecialchars($l['role']) ?></span></td>
              <td><?= htmlspecialchars($l['full_name']) ?></td>
              <td class="case-client"><?= $l['effective_from'] ? date('d M Y', strtotime($l['effective_from'])) : '—' ?></td>
              <td class="case-client"><?= $l['effective_to'] ? date('d M Y', strtotime($l['effective_to'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </details>
      <?php endif; ?>
    </div>

  </div>

  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Onboarding / KYC actions -->
    <div class="card card-pad">
      <div class="card-head"><h3>Onboarding &amp; KYC</h3></div>
      <p class="case-client" style="margin-bottom:10px">Move this client through onboarding:</p>
      <form method="post" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_onboarding">
        <?php foreach (CLIENT_ONBOARDING_STAGES as $stage): ?>
          <button type="submit" name="onboarding_status" value="<?= $stage ?>"
            class="filter-chip <?= $client['onboarding_status'] === $stage ? 'active' : '' ?>">
            <?= htmlspecialchars(str_replace('_', ' ', ucfirst($stage))) ?>
          </button>
        <?php endforeach; ?>
      </form>
      <p class="case-client" style="margin-bottom:10px">KYC status:</p>
      <form method="post" style="display:flex;flex-wrap:wrap;gap:8px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_kyc">
        <?php foreach (CLIENT_KYC_STAGES as $stage): ?>
          <button type="submit" name="kyc_status" value="<?= $stage ?>"
            class="filter-chip <?= $client['kyc_status'] === $stage ? 'active' : '' ?>">
            <?= htmlspecialchars(ucfirst($stage)) ?>
          </button>
        <?php endforeach; ?>
      </form>
    </div>

    <!-- Secondary contacts -->
    <div class="card card-pad">
      <div class="card-head">
        <h3>Secondary contacts</h3>
        <button class="btn btn-sm btn-primary" type="button" id="contact-toggle-btn"><?= icon('plus') ?> Add</button>
      </div>

      <div class="inline-panel" id="contact-panel">
        <form method="post" style="padding:4px 0 14px">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_contact">
          <div class="input-row">
            <div class="field"><label>Full name</label><input class="input" type="text" name="full_name" required></div>
            <div class="field"><label>Designation</label><input class="input" type="text" name="designation" placeholder="e.g. Accountant"></div>
          </div>
          <div class="input-row">
            <div class="field"><label>Email</label><input class="input" type="email" name="email"></div>
            <div class="field"><label>Phone</label><input class="input" type="text" name="phone"></div>
          </div>
          <div class="field"><label>Notes</label><input class="input" type="text" name="notes"></div>
          <div style="display:flex;justify-content:flex-end;gap:10px">
            <button type="button" class="btn btn-ghost" id="contact-cancel-btn">Cancel</button>
            <button type="submit" class="btn btn-primary">Save contact</button>
          </div>
        </form>
      </div>

      <?php if ($contacts): foreach ($contacts as $ct): ?>
        <div class="task-row">
          <div>
            <div class="task-title"><?= htmlspecialchars($ct['full_name']) ?></div>
            <div class="task-meta"><?= htmlspecialchars($ct['designation'] ?: 'Contact') ?> · <?= htmlspecialchars($ct['email'] ?: $ct['phone'] ?: '—') ?></div>
          </div>
          <form method="post" style="margin-left:auto">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_contact">
            <input type="hidden" name="contact_id" value="<?= (int)$ct['id'] ?>">
            <button type="submit" class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)" onclick="return confirm('Remove this contact?')"><?= icon('trash') ?></button>
          </form>
        </div>
      <?php endforeach; else: ?>
        <div class="empty-state"><p>No secondary contacts yet.</p></div>
      <?php endif; ?>
    </div>

    <!-- Documents -->
    <div class="card card-pad">
      <div class="card-head"><h3>Documents</h3></div>

      <form method="post" enctype="multipart/form-data" style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border-card)">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload_doc">
        <div class="input-row">
          <div class="field">
            <label>Document type</label>
            <select class="input" name="doc_type">
              <?php foreach (client_doc_types() as $dt): ?><option><?= htmlspecialchars($dt) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label>Relates to</label>
            <select class="input" name="leadership_id">
              <option value="">Client (general)</option>
              <?php foreach ($allLeadersForDocs as $l): ?>
                <option value="<?= (int)$l['id'] ?>"><?= htmlspecialchars($l['role'] . ' — ' . $l['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="field">
          <label>File <span style="color:var(--text-muted);font-weight:400">(PDF, JPG, PNG, DOC, DOCX — up to 5MB)</span></label>
          <input class="input" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Upload</button>
      </form>

      <?php if ($documents): foreach ($documents as $d): ?>
        <div class="task-row">
          <span class="task-check" style="border-color:var(--accent-600);color:var(--accent-600)"><?= icon('documents') ?></span>
          <div>
            <div class="task-title"><?= htmlspecialchars($d['doc_type']) ?></div>
            <div class="task-meta">
              <?= htmlspecialchars($d['original_name']) ?> · <?= round($d['file_size'] / 1024) ?>KB
              <?= $d['leader_name'] ? ' · ' . htmlspecialchars($d['leader_role'] . ': ' . $d['leader_name']) : '' ?>
              · <?= time_ago($d['uploaded_at']) ?>
            </div>
          </div>
          <div style="margin-left:auto;display:flex;gap:6px">
            <a class="icon-btn btn-sm" style="display:inline-grid" href="<?= base_url('download.php?doc=' . (int)$d['id']) ?>" target="_blank" rel="noopener">⬇</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Delete this document?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete_doc">
              <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
              <button type="submit" class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)"><?= icon('trash') ?></button>
            </form>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div class="empty-state"><?= icon('documents') ?><p>No documents uploaded yet.</p></div>
      <?php endif; ?>
    </div>

    <!-- Danger zone -->
    <div class="card card-pad" style="border-color:rgba(193,59,59,0.25)">
      <div class="card-head"><h3 style="color:var(--danger)">Remove client</h3></div>
      <p class="case-client" style="margin-bottom:14px">Deletes this client along with their leadership history, contacts and uploaded documents. This can't be undone.</p>
      <form method="post" onsubmit="return confirm('Permanently delete this client and all related records?')">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete_client">
        <button type="submit" class="btn btn-ghost" style="border-color:var(--danger);color:var(--danger)">Delete client</button>
      </form>
    </div>

  </div>
</div>

<script>
(function () {
  function wireToggle(toggleId, panelId, cancelId, closeId) {
    var toggle = document.getElementById(toggleId);
    var panel = document.getElementById(panelId);
    if (!toggle || !panel) return;
    toggle.addEventListener('click', function () {
      panel.classList.toggle('open');
      if (panel.classList.contains('open')) panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    var cancel = document.getElementById(cancelId);
    if (cancel) cancel.addEventListener('click', function () { panel.classList.remove('open'); });
    var close = document.getElementById(closeId);
    if (close) close.addEventListener('click', function () { panel.classList.remove('open'); });
  }
  wireToggle('edit-toggle-btn', 'edit-panel', 'edit-cancel-btn', 'edit-panel-close');
  wireToggle('leader-toggle-btn', 'leader-panel', 'leader-cancel-btn', null);
  wireToggle('contact-toggle-btn', 'contact-panel', 'contact-cancel-btn', null);
})();
</script>

<?php require __DIR__ . '/includes/app_footer.php'; ?>
