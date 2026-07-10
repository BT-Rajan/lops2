<?php
require_once dirname(__DIR__, 3) . '/libs/litigation_types.php';
$statuses   = ['open', 'pending', 'closed'];
$priorities = ['low', 'medium', 'high'];
$areas      = ['Commercial', 'Civil Litigation', 'Corporate', 'Real Estate', 'Intellectual Property', 'Arbitration', 'Estate & Succession', 'Family', 'Criminal', 'Taxation', 'Other'];
$courtTypes = court_types();
$caseStages = case_stages();

/** Renders the shared "Court & proceedings" disclosure for both the new-matter and edit-matter forms. */
function render_intel_fieldset(string $prefix, array $courtTypes, array $caseStages, array $case = []): void
{
    $v = fn(string $f) => htmlspecialchars($case[$f] ?? '');
    ?>
    <details class="disclosure">
      <summary><?= icon('scales') ?> Court &amp; proceedings <span style="font-weight:400;color:var(--text-muted)">— optional, for litigation matters</span></summary>
      <div class="disclosure-body">
        <div class="fieldset-label" style="margin-top:0">Forum</div>
        <div class="input-row">
          <div class="field">
            <label>Court type</label>
            <select class="input" name="court_type" id="<?= $prefix ?>-court_type">
              <option value="">— Select —</option>
              <?php foreach ($courtTypes as $ct): ?><option <?= ($case['court_type'] ?? '')===$ct?'selected':'' ?>><?= htmlspecialchars($ct) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Court name</label><input class="input" type="text" name="court_name" id="<?= $prefix ?>-court_name" placeholder="e.g. Delhi High Court" value="<?= $v('court_name') ?>"></div>
        </div>
        <div class="input-row">
          <div class="field"><label>Bench</label><input class="input" type="text" name="bench" id="<?= $prefix ?>-bench" value="<?= $v('bench') ?>"></div>
          <div class="field"><label>Court hall</label><input class="input" type="text" name="court_hall" id="<?= $prefix ?>-court_hall" value="<?= $v('court_hall') ?>"></div>
        </div>
        <div class="input-row">
          <div class="field"><label>Judge</label><input class="input" type="text" name="judge_name" id="<?= $prefix ?>-judge_name" value="<?= $v('judge_name') ?>"></div>
          <div class="field"><label>Jurisdiction</label><input class="input" type="text" name="jurisdiction" id="<?= $prefix ?>-jurisdiction" value="<?= $v('jurisdiction') ?>"></div>
        </div>
        <div class="input-row">
          <div class="field"><label>Opposite counsel</label><input class="input" type="text" name="opposite_counsel" id="<?= $prefix ?>-opposite_counsel" value="<?= $v('opposite_counsel') ?>"></div>
          <div class="field">
            <label>Case stage</label>
            <select class="input" name="case_stage" id="<?= $prefix ?>-case_stage">
              <option value="">— Select —</option>
              <?php foreach ($caseStages as $cs): ?><option <?= ($case['case_stage'] ?? '')===$cs?'selected':'' ?>><?= htmlspecialchars($cs) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="fieldset-label">Criminal matters</div>
        <div class="input-row">
          <div class="field"><label>Police station</label><input class="input" type="text" name="police_station" id="<?= $prefix ?>-police_station" value="<?= $v('police_station') ?>"></div>
          <div class="field"><label>FIR no.</label><input class="input" type="text" name="fir_number" id="<?= $prefix ?>-fir_number" value="<?= $v('fir_number') ?>"></div>
          <div class="field"><label>Crime no.</label><input class="input" type="text" name="crime_number" id="<?= $prefix ?>-crime_number" value="<?= $v('crime_number') ?>"></div>
        </div>

        <div class="fieldset-label">Statutory basis &amp; relief</div>
        <div class="input-row">
          <div class="field"><label>Acts involved</label><textarea class="input" name="acts_involved" id="<?= $prefix ?>-acts_involved" rows="2" placeholder="e.g. Indian Penal Code, Negotiable Instruments Act"><?= $v('acts_involved') ?></textarea></div>
          <div class="field"><label>Sections involved</label><textarea class="input" name="sections_involved" id="<?= $prefix ?>-sections_involved" rows="2" placeholder="e.g. Sec 420, Sec 138"><?= $v('sections_involved') ?></textarea></div>
        </div>
        <div class="input-row">
          <div class="field"><label>Prayer</label><textarea class="input" name="prayer" id="<?= $prefix ?>-prayer" rows="2"><?= $v('prayer') ?></textarea></div>
          <div class="field"><label>Reliefs sought</label><textarea class="input" name="reliefs_sought" id="<?= $prefix ?>-reliefs_sought" rows="2"><?= $v('reliefs_sought') ?></textarea></div>
        </div>

        <div class="fieldset-label">Key dates</div>
        <div class="input-row">
          <div class="field"><label>Limitation date</label><input class="input" type="date" name="limitation_date" id="<?= $prefix ?>-limitation_date" value="<?= $v('limitation_date') ?>"></div>
          <div class="field"><label>Filing date</label><input class="input" type="date" name="filing_date" id="<?= $prefix ?>-filing_date" value="<?= $v('filing_date') ?>"></div>
        </div>
        <div class="input-row">
          <div class="field"><label>Service date</label><input class="input" type="date" name="service_date" id="<?= $prefix ?>-service_date" value="<?= $v('service_date') ?>"></div>
          <div class="field"><label>Disposal date</label><input class="input" type="date" name="disposal_date" id="<?= $prefix ?>-disposal_date" value="<?= $v('disposal_date') ?>"></div>
        </div>
        <div class="field"><label>Next hearing purpose</label><input class="input" type="text" name="next_hearing_purpose" id="<?= $prefix ?>-next_hearing_purpose" placeholder="e.g. For arguments" value="<?= $v('next_hearing_purpose') ?>"></div>

        <div class="fieldset-label">Outcome</div>
        <div class="field"><label>Result</label><textarea class="input" name="result" id="<?= $prefix ?>-result" rows="2" placeholder="e.g. Allowed, Dismissed, Partly allowed, Settled"><?= $v('result') ?></textarea></div>
      </div>
    </details>
    <?php
}
?>
<div class="page-head">
  <div>
    <span class="eyebrow-gold">Practice ledger</span>
    <h1>Cases</h1>
    <p class="sub"><?= count($cases) ?> matter<?= count($cases) !== 1 ? 's' : '' ?> on file.</p>
  </div>
  <button class="btn btn-primary" type="button" id="case-toggle-btn"><?= icon('plus') ?> New matter</button>
