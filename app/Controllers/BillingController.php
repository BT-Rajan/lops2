<?php

namespace Lops2\Controllers;

class BillingController extends BaseController
{
    private const COUNTRIES = [
        'IN' => 'India', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'BH' => 'Bahrain',
        'OM' => 'Oman', 'KW' => 'Kuwait', 'QA' => 'Qatar', 'GB' => 'United Kingdom', 'US' => 'United States',
        'SG' => 'Singapore', 'XX' => 'Other',
    ];

    public function index(): void
    {
        $this->requireLogin();
        require_once dirname(__DIR__, 2) . '/libs/Invoicing.php';
        require_once dirname(__DIR__, 2) . '/libs/InvoicePdf.php';

        $entities = $this->pdo->query(
            'SELECT * FROM legalops_billing_entities WHERE is_active = 1 ORDER BY name'
        )->fetchAll();

        $statusFilter  = $_GET['status'] ?? 'all';
        $entityFilter  = (int)($_GET['entity'] ?? 0);
        $search        = trim($_GET['q'] ?? '');
        $currencyFilter = trim($_GET['currency'] ?? '');
        $fromFilter    = trim($_GET['from'] ?? '');
        $toFilter      = trim($_GET['to'] ?? '');
        $paidFromFilter = trim($_GET['paid_from'] ?? '');
        $paidToFilter   = trim($_GET['paid_to'] ?? '');
        $balanceFilter  = trim($_GET['balance'] ?? ''); // outstanding | overdue | aging_current | aging_1_30 | aging_31_60 | aging_61_90 | aging_90_plus
        $caseIdFilter   = (int)($_GET['case_id'] ?? 0);

        $sql = 'SELECT i.*, e.name AS entity_name, c.case_number, c.title AS case_title FROM legalops_invoices i
                JOIN legalops_billing_entities e ON e.id = i.billing_entity_id
                LEFT JOIN legalops_cases c ON c.id = i.case_id WHERE 1=1';
        $params = [];
        if (in_array($statusFilter, ['draft', 'issued', 'void'], true)) {
            $sql .= ' AND i.status = ?';
            $params[] = $statusFilter;
        }
        if ($entityFilter > 0) {
            $sql .= ' AND i.billing_entity_id = ?';
            $params[] = $entityFilter;
        }
        if ($currencyFilter !== '') {
            $sql .= ' AND i.currency = ?';
            $params[] = $currencyFilter;
        }
        if ($caseIdFilter > 0) {
            $sql .= ' AND i.case_id = ?';
            $params[] = $caseIdFilter;
        }
        if ($fromFilter !== '') { $sql .= ' AND i.invoice_date >= ?'; $params[] = $fromFilter; }
        if ($toFilter !== '')   { $sql .= ' AND i.invoice_date < ?';  $params[] = $toFilter; }
        if ($paidFromFilter !== '') { $sql .= ' AND i.paid_at >= ?'; $params[] = $paidFromFilter; }
        if ($paidToFilter !== '')   { $sql .= ' AND i.paid_at < ?';  $params[] = $paidToFilter; }
        if ($balanceFilter !== '') {
            $sql .= " AND i.status = 'issued' AND (i.grand_total - i.amount_paid) > 0.01";
            if ($balanceFilter === 'overdue') {
                $sql .= ' AND i.due_date IS NOT NULL AND i.due_date < CURDATE()';
            } elseif (str_starts_with($balanceFilter, 'aging_') && $balanceFilter !== 'aging_current') {
                [$lo, $hi] = match ($balanceFilter) {
                    'aging_d1_30'   => [1, 30],
                    'aging_d31_60'  => [31, 60],
                    'aging_d61_90'  => [61, 90],
                    'aging_d90_plus' => [91, 100000],
                    default => [0, 0],
                };
                $sql .= ' AND i.due_date IS NOT NULL AND DATEDIFF(CURDATE(), i.due_date) BETWEEN ? AND ?';
                $params[] = $lo; $params[] = $hi;
            } elseif ($balanceFilter === 'aging_current') {
                $sql .= ' AND (i.due_date IS NULL OR i.due_date >= CURDATE())';
            }
            // 'outstanding' needs no extra clause beyond the balance>0 check above
        }
        if ($search !== '') {
            $sql .= ' AND (i.client_name LIKE ? OR i.invoice_no LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $sql .= ' ORDER BY i.created_at DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();

        $cases = $this->pdo->query(
            'SELECT id, case_number, title, client_name FROM legalops_cases ORDER BY created_at DESC'
        )->fetchAll();

        $caseIdFilterLabel = null;
        if ($caseIdFilter > 0) {
            $stmt = $this->pdo->prepare('SELECT case_number, title FROM legalops_cases WHERE id = ?');
            $stmt->execute([$caseIdFilter]);
            if ($row = $stmt->fetch()) { $caseIdFilterLabel = $row['case_number'] . ' — ' . $row['title']; }
        }

        $this->view('billing/index', [
            'pageTitle'    => 'Billing',
            'activeNav'    => 'billing',
            'entities'     => $entities,
            'invoices'     => $invoices,
            'cases'        => $cases,
            'countries'    => self::COUNTRIES,
            'statusFilter' => $statusFilter,
            'entityFilter' => $entityFilter,
            'search'       => $search,
            'currencyFilter' => $currencyFilter,
            'fromFilter'   => $fromFilter,
            'toFilter'     => $toFilter,
            'paidFromFilter' => $paidFromFilter,
            'paidToFilter' => $paidToFilter,
            'balanceFilter' => $balanceFilter,
            'caseIdFilter'  => $caseIdFilter,
            'caseIdFilterLabel' => $caseIdFilterLabel,
            'pdfReady'     => invoice_pdf_engine_ready(),
        ]);
    }

    /** Handles the invoice form panel's action=save|issue|void|delete (mirrors the old billing.php POST switch). */
    public function store(): void
    {
        $user = $this->requireLogin();
        require_once dirname(__DIR__, 2) . '/libs/Invoicing.php';

        if (!csrf_valid()) {
            flash('error', 'Session expired.');
            $this->redirect('billing');
        }

        $entitiesById = [];
        foreach ($this->pdo->query('SELECT * FROM legalops_billing_entities')->fetchAll() as $e) {
            $entitiesById[(int)$e['id']] = $e;
        }

        $action = $_POST['action'] ?? '';
        $uid    = (int)$user['uid'];

        match ($action) {
            'save'            => $this->saveInvoice($uid, $entitiesById),
            'issue'           => $this->issueInvoice($uid, (int)($_POST['id'] ?? 0), $entitiesById),
            'void'            => $this->voidInvoice($uid, (int)($_POST['id'] ?? 0)),
            'delete'          => $this->deleteInvoice($uid, (int)($_POST['id'] ?? 0)),
            'record_payment'  => $this->recordPayment($uid, (int)($_POST['id'] ?? 0)),
            default           => null,
        };

        $this->redirect('billing');
    }

    /** GET /billing/invoices/{id}/items — feeds the edit panel's line-item fetch (was invoice_items.php). */
    public function items(array $params): void
    {
        $this->requireLogin();
        $id = (int)$params['id'];

        $stmt = $this->pdo->prepare(
            'SELECT description, hsn_sac, quantity, unit_price FROM legalops_invoice_items WHERE invoice_id = ? ORDER BY sort_order'
        );
        $stmt->execute([$id]);
        $this->json($stmt->fetchAll());
    }

    /** GET /billing/invoices/{id}/pdf — streams the invoice PDF (was invoice_download.php). */
    public function pdf(array $params): void
    {
        $this->requireLogin();
        require_once dirname(__DIR__, 2) . '/libs/Invoicing.php';
        require_once dirname(__DIR__, 2) . '/libs/InvoicePdf.php';

        $id = (int)$params['id'];

        $stmt = $this->pdo->prepare('SELECT * FROM legalops_invoices WHERE id = ?');
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            $this->abort(404, 'Invoice not found.');
        }

        $itemsStmt = $this->pdo->prepare('SELECT * FROM legalops_invoice_items WHERE invoice_id = ? ORDER BY sort_order');
        $itemsStmt->execute([$id]);
        $items = $itemsStmt->fetchAll();

        $entityStmt = $this->pdo->prepare('SELECT * FROM legalops_billing_entities WHERE id = ?');
        $entityStmt->execute([$invoice['billing_entity_id']]);
        $entity = $entityStmt->fetch();
        if (!$entity) {
            $this->abort(500, 'The billing entity for this invoice could not be found.');
        }

        try {
            $pdf = render_invoice_pdf($invoice, $items, $entity);
        } catch (\Throwable $e) {
            $this->abort(500, $e->getMessage());
        }

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $invoice['invoice_no']) . '.pdf';

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    // ── Action handlers (mirror the old billing.php functions) ────────────────

