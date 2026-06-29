<?php
/**
 * Invoice PDF layout. Variables in scope (passed from render_invoice_html()):
 *   $invoice   – legalops_invoices row (tax_breakdown already decoded into $breakdown)
 *   $items     – legalops_invoice_items rows
 *   $entity    – legalops_billing_entities row
 *   $profile   – the tax profile array (label, reg_label, needs_hsn, declaration)
 *   $breakdown – ['CGST' => ['rate'=>9,'amount'=>180], ...]
 *
 * Plain HTML/CSS only — dompdf doesn't support a modern browser's full
 * CSS feature set (no flexbox/grid), so this stays table-based and simple.
 */

$money = static fn (float $v): string => number_format($v, 2);
$statusLabel = ucfirst($invoice['status']);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
  @page { margin: 28px 34px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1b1b1f; }
  table { width: 100%; border-collapse: collapse; }
  .head-table td { vertical-align: top; padding: 0; }
  .brand { font-size: 19px; font-weight: bold; color: #1b1b1f; }
  .muted { color: #66666f; }
  .small { font-size: 9.5px; }
  .invoice-title { font-size: 22px; font-weight: bold; text-align: right; color: #1b1b1f; }
  .invoice-meta { text-align: right; font-size: 10.5px; line-height: 1.5; }
  .status-pill {
    display: inline-block; padding: 2px 9px; border-radius: 10px; font-size: 9px;
    font-weight: bold; text-transform: uppercase; letter-spacing: .03em;
  }
  .status-issued { background: #E7EEFC; color: #3B6FE0; }
  .status-draft  { background: #EFEFF2; color: #5B5870; }
  .status-void   { background: #FBE8E8; color: #C13B3B; }
  .section-gap { height: 18px; }
  .parties-table td { vertical-align: top; width: 50%; padding: 10px 14px; background: #F8F7F4; }
  .parties-table .label { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #8a8794; margin-bottom: 4px; }
  .parties-table .name { font-size: 12.5px; font-weight: bold; margin-bottom: 3px; }
  .items-table { margin-top: 18px; }
  .items-table th {
    background: #1b1b1f; color: #fff; font-size: 9.5px; text-transform: uppercase;
    letter-spacing: .03em; padding: 7px 8px; text-align: left;
  }
  .items-table th.num, .items-table td.num { text-align: right; }
  .items-table td { padding: 7px 8px; border-bottom: 1px solid #e3e1da; font-size: 10.5px; }
  .totals-table { margin-top: 10px; width: 260px; float: right; }
  .totals-table td { padding: 4px 0; font-size: 11px; }
  .totals-table .grand td { border-top: 1.5px solid #1b1b1f; font-weight: bold; font-size: 13px; padding-top: 8px; }
  .declaration { margin-top: 90px; font-size: 9.5px; color: #66666f; line-height: 1.5; border-top: 1px solid #e3e1da; padding-top: 10px; }
  .notes { margin-top: 14px; font-size: 10px; color: #44424c; }
  .footer { margin-top: 22px; font-size: 9.5px; color: #66666f; border-top: 1px solid #e3e1da; padding-top: 10px; }
  .clear { clear: both; }
</style>
</head>
<body>

  <table class="head-table">
    <tr>
      <td style="width:60%">
        <div class="brand"><?= htmlspecialchars($entity['name']) ?></div>
        <div class="small muted" style="margin-top:4px;line-height:1.5">
          <?= nl2br(htmlspecialchars($entity['address'] ?? '')) ?><br>
          <?php if (!empty($profile['reg_label']) && !empty($entity['tax_reg_no'])): ?>
            <?= htmlspecialchars($profile['reg_label']) ?>: <?= htmlspecialchars($entity['tax_reg_no']) ?>
          <?php endif; ?>
        </div>
      </td>
      <td style="width:40%">
        <div class="invoice-title">INVOICE</div>
        <div class="invoice-meta">
          <div><?= htmlspecialchars($invoice['invoice_no']) ?></div>
          <div class="muted">Date: <?= htmlspecialchars(date('d M Y', strtotime($invoice['invoice_date']))) ?></div>
          <?php if (!empty($invoice['due_date'])): ?>
            <div class="muted">Due: <?= htmlspecialchars(date('d M Y', strtotime($invoice['due_date']))) ?></div>
          <?php endif; ?>
          <div style="margin-top:6px">
            <span class="status-pill status-<?= htmlspecialchars($invoice['status']) ?>"><?= htmlspecialchars($statusLabel) ?></span>
          </div>
        </div>
      </td>
    </tr>
  </table>

  <div class="section-gap"></div>

  <table class="parties-table">
    <tr>
      <td style="padding-right:7px">
        <div class="label">Billed by</div>
        <div class="name"><?= htmlspecialchars($entity['name']) ?></div>
        <div class="small muted"><?= htmlspecialchars($entity['country']) ?><?= !empty($entity['state_or_emirate']) ? ' · ' . htmlspecialchars($entity['state_or_emirate']) : '' ?></div>
      </td>
      <td style="padding-left:7px">
        <div class="label">Billed to</div>
        <div class="name"><?= htmlspecialchars($invoice['client_name']) ?></div>
        <?php if (!empty($invoice['client_address'])): ?>
          <div class="small muted"><?= nl2br(htmlspecialchars($invoice['client_address'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($profile['reg_label']) && !empty($invoice['client_tax_reg_no'])): ?>
          <div class="small muted" style="margin-top:3px"><?= htmlspecialchars($profile['reg_label']) ?>: <?= htmlspecialchars($invoice['client_tax_reg_no']) ?></div>
        <?php endif; ?>
        <?php if (!empty($invoice['place_of_supply'])): ?>
          <div class="small muted" style="margin-top:3px">Place of supply: <?= htmlspecialchars($invoice['place_of_supply']) ?></div>
        <?php endif; ?>
      </td>
    </tr>
  </table>

  <table class="items-table">
    <thead>
      <tr>
        <th style="width:34px">#</th>
        <th>Description</th>
        <?php if (!empty($profile['needs_hsn'])): ?><th style="width:62px">HSN/SAC</th><?php endif; ?>
        <th class="num" style="width:48px">Qty</th>
        <th class="num" style="width:78px">Unit price</th>
        <th class="num" style="width:48px">Tax %</th>
        <th class="num" style="width:88px">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $item): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= htmlspecialchars($item['description']) ?></td>
          <?php if (!empty($profile['needs_hsn'])): ?><td><?= htmlspecialchars($item['hsn_sac'] ?? '—') ?></td><?php endif; ?>
          <td class="num"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['quantity'], 2), '0'), '.')) ?></td>
          <td class="num"><?= $money((float)$item['unit_price']) ?></td>
          <td class="num"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$item['tax_rate'], 2), '0'), '.')) ?>%</td>
          <td class="num"><?= $money((float)$item['line_total']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="totals-table">
    <tr><td class="muted">Subtotal</td><td class="num"><?= htmlspecialchars($invoice['currency']) ?> <?= $money((float)$invoice['subtotal']) ?></td></tr>
    <?php foreach ($breakdown as $component => $row): ?>
      <?php if ((float)$row['amount'] == 0.0 && (float)$row['rate'] == 0.0) continue; ?>
      <tr>
        <td class="muted"><?= htmlspecialchars($component) ?> (<?= htmlspecialchars(rtrim(rtrim(number_format((float)$row['rate'], 2), '0'), '.')) ?>%)</td>
        <td class="num"><?= htmlspecialchars($invoice['currency']) ?> <?= $money((float)$row['amount']) ?></td>
      </tr>
    <?php endforeach; ?>
    <tr class="grand"><td>Total</td><td class="num"><?= htmlspecialchars($invoice['currency']) ?> <?= $money((float)$invoice['grand_total']) ?></td></tr>
  </table>
  <div class="clear"></div>

  <?php if (!empty($invoice['notes'])): ?>
    <div class="notes"><strong>Notes:</strong> <?= nl2br(htmlspecialchars($invoice['notes'])) ?></div>
  <?php endif; ?>

  <?php if (!empty($profile['declaration'])): ?>
    <div class="declaration"><?= htmlspecialchars($profile['declaration']) ?></div>
  <?php endif; ?>

  <?php if (!empty($entity['bank_details'])): ?>
    <div class="footer"><strong>Payment details:</strong> <?= nl2br(htmlspecialchars($entity['bank_details'])) ?></div>
  <?php endif; ?>

</body>
</html>
