<?php
require_once dirname(__DIR__, 3) . '/libs/litigation_types.php';
$courtTypes = court_types();
$caseStages = case_stages();
$hasIntel   = (bool)array_filter([
    $case['court_type'] ?? null, $case['court_name'] ?? null, $case['judge_name'] ?? null,
    $case['case_stage'] ?? null, $case['acts_involved'] ?? null, $case['sections_involved'] ?? null,
    $case['fir_number'] ?? null, $case['result'] ?? null,
]);
?>
<div class="page-head">
  <div>
    <span class="eyebrow-gold"><?= htmlspecialchars($case['practice_area'] ?: 'Matter') ?></span>
    <h1><?= htmlspecialchars($case['title']) ?></h1>
    <p class="sub">
      <span class="badge badge-<?= $case['status'] ?>"><?= $case['status'] ?></span>&nbsp;
      <span class="badge badge-<?= $case['priority'] ?>"><?= $case['priority'] ?></span>&nbsp;
      <span class="case-number"><?= htmlspecialchars($case['case_number']) ?></span>
      <?php if ($case['case_stage']): ?>&nbsp;<span class="badge" style="background:var(--accent-100);color:var(--accent-600)"><?= htmlspecialchars($case['case_stage']) ?></span><?php endif; ?>
    </p>
  </div>
  <div style="display:flex;gap:10px">
    <a class="btn btn-ghost" href="<?= url('cases') ?>">← All cases</a>
    <button class="btn btn-primary" type="button" id="edit-toggle"><?= icon('edit') ?> Edit</button>
  </div>
</div>

