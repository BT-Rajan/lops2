<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/libs/Invoicing.php';
require_once __DIR__ . '/libs/InvoicePdf.php';

$current_user = require_login($auth);
$page_title = 'Billing';
$active_nav = 'billing';

$entities = $pdo->query('SELECT * FROM legalops_billing_entities WHERE is_active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$entitiesById = [];
foreach ($entities as $e) {
    $entitiesById[(int)$e['id']] = $e;
}

$countries = [
    'IN' => 'India', 'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'BH' => 'Bahrain',
    'OM' => 'Oman', 'KW' => 'Kuwait', 'QA' => 'Qatar', 'GB' => 'United Kingdom', 'US' => 'United States',
    'SG' => 'Singapore', 'XX' => 'Other',
];

// ---- Helpers --------------------------------------------------------------

function read_items_from_post(): array
{
    $desc = $_POST['item_description'] ?? [];
    $hsn = $_POST['item_hsn_sac'] ?? [];
    $qty = $_POST['item_quantity'] ?? [];
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

function save_invoice(PDO $pdo, int $uid, array $entitiesById): void
{
    $id = (int)($_POST['id'] ?? 0);
    $entityId = (int)($_POST['billing_entity_id'] ?? 0);
    $entity = $entitiesById[$entityId] ?? null;

    $clientName = trim($_POST['client_name'] ?? '');
    $taxProfileKey = trim($_POST['tax_profile_key'] ?? '');
    $items = read_items_from_post();

    if (!$entity || $clientName === '' || tax_profile($taxProfileKey) === null || !$items) {
        flash('error', 'Billing entity, client name, a valid tax treatment, and at least one line item are required.');
        return;
    }

    $totals = compute_invoice_totals($items, $taxProfileKey);

    $data = [
        'billing_entity_id'  => $entityId,
        'case_id'            => $_POST['case_id'] !== '' ? (int)$_POST['case_id'] : null,
        'client_name'        => $clientName,
        'client_country'     => trim($_POST['client_country'] ?? '') ?: null,
        'client_tax_reg_no'  => trim($_POST['client_tax_reg_no'] ?? '') ?: null,
        'client_address'     => trim($_POST['client_address'] ?? '') ?: null,
        'tax_profile_key'    => $taxProfileKey,
        'place_of_supply'    => trim($_POST['place_of_supply'] ?? '') ?: null,
        'currency'           => trim($_POST['currency'] ?? '') ?: $entity['default_currency'],
        'invoice_date'       => $_POST['invoice_date'] ?: date('Y-m-d'),
        'due_date'           => $_POST['due_date'] ?: null,
        'notes'              => trim($_POST['notes'] ?? '') ?: null,
        'subtotal'           => $totals['subtotal'],
        'tax_total'          => $totals['tax_total'],
        'grand_total'        => $totals['grand_total'],
        'tax_breakdown'      => json_encode($totals['tax_breakdown']),
    ];

    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            $existing = $pdo->prepare('SELECT status FROM legalops_invoices WHERE id = ?');
            $existing->execute([$id]);
            $row = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$row || $row['status'] !== 'draft') {
                throw new RuntimeException('Only draft invoices can be edited.');
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
                 . 'subtotal, tax_total, grand_total, tax_breakdown, status, created_by) '
                 . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'draft\',?)';
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
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', $e->getMessage());
    }
}

function issue_invoice(PDO $pdo, int $uid, int $id, array $entitiesById): void
{
    $stmt = $pdo->prepare('SELECT * FROM legalops_invoices WHERE id = ?');
    $stmt->execute([$id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
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
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', $e->getMessage());
    }
}

function void_invoice(PDO $pdo, int $uid, int $id): void
{
    $stmt = $pdo->prepare('SELECT * FROM legalops_invoices WHERE id = ?');
    $stmt->execute([$id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
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

function delete_invoice(PDO $pdo, int $uid, int $id): void
{
    $stmt = $pdo->prepare('SELECT * FROM legalops_invoices WHERE id = ?');
    $stmt->execute([$id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice || $invoice['status'] !== 'draft') {
        flash('error', 'Only draft invoices can be deleted.');
        return;
    }
    $pdo->prepare('DELETE FROM legalops_invoice_items WHERE invoice_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM legalops_invoices WHERE id = ?')->execute([$id]);
    log_activity($pdo, $uid, 'invoice_deleted', "Deleted draft invoice — {$invoice['client_name']}");
    flash('success', 'Draft invoice deleted.');
}

// ---- Handle POST actions ---------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        save_invoice($pdo, (int)$current_user['uid'], $entitiesById);
    } elseif ($action === 'issue') {
        issue_invoice($pdo, (int)$current_user['uid'], (int)($_POST['id'] ?? 0), $entitiesById);
    } elseif ($action === 'void') {
        void_invoice($pdo, (int)$current_user['uid'], (int)($_POST['id'] ?? 0));
    } elseif ($action === 'delete') {
        delete_invoice($pdo, (int)$current_user['uid'], (int)($_POST['id'] ?? 0));
    }
    header('Location: ' . base_url('billing.php'));
    exit;
}

// ---- Fetch for listing ------------------------------------------------------

$statusFilter = $_GET['status'] ?? 'all';
$entityFilter = (int)($_GET['entity'] ?? 0);
$search = trim($_GET['q'] ?? '');

$sql = 'SELECT i.*, e.name AS entity_name FROM legalops_invoices i
        JOIN legalops_billing_entities e ON e.id = i.billing_entity_id WHERE 1=1';
$params = [];
if (in_array($statusFilter, ['draft', 'issued', 'void'], true)) {
    $sql .= ' AND i.status = ?';
    $params[] = $statusFilter;
}
if ($entityFilter > 0) {
    $sql .= ' AND i.billing_entity_id = ?';
    $params[] = $entityFilter;
}
if ($search !== '') {
    $sql .= ' AND (i.client_name LIKE ? OR i.invoice_no LIKE ?)';
    $like = '%' . $search . '%';
    $params[] = $like; $params[] = $like;
}
$sql .= ' ORDER BY i.created_at DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cases = $pdo->query("SELECT id, case_number, title, client_name FROM legalops_cases ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

$pdfReady = invoice_pdf_engine_ready();

require __DIR__ . '/includes/app_header.php';
?>

<div class="page-head">
  <div>
    <span class="eyebrow-gold">Practice ledger</span>
    <h1>Billing</h1>
    <p class="sub"><?= count($invoices) ?> invoice<?= count($invoices) === 1 ? '' : 's' ?> on file.</p>
  </div>
  <div style="display:flex;gap:10px">
    <a class="btn btn-ghost" href="<?= base_url('billing_entities.php') ?>"><?= icon('building') ?> Billing entities</a>
    <button class="btn btn-primary" type="button" id="invoice-toggle-btn"<?= !$entities ? ' disabled title="Add a billing entity first"' : '' ?>><?= icon('plus') ?> New invoice</button>
  </div>
</div>

<?php if (!$pdfReady): ?>
  <div class="alert alert-error" style="margin-bottom:18px">
    PDF engine not installed yet. Run <code>composer require dompdf/dompdf</code> in the legalops folder to enable
    downloading/printing invoices. You can still create and issue invoices without it.
  </div>
<?php endif; ?>

<?php if (!$entities): ?>
  <div class="card card-pad" style="margin-bottom:18px">
    <div class="empty-state">
      <?= icon('building') ?>
      <p>No billing entities yet. Add the India / GCC entity you'll be invoicing from before creating your first invoice.</p>
      <div style="margin-top:14px"><a class="btn btn-primary" href="<?= base_url('billing_entities.php') ?>">Add a billing entity</a></div>
    </div>
  </div>
<?php endif; ?>

<!-- Inline create/edit panel -->
<div class="card inline-panel" id="invoice-panel">
  <form method="post" id="invoice-form">
    <div class="card-head" style="padding:20px 24px 0">
      <h3 id="invoice-panel-title">New invoice</h3>
      <span class="modal-close" id="invoice-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="f-id" value="">

      <div class="input-row">
        <div class="field">
          <label>Billing entity</label>
          <select class="input" name="billing_entity_id" id="f-entity" required>
            <option value="">Select…</option>
            <?php foreach ($entities as $e): ?>
              <option value="<?= (int)$e['id'] ?>"
                data-type="<?= htmlspecialchars($e['entity_type']) ?>"
                data-country="<?= htmlspecialchars($e['country']) ?>"
                data-state="<?= htmlspecialchars($e['state_or_emirate'] ?? '') ?>"
                data-currency="<?= htmlspecialchars($e['default_currency']) ?>">
                <?= htmlspecialchars($e['name']) ?> (<?= htmlspecialchars($e['country']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Related matter (optional)</label>
          <select class="input" name="case_id" id="f-case">
            <option value="">No matter linked</option>
            <?php foreach ($cases as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['case_number'] . ' — ' . $c['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="input-row">
        <div class="field">
          <label>Client name</label>
          <input class="input" type="text" name="client_name" id="f-client_name" placeholder="Client or company name" required>
        </div>
        <div class="field">
          <label>Client country</label>
          <select class="input" name="client_country" id="f-client_country">
            <?php foreach ($countries as $code => $label): ?>
              <option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="input-row">
        <div class="field">
          <label>Client GSTIN / TRN (optional)</label>
          <input class="input mono" type="text" name="client_tax_reg_no" id="f-client_tax_reg_no" placeholder="e.g. 33BBBBB1111B1Z2 or 100123456700003">
        </div>
        <div class="field">
          <label>Place of supply (state / emirate)</label>
          <input class="input" type="text" name="place_of_supply" id="f-place_of_supply" placeholder="e.g. Tamil Nadu, or Dubai">
        </div>
      </div>

      <div class="field">
        <label>Client address</label>
        <textarea class="input" name="client_address" id="f-client_address" rows="2" placeholder="Billing address"></textarea>
      </div>

      <div class="input-row">
        <div class="field">
          <label>Tax treatment</label>
          <select class="input" name="tax_profile_key" id="f-tax_profile_key" required>
            <?php foreach (tax_profiles() as $key => $p): ?>
              <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($p['label']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="small muted" style="margin-top:4px">Auto-suggested from entity + client country — double-check before issuing.</div>
        </div>
        <div class="field">
          <label>Currency</label>
          <input class="input mono" type="text" name="currency" id="f-currency" maxlength="3" style="text-transform:uppercase" placeholder="INR">
        </div>
      </div>

      <div class="input-row">
        <div class="field">
          <label>Invoice date</label>
          <input class="input" type="date" name="invoice_date" id="f-invoice_date" required>
        </div>
        <div class="field">
          <label>Due date</label>
          <input class="input" type="date" name="due_date" id="f-due_date">
        </div>
      </div>

      <div class="field" style="margin-top:6px">
        <label>Line items</label>
        <table class="table" id="items-table">
          <thead>
            <tr>
              <th>Description</th><th style="width:90px">HSN/SAC</th><th style="width:60px">Qty</th>
              <th style="width:100px">Unit price</th><th style="width:100px">Line total</th><th style="width:36px"></th>
            </tr>
          </thead>
          <tbody id="items-body"></tbody>
        </table>
        <button type="button" class="btn btn-ghost btn-sm" id="add-item-btn" style="margin-top:8px"><?= icon('plus') ?> Add line</button>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:28px;margin-top:10px;font-size:13.5px">
        <div>Subtotal: <strong id="preview-subtotal">0.00</strong></div>
        <div>Tax: <strong id="preview-tax">0.00</strong></div>
        <div>Total: <strong id="preview-total">0.00</strong></div>
      </div>
      <div class="small muted" style="text-align:right;margin-top:2px">Preview only — final figures are recalculated on save.</div>

      <div class="field" style="margin-top:10px">
        <label>Notes (optional)</label>
        <textarea class="input" name="notes" id="f-notes" rows="2" placeholder="Shown on the invoice, e.g. payment terms"></textarea>
      </div>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" class="btn btn-ghost" id="invoice-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save as draft</button>
      </div>
    </div>
  </form>
</div>

<form method="get" class="toolbar">
  <input class="input" type="text" name="q" placeholder="Search by client or invoice #…" value="<?= htmlspecialchars($search) ?>">
  <select class="input" name="entity" onchange="this.form.submit()">
    <option value="0">All entities</option>
    <?php foreach ($entities as $e): ?>
      <option value="<?= (int)$e['id'] ?>" <?= $entityFilter === (int)$e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
    <?php endforeach; ?>
  </select>
  <a class="filter-chip <?= $statusFilter === 'all' ? 'active' : '' ?>" href="?status=all">All</a>
  <a class="filter-chip <?= $statusFilter === 'draft' ? 'active' : '' ?>" href="?status=draft">Draft</a>
  <a class="filter-chip <?= $statusFilter === 'issued' ? 'active' : '' ?>" href="?status=issued">Issued</a>
  <a class="filter-chip <?= $statusFilter === 'void' ? 'active' : '' ?>" href="?status=void">Void</a>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad">
  <?php if ($invoices): ?>
  <table class="table">
    <thead>
      <tr><th>Invoice</th><th>Client</th><th>Entity</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr>
          <td><span class="mono"><?= htmlspecialchars($inv['invoice_no']) ?></span></td>
          <td class="case-client"><?= htmlspecialchars($inv['client_name']) ?></td>
          <td class="case-client"><?= htmlspecialchars($inv['entity_name']) ?></td>
          <td class="case-client"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
          <td class="mono"><?= htmlspecialchars(format_money((float)$inv['grand_total'], $inv['currency'])) ?></td>
          <td><span class="badge badge-<?= htmlspecialchars($inv['status']) ?>"><?= htmlspecialchars(ucfirst($inv['status'])) ?></span></td>
          <td style="text-align:right;white-space:nowrap">
            <?php if ($inv['status'] === 'draft'): ?>
              <button class="icon-btn btn-sm invoice-edit-btn" style="display:inline-grid" type="button"
                data-invoice='<?= htmlspecialchars(json_encode($inv), ENT_QUOTES) ?>'><?= icon('edit') ?></button>
              <form method="post" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="issue">
                <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                <button class="icon-btn btn-sm" style="display:inline-grid" type="submit" title="Issue (assigns the official number)"><?= icon('check') ?></button>
              </form>
              <form method="post" style="display:inline" onsubmit="return confirm('Delete this draft? This can\'t be undone.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)" type="submit"><?= icon('trash') ?></button>
              </form>
            <?php else: ?>
              <a class="icon-btn btn-sm" style="display:inline-grid" href="<?= base_url('invoice_download.php?id=' . (int)$inv['id']) ?>" target="_blank" title="View / download PDF"><?= icon('download') ?></a>
              <?php if ($inv['status'] === 'issued'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Void this invoice? The number stays reserved for audit purposes.')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="void">
                  <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                  <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)" type="submit" title="Void"><?= icon('close') ?></button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state"><?= icon('billing') ?><p>No invoices match that search.</p></div>
  <?php endif; ?>
</div>

<script>
(function () {
  // ---- suggestion logic mirrors libs/Invoicing.php::suggest_tax_profile() ----
  function suggestProfile(entityOpt, clientCountry, placeOfSupply) {
    if (!entityOpt) return '';
    var type = entityOpt.dataset.type, country = entityOpt.dataset.country, state = entityOpt.dataset.state;
    clientCountry = (clientCountry || '').toUpperCase();
    if (type === 'IN_GST') {
      if (clientCountry && clientCountry !== 'IN') return 'IN_GST_export';
      var same = (placeOfSupply || '').trim().toLowerCase() === (state || '').trim().toLowerCase();
      return same ? 'IN_GST_domestic' : 'IN_GST_interstate';
    }
    if (type === 'GCC_VAT') {
      if (clientCountry && clientCountry !== country) return 'GCC_VAT_zero_rated';
      var map = { AE: 'AE_VAT', SA: 'SA_VAT', BH: 'BH_VAT', OM: 'OM_VAT' };
      return map[country] || 'NO_VAT';
    }
    return 'NO_VAT';
  }

  var panel = document.getElementById('invoice-panel');
  var form = document.getElementById('invoice-form');
  var title = document.getElementById('invoice-panel-title');
  var entitySel = document.getElementById('f-entity');
  var countrySel = document.getElementById('f-client_country');
  var posInput = document.getElementById('f-place_of_supply');
  var profileSel = document.getElementById('f-tax_profile_key');
  var currencyInput = document.getElementById('f-currency');
  var itemsBody = document.getElementById('items-body');
  var ICON_TRASH = <?= json_encode(icon('trash')) ?>;

  function openPanel(scroll) {
    panel.classList.add('open');
    if (scroll) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function closePanel() { panel.classList.remove('open'); }
  function escAttr(s) { return String(s).replace(/"/g, '&quot;'); }

  function addItemRow(data) {
    data = data || {};
    var tr = document.createElement('tr');
    tr.innerHTML =
      '<td><input class="input" type="text" name="item_description[]" value="' + (data.description ? escAttr(data.description) : '') + '" placeholder="e.g. Drafting services"></td>' +
      '<td><input class="input mono" type="text" name="item_hsn_sac[]" value="' + (data.hsn_sac ? escAttr(data.hsn_sac) : '') + '"></td>' +
      '<td><input class="input qty" type="number" step="0.01" min="0" name="item_quantity[]" value="' + (data.quantity || 1) + '"></td>' +
      '<td><input class="input price" type="number" step="0.01" min="0" name="item_unit_price[]" value="' + (data.unit_price || '') + '"></td>' +
      '<td class="line-total mono">0.00</td>' +
      '<td><span class="icon-btn btn-sm remove-row" style="display:inline-grid;cursor:pointer">' + ICON_TRASH + '</span></td>';
    itemsBody.appendChild(tr);
    tr.querySelectorAll('input').forEach(function (inp) { inp.addEventListener('input', recalc); });
    tr.querySelector('.remove-row').addEventListener('click', function () { tr.remove(); recalc(); });
  }

  function recalc() {
    var rows = itemsBody.querySelectorAll('tr');
    var profile = profileSel.value;
    var rateMap = {
      IN_GST_domestic: 18, IN_GST_interstate: 18, IN_GST_export: 0,
      AE_VAT: 5, SA_VAT: 15, BH_VAT: 10, OM_VAT: 5, GCC_VAT_zero_rated: 0, NO_VAT: 0,
    };
    var rate = rateMap[profile] || 0;
    var subtotal = 0, tax = 0;
    rows.forEach(function (tr) {
      var qty = parseFloat(tr.querySelector('.qty').value) || 0;
      var price = parseFloat(tr.querySelector('.price').value) || 0;
      var lineSub = qty * price;
      var lineTax = lineSub * rate / 100;
      subtotal += lineSub; tax += lineTax;
      tr.querySelector('.line-total').textContent = (lineSub + lineTax).toFixed(2);
    });
    document.getElementById('preview-subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('preview-tax').textContent = tax.toFixed(2);
    document.getElementById('preview-total').textContent = (subtotal + tax).toFixed(2);
  }

  function applySuggestion() {
    var opt = entitySel.options[entitySel.selectedIndex];
    if (!opt || !opt.value) return;
    if (!currencyInput.value) currencyInput.value = opt.dataset.currency || '';
    var suggested = suggestProfile(opt, countrySel.value, posInput.value);
    if (suggested) profileSel.value = suggested;
    recalc();
  }

  entitySel.addEventListener('change', applySuggestion);
  countrySel.addEventListener('change', applySuggestion);
  posInput.addEventListener('input', applySuggestion);
  profileSel.addEventListener('change', recalc);

  function resetForm() {
    form.reset();
    document.getElementById('f-id').value = '';
    itemsBody.innerHTML = '';
    addItemRow({});
    title.textContent = 'New invoice';
    document.getElementById('f-invoice_date').value = new Date().toISOString().slice(0, 10);
    recalc();
  }

  document.getElementById('invoice-toggle-btn').addEventListener('click', function () {
    if (panel.classList.contains('open') && document.getElementById('f-id').value === '') {
      closePanel();
    } else {
      resetForm();
      openPanel(true);
    }
  });
  document.getElementById('invoice-cancel-btn').addEventListener('click', closePanel);
  document.getElementById('invoice-panel-close').addEventListener('click', closePanel);
  document.getElementById('add-item-btn').addEventListener('click', function () { addItemRow({}); recalc(); });

  document.querySelectorAll('.invoice-edit-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var inv = JSON.parse(btn.getAttribute('data-invoice'));
      title.textContent = 'Edit draft invoice';
      document.getElementById('f-id').value = inv.id;
      entitySel.value = inv.billing_entity_id;
      document.getElementById('f-case').value = inv.case_id || '';
      document.getElementById('f-client_name').value = inv.client_name;
      countrySel.value = inv.client_country || '';
      document.getElementById('f-client_tax_reg_no').value = inv.client_tax_reg_no || '';
      posInput.value = inv.place_of_supply || '';
      document.getElementById('f-client_address').value = inv.client_address || '';
      profileSel.value = inv.tax_profile_key;
      currencyInput.value = inv.currency;
      document.getElementById('f-invoice_date').value = inv.invoice_date;
      document.getElementById('f-due_date').value = inv.due_date || '';
      document.getElementById('f-notes').value = inv.notes || '';

      itemsBody.innerHTML = '';
      // Items aren't embedded in the row dataset (keeps the HTML light) —
      // fetched on demand only when an edit is opened.
      fetch('<?= base_url('invoice_items.php') ?>?id=' + inv.id)
        .then(function (r) { return r.json(); })
        .then(function (items) {
          if (!items.length) { addItemRow({}); } else { items.forEach(addItemRow); }
          recalc();
        });

      openPanel(true);
    });
  });

  resetForm();
})();
</script>

<?php require __DIR__ . '/includes/app_footer.php'; ?>