</div>

<!-- New-matter modal -->
<div class="modal-overlay" id="case-panel-overlay">
<div class="card inline-panel" id="case-panel">
  <form method="post" action="<?= url('cases') ?>">
    <div class="card-head" style="padding:20px 24px 0">
      <h3 id="case-panel-title">New matter</h3>
      <span class="modal-close" id="case-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <div class="input-row">
        <div class="field">
          <label>Matter number</label>
          <input class="input mono" type="text" name="case_number" id="f-case_number" placeholder="LO-2026-XXX" required>
        </div>
        <div class="field">
          <label>Practice area</label>
          <select class="input" name="practice_area" id="f-practice_area">
            <option value="">— Select —</option>
            <?php foreach ($areas as $a): ?><option><?= htmlspecialchars($a) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field">
        <label>Matter title</label>
        <input class="input" type="text" name="title" id="f-title" placeholder="Short descriptive title" required>
      </div>
      <div class="input-row">
        <div class="field">
          <label>Linked client (optional)</label>
          <select class="input" name="client_id" id="f-client_id">
            <option value="">— Not linked (type a name below) —</option>
            <?php foreach ($clients as $cl): ?>
              <option value="<?= (int)$cl['id'] ?>" data-name="<?= htmlspecialchars($cl['display_name']) ?>"><?= htmlspecialchars($cl['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Client name</label>
          <input class="input" type="text" name="client_name" id="f-client_name" placeholder="Client or company name" required>
        </div>
      </div>
      <?php if (!$clients): ?>
        <div class="small muted" style="margin-top:-8px;margin-bottom:14px">No client records yet — <a href="<?= url('clients') ?>">add one</a> to link matters to KYC'd clients instead of a free-text name.</div>
      <?php endif; ?>
      <div class="input-row">
        <div class="field">
          <label>Status</label>
          <select class="input" name="status" id="f-status">
            <?php foreach ($statuses as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Priority</label>
          <select class="input" name="priority" id="f-priority">
            <?php foreach ($priorities as $p): ?><option value="<?= $p ?>"><?= ucfirst($p) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="input-row">
        <div class="field"><label>Opened on</label><input class="input" type="date" name="opened_on" id="f-opened_on"></div>
        <div class="field"><label>Due on</label><input class="input" type="date" name="due_on" id="f-due_on"></div>
      </div>
      <div class="input-row">
        <div class="field">
          <label>Next hearing date <span style="color:var(--text-muted);font-weight:400">(drives auto reminder)</span></label>
          <input class="input" type="date" name="next_hearing_date" id="f-next_hearing_date">
        </div>
        <div class="field">
          <label>Hearing time</label>
          <input class="input" type="time" name="next_hearing_time" id="f-next_hearing_time">
        </div>
      </div>

      <?php render_intel_fieldset('f', $courtTypes, $caseStages); ?>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" class="btn btn-ghost" id="case-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Save matter</button>
      </div>
    </div>
  </form>
</div>
</div>

<?php
$caseDrillParts = [];
if ($practiceAreaFilter !== '') $caseDrillParts[] = $practiceAreaFilter;
if ($openedMonthFilter !== '') $caseDrillParts[] = 'opened ' . date('F Y', strtotime($openedMonthFilter . '-01'));
if ($closedMonthFilter !== '') $caseDrillParts[] = 'closed ' . date('F Y', strtotime($closedMonthFilter . '-01'));
?>
<?php if ($caseDrillParts): ?>
  <div class="alert alert-info" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <span>Showing: <strong><?= htmlspecialchars(implode(' · ', $caseDrillParts)) ?></strong> — from Reports.</span>
    <a class="link" href="<?= url('cases') ?>">Clear filter ×</a>
  </div>
<?php endif; ?>

<form method="get" action="<?= url('cases') ?>" class="toolbar">
  <input class="input" type="text" name="q" placeholder="Search title, client, matter no, judge, court…" value="<?= htmlspecialchars($search) ?>">
  <a class="filter-chip <?= $statusFilter === 'all' ? 'active' : '' ?>" href="<?= url('cases') ?>">All</a>
  <a class="filter-chip <?= $statusFilter === 'open' ? 'active' : '' ?>" href="<?= url('cases?status=open') ?>">Open</a>
  <a class="filter-chip <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="<?= url('cases?status=pending') ?>">Pending</a>
  <a class="filter-chip <?= $statusFilter === 'closed' ? 'active' : '' ?>" href="<?= url('cases?status=closed') ?>">Closed</a>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad">
  <?php if ($cases): ?>
  <table class="table">
    <thead>
      <tr><th>Matter</th><th>Client</th><th>Area</th><th>Status</th><th>Priority</th><th>Due</th><th>Next hearing</th><th>Docs</th><th></th></tr>
    </thead>
    <tbody>
      <?php foreach ($cases as $c): ?>
      <tr>
        <td onclick="window.location='<?= url('cases/' . $c['id']) ?>'" style="cursor:pointer">
          <div class="case-title"><?= htmlspecialchars($c['title']) ?></div>
          <div class="case-number"><?= htmlspecialchars($c['case_number']) ?></div>
          <?php if ($c['court_name'] || $c['case_stage']): ?>
            <div class="case-number" style="color:var(--text-muted)"><?= htmlspecialchars(implode(' · ', array_filter([$c['court_name'], $c['case_stage']]))) ?></div>
          <?php endif; ?>
        </td>
        <td class="case-client">
          <?php if ($c['client_id']): ?>
            <a href="<?= url('clients/' . (int)$c['client_id']) ?>" style="color:inherit;text-decoration:underline;text-decoration-color:var(--border-card)"><?= htmlspecialchars($c['client_name']) ?></a>
          <?php else: ?>
            <span onclick="window.location='<?= url('cases/' . $c['id']) ?>'" style="cursor:pointer"><?= htmlspecialchars($c['client_name']) ?></span>
          <?php endif; ?>
        </td>
        <td class="case-client"><?= htmlspecialchars($c['practice_area'] ?: '—') ?></td>
        <td><span class="badge badge-<?= $c['status'] ?>"><?= $c['status'] ?></span></td>
        <td><span class="badge badge-<?= $c['priority'] ?>"><?= $c['priority'] ?></span></td>
        <td class="case-client"><?= fmt_date($c['due_on']) ?></td>
        <td class="case-client"><?= fmt_date($c['next_hearing_date']) ?></td>
        <td class="case-client">
          <a href="<?= url('cases/' . $c['id'] . '#documents') ?>" style="display:inline-flex;align-items:center;gap:4px;color:inherit">
            <?= icon('documents') ?> <?= (int)($c['doc_count'] ?? 0) ?>
          </a>
        </td>
        <td style="text-align:right;white-space:nowrap">
          <button class="icon-btn btn-sm case-edit-btn" type="button" style="display:inline-grid"
            data-case='<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>'><?= icon('edit') ?></button>
          <form method="post" action="<?= url('cases/' . $c['id'] . '/delete') ?>" style="display:inline"
                onsubmit="return confirm('Remove this matter and all its documents?')">
            <?= csrf_field() ?>
            <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)"><?= icon('trash') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state"><?= icon('briefcase') ?><p>No matters match that search.</p></div>
  <?php endif; ?>
</div>

<!-- Edit modal (reuses the same form, posts to /cases/{id}) -->
<div class="modal-overlay" id="case-edit-overlay">
<div class="card inline-panel" id="case-edit-panel">
  <form method="post" id="case-edit-form">
    <?= csrf_field() ?>
    <div class="card-head" style="padding:20px 24px 0">
      <h3>Edit matter</h3>
      <span class="modal-close" id="case-edit-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <div class="input-row">
        <div class="field"><label>Matter number</label><input class="input mono" type="text" name="case_number" id="ef-case_number" required></div>
        <div class="field">
          <label>Practice area</label>
          <select class="input" name="practice_area" id="ef-practice_area">
            <option value="">— Select —</option>
            <?php foreach ($areas as $a): ?><option><?= htmlspecialchars($a) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field"><label>Matter title</label><input class="input" type="text" name="title" id="ef-title" required></div>
      <div class="input-row">
        <div class="field">
          <label>Linked client (optional)</label>
          <select class="input" name="client_id" id="ef-client_id">
            <option value="">— Not linked (type a name below) —</option>
            <?php foreach ($clients as $cl): ?>
              <option value="<?= (int)$cl['id'] ?>" data-name="<?= htmlspecialchars($cl['display_name']) ?>"><?= htmlspecialchars($cl['display_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field"><label>Client name</label><input class="input" type="text" name="client_name" id="ef-client_name" required></div>
      </div>
      <div class="input-row">
        <div class="field">
          <label>Status</label>
          <select class="input" name="status" id="ef-status">
            <?php foreach ($statuses as $s): ?><option value="<?= $s ?>"><?= ucfirst($s) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Priority</label>
          <select class="input" name="priority" id="ef-priority">
            <?php foreach ($priorities as $p): ?><option value="<?= $p ?>"><?= ucfirst($p) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="input-row">
        <div class="field"><label>Opened on</label><input class="input" type="date" name="opened_on" id="ef-opened_on"></div>
        <div class="field"><label>Due on</label><input class="input" type="date" name="due_on" id="ef-due_on"></div>
      </div>
      <div class="input-row">
        <div class="field"><label>Next hearing date</label><input class="input" type="date" name="next_hearing_date" id="ef-next_hearing_date"></div>
        <div class="field"><label>Hearing time</label><input class="input" type="time" name="next_hearing_time" id="ef-next_hearing_time"></div>
      </div>

      <?php render_intel_fieldset('ef', $courtTypes, $caseStages); ?>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px">
        <button type="button" class="btn btn-ghost" id="case-edit-cancel">Cancel</button>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </form>
</div>
</div>

<script>
(function(){
  var overlay     = document.getElementById('case-panel-overlay');
  var editOverlay = document.getElementById('case-edit-overlay');
  var editPanel = document.getElementById('case-edit-panel');
  var editForm  = document.getElementById('case-edit-form');

  function openNew(){  overlay.classList.add('open');     document.getElementById('f-case_number').focus(); }
  function closeNew(){ overlay.classList.remove('open'); }
  function openEdit(){ editOverlay.classList.add('open'); }
  function closeEdit(){ editOverlay.classList.remove('open'); }

  document.getElementById('case-toggle-btn').addEventListener('click',function(){
    if(overlay.classList.contains('open')){ closeNew(); } else { closeNew(); closeEdit(); openNew(); }
  });
  document.getElementById('case-cancel-btn').addEventListener('click', closeNew);
  document.getElementById('case-panel-close').addEventListener('click', closeNew);
  document.getElementById('case-edit-close').addEventListener('click', closeEdit);
  document.getElementById('case-edit-cancel').addEventListener('click', closeEdit);

  // Click the dimmed backdrop (not the panel itself) or press Escape to close.
  [overlay, editOverlay].forEach(function (ov) {
    ov.addEventListener('click', function (e) { if (e.target === ov) { closeNew(); closeEdit(); } });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeNew(); closeEdit(); }
  });

  // Selecting a client auto-fills + locks the free-text name so it can never
  // drift from the actual client record; "Not linked" hands control back.
  function wireClientPicker(selectId, nameId) {
    var sel = document.getElementById(selectId), nameInput = document.getElementById(nameId);
    if (!sel || !nameInput) return;
    sel.addEventListener('change', function () {
      var opt = sel.options[sel.selectedIndex];
      if (opt.value) { nameInput.value = opt.dataset.name; nameInput.readOnly = true; }
      else { nameInput.readOnly = false; }
    });
  }
  wireClientPicker('f-client_id', 'f-client_name');
  wireClientPicker('ef-client_id', 'ef-client_name');

  document.querySelectorAll('.case-edit-btn').forEach(function(btn){
    btn.addEventListener('click',function(){
      var c = JSON.parse(btn.getAttribute('data-case'));
      editForm.action = '<?= url('cases/') ?>' + c.id;
      [
        'case_number','title','client_id','client_name','practice_area','status','priority','opened_on','due_on','next_hearing_date','next_hearing_time',
        'court_type','court_name','bench','court_hall','judge_name','jurisdiction','opposite_counsel','case_stage',
        'police_station','fir_number','crime_number','acts_involved','sections_involved','prayer','reliefs_sought',
        'limitation_date','filing_date','service_date','disposal_date','next_hearing_purpose','result',
      ].forEach(function(f){
        var el = document.getElementById('ef-'+f);
        if(el) el.value = c[f] || '';
      });
      document.getElementById('ef-client_name').readOnly = !!c.client_id;
      var hasIntel = ['court_type','court_name','judge_name','case_stage','acts_involved','fir_number','result'].some(function(f){ return c[f]; });
      var details = editPanel.querySelector('.disclosure');
      if (details) details.open = hasIntel;
      closeNew();
      openEdit();
    });
  });
})();
</script>
