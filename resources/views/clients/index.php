<div class="page-head">
  <div><span class="eyebrow-gold">Client register</span><h1>Clients</h1><p class="sub"><?= count($clients) ?> client<?= count($clients) !== 1 ? 's' : '' ?> on file.</p></div>
  <button class="btn btn-primary" type="button" id="client-toggle-btn"><?= icon('plus') ?> New client</button>
</div>

<!-- Inline onboarding panel -->
<div class="card inline-panel" id="client-panel">
  <form method="post" action="<?= url('clients') ?>">
    <div class="card-head" style="padding:20px 24px 0">
      <h3>Onboard a new client</h3>
      <span class="modal-close" id="client-panel-close"><?= icon('close') ?></span>
    </div>
    <div class="card-pad" style="padding-top:14px">
      <?= csrf_field() ?>
      <div class="field">
        <label>Entity type</label>
        <select class="input" name="entity_type" id="f-entity-type">
          <?php foreach ($types as $k => $m): ?><option value="<?= $k ?>"><?= htmlspecialchars($m['label']) ?></option><?php endforeach; ?>
        </select>
        <div class="hint" id="entity-hint" style="margin-top:6px;font-size:12px;color:var(--text-muted)"></div>
      </div>
      <div class="field"><label id="name-label">Client / entity name</label><input class="input" type="text" name="display_name" placeholder="Full name or registered entity name" required></div>
      <div class="input-row">
        <div class="field"><label>PAN</label><input class="input mono" type="text" name="pan" maxlength="10" placeholder="ABCDE1234F" style="text-transform:uppercase"></div>
        <div class="field" id="reg-field">
          <label id="reg-label">Registration no.</label>
          <input class="input mono" type="text" name="registration_number" id="f-reg">
        </div>
      </div>
      <div class="input-row">
        <div class="field"><label>Email</label><input class="input" type="email" name="email"></div>
        <div class="field"><label>Phone</label><input class="input" type="text" name="phone" placeholder="+91 …"></div>
      </div>
      <div class="input-row">
        <div class="field"><label>Address line 1</label><input class="input" type="text" name="address_line1"></div>
        <div class="field"><label>Address line 2</label><input class="input" type="text" name="address_line2"></div>
      </div>
      <div class="input-row">
        <div class="field"><label>City</label><input class="input" type="text" name="city"></div>
        <div class="field"><label>State</label><input class="input" type="text" name="state"></div>
        <div class="field"><label>PIN code</label><input class="input" type="text" name="pincode"></div>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px">
        <button type="button" class="btn btn-ghost" id="client-cancel-btn">Cancel</button>
        <button type="submit" class="btn btn-primary">Create &amp; continue onboarding</button>
      </div>
    </div>
  </form>
</div>

<form method="get" action="<?= url('clients') ?>" class="toolbar">
  <input class="input" type="text" name="q" placeholder="Name, PAN, registration no., email…" value="<?= htmlspecialchars($search) ?>">
  <select class="input" name="type" onchange="this.form.submit()">
    <option value="all">All entity types</option>
    <?php foreach ($types as $k => $m): ?><option value="<?= $k ?>" <?= $entityFilter===$k?'selected':'' ?>><?= htmlspecialchars($m['label']) ?></option><?php endforeach; ?>
  </select>
  <select class="input" name="status" onchange="this.form.submit()">
    <option value="all">All stages</option>
    <?php foreach (['draft','kyc_pending','kyc_verified','active','inactive'] as $s): ?><option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucwords(str_replace('_',' ',$s)) ?></option><?php endforeach; ?>
  </select>
  <button class="btn btn-ghost btn-sm" type="submit">Search</button>
</form>

<div class="card card-pad">
  <?php if ($clients): ?>
  <table class="table">
    <thead><tr><th>Client</th><th>Type</th><th>PAN / Registration</th><th>Contact</th><th>Onboarding</th><th>KYC</th></tr></thead>
    <tbody>
      <?php foreach ($clients as $c): ?>
      <tr style="cursor:pointer" onclick="window.location='<?= url('clients/' . $c['id']) ?>'">
        <td>
          <div class="case-title"><?= htmlspecialchars($c['display_name']) ?></div>
          <div class="case-client"><?= htmlspecialchars($c['city'] ?: '—') ?><?= $c['state'] ? ', ' . htmlspecialchars($c['state']) : '' ?></div>
        </td>
        <td class="case-client"><?= htmlspecialchars(client_type_label($c['entity_type'])) ?></td>
        <td>
          <div class="mono case-client"><?= htmlspecialchars($c['pan'] ?: '—') ?></div>
          <div class="mono case-client" style="opacity:.7"><?= htmlspecialchars($c['registration_number'] ?: '') ?></div>
        </td>
        <td class="case-client"><?= htmlspecialchars($c['email'] ?: $c['phone'] ?: '—') ?></td>
        <td><span class="badge badge-onboard-<?= $c['onboarding_status'] ?>"><?= str_replace('_',' ',$c['onboarding_status']) ?></span></td>
        <td><span class="badge badge-kyc-<?= $c['kyc_status'] ?>"><?= $c['kyc_status'] ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <div class="empty-state"><?= icon('clients') ?><p>No clients match that search.</p></div>
  <?php endif; ?>
</div>

<script>
var CLIENT_TYPES = <?= json_encode($types) ?>;
(function(){
  var panel = document.getElementById('client-panel');
  document.getElementById('client-toggle-btn').addEventListener('click',function(){ panel.classList.toggle('open'); if(panel.classList.contains('open')){ panel.scrollIntoView({behavior:'smooth',block:'start'}); updateEntityFields(); } });
  document.getElementById('client-cancel-btn').addEventListener('click',function(){ panel.classList.remove('open'); });
  document.getElementById('client-panel-close').addEventListener('click',function(){ panel.classList.remove('open'); });
  document.getElementById('f-entity-type').addEventListener('change', updateEntityFields);
  function updateEntityFields(){
    var type=document.getElementById('f-entity-type').value, meta=CLIENT_TYPES[type];
    var rf=document.getElementById('reg-field'), ri=document.getElementById('f-reg'), rl=document.getElementById('reg-label'), nl=document.getElementById('name-label'), hint=document.getElementById('entity-hint');
    if(meta.registration_label){ rf.style.display=''; rl.textContent=meta.registration_label+(meta.registration_required?' *':''); ri.required=!!meta.registration_required; } else { rf.style.display='none'; ri.required=false; ri.value=''; }
    nl.textContent=type==='individual'?'Full name':'Entity / registered name';
    hint.textContent='Leadership type: '+meta.leadership_label+(meta.leadership_singular?' (one person)':'(multiple)');
  }
  updateEntityFields();
})();
</script>
