<?php
require_once __DIR__ . '/config/bootstrap.php';
require_login($auth);

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT description, hsn_sac, quantity, unit_price FROM legalops_invoice_items WHERE invoice_id = ? ORDER BY sort_order'
);
$stmt->execute([$id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
