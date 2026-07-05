<?php

namespace Lops2\Controllers;

class ClientController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        require_once dirname(__DIR__, 2) . '/libs/client_types.php';

        $types         = client_types();
        $entityFilter  = $_GET['type'] ?? 'all';
        $statusFilter  = $_GET['status'] ?? 'all';
        $search        = trim($_GET['q'] ?? '');

        $sql    = 'SELECT * FROM legalops_clients WHERE 1=1';
        $params = [];
        if (array_key_exists($entityFilter, $types)) {
            $sql .= ' AND entity_type=?'; $params[] = $entityFilter;
        }
        if (in_array($statusFilter, CLIENT_ONBOARDING_STAGES, true)) {
            $sql .= ' AND onboarding_status=?'; $params[] = $statusFilter;
        }
        if ($search !== '') {
            $sql .= ' AND (display_name LIKE ? OR pan LIKE ? OR registration_number LIKE ? OR email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);

        $this->view('clients/index', [
            'pageTitle'    => 'Clients',
            'activeNav'    => 'clients',
            'clients'      => $stmt->fetchAll(),
            'types'        => $types,
            'entityFilter' => $entityFilter,
            'statusFilter' => $statusFilter,
            'search'       => $search,
        ]);
    }

    public function store(): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('clients'); }
        require_once dirname(__DIR__, 2) . '/libs/client_types.php';

        $types      = client_types();
        $entityType = array_key_exists($_POST['entity_type'] ?? '', $types) ? $_POST['entity_type'] : 'individual';
        $name       = trim($_POST['display_name'] ?? '');
        $reg        = trim($_POST['registration_number'] ?? '');

        if (!$name) { flash('error', 'Client name is required.'); $this->redirect('clients'); }
        if ($types[$entityType]['registration_required'] && !$reg) {
            flash('error', client_type_label($entityType) . ' clients need a registration number.');
            $this->redirect('clients');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO legalops_clients (entity_type,display_name,pan,registration_number,email,phone,address_line1,address_line2,city,state,pincode,created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $entityType, $name,
            strtoupper(trim($_POST['pan'] ?? '')) ?: null,
            $reg ?: null,
            trim($_POST['email'] ?? '') ?: null,
            trim($_POST['phone'] ?? '') ?: null,
            trim($_POST['address_line1'] ?? '') ?: null,
            trim($_POST['address_line2'] ?? '') ?: null,
            trim($_POST['city'] ?? '') ?: null,
            trim($_POST['state'] ?? '') ?: null,
            trim($_POST['pincode'] ?? '') ?: null,
            $user['uid'],
        ]);
        $newId = (int)$this->pdo->lastInsertId();
        log_activity($this->pdo, (int)$user['uid'], 'client_onboarded', 'Started onboarding ' . client_type_label($entityType) . ' — ' . $name);
        flash('success', 'Client created — continue onboarding below.');
        $this->redirect('clients/' . $newId);
    }

    public function show(array $params): void
    {
        $this->requireLogin();
        require_once dirname(__DIR__, 2) . '/libs/client_types.php';

        $client  = $this->findClient((int)$params['id']);
        $meta    = client_type_meta($client['entity_type']);
        $id      = $client['id'];

        $stmt = $this->pdo->prepare("SELECT * FROM legalops_client_leadership WHERE client_id=? AND status='active' ORDER BY effective_from");
        $stmt->execute([$id]); $activeLeaders = $stmt->fetchAll();

        $stmt = $this->pdo->prepare("SELECT * FROM legalops_client_leadership WHERE client_id=? AND status='removed' ORDER BY effective_to DESC");
        $stmt->execute([$id]); $pastLeaders = $stmt->fetchAll();

        $stmt = $this->pdo->prepare('SELECT * FROM legalops_client_contacts WHERE client_id=? ORDER BY created_at DESC');
        $stmt->execute([$id]); $contacts = $stmt->fetchAll();

        $stmt = $this->pdo->prepare('SELECT d.*,l.role AS leader_role,l.full_name AS leader_name FROM legalops_client_documents d LEFT JOIN legalops_client_leadership l ON l.id=d.leadership_id WHERE d.client_id=? ORDER BY d.uploaded_at DESC');
        $stmt->execute([$id]); $documents = $stmt->fetchAll();

        $stmt = $this->pdo->prepare(
            'SELECT c.*, (SELECT COUNT(*) FROM legalops_case_documents WHERE case_id=c.id) AS doc_count
             FROM legalops_cases c WHERE c.client_id = ? ORDER BY c.created_at DESC'
        );
        $stmt->execute([$id]); $matters = $stmt->fetchAll();

        $this->view('clients/show', [
            'pageTitle'     => $client['display_name'],
            'activeNav'     => 'clients',
            'client'        => $client,
            'meta'          => $meta,
            'activeLeaders' => $activeLeaders,
            'pastLeaders'   => $pastLeaders,
            'contacts'      => $contacts,
            'documents'     => $documents,
            'matters'       => $matters,
            'allLeaders'    => array_merge($activeLeaders, $pastLeaders),
            'docTypes'      => client_doc_types(),
        ]);
    }

    public function update(array $params): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('clients/' . $params['id']); }
        require_once dirname(__DIR__, 2) . '/libs/client_types.php';

        $client = $this->findClient((int)$params['id']);
        $id     = $client['id'];
        $uid    = (int)$user['uid'];
        $action = $_POST['_action'] ?? 'update_core';

        if ($action === 'update_core') {
            $name = trim($_POST['display_name'] ?? '');
            if (!$name) { flash('error', 'Name required.'); $this->redirect('clients/' . $id); }
            $this->pdo->prepare('UPDATE legalops_clients SET display_name=?,pan=?,registration_number=?,email=?,phone=?,address_line1=?,address_line2=?,city=?,state=?,pincode=? WHERE id=?')
                ->execute([
                    $name, strtoupper(trim($_POST['pan'] ?? '')) ?: null,
                    trim($_POST['registration_number'] ?? '') ?: null,
                    trim($_POST['email'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null,
                    trim($_POST['address_line1'] ?? '') ?: null, trim($_POST['address_line2'] ?? '') ?: null,
                    trim($_POST['city'] ?? '') ?: null, trim($_POST['state'] ?? '') ?: null,
                    trim($_POST['pincode'] ?? '') ?: null, $id,
                ]);
            flash('success', 'Client updated.');

        } elseif ($action === 'set_onboarding') {
            $s = $_POST['onboarding_status'] ?? '';
            if (in_array($s, CLIENT_ONBOARDING_STAGES, true)) {
                $this->pdo->prepare('UPDATE legalops_clients SET onboarding_status=? WHERE id=?')->execute([$s, $id]);
                flash('success', 'Onboarding stage updated.');
            }
        } elseif ($action === 'set_kyc') {
            $s = $_POST['kyc_status'] ?? '';
            if (in_array($s, CLIENT_KYC_STAGES, true)) {
                $this->pdo->prepare('UPDATE legalops_clients SET kyc_status=? WHERE id=?')->execute([$s, $id]);
                flash('success', 'KYC status updated.');
            }
        } elseif ($action === 'add_leader') {
            $role = trim($_POST['role'] ?? '');
            $name = trim($_POST['full_name'] ?? '');
            if (!$role || !$name) { flash('error', 'Role and name required.'); $this->redirect('clients/' . $id); }

            $meta = client_type_meta($client['entity_type']);
            if ($meta['leadership_singular']) {
                $this->pdo->prepare("UPDATE legalops_client_leadership SET status='removed',effective_to=CURDATE() WHERE client_id=? AND status='active'")->execute([$id]);
            }
            $this->pdo->prepare('INSERT INTO legalops_client_leadership (client_id,role,full_name,pan,id_proof_type,id_proof_number,din_or_membership_no,email,phone,address,kyc_verified,effective_from,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([$id, $role, $name, strtoupper(trim($_POST['pan'] ?? '')) ?: null, trim($_POST['id_proof_type'] ?? '') ?: null, trim($_POST['id_proof_number'] ?? '') ?: null, trim($_POST['din_or_membership_no'] ?? '') ?: null, trim($_POST['email'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null, trim($_POST['address'] ?? '') ?: null, isset($_POST['kyc_verified']) ? 1 : 0, $_POST['effective_from'] ?: date('Y-m-d'), $uid]);
            log_activity($this->pdo, $uid, 'leadership_changed', 'Added ' . $role . ' ' . $name . ' for ' . $client['display_name']);
            flash('success', 'Leadership updated.');

        } elseif ($action === 'end_leader') {
            $lid = (int)($_POST['leader_id'] ?? 0);
            $this->pdo->prepare("UPDATE legalops_client_leadership SET status='removed',effective_to=CURDATE() WHERE id=? AND client_id=?")->execute([$lid, $id]);
            flash('success', 'Leadership closed out.');

        } elseif ($action === 'toggle_leader_kyc') {
            $lid = (int)($_POST['leader_id'] ?? 0);
            $this->pdo->prepare('UPDATE legalops_client_leadership SET kyc_verified=NOT kyc_verified WHERE id=? AND client_id=?')->execute([$lid, $id]);
            flash('success', 'KYC status toggled.');

        } elseif ($action === 'add_contact') {
            $name = trim($_POST['full_name'] ?? '');
            if (!$name) { flash('error', 'Contact name required.'); $this->redirect('clients/' . $id); }
            $this->pdo->prepare('INSERT INTO legalops_client_contacts (client_id,full_name,designation,email,phone,notes,created_by) VALUES (?,?,?,?,?,?,?)')
                ->execute([$id, $name, trim($_POST['designation'] ?? '') ?: null, trim($_POST['email'] ?? '') ?: null, trim($_POST['phone'] ?? '') ?: null, trim($_POST['notes'] ?? '') ?: null, $uid]);
            flash('success', 'Contact added.');

        } elseif ($action === 'delete_contact') {
            $this->pdo->prepare('DELETE FROM legalops_client_contacts WHERE id=? AND client_id=?')->execute([(int)($_POST['contact_id'] ?? 0), $id]);
            flash('success', 'Contact removed.');

        } elseif ($action === 'upload_doc') {
            $result = handle_client_upload($this->pdo, $id, (int)($_POST['leadership_id'] ?? 0) ?: null, trim($_POST['doc_type'] ?? 'Other'), $_FILES['document'] ?? [], $uid);
            flash($result['ok'] ? 'success' : 'error', $result['message']);
            if ($result['ok']) log_activity($this->pdo, $uid, 'doc_uploaded', 'Uploaded document for ' . $client['display_name']);

        } elseif ($action === 'delete_doc') {
            $docId = (int)($_POST['doc_id'] ?? 0);
            $stmt  = $this->pdo->prepare('SELECT * FROM legalops_client_documents WHERE id=? AND client_id=?');
            $stmt->execute([$docId, $id]);
            if ($doc = $stmt->fetch()) {
                $path = rtrim(STORAGE_PATH, '/') . '/uploads/clients/' . $id . '/' . $doc['stored_name'];
                if (is_file($path)) @unlink($path);
                $this->pdo->prepare('DELETE FROM legalops_client_documents WHERE id=?')->execute([$docId]);
                flash('success', 'Document removed.');
            }
        }

        $this->redirect('clients/' . $id);
    }

    public function destroy(array $params): void
    {
        $user = $this->requireLogin();
        if (!csrf_valid()) { flash('error', 'Session expired.'); $this->redirect('clients'); }

        $client = $this->findClient((int)$params['id']);
        $id     = $client['id'];

        $dir = rtrim(STORAGE_PATH, '/') . '/uploads/clients/' . $id;
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
            @rmdir($dir);
        }
        foreach (['legalops_client_documents', 'legalops_client_contacts', 'legalops_client_leadership'] as $t) {
            $this->pdo->prepare("DELETE FROM {$t} WHERE client_id=?")->execute([$id]);
        }
        // Matters are NOT deleted — only unlinked. client_name (already a
        // plain copy of the client's name at link time) stays exactly as
        // it was, so the matter's history doesn't silently lose its client.
        $this->pdo->prepare('UPDATE legalops_cases SET client_id = NULL WHERE client_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM legalops_clients WHERE id=?')->execute([$id]);
        log_activity($this->pdo, (int)$user['uid'], 'client_deleted', 'Removed client — ' . $client['display_name']);
        flash('success', 'Client removed.');
        $this->redirect('clients');
    }

    private function findClient(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM legalops_clients WHERE id=?');
        $stmt->execute([$id]);
        $c = $stmt->fetch();
        if (!$c) { flash('error', 'Client not found.'); $this->redirect('clients'); }
        return $c;
    }
}
