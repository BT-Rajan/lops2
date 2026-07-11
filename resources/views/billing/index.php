<div class="page-head">
  <div>
    <span class="eyebrow-gold">Practice ledger</span>
    <h1>Billing</h1>
    <p class="sub"><?= count($invoices) ?> invoice<?= count($invoices) === 1 ? '' : 's' ?> on file.</p>
  </div>
  <div style="display:flex;gap:10px">
    <a class="btn btn-ghost" href="<?= url('billing/entities') ?>"><?= icon('building') ?> Billing entities</a>
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
      <div style="margin-top:14px"><a class="btn btn-primary" href="<?= url('billing/entities') ?>">Add a billing entity</a></div>
    </div>
  </div>
<?php endif; ?>

<!-- Inline create/edit panel -->
<div class="card inline-panel" id="invoice-panel">
  <form method="post" action="<?= url('billing') ?>" id="invoice-form">
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

<?php
$balanceLabels = [
    'outstanding' => 'Outstanding invoices', 'overdue' => 'Overdue invoices',
    'aging_current' => 'Not yet due', 'aging_d1_30' => '1–30 days overdue',
    'aging_d31_60' => '31–60 days overdue', 'aging_d61_90' => '61–90 days overdue', 'aging_d90_plus' => '90+ days overdue',
];
$drillParts = [];
if ($balanceFilter !== '' && isset($balanceLabels[$balanceFilter])) $drillParts[] = $balanceLabels[$balanceFilter];
if ($currencyFilter !== '') $drillParts[] = $currencyFilter;
if ($fromFilter !== '' || $toFilter !== '') $drillParts[] = 'invoiced ' . ($fromFilter ? date('d M Y', strtotime($fromFilter)) : '…') . ' – ' . ($toFilter ? date('d M Y', strtotime($toFilter . ' -1 day')) : '…');
if ($paidFromFilter !== '' || $paidToFilter !== '') $drillParts[] = 'paid ' . ($paidFromFilter ? date('d M Y', strtotime($paidFromFilter)) : '…') . ' – ' . ($paidToFilter ? date('d M Y', strtotime($paidToFilter . ' -1 day')) : '…');
if ($caseIdFilter > 0 && $caseIdFilterLabel) $drillParts[] = 'matter: ' . $caseIdFilterLabel;
?>
<?php if ($drillParts): ?>
  <div class="alert alert-info" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <span>Showing: <strong><?= htmlspecialchars(implode(' · ', $drillParts)) ?></strong> — from Reports.</span>
    <a class="link" href="<?= url('billing') ?>">Clear filter ×</a>
  </div>
