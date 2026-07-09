<?php
// Every figure on this page is issued invoices only — drafts and void
// invoices are excluded throughout, since neither represents real revenue.
$currencies = array_unique(array_merge(
    array_column($invoicedThisMonth, 'currency'),
    array_keys($outstandingByCcy),
    array_column($topClients, 'currency')
));
sort($currencies);

$invoicedByCcy = array_column($invoicedThisMonth, 'total', 'currency');
$invoicedCntByCcy = array_column($invoicedThisMonth, 'cnt', 'currency');
$collectedByCcy = array_column($collectedThisMonth, 'total', 'currency');

$agingLabels = ['current' => 'Current', 'd1_30' => '1–30 days', 'd31_60' => '31–60 days', 'd61_90' => '61–90 days', 'd90_plus' => '90+ days'];
?>
<div class="page-head">
  <div>
    <span class="eyebrow-gold">Admin</span>
    <h1>Reports</h1>
    <p class="sub">Revenue, outstanding balances, and matter throughput — issued invoices only.</p>
  </div>
</div>

<?php if (!$currencies): ?>
  <div class="card card-pad">
    <div class="empty-state"><?= icon('reports') ?><p>No issued invoices yet — figures will appear here once you issue your first one from Billing.</p></div>
  </div>
<?php else: ?>

