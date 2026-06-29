<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/libs/Invoicing.php';
require_once __DIR__ . '/libs/InvoicePdf.php';

require_login($auth);

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM legalops_invoices WHERE id = ?');
$stmt->execute([$id]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    http_response_code(404);
    die('Invoice not found.');
}

$itemsStmt = $pdo->prepare('SELECT * FROM legalops_invoice_items WHERE invoice_id = ? ORDER BY sort_order');
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$entityStmt = $pdo->prepare('SELECT * FROM legalops_billing_entities WHERE id = ?');
$entityStmt->execute([$invoice['billing_entity_id']]);
$entity = $entityStmt->fetch(PDO::FETCH_ASSOC);

if (!$entity) {
    http_response_code(500);
    die('The billing entity for this invoice could not be found.');
}

try {
    $pdf = render_invoice_pdf($invoice, $items, $entity);
} catch (Throwable $e) {
    http_response_code(500);
    die(htmlspecialchars($e->getMessage()));
}

$filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $invoice['invoice_no']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
