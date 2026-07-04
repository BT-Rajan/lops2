<?php
/**
 * LegalOps — invoice PDF template.
 *
 * Rendered by libs/InvoicePdf.php via output buffering, then fed to
 * dompdf. Kept dompdf-safe: no flexbox/grid, table-based layout only.
 *
 * Variables in scope (see render_invoice_html()):
 *   $invoice   row from legalops_invoices
 *   $items     rows from legalops_invoice_items
 *   $entity    row from legalops_billing_entities
 *   $profile   the resolved tax_profiles.php entry
 *   $breakdown ['CGST' => ['rate'=>9,'amount'=>123.45], ...]
 */

$money = static fn(float $n) => number_format($n, 2);
$isDraft = $invoice['status'] === 'draft';
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1b1b1f; }
  table { width: 100%; border-collapse: collapse; }
  .head-table td { vertical-align: top; }
  .brand { font-size: 20px; font-weight: bold; }
  .muted { color: #666; }
  .right { text-align: right; }
  .center { text-align: center; }
  .draft-badge { display: inline-block; padding: 3px 10px; border: 1.5px solid #b91c1c; color: #b91c1c;
                 font-weight: bold; letter-spacing: 1px; }
  .items { margin-top: 22px; }
  .items th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 11px; text-transform: uppercase; }
  .items td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
  .totals-table { width: 260px; margin-left: auto; margin-top: 10px; }
  .totals-table td { padding: 3px 0; }
  .totals-table .grand td { border-top: 1.5px solid #1b1b1f; font-weight: bold; font-size: 13.5px; padding-top: 6px; }
  .declaration { margin-top: 18px; font-size: 10.5px; color: #444; font-style: italic; }
  .footer { margin-top: 26px; font-size: 10.5px; color: #666; }
  hr { border: none; border-top: 1px solid #e5e7eb; margin: 14px 0; }
</style>
</head>
<body>

<table class="head-table">
  <tr>
    <td style="width:60%">
      <div class="brand"><?= htmlspecialchars($entity['name']) ?></div>
      <?php if (!empty($entity['address'])): ?>
        <div class="muted"><?= nl2br(htmlspecialchars($entity['address'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($profile['reg_label']) && !empty($entity['tax_reg_no'])): ?>
        <div class="muted"><?= htmlspecialchars($profile['reg_label']) ?>: <?= htmlspecialchars($entity['tax_reg_no']) ?></div>
      <?php endif; ?>
    </td>
    <td class="right">
      <div class="brand">INVOICE</div>
      <?php if ($isDraft): ?>
        <div style="margin:6px 0"><span class="draft-badge">DRAFT</span></div>
      <?php else: ?>
        <div class="muted">No: <strong><?= htmlspecialchars($invoice['invoice_no']) ?></strong></div>
      <?php endif; ?>
      <div class="muted">Date: <?= htmlspecialchars(date('d M Y', strtotime($invoice['invoice_date']))) ?></div>
      <?php if (!empty($invoice['due_date'])): ?>
        <div class="muted">Due: <?= htmlspecialchars(date('d M Y', strtotime($invoice['due_date']))) ?></div>
      <?php endif; ?>
    </td>
  </tr>
</table>

<hr>

<table class="head-table">
  <tr>
    <td style="width:60%">
      <div class="muted" style="text-transform:uppercase;font-size:10.5px;margin-bottom:3px">Bill to</div>
      <div style="font-weight:bold"><?= htmlspecialchars($invoice['client_name']) ?></div>
      <?php if (!empty($invoice['client_address'])): ?>
        <div class="muted"><?= nl2br(htmlspecialchars($invoice['client_address'])) ?></div>
      <?php endif; ?>
      <?php if (!empty($profile['reg_label']) && !empty($invoice['client_tax_reg_no'])): ?>
        <div class="muted"><?= htmlspecialchars($profile['reg_label']) ?>: <?= htmlspecialchars($invoice['client_tax_reg_no']) ?></div>
      <?php endif; ?>
    </td>
    <td class="right">
      <?php if (!empty($invoice['place_of_supply'])): ?>
        <div class="muted">Place of supply: <?= htmlspecialchars($invoice['place_of_supply']) ?></div>
      <?php endif; ?>
      <div class="muted">Currency: <?= htmlspecialchars($invoice['currency']) ?></div>
      <div class="muted"><?= htmlspecialchars($profile['label'] ?? $invoice['tax_profile_key']) ?></div>
    </td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th>Description</th>
      <?php if (!empty($profile['needs_hsn'])): ?><th>HSN/SAC</th><?php endif; ?>
      <th class="right">Qty</th>
      <th class="right">Unit price</th>
      <th class="right">Tax %</th>
      <th class="right">Line total</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $item): ?>
      <tr>
        <td><?= htmlspecialchars($item['description']) ?></td>
        <?php if (!empty($profile['needs_hsn'])): ?><td><?= htmlspecialchars($item['hsn_sac'] ?? '—') ?></td><?php endif; ?>
        <td class="right"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.')) ?></td>
        <td class="right"><?= $money((float)$item['unit_price']) ?></td>
        <td class="right"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['tax_rate'], 2), '0'), '.')) ?>%</td>
        <td class="right"><?= $money((float)$item['line_total']) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table class="totals-table">
  <tr><td>Subtotal</td><td class="right"><?= $money((float)$invoice['subtotal']) ?></td></tr>
  <?php foreach ($breakdown as $component => $row): ?>
    <?php if ((float)$row['amount'] > 0 || (float)$row['rate'] > 0): ?>
      <tr><td><?= htmlspecialchars($component) ?> (<?= htmlspecialchars(rtrim(rtrim(number_format((float)$row['rate'], 2), '0'), '.')) ?>%)</td>
          <td class="right"><?= $money((float)$row['amount']) ?></td></tr>
    <?php endif; ?>
  <?php endforeach; ?>
  <tr class="grand"><td>Total</td><td class="right"><?= htmlspecialchars($invoice['currency']) ?> <?= $money((float)$invoice['grand_total']) ?></td></tr>
</table>

<?php if (!empty($profile['declaration'])): ?>
  <div class="declaration"><?= htmlspecialchars($profile['declaration']) ?></div>
<?php endif; ?>

<?php if (!empty($invoice['notes'])): ?>
  <hr>
  <div><strong>Notes</strong><br><?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
<?php endif; ?>

<?php if (!empty($entity['bank_details'])): ?>
  <div class="footer"><strong>Payment details:</strong> <?= htmlspecialchars($entity['bank_details']) ?></div>
<?php endif; ?>

</body>
</html>