    private function readItemsFromPost(): array
    {
        $desc  = $_POST['item_description'] ?? [];
        $hsn   = $_POST['item_hsn_sac'] ?? [];
        $qty   = $_POST['item_quantity'] ?? [];
        $price = $_POST['item_unit_price'] ?? [];

        $items = [];
        foreach ($desc as $i => $d) {
            $d = trim($d);
            if ($d === '') {
                continue; // skip blank rows the JS may have left behind
            }
            $items[] = [
                'description' => $d,
                'hsn_sac'     => trim($hsn[$i] ?? '') ?: null,
                'quantity'    => (float)($qty[$i] ?? 1),
                'unit_price'  => (float)($price[$i] ?? 0),
            ];
        }
        return $items;
    }

    private function saveInvoice(int $uid, array $entitiesById): void
    {
        $id       = (int)($_POST['id'] ?? 0);
        $entityId = (int)($_POST['billing_entity_id'] ?? 0);
        $entity   = $entitiesById[$entityId] ?? null;

        $clientName    = trim($_POST['client_name'] ?? '');
        $taxProfileKey = trim($_POST['tax_profile_key'] ?? '');
        $items         = $this->readItemsFromPost();

        if (!$entity || $clientName === '' || tax_profile($taxProfileKey) === null || !$items) {
            flash('error', 'Billing entity, client name, a valid tax treatment, and at least one line item are required.');
            return;
        }

        $totals = compute_invoice_totals($items, $taxProfileKey);

        $data = [
            'billing_entity_id' => $entityId,
            'case_id'           => $_POST['case_id'] !== '' ? (int)$_POST['case_id'] : null,
            'client_name'       => $clientName,
            'client_country'    => trim($_POST['client_country'] ?? '') ?: null,
            'client_tax_reg_no' => trim($_POST['client_tax_reg_no'] ?? '') ?: null,
            'client_address'    => trim($_POST['client_address'] ?? '') ?: null,
            'tax_profile_key'   => $taxProfileKey,
            'place_of_supply'   => trim($_POST['place_of_supply'] ?? '') ?: null,
            'currency'          => trim($_POST['currency'] ?? '') ?: $entity['default_currency'],
            'invoice_date'      => $_POST['invoice_date'] ?: date('Y-m-d'),
            'due_date'          => $_POST['due_date'] ?: null,
            'notes'             => trim($_POST['notes'] ?? '') ?: null,
            'subtotal'          => $totals['subtotal'],
            'tax_total'         => $totals['tax_total'],
            'grand_total'       => $totals['grand_total'],
            'tax_breakdown'     => json_encode($totals['tax_breakdown']),
        ];

        $pdo = $this->pdo;
        $pdo->beginTransaction();
        try {
            if ($id > 0) {
                $existing = $pdo->prepare('SELECT status FROM legalops_invoices WHERE id = ?');
                $existing->execute([$id]);
                $row = $existing->fetch();
                if (!$row || $row['status'] !== 'draft') {
                    throw new \RuntimeException('Only draft invoices can be edited.');
                }

                $sql = 'UPDATE legalops_invoices SET billing_entity_id=?, case_id=?, client_name=?, client_country=?, '
                     . 'client_tax_reg_no=?, client_address=?, tax_profile_key=?, place_of_supply=?, currency=?, '
                     . 'invoice_date=?, due_date=?, notes=?, subtotal=?, tax_total=?, grand_total=?, tax_breakdown=? '
                     . 'WHERE id=?';
                $pdo->prepare($sql)->execute([
                    $data['billing_entity_id'], $data['case_id'], $data['client_name'], $data['client_country'],
                    $data['client_tax_reg_no'], $data['client_address'], $data['tax_profile_key'], $data['place_of_supply'],
                    $data['currency'], $data['invoice_date'], $data['due_date'], $data['notes'],
                    $data['subtotal'], $data['tax_total'], $data['grand_total'], $data['tax_breakdown'], $id,
                ]);
                $pdo->prepare('DELETE FROM legalops_invoice_items WHERE invoice_id = ?')->execute([$id]);
                log_activity($pdo, $uid, 'invoice_updated', 'Updated draft invoice for ' . $clientName);
            } else {
                $sql = 'INSERT INTO legalops_invoices '
                     . '(invoice_no, billing_entity_id, case_id, client_name, client_country, client_tax_reg_no, '
                     . 'client_address, tax_profile_key, place_of_supply, currency, invoice_date, due_date, notes, '
                     . "subtotal, tax_total, grand_total, tax_breakdown, status, created_by) "
                     . "VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'draft',?)";
                // Drafts get a placeholder number ("DRAFT-<id>") — the real
                // sequential number is only reserved when the invoice is issued.
                $pdo->prepare($sql)->execute([
                    'DRAFT', $data['billing_entity_id'], $data['case_id'], $data['client_name'], $data['client_country'],
                    $data['client_tax_reg_no'], $data['client_address'], $data['tax_profile_key'], $data['place_of_supply'],
                    $data['currency'], $data['invoice_date'], $data['due_date'], $data['notes'],
                    $data['subtotal'], $data['tax_total'], $data['grand_total'], $data['tax_breakdown'], $uid,
                ]);
                $id = (int)$pdo->lastInsertId();
                $pdo->prepare('UPDATE legalops_invoices SET invoice_no = ? WHERE id = ?')
                    ->execute(['DRAFT-' . $id, $id]);
                log_activity($pdo, $uid, 'invoice_created', 'Drafted a new invoice for ' . $clientName);
            }

            $itemStmt = $pdo->prepare(
                'INSERT INTO legalops_invoice_items '
                . '(invoice_id, description, hsn_sac, quantity, unit_price, tax_rate, line_subtotal, line_tax, line_total, sort_order) '
                . 'VALUES (?,?,?,?,?,?,?,?,?,?)'
            );
            foreach ($totals['items'] as $i => $item) {
                $itemStmt->execute([
                    $id, $item['description'], $item['hsn_sac'], $item['quantity'], $item['unit_price'],
                    $item['tax_rate'], $item['line_subtotal'], $item['line_tax'], $item['line_total'], $i,
                ]);
            }

            $pdo->commit();
            flash('success', 'Invoice saved as draft.');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('error', $e->getMessage());
        }
    }