<!-- Revenue summary -->
<div class="card card-pad" style="margin-bottom:20px">
  <div class="card-head"><h3>Revenue summary</h3></div>
  <p class="case-client" style="margin-top:-10px;margin-bottom:14px">Kept separate per currency — adding INR, AED, and USD together would produce a meaningless number.</p>
  <table class="table">
    <thead><tr><th>Currency</th><th>Invoiced this month</th><th>vs last month</th><th>Payments recorded this month</th><th>Outstanding</th><th>Overdue</th></tr></thead>
    <tbody>
      <?php foreach ($currencies as $ccy):
        $now = (float)($invoicedByCcy[$ccy] ?? 0);
        $prev = (float)($invoicedLastMonthByCcy[$ccy] ?? 0);
        if ($prev == 0 && $now == 0) { $delta = '—'; $deltaClass = 'kpi-flat'; }
        elseif ($prev == 0) { $delta = 'new this month'; $deltaClass = 'kpi-up'; }
        else {
          $pct = round((($now - $prev) / $prev) * 100);
          $delta = ($pct >= 0 ? '+' : '') . $pct . '% vs last month';
          $deltaClass = $pct > 0 ? 'kpi-up' : ($pct < 0 ? 'kpi-down' : 'kpi-flat');
        }
        $overdue = (float)($overdueByCcy[$ccy] ?? 0);
      ?>
        <tr>
          <td class="mono" style="font-weight:700"><?= htmlspecialchars($ccy) ?></td>
          <td class="mono"><?= htmlspecialchars(format_money($now, $ccy)) ?> <span class="case-client">(<?= (int)($invoicedCntByCcy[$ccy] ?? 0) ?> invoice<?= ($invoicedCntByCcy[$ccy] ?? 0) === 1 ? '' : 's' ?>)</span></td>
          <td><span class="kpi-delta <?= $deltaClass ?>"><?= htmlspecialchars($delta) ?></span></td>
          <td class="mono"><?= htmlspecialchars(format_money((float)($collectedByCcy[$ccy] ?? 0), $ccy)) ?></td>
          <td class="mono"><?= htmlspecialchars(format_money((float)($outstandingByCcy[$ccy] ?? 0), $ccy)) ?></td>
          <td class="mono" style="<?= $overdue > 0 ? 'color:var(--danger);font-weight:700' : '' ?>"><?= htmlspecialchars(format_money($overdue, $ccy)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- AR Aging -->
<div class="card card-pad" style="margin-bottom:20px">
  <div class="card-head"><h3>Accounts receivable aging</h3></div>
  <p class="case-client" style="margin-top:-10px;margin-bottom:14px">Outstanding balance on issued invoices, bucketed by days past due date.</p>
  <?php if (!$agingByCcy): ?>
    <div class="empty-state"><p>Nothing outstanding right now.</p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Currency</th><?php foreach ($agingLabels as $lbl): ?><th><?= $lbl ?></th><?php endforeach; ?><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($agingByCcy as $ccy => $buckets): ?>
        <tr>
          <td class="mono" style="font-weight:700"><?= htmlspecialchars($ccy) ?></td>
          <?php $rowTotal = 0; foreach (array_keys($agingLabels) as $key): $amt = (float)($buckets[$key] ?? 0); $rowTotal += $amt; ?>
            <td class="mono" style="<?= $key !== 'current' && $amt > 0 ? 'color:var(--danger)' : '' ?>"><?= $amt > 0 ? htmlspecialchars(format_money($amt, $ccy)) : '—' ?></td>
          <?php endforeach; ?>
          <td class="mono" style="font-weight:700"><?= htmlspecialchars(format_money($rowTotal, $ccy)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="grid-2">
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Revenue by practice area -->
    <div class="card card-pad">
      <div class="card-head"><h3>Revenue by practice area</h3></div>
      <?php foreach ($currencies as $ccy):
        $rows = array_values(array_filter($byPracticeArea, fn($r) => $r['currency'] === $ccy));
        if (!$rows) continue;
        $max = max(array_column($rows, 'total'));
      ?>
        <div style="margin-bottom:16px">
          <div class="small muted" style="font-weight:700;margin-bottom:8px"><?= htmlspecialchars($ccy) ?></div>
          <?php foreach ($rows as $r): $pct = $max > 0 ? round(((float)$r['total'] / $max) * 100) : 0; ?>
            <div style="margin-bottom:8px">
              <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px">
                <span><?= htmlspecialchars($r['practice_area']) ?></span>
                <span class="mono"><?= htmlspecialchars(format_money((float)$r['total'], $ccy)) ?></span>
              </div>
              <div style="background:var(--border-card);border-radius:4px;height:6px;overflow:hidden">
                <div style="background:var(--accent-600);height:100%;width:<?= $pct ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Top clients -->
    <div class="card card-pad">
      <div class="card-head"><h3>Top clients by revenue</h3></div>
      <?php foreach ($currencies as $ccy):
        $rows = array_values(array_filter($topClients, fn($r) => $r['currency'] === $ccy));
        if (!$rows) continue;
        $max = max(array_column($rows, 'total'));
      ?>
        <div style="margin-bottom:16px">
          <div class="small muted" style="font-weight:700;margin-bottom:8px"><?= htmlspecialchars($ccy) ?></div>
          <?php foreach ($rows as $r): $pct = $max > 0 ? round(((float)$r['total'] / $max) * 100) : 0; ?>
            <div style="margin-bottom:8px">
              <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:3px">
                <span><?= htmlspecialchars($r['client_name']) ?> <span class="case-client">(<?= (int)$r['cnt'] ?>)</span></span>
                <span class="mono"><?= htmlspecialchars(format_money((float)$r['total'], $ccy)) ?></span>
              </div>
              <div style="background:var(--border-card);border-radius:4px;height:6px;overflow:hidden">
                <div style="background:var(--brass-500);height:100%;width:<?= $pct ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<!-- Matter throughput -->
<div class="card card-pad" style="margin-top:20px">
  <div class="card-head"><h3>Matter throughput</h3></div>
  <p class="case-client" style="margin-top:-10px;margin-bottom:14px">Matters opened vs. closed, last 6 months.</p>
  <?php $maxT = max(1, max(array_map(fn($m) => max($m['opened'], $m['closed']), $months))); ?>
  <div style="display:flex;gap:18px;align-items:flex-end;height:140px">
    <?php foreach ($months as $m): ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;height:100%;justify-content:flex-end">
        <div style="display:flex;gap:3px;align-items:flex-end;height:100px">
          <div title="<?= $m['opened'] ?> opened" style="width:16px;border-radius:3px 3px 0 0;background:var(--accent-600);height:<?= round(($m['opened']/$maxT)*100) ?>%;min-height:<?= $m['opened']>0?'3px':'0' ?>"></div>
          <div title="<?= $m['closed'] ?> closed" style="width:16px;border-radius:3px 3px 0 0;background:var(--success);height:<?= round(($m['closed']/$maxT)*100) ?>%;min-height:<?= $m['closed']>0?'3px':'0' ?>"></div>
        </div>
        <div class="small muted"><?= $m['label'] ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:18px;margin-top:12px;font-size:12.5px">
    <span style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:2px;background:var(--accent-600);display:inline-block"></span> Opened</span>
    <span style="display:flex;align-items:center;gap:6px"><span style="width:10px;height:10px;border-radius:2px;background:var(--success);display:inline-block"></span> Closed</span>
  </div>
</div>

<?php endif; ?>
