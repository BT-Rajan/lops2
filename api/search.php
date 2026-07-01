<?php
/**
 * Lightweight JSON search used by the topbar command-bar.
 * GET /api/search.php?q=...  →  { results: [ {type, title, sub, badge, url}, ... ] }
 */
require_once __DIR__ . '/../config/bootstrap.php';

header('Content-Type: application/json');

if (!$auth->isLogged()) {
    http_response_code(401);
    echo json_encode(['results' => [], 'error' => 'not_authenticated']);
    exit;
}

$q = trim($_GET['q'] ?? '');
$results = [];

if (mb_strlen($q) >= 2) {
    $like = '%' . $q . '%';

    $stmt = $pdo->prepare(
        "SELECT id, case_number, title, client_name, status FROM legalops_cases
         WHERE title LIKE ? OR client_name LIKE ? OR case_number LIKE ?
         ORDER BY created_at DESC LIMIT 6"
    );
    $stmt->execute([$like, $like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $results[] = [
            'type' => 'case',
            'title' => $c['title'],
            'sub' => $c['case_number'] . ' · ' . $c['client_name'],
            'badge' => $c['status'],
            'url' => base_url('cases.php?q=' . urlencode($c['case_number'])),
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT t.id, t.title, t.status, t.due_on, t.case_id, c.title AS case_title FROM legalops_tasks t
         LEFT JOIN legalops_cases c ON c.id = t.case_id
         WHERE t.title LIKE ?
         ORDER BY t.due_on ASC LIMIT 4"
    );
    $stmt->execute([$like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $results[] = [
            'type' => 'task',
            'title' => $t['title'],
            'sub' => $t['case_title'] ? 'Linked to ' . $t['case_title'] : 'No matter linked',
            'badge' => $t['status'],
            'url' => $t['case_id'] ? base_url('case-view.php?id=' . (int)$t['case_id']) : base_url('modules.php?m=tasks'),
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT d.id, d.original_name, d.doc_type, d.case_id, c.title AS case_title, c.case_number FROM legalops_case_documents d
         JOIN legalops_cases c ON c.id = d.case_id
         WHERE d.original_name LIKE ? OR d.doc_type LIKE ? OR c.title LIKE ?
         ORDER BY d.uploaded_at DESC LIMIT 4"
    );
    $stmt->execute([$like, $like, $like]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
        $results[] = [
            'type' => 'document',
            'title' => $d['original_name'],
            'sub' => $d['doc_type'] . ' · ' . $d['case_number'] . ' — ' . $d['case_title'],
            'badge' => null,
            'url' => base_url('case-view.php?id=' . (int)$d['case_id']),
        ];
    }
}

echo json_encode(['results' => $results]);