<!-- Inline edit panel -->
<div class="card inline-panel" id="edit-panel">
  <form method="post" action="<?= url('cases/' . $case['id']) ?>">
    <?= csrf_field() ?>
    <div class="card-head" style="padding:20px 24px 0">
      <h3>Edit matter</h3>
      <span class="modal-close" id="edit-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <div class="input-row">
        <div class="field"><label>Matter number</label><input class="input mono" type="text" name="case_number" value="<?= htmlspecialchars($case['case_number']) ?>" required></div>
        <div class="field"><label>Practice area</label><input class="input" type="text" name="practice_area" value="<?= htmlspecialchars($case['practice_area'] ?? '') ?>"></div>
      </div>
      <div class="field"><label>Title</label><input class="input" type="text" name="title" value="<?= htmlspecialchars($case['title']) ?>" required></div>
      <div class="field"><label>Client name</label><input class="input" type="text" name="client_name" value="<?= htmlspecialchars($case['client_name']) ?>" required></div>
      <div class="input-row">
        <div class="field"><label>Status</label><select class="input" name="status"><?php foreach (['open','pending','closed'] as $s): ?><option value="<?= $s ?>" <?= $case['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Priority</label><select class="input" name="priority"><?php foreach (['low','medium','high'] as $p): ?><option value="<?= $p ?>" <?= $case['priority']===$p?'selected':'' ?>><?= ucfirst($p) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="input-row">
        <div class="field"><label>Opened on</label><input class="input" type="date" name="opened_on" value="<?= $case['opened_on'] ?? '' ?>"></div>
        <div class="field"><label>Due on</label><input class="input" type="date" name="due_on" value="<?= $case['due_on'] ?? '' ?>"></div>
      </div>
      <div class="input-row">
        <div class="field"><label>Next hearing date</label><input class="input" type="date" name="next_hearing_date" value="<?= $case['next_hearing_date'] ?? '' ?>"></div>
        <div class="field"><label>Hearing time</label><input class="input" type="time" name="next_hearing_time" value="<?= $case['next_hearing_time'] ?? '' ?>"></div>
      </div>

      <details class="disclosure" <?= $hasIntel ? 'open' : '' ?>>
        <summary><?= icon('scales') ?> Court &amp; proceedings <span style="font-weight:400;color:var(--text-muted)">— optional, for litigation matters</span></summary>
        <div class="disclosure-body">

          <div class="fieldset-label">Forum</div>
          <div class="input-row">
            <div class="field">
              <label>Court type</label>
              <select class="input" name="court_type">
                <option value="">— Select —</option>
                <?php foreach ($courtTypes as $ct): ?><option <?= ($case['court_type'] ?? '')===$ct?'selected':'' ?>><?= htmlspecialchars($ct) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field"><label>Court name</label><input class="input" type="text" name="court_name" placeholder="e.g. Delhi High Court" value="<?= htmlspecialchars($case['court_name'] ?? '') ?>"></div>
          </div>
          <div class="input-row">
            <div class="field"><label>Bench</label><input class="input" type="text" name="bench" value="<?= htmlspecialchars($case['bench'] ?? '') ?>"></div>
            <div class="field"><label>Court hall</label><input class="input" type="text" name="court_hall" value="<?= htmlspecialchars($case['court_hall'] ?? '') ?>"></div>
          </div>
          <div class="input-row">
            <div class="field"><label>Judge</label><input class="input" type="text" name="judge_name" value="<?= htmlspecialchars($case['judge_name'] ?? '') ?>"></div>
            <div class="field"><label>Jurisdiction</label><input class="input" type="text" name="jurisdiction" placeholder="e.g. Territorial / pecuniary jurisdiction" value="<?= htmlspecialchars($case['jurisdiction'] ?? '') ?>"></div>
          </div>
          <div class="input-row">
            <div class="field"><label>Opposite counsel</label><input class="input" type="text" name="opposite_counsel" value="<?= htmlspecialchars($case['opposite_counsel'] ?? '') ?>"></div>
            <div class="field">
              <label>Case stage</label>
              <select class="input" name="case_stage">
                <option value="">— Select —</option>
                <?php foreach ($caseStages as $cs): ?><option <?= ($case['case_stage'] ?? '')===$cs?'selected':'' ?>><?= htmlspecialchars($cs) ?></option><?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="fieldset-label">Criminal matters</div>
          <div class="input-row">
            <div class="field"><label>Police station</label><input class="input" type="text" name="police_station" value="<?= htmlspecialchars($case['police_station'] ?? '') ?>"></div>
            <div class="field"><label>FIR no.</label><input class="input" type="text" name="fir_number" value="<?= htmlspecialchars($case['fir_number'] ?? '') ?>"></div>
            <div class="field"><label>Crime no.</label><input class="input" type="text" name="crime_number" value="<?= htmlspecialchars($case['crime_number'] ?? '') ?>"></div>
          </div>

          <div class="fieldset-label">Statutory basis &amp; relief</div>
          <div class="input-row">
            <div class="field"><label>Acts involved</label><textarea class="input" name="acts_involved" rows="2" placeholder="e.g. Indian Penal Code, Negotiable Instruments Act"><?= htmlspecialchars($case['acts_involved'] ?? '') ?></textarea></div>
            <div class="field"><label>Sections involved</label><textarea class="input" name="sections_involved" rows="2" placeholder="e.g. Sec 420, Sec 138"><?= htmlspecialchars($case['sections_involved'] ?? '') ?></textarea></div>
          </div>
          <div class="input-row">
            <div class="field"><label>Prayer</label><textarea class="input" name="prayer" rows="2" placeholder="What is being sought from the court"><?= htmlspecialchars($case['prayer'] ?? '') ?></textarea></div>
            <div class="field"><label>Reliefs sought</label><textarea class="input" name="reliefs_sought" rows="2"><?= htmlspecialchars($case['reliefs_sought'] ?? '') ?></textarea></div>
          </div>

          <div class="fieldset-label">Key dates</div>
          <div class="input-row">
            <div class="field"><label>Limitation date</label><input class="input" type="date" name="limitation_date" value="<?= $case['limitation_date'] ?? '' ?>"></div>
            <div class="field"><label>Filing date</label><input class="input" type="date" name="filing_date" value="<?= $case['filing_date'] ?? '' ?>"></div>
          </div>
          <div class="input-row">
            <div class="field"><label>Service date</label><input class="input" type="date" name="service_date" value="<?= $case['service_date'] ?? '' ?>"></div>
            <div class="field"><label>Disposal date</label><input class="input" type="date" name="disposal_date" value="<?= $case['disposal_date'] ?? '' ?>"></div>
          </div>
          <div class="field"><label>Next hearing purpose</label><input class="input" type="text" name="next_hearing_purpose" placeholder="e.g. For arguments" value="<?= htmlspecialchars($case['next_hearing_purpose'] ?? '') ?>"></div>

          <div class="fieldset-label">Outcome</div>
          <div class="field"><label>Result</label><textarea class="input" name="result" rows="2" placeholder="e.g. Allowed, Dismissed, Partly allowed, Settled"><?= htmlspecialchars($case['result'] ?? '') ?></textarea></div>

        </div>
      </details>

      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px"><button type="button" class="btn btn-ghost" id="edit-cancel">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </div>
  </form>
</div>

<div class="grid-2">
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Summary -->
    <div class="card card-pad">
      <div class="card-head"><h3>Summary</h3></div>
      <table class="table">
        <tr><td style="width:150px;color:var(--text-muted)">Client</td><td><?= htmlspecialchars($case['client_name']) ?></td></tr>
        <tr><td style="color:var(--text-muted)">Opened on</td><td><?= fmt_date($case['opened_on']) ?></td></tr>
        <tr><td style="color:var(--text-muted)">Due on</td><td><?= fmt_date($case['due_on']) ?></td></tr>
        <tr><td style="color:var(--text-muted)">Next hearing</td><td>
          <?= fmt_date($case['next_hearing_date']) ?><?= $case['next_hearing_time'] ? ' at ' . date('g:i A', strtotime($case['next_hearing_time'])) : '' ?><?= $case['next_hearing_purpose'] ? ' — ' . htmlspecialchars($case['next_hearing_purpose']) : '' ?>
        </td></tr>
        <?php if ($case['limitation_date']): ?><tr><td style="color:var(--text-muted)">Limitation date</td><td><?= fmt_date($case['limitation_date']) ?></td></tr><?php endif; ?>
      </table>
    </div>

    <!-- Court & proceedings (read-only view; edit via the Edit button above) -->
    <?php if ($hasIntel): ?>
    <div class="card card-pad">
      <div class="card-head"><h3><?= icon('scales') ?> Court &amp; proceedings</h3></div>
      <table class="table kv-table">
        <?php if ($case['court_type'] || $case['court_name']): ?>
          <tr><td>Court</td><td><?= htmlspecialchars(trim(($case['court_type'] ?: '') . ($case['court_name'] ? ' — ' . $case['court_name'] : ''), ' —')) ?></td></tr>
        <?php endif; ?>
        <?php if ($case['bench']): ?><tr><td>Bench</td><td><?= htmlspecialchars($case['bench']) ?></td></tr><?php endif; ?>
        <?php if ($case['court_hall']): ?><tr><td>Court hall</td><td><?= htmlspecialchars($case['court_hall']) ?></td></tr><?php endif; ?>
        <?php if ($case['judge_name']): ?><tr><td>Judge</td><td><?= htmlspecialchars($case['judge_name']) ?></td></tr><?php endif; ?>
        <?php if ($case['jurisdiction']): ?><tr><td>Jurisdiction</td><td><?= htmlspecialchars($case['jurisdiction']) ?></td></tr><?php endif; ?>
        <?php if ($case['opposite_counsel']): ?><tr><td>Opposite counsel</td><td><?= htmlspecialchars($case['opposite_counsel']) ?></td></tr><?php endif; ?>
        <?php if ($case['case_stage']): ?><tr><td>Stage</td><td><?= htmlspecialchars($case['case_stage']) ?></td></tr><?php endif; ?>
        <?php if ($case['police_station'] || $case['fir_number'] || $case['crime_number']): ?>
          <tr><td>FIR</td><td><?= htmlspecialchars(implode(' · ', array_filter([
              $case['police_station'] ? 'PS ' . $case['police_station'] : null,
              $case['fir_number'] ? 'FIR ' . $case['fir_number'] : null,
              $case['crime_number'] ? 'Crime No. ' . $case['crime_number'] : null,
          ]))) ?></td></tr>
        <?php endif; ?>
        <?php if ($case['acts_involved']): ?><tr><td>Acts</td><td><?= nl2br(htmlspecialchars($case['acts_involved'])) ?></td></tr><?php endif; ?>
        <?php if ($case['sections_involved']): ?><tr><td>Sections</td><td><?= nl2br(htmlspecialchars($case['sections_involved'])) ?></td></tr><?php endif; ?>
        <?php if ($case['prayer']): ?><tr><td>Prayer</td><td><?= nl2br(htmlspecialchars($case['prayer'])) ?></td></tr><?php endif; ?>
        <?php if ($case['reliefs_sought']): ?><tr><td>Reliefs sought</td><td><?= nl2br(htmlspecialchars($case['reliefs_sought'])) ?></td></tr><?php endif; ?>
        <?php if ($case['filing_date']): ?><tr><td>Filed on</td><td><?= fmt_date($case['filing_date']) ?></td></tr><?php endif; ?>
        <?php if ($case['service_date']): ?><tr><td>Served on</td><td><?= fmt_date($case['service_date']) ?></td></tr><?php endif; ?>
        <?php if ($case['disposal_date']): ?><tr><td>Disposed on</td><td><?= fmt_date($case['disposal_date']) ?></td></tr><?php endif; ?>
        <?php if ($case['result']): ?><tr><td>Result</td><td><?= nl2br(htmlspecialchars($case['result'])) ?></td></tr><?php endif; ?>
      </table>
    </div>
    <?php endif; ?>

    <!-- Documents -->
    <div class="card card-pad" id="documents">
      <div class="card-head"><h3>Documents</h3></div>
      <form method="post" action="<?= url('cases/' . $case['id']) ?>" enctype="multipart/form-data" style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border-card)">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="upload_doc">
        <div class="input-row">
          <div class="field">
            <label>Document type</label>
            <select class="input" name="doc_type">
              <?php foreach (['Pleading','Written Statement','Order','Judgement','Notice','Agreement','Evidence','Correspondence','Other'] as $dt): ?><option><?= htmlspecialchars($dt) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="field"><label>Notes (optional)</label><input class="input" type="text" name="doc_notes" placeholder="Brief description"></div>
        </div>
        <div class="field">
          <label>File <span style="color:var(--text-muted);font-weight:400">(PDF/JPG/PNG/DOC/DOCX · 5MB)</span></label>
          <input class="input" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><?= icon('plus') ?> Upload</button>
      </form>
      <?php if ($docs): foreach ($docs as $d): ?>
        <div class="task-row">
          <span class="task-check" style="border-color:var(--accent-600);color:var(--accent-600)"><?= icon('documents') ?></span>
          <div>
            <div class="task-title"><?= htmlspecialchars($d['doc_type']) ?></div>
            <div class="task-meta"><?= htmlspecialchars($d['original_name']) ?> · <?= fmt_size((int)$d['file_size']) ?><?= $d['notes'] ? ' · ' . htmlspecialchars($d['notes']) : '' ?> · <?= time_ago($d['uploaded_at']) ?></div>
          </div>
          <div style="margin-left:auto;display:flex;gap:6px">
            <a class="icon-btn btn-sm" style="display:inline-grid" href="<?= url('storage/cases/' . $case['id'] . '/' . urlencode($d['stored_name'])) ?>" target="_blank">⬇</a>
            <form method="post" action="<?= url('cases/' . $case['id']) ?>" style="display:inline" onsubmit="return confirm('Delete document?')">
              <?= csrf_field() ?><input type="hidden" name="_action" value="delete_doc"><input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
              <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)"><?= icon('trash') ?></button>
            </form>
          </div>
        </div>
      <?php endforeach; else: ?>
        <div class="empty-state"><?= icon('documents') ?><p>No documents uploaded yet.</p></div>
      <?php endif; ?>
    </div>

  </div>

  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Tasks -->
    <div class="card card-pad">
      <div class="card-head"><h3>Linked tasks</h3><a class="link" href="<?= url('tasks') ?>">All tasks →</a></div>
      <?php if ($tasks): foreach ($tasks as $t): ?>
        <div class="task-row <?= $t['status'] === 'done' ? 'done' : '' ?>">
          <span class="task-check"><?= $t['status'] === 'done' ? icon('check') : '' ?></span>
          <div>
            <div class="task-title"><?= htmlspecialchars($t['title']) ?></div>
            <div class="task-meta"><?= htmlspecialchars($t['assignee_name'] ?? 'Unassigned') ?> · <?= fmt_date($t['due_on'], 'd M') ?></div>
          </div>
          <span class="badge badge-<?= $t['priority'] ?>" style="margin-left:auto"><?= $t['priority'] ?></span>
        </div>
      <?php endforeach; else: ?>
        <div class="empty-state"><p>No tasks linked to this matter.</p></div>
      <?php endif; ?>
    </div>

    <!-- Related matters: connected matters + appeal chain -->
    <div class="card card-pad">
      <div class="card-head"><h3>Related matters</h3></div>

      <?php if ($appeals): ?>
        <div class="fieldset-label" style="margin-top:0">Appeal chain</div>
        <?php foreach ($appeals as $l): $rc = $l['case']; ?>
          <div class="link-row">
            <span class="link-arrow"><?= $l['direction'] === 'appealed_from' ? 'Appeal of' : 'Appealed to' ?></span>
            <div class="link-info">
              <div class="link-title"><a class="link" href="<?= url('cases/' . $rc['id']) ?>"><?= htmlspecialchars($rc['case_number']) ?></a></div>
              <div class="link-sub"><?= htmlspecialchars($rc['title']) ?></div>
            </div>
            <span class="badge badge-<?= $rc['status'] ?>"><?= $rc['status'] ?></span>
            <form method="post" action="<?= url('cases/' . $case['id']) ?>" onsubmit="return confirm('Remove this link?')">
              <?= csrf_field() ?><input type="hidden" name="_action" value="remove_link"><input type="hidden" name="link_id" value="<?= (int)$l['link_id'] ?>">
              <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)"><?= icon('trash') ?></button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($connected): ?>
        <div class="fieldset-label">Connected matters</div>
        <?php foreach ($connected as $l): $rc = $l['case']; ?>
          <div class="link-row">
            <div class="link-info">
              <div class="link-title"><a class="link" href="<?= url('cases/' . $rc['id']) ?>"><?= htmlspecialchars($rc['case_number']) ?></a></div>
              <div class="link-sub"><?= htmlspecialchars($rc['title']) ?></div>
            </div>
            <span class="badge badge-<?= $rc['status'] ?>"><?= $rc['status'] ?></span>
            <form method="post" action="<?= url('cases/' . $case['id']) ?>" onsubmit="return confirm('Remove this link?')">
              <?= csrf_field() ?><input type="hidden" name="_action" value="remove_link"><input type="hidden" name="link_id" value="<?= (int)$l['link_id'] ?>">
              <button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)"><?= icon('trash') ?></button>
            </form>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!$appeals && !$connected): ?>
        <div class="empty-state" style="padding:16px 0"><p>No linked matters yet.</p></div>
      <?php endif; ?>

      <form method="post" action="<?= url('cases/' . $case['id']) ?>" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--border-card)">
        <?= csrf_field() ?>
        <input type="hidden" name="_action" value="add_link">
        <div class="input-row">
          <div class="field" style="margin-bottom:8px"><input class="input mono" type="text" name="linked_case_number" placeholder="Matter number, e.g. LO-2026-014"></div>
          <div class="field" style="margin-bottom:8px;flex:0 0 190px">
            <select class="input" name="link_type">
              <?php foreach (case_link_types() as $val => $label): ?><option value="<?= $val ?>"><?= htmlspecialchars($label) ?></option><?php endforeach; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-ghost btn-sm"><?= icon('plus') ?> Link matter</button>
      </form>
    </div>

    <!-- Activity -->
    <div class="card card-pad">
      <div class="card-head"><h3>Activity</h3></div>
      <div class="activity-feed">
        <?php if ($activity): foreach ($activity as $a): ?>
          <div class="activity-item">
            <span class="activity-dot"></span>
            <div><p><?= htmlspecialchars($a['description']) ?></p><time><?= time_ago($a['created_at']) ?></time></div>
          </div>
        <?php endforeach; else: ?>
          <div class="empty-state"><p>No activity recorded.</p></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Danger zone -->
    <div class="card card-pad" style="border-color:rgba(193,59,59,.25)">
      <div class="card-head"><h3 style="color:var(--danger)">Remove matter</h3></div>
      <p class="case-client" style="margin-bottom:14px">Permanently deletes this matter, all linked documents, and all linked tasks.</p>
      <form method="post" action="<?= url('cases/' . $case['id'] . '/delete') ?>" onsubmit="return confirm('Permanently delete this matter?')">
        <?= csrf_field() ?>
        <button class="btn btn-ghost" style="border-color:var(--danger);color:var(--danger)">Delete matter</button>
      </form>
    </div>

  </div>
</div>

<script>
(function(){
  var panel = document.getElementById('edit-panel');
  document.getElementById('edit-toggle').addEventListener('click',function(){ panel.classList.toggle('open'); if(panel.classList.contains('open')) panel.scrollIntoView({behavior:'smooth',block:'start'}); });
  document.getElementById('edit-cancel').addEventListener('click',function(){ panel.classList.remove('open'); });
  document.getElementById('edit-close').addEventListener('click',function(){ panel.classList.remove('open'); });
})();
</script>