<?php endif; ?>

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
      <tr><th>Invoice</th><th>Client</th><th>Entity</th><th>Date</th><th>Total</th><th>Balance</th><th>Status</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($invoices as $inv):
        $balance = round((float)$inv['grand_total'] - (float)$inv['amount_paid'], 2);
        $isOverdue = $inv['status'] === 'issued' && $balance > 0.01 && $inv['due_date'] && $inv['due_date'] < date('Y-m-d');
      ?>
        <tr>
          <td><span class="mono"><?= htmlspecialchars($inv['invoice_no']) ?></span></td>
          <td class="case-client">
            <?= htmlspecialchars($inv['client_name']) ?>
            <?php if ($inv['case_number']): ?><br><a class="link" style="font-size:11.5px" href="<?= url('cases/' . (int)$inv['case_id']) ?>"><?= htmlspecialchars($inv['case_number']) ?></a><?php endif; ?>
          </td>
          <td class="case-client"><?= htmlspecialchars($inv['entity_name']) ?></td>
          <td class="case-client"><?= date('d M Y', strtotime($inv['invoice_date'])) ?></td>
          <td class="mono"><?= htmlspecialchars(format_money((float)$inv['grand_total'], $inv['currency'])) ?></td>
          <td class="mono">
            <?php if ($inv['status'] === 'issued'): ?>
              <?php if ($balance <= 0.01): ?>
                <span style="color:var(--success)">Paid</span>
              <?php else: ?>
                <span style="<?= $isOverdue ? 'color:var(--danger);font-weight:700' : '' ?>"><?= htmlspecialchars(format_money($balance, $inv['currency'])) ?></span>
                <?php if ($isOverdue): ?><div class="small" style="color:var(--danger)">Overdue</div>
                <?php elseif ((float)$inv['amount_paid'] > 0): ?><div class="small muted">Partially paid</div><?php endif; ?>
              <?php endif; ?>
            <?php else: ?>—<?php endif; ?>
          </td>
          <td><span class="badge badge-<?= htmlspecialchars($inv['status']) ?>"><?= htmlspecialchars(ucfirst($inv['status'])) ?></span></td>
          <td style="text-align:right;white-space:nowrap">
            <?php if ($inv['status'] === 'draft'): ?>
              <button class="icon-btn btn-sm invoice-edit-btn" style="display:inline-grid" type="button"
                data-invoice='<?= htmlspecialchars(json_encode($inv), ENT_QUOTES) ?>'><?= icon('edit') ?></button>
              <form method="post" action="<?= url('billing') ?>" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="issue">
                <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                <button class="icon-btn btn-sm" style="display:inline-grid" type="submit" title="Issue (assigns the official number)"><?= icon('check') ?></button>
              </form>
              <form method="post" action="<?= url('billing') ?>" style="display:inline" onsubmit="return confirm('Delete this draft? This can\'t be undone.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$inv['id'] ?>">
                <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)" type="submit"><?= icon('trash') ?></button>
              </form>
            <?php else: ?>
              <a class="icon-btn btn-sm" style="display:inline-grid" href="<?= url('billing/invoices/' . (int)$inv['id'] . '/pdf') ?>" target="_blank" title="View / download PDF"><?= icon('download') ?></a>
              <?php if ($inv['status'] === 'issued'): ?>
                <?php if ($balance > 0.01): ?>
                  <button class="icon-btn btn-sm record-payment-btn" style="display:inline-grid" type="button" title="Record payment"
                    data-id="<?= (int)$inv['id'] ?>" data-invoice-no="<?= htmlspecialchars($inv['invoice_no']) ?>"
                    data-balance="<?= $balance ?>" data-currency="<?= htmlspecialchars($inv['currency']) ?>"><?= icon('cash') ?></button>
                <?php endif; ?>
                <form method="post" action="<?= url('billing') ?>" style="display:inline" onsubmit="return confirm('Void this invoice? The number stays reserved for audit purposes.')">
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
      fetch('<?= url('billing/invoices/') ?>' + inv.id + '/items')
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

<!-- Record payment modal -->
<div class="modal-overlay" id="payment-modal-overlay">
<div class="card inline-panel" id="payment-modal" style="max-width:420px">
  <div class="card-head" style="padding:20px 24px 0">
    <h3 id="payment-modal-title">Record payment</h3>
    <span class="modal-close" id="payment-modal-close"><?= icon('close') ?></span>
  </div>
  <div class="card-pad" style="padding-top:14px">
    <form method="post" action="<?= url('billing') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="record_payment">
      <input type="hidden" name="id" id="payment-invoice-id">
      <p class="case-client" id="payment-balance-line" style="margin-bottom:14px"></p>
      <div class="field"><label>Amount received</label><input class="input mono" type="number" step="0.01" min="0.01" name="payment_amount" id="payment-amount" required></div>
      <div class="field"><label>Reference (optional)</label><input class="input" type="text" name="payment_reference" placeholder="UTR / cheque no. / transaction ID"></div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px">
        <button type="button" class="btn btn-ghost" id="payment-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Record payment</button>
      </div>
    </form>
  </div>
</div>
</div>

<script>
(function () {
  var overlay = document.getElementById('payment-modal-overlay');
  document.querySelectorAll('.record-payment-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.getElementById('payment-invoice-id').value = btn.dataset.id;
      var amountInput = document.getElementById('payment-amount');
      amountInput.value = btn.dataset.balance;
      amountInput.max = btn.dataset.balance;
      document.getElementById('payment-balance-line').textContent =
        'Invoice ' + btn.dataset.invoiceNo + ' — outstanding balance: ' + btn.dataset.currency + ' ' + parseFloat(btn.dataset.balance).toFixed(2);
      overlay.classList.add('open');
    });
  });
  document.getElementById('payment-modal-close').addEventListener('click', function () { overlay.classList.remove('open'); });
  document.getElementById('payment-cancel-btn').addEventListener('click', function () { overlay.classList.remove('open'); });
  overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.classList.remove('open'); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') overlay.classList.remove('open'); });
})();
</script>