    private function issueInvoice(int $uid, int $id, array $entitiesById): void
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare('SELECT * FROM legalops_invoices WHERE id = ?');
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();
        if (!$invoice || $invoice['status'] !== 'draft') {
            flash('error', 'Only draft invoices can be issued.');
            return;
        }
        $entity = $entitiesById[(int)$invoice['billing_entity_id']] ?? null;
        if (!$entity) {
            flash('error', 'The billing entity for this invoice could not be found.');
            return;
        }

        $pdo->beginTransaction();
        try {
            $period = invoice_period_key($entity, $invoice['invoice_date']);
            $number = next_invoice_number($pdo, (int)$entity['id'], $period, $entity['invoice_prefix']);
            $pdo->prepare("UPDATE legalops_invoices SET invoice_no = ?, status = 'issued', issued_at = NOW() WHERE id = ?")
                ->execute([$number, $id]);
            log_activity($pdo, $uid, 'invoice_issued', "Issued invoice {$number} — {$invoice['client_name']}");
            $pdo->commit();
            flash('success', "Invoice {$number} issued.");
        } catch (\Throwable $e) {
            $pdo->rollBack();
            flash('error', $e->getMessage());
        }
    }

    private function recordPayment(int $uid, int $id): void
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare('SELECT * FROM legalops_invoices WHERE id = ?');
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();
        if (!$invoice || $invoice['status'] !== 'issued') {
            flash('error', 'Only issued invoices can have a payment recorded against them.');
            return;
        }

        $amount = (float)($_POST['payment_amount'] ?? 0);
        $ref    = trim($_POST['payment_reference'] ?? '') ?: null;
        $balanceBefore = (float)$invoice['grand_total'] - (float)$invoice['amount_paid'];

        if ($amount <= 0) {
            flash('error', 'Enter a payment amount greater than zero.');
            return;
        }
        if ($amount > $balanceBefore + 0.01) { // small epsilon for float rounding
            flash('error', sprintf('That\'s more than the outstanding balance (%s %s).', $invoice['currency'], number_format($balanceBefore, 2)));
            return;
        }

        $newPaid = round((float)$invoice['amount_paid'] + $amount, 2);
        $pdo->prepare('UPDATE legalops_invoices SET amount_paid = ?, paid_at = NOW(), payment_reference = ? WHERE id = ?')
            ->execute([$newPaid, $ref, $id]);

        $fullyPaid = $newPaid >= (float)$invoice['grand_total'] - 0.01;
        log_activity($pdo, $uid, 'invoice_payment', sprintf(
            'Recorded payment of %s %s against invoice %s%s',
            $invoice['currency'], number_format($amount, 2), $invoice['invoice_no'], $fullyPaid ? ' (now fully paid)' : ''
        ));
        flash('success', $fullyPaid
            ? "Payment recorded — invoice {$invoice['invoice_no']} is now fully paid."
            : "Payment recorded — {$invoice['currency']} " . number_format((float)$invoice['grand_total'] - $newPaid, 2) . ' still outstanding.');
    }

    private function voidInvoice(int $uid, int $id): void
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare('SELECT * FROM legalops_invoices WHERE id = ?');
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();
        if (!$invoice || $invoice['status'] !== 'issued') {
            flash('error', 'Only issued invoices can be voided.');
            return;
        }
        // The number is NEVER reused or deleted — voiding keeps the audit
        // trail gapless, which both GST and ZATCA expect.
        $pdo->prepare("UPDATE legalops_invoices SET status = 'void' WHERE id = ?")->execute([$id]);
        log_activity($pdo, $uid, 'invoice_void', "Voided invoice {$invoice['invoice_no']} — {$invoice['client_name']}");
        flash('success', "Invoice {$invoice['invoice_no']} marked void (number retained for audit purposes).");
    }

    private function deleteInvoice(int $uid, int $id): void
    {
        $pdo = $this->pdo;
        $stmt = $pdo->prepare('SELECT * FROM legalops_invoices WHERE id = ?');
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();
        if (!$invoice || $invoice['status'] !== 'draft') {
            flash('error', 'Only draft invoices can be deleted.');
            return;
        }
        $pdo->prepare('DELETE FROM legalops_invoice_items WHERE invoice_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM legalops_invoices WHERE id = ?')->execute([$id]);
        log_activity($pdo, $uid, 'invoice_deleted', "Deleted draft invoice — {$invoice['client_name']}");
        flash('success', 'Draft invoice deleted.');
    }
}
