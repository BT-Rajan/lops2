<div class="page-head">
  <div>
    <span class="eyebrow-gold"><?= htmlspecialchars($meta['label']) ?></span>
    <h1><?= htmlspecialchars($client['display_name']) ?></h1>
    <p class="sub">
      <span class="badge badge-onboard-<?= $client['onboarding_status'] ?>"><?= str_replace('_',' ',$client['onboarding_status']) ?></span>
      &nbsp;<span class="badge badge-kyc-<?= $client['kyc_status'] ?>">KYC <?= $client['kyc_status'] ?></span>
    </p>
  </div>
  <div style="display:flex;gap:10px">
    <a class="btn btn-ghost" href="<?= url('clients') ?>">← All clients</a>
    <button class="btn btn-primary" type="button" id="edit-toggle"><?= icon('edit') ?> Edit</button>
  </div>
</div>

<!-- Edit panel -->
<div class="card inline-panel" id="edit-panel">
  <form method="post" action="<?= url('clients/' . $client['id']) ?>">
    <?= csrf_field() ?><input type="hidden" name="_action" value="update_core">
    <div class="card-head" style="padding:20px 24px 0"><h3>Edit client details</h3><span class="modal-close" id="edit-close"><?= icon('close') ?></span></div>
    <div class="card-pad" style="padding-top:14px">
      <div class="field"><label><?= $client['entity_type']==='individual'?'Full name':'Entity / registered name' ?></label><input class="input" type="text" name="display_name" value="<?= htmlspecialchars($client['display_name']) ?>" required></div>
      <div class="input-row">
        <div class="field"><label>PAN</label><input class="input mono" type="text" name="pan" maxlength="10" value="<?= htmlspecialchars($client['pan'] ?? '') ?>" style="text-transform:uppercase"></div>
        <?php if ($meta['registration_label']): ?>
        <div class="field"><label><?= htmlspecialchars($meta['registration_label']) ?><?= $meta['registration_required']?' *':' (optional)' ?></label><input class="input mono" type="text" name="registration_number" value="<?= htmlspecialchars($client['registration_number'] ?? '') ?>" <?= $meta['registration_required']?'required':'' ?>></div>
        <?php endif; ?>
      </div>
      <div class="input-row"><div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= htmlspecialchars($client['email']??'') ?>"></div><div class="field"><label>Phone</label><input class="input" type="text" name="phone" value="<?= htmlspecialchars($client['phone']??'') ?>"></div></div>
      <div class="input-row"><div class="field"><label>Address line 1</label><input class="input" type="text" name="address_line1" value="<?= htmlspecialchars($client['address_line1']??'') ?>"></div><div class="field"><label>Address line 2</label><input class="input" type="text" name="address_line2" value="<?= htmlspecialchars($client['address_line2']??'') ?>"></div></div>
      <div class="input-row"><div class="field"><label>City</label><input class="input" type="text" name="city" value="<?= htmlspecialchars($client['city']??'') ?>"></div><div class="field"><label>State</label><input class="input" type="text" name="state" value="<?= htmlspecialchars($client['state']??'') ?>"></div><div class="field"><label>PIN</label><input class="input" type="text" name="pincode" value="<?= htmlspecialchars($client['pincode']??'') ?>"></div></div>
      <div style="display:flex;justify-content:flex-end;gap:10px"><button type="button" class="btn btn-ghost" id="edit-cancel">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
    </div>
  </form>
</div>

<div class="grid-2">
  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Summary -->
    <div class="card card-pad">
      <div class="card-head"><h3>Client summary</h3></div>
      <table class="table">
        <tr><td style="width:160px;color:var(--text-muted)">PAN</td><td class="mono"><?= htmlspecialchars($client['pan']?:'—') ?></td></tr>
        <?php if ($meta['registration_label']): ?><tr><td style="color:var(--text-muted)"><?= htmlspecialchars($meta['registration_label']) ?></td><td class="mono"><?= htmlspecialchars($client['registration_number']?:'—') ?></td></tr><?php endif; ?>
        <tr><td style="color:var(--text-muted)">Email</td><td><?= htmlspecialchars($client['email']?:'—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">Phone</td><td><?= htmlspecialchars($client['phone']?:'—') ?></td></tr>
        <tr><td style="color:var(--text-muted)">Address</td><td><?= htmlspecialchars(trim(implode(', ', array_filter([$client['address_line1'],$client['address_line2'],$client['city'],$client['state'],$client['pincode']]))) ?: '—') ?></td></tr>
      </table>
    </div>

    <!-- Leadership -->
    <div class="card card-pad">
      <div class="card-head"><h3><?= htmlspecialchars($meta['leadership_label']) ?> — KYC</h3><button class="btn btn-sm btn-primary" type="button" id="leader-toggle"><?= icon('plus') ?> <?= $meta['leadership_singular']?'Change':'Add' ?></button></div>
      <div class="inline-panel" id="leader-panel">
        <form method="post" action="<?= url('clients/' . $client['id']) ?>" style="padding:4px 0 14px">
          <?= csrf_field() ?><input type="hidden" name="_action" value="add_leader">
          <?php if ($meta['leadership_singular'] && $activeLeaders): ?>
            <div class="alert alert-info">Adding a new <?= strtolower($meta['leadership_label']) ?> will end the current one as of today.</div>
          <?php endif; ?>
          <div class="input-row">
            <div class="field"><label>Role</label><select class="input" name="role"><?php foreach ($meta['leadership_roles'] as $r): ?><option><?= htmlspecialchars($r) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Full name</label><input class="input" type="text" name="full_name" required></div>
          </div>
          <div class="input-row"><div class="field"><label>PAN</label><input class="input mono" type="text" name="pan" maxlength="10" style="text-transform:uppercase"></div><div class="field"><label>ID proof type</label><select class="input" name="id_proof_type"><option value="">— Select —</option><option>Aadhaar</option><option>Passport</option><option>Voter ID</option><option>Driving Licence</option><option>Other</option></select></div></div>
          <div class="input-row"><div class="field"><label>ID proof no.</label><input class="input" type="text" name="id_proof_number"></div><div class="field"><label>DIN / membership no.</label><input class="input" type="text" name="din_or_membership_no"></div></div>
          <div class="input-row"><div class="field"><label>Email</label><input class="input" type="email" name="email"></div><div class="field"><label>Phone</label><input class="input" type="text" name="phone"></div></div>
          <div class="input-row"><div class="field"><label>Effective from</label><input class="input" type="date" name="effective_from" value="<?= date('Y-m-d') ?>"></div><div class="field" style="display:flex;align-items:flex-end;padding-bottom:11px"><label style="display:flex;align-items:center;gap:8px"><input type="checkbox" name="kyc_verified"> KYC verified</label></div></div>
          <div style="display:flex;justify-content:flex-end;gap:10px"><button type="button" class="btn btn-ghost" id="leader-cancel">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
      </div>
      <?php if ($activeLeaders): ?>
      <table class="table">
        <thead><tr><th>Role</th><th>Name</th><th>PAN / ID</th><th>KYC</th><th>Since</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($activeLeaders as $l): ?>
          <tr>
            <td><span class="badge badge-open"><?= htmlspecialchars($l['role']) ?></span></td>
            <td><div class="case-title"><?= htmlspecialchars($l['full_name']) ?></div><div class="case-client"><?= htmlspecialchars($l['email']?:$l['phone']?:'') ?></div></td>
            <td><div class="mono case-client"><?= htmlspecialchars($l['pan']?:'—') ?></div><div class="case-client"><?= htmlspecialchars($l['id_proof_type'] ? $l['id_proof_type'].' · '.$l['id_proof_number'] : '') ?></div></td>
            <td><form method="post" action="<?= url('clients/'.$client['id']) ?>" style="display:inline"><?= csrf_field() ?><input type="hidden" name="_action" value="toggle_leader_kyc"><input type="hidden" name="leader_id" value="<?= $l['id'] ?>"><button type="submit" class="badge badge-kyc-<?= $l['kyc_verified']?'verified':'pending' ?>" style="border:none;cursor:pointer"><?= $l['kyc_verified']?'Verified':'Pending' ?></button></form></td>
            <td class="case-client"><?= fmt_date($l['effective_from']) ?></td>
            <td><form method="post" action="<?= url('clients/'.$client['id']) ?>" onsubmit="return confirm('End this leadership role?')"><?= csrf_field() ?><input type="hidden" name="_action" value="end_leader"><input type="hidden" name="leader_id" value="<?= $l['id'] ?>"><button class="btn btn-ghost btn-sm" type="submit">End</button></form></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><div class="empty-state"><p>No active leadership on file.</p></div><?php endif; ?>
      <?php if ($pastLeaders): ?>
      <details style="margin-top:16px"><summary style="cursor:pointer;font-size:13px;font-weight:600;color:var(--text-muted)">Leadership history (<?= count($pastLeaders) ?>)</summary>
        <table class="table" style="margin-top:10px"><thead><tr><th>Role</th><th>Name</th><th>From</th><th>To</th></tr></thead><tbody>
          <?php foreach ($pastLeaders as $l): ?><tr><td><span class="badge badge-closed"><?= htmlspecialchars($l['role']) ?></span></td><td><?= htmlspecialchars($l['full_name']) ?></td><td class="case-client"><?= fmt_date($l['effective_from']) ?></td><td class="case-client"><?= fmt_date($l['effective_to']) ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </details>
      <?php endif; ?>
    </div>

    <!-- Documents -->
    <div class="card card-pad">
      <div class="card-head"><h3>Documents</h3></div>
      <form method="post" action="<?= url('clients/' . $client['id']) ?>" enctype="multipart/form-data" style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border-card)">
        <?= csrf_field() ?><input type="hidden" name="_action" value="upload_doc">
        <div class="input-row">
          <div class="field"><label>Document type</label><select class="input" name="doc_type"><?php foreach ($docTypes as $dt): ?><option><?= htmlspecialchars($dt) ?></option><?php endforeach; ?></select></div>
          <div class="field"><label>Relates to</label><select class="input" name="leadership_id"><option value="">Client (general)</option><?php foreach ($allLeaders as $l): ?><option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['role'].' — '.$l['full_name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="field"><label>File <span style="color:var(--text-muted);font-weight:400">(PDF/JPG/PNG/DOC/DOCX · 5MB)</span></label><input class="input" type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required></div>
        <button type="submit" class="btn btn-primary btn-sm">Upload</button>
      </form>
      <?php if ($documents): foreach ($documents as $d): ?>
        <div class="task-row">
          <span class="task-check" style="border-color:var(--accent-600);color:var(--accent-600)"><?= icon('documents') ?></span>
          <div><div class="task-title"><?= htmlspecialchars($d['doc_type']) ?></div><div class="task-meta"><?= htmlspecialchars($d['original_name']) ?> · <?= fmt_size((int)$d['file_size']) ?><?= $d['leader_name']?' · '.htmlspecialchars($d['leader_role'].': '.$d['leader_name']):'' ?> · <?= time_ago($d['uploaded_at']) ?></div></div>
          <div style="margin-left:auto;display:flex;gap:6px">
            <a class="icon-btn btn-sm" style="display:inline-grid" href="<?= url('storage/clients/' . $client['id'] . '/' . urlencode($d['stored_name'])) ?>" target="_blank">⬇</a>
            <form method="post" action="<?= url('clients/'.$client['id']) ?>" style="display:inline" onsubmit="return confirm('Delete document?')"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_doc"><input type="hidden" name="doc_id" value="<?= $d['id'] ?>"><button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)"><?= icon('trash') ?></button></form>
          </div>
        </div>
      <?php endforeach; else: ?><div class="empty-state"><?= icon('documents') ?><p>No documents yet.</p></div><?php endif; ?>
    </div>

  </div>

  <div style="display:flex;flex-direction:column;gap:20px">

    <!-- Matters -->
    <div class="card card-pad">
      <div class="card-head"><h3>Matters</h3><a class="link" href="<?= url('cases?client_id=' . (int)$client['id']) ?>">All matters →</a></div>
      <?php if ($matters): ?>
        <table class="table">
          <thead><tr><th>Matter</th><th>Status</th><th>Docs</th></tr></thead>
          <tbody>
            <?php foreach ($matters as $m): ?>
              <tr>
                <td>
                  <a class="link case-title" href="<?= url('cases/' . $m['id']) ?>"><?= htmlspecialchars($m['case_number']) ?></a>
                  <div class="case-client"><?= htmlspecialchars($m['title']) ?></div>
                </td>
                <td><span class="badge badge-<?= $m['status'] ?>"><?= $m['status'] ?></span></td>
                <td class="case-client">
                  <a href="<?= url('cases/' . $m['id'] . '#documents') ?>" style="display:inline-flex;align-items:center;gap:4px;color:inherit">
                    <?= icon('documents') ?> <?= (int)($m['doc_count'] ?? 0) ?>
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state"><p>No matters linked to this client yet.</p></div>
      <?php endif; ?>
    </div>

    <!-- Billing -->
    <div class="card card-pad">
      <div class="card-head"><h3>Billing</h3><a class="link" href="<?= url('billing?q=' . urlencode($client['display_name'])) ?>">View invoices →</a></div>
      <?php if ($billingSummary): ?>
        <table class="table">
          <thead><tr><th>Currency</th><th>Invoiced</th><th>Outstanding</th></tr></thead>
          <tbody>
            <?php foreach ($billingSummary as $b): ?>
              <tr>
                <td class="mono" style="font-weight:700"><?= htmlspecialchars($b['currency']) ?></td>
                <td class="mono"><?= htmlspecialchars(format_money((float)$b['total'], $b['currency'])) ?> <span class="case-client">(<?= (int)$b['cnt'] ?>)</span></td>
                <td class="mono" style="<?= (float)$b['outstanding'] > 0.01 ? 'color:var(--danger)' : '' ?>"><?= htmlspecialchars(format_money((float)$b['outstanding'], $b['currency'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-state"><p>No issued invoices for this client yet.</p></div>
      <?php endif; ?>
    </div>

    <!-- Onboarding / KYC -->
    <div class="card card-pad">
      <div class="card-head"><h3>Onboarding &amp; KYC</h3></div>
      <p class="case-client" style="margin-bottom:10px">Move through onboarding stages:</p>
      <form method="post" action="<?= url('clients/'.$client['id']) ?>" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px">
        <?= csrf_field() ?><input type="hidden" name="_action" value="set_onboarding">
        <?php foreach (['draft','kyc_pending','kyc_verified','active','inactive'] as $s): ?><button type="submit" name="onboarding_status" value="<?= $s ?>" class="filter-chip <?= $client['onboarding_status']===$s?'active':'' ?>"><?= ucwords(str_replace('_',' ',$s)) ?></button><?php endforeach; ?>
      </form>
      <p class="case-client" style="margin-bottom:10px">KYC status:</p>
      <form method="post" action="<?= url('clients/'.$client['id']) ?>" style="display:flex;flex-wrap:wrap;gap:8px">
        <?= csrf_field() ?><input type="hidden" name="_action" value="set_kyc">
        <?php foreach (['pending','verified','rejected'] as $s): ?><button type="submit" name="kyc_status" value="<?= $s ?>" class="filter-chip <?= $client['kyc_status']===$s?'active':'' ?>"><?= ucfirst($s) ?></button><?php endforeach; ?>
      </form>
    </div>

    <!-- Secondary contacts -->
    <div class="card card-pad">
      <div class="card-head"><h3>Secondary contacts</h3><button class="btn btn-sm btn-primary" type="button" id="contact-toggle"><?= icon('plus') ?> Add</button></div>
      <div class="inline-panel" id="contact-panel">
        <form method="post" action="<?= url('clients/'.$client['id']) ?>" style="padding:4px 0 14px">
          <?= csrf_field() ?><input type="hidden" name="_action" value="add_contact">
          <div class="input-row"><div class="field"><label>Full name</label><input class="input" type="text" name="full_name" required></div><div class="field"><label>Designation</label><input class="input" type="text" name="designation"></div></div>
          <div class="input-row"><div class="field"><label>Email</label><input class="input" type="email" name="email"></div><div class="field"><label>Phone</label><input class="input" type="text" name="phone"></div></div>
          <div class="field"><label>Notes</label><input class="input" type="text" name="notes"></div>
          <div style="display:flex;justify-content:flex-end;gap:10px"><button type="button" class="btn btn-ghost" id="contact-cancel">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
        </form>
      </div>
      <?php if ($contacts): foreach ($contacts as $ct): ?>
        <div class="task-row">
          <div><div class="task-title"><?= htmlspecialchars($ct['full_name']) ?></div><div class="task-meta"><?= htmlspecialchars($ct['designation']?:'Contact') ?> · <?= htmlspecialchars($ct['email']?:$ct['phone']?:'—') ?></div></div>
          <form method="post" action="<?= url('clients/'.$client['id']) ?>" style="margin-left:auto"><?= csrf_field() ?><input type="hidden" name="_action" value="delete_contact"><input type="hidden" name="contact_id" value="<?= $ct['id'] ?>"><button class="icon-btn btn-sm" style="display:inline-grid;color:var(--danger)" onclick="return confirm('Remove contact?')"><?= icon('trash') ?></button></form>
        </div>
      <?php endforeach; else: ?><div class="empty-state"><p>No secondary contacts.</p></div><?php endif; ?>
    </div>

    <!-- Danger zone -->
    <div class="card card-pad" style="border-color:rgba(193,59,59,.25)">
      <div class="card-head"><h3 style="color:var(--danger)">Remove client</h3></div>
      <p class="case-client" style="margin-bottom:14px">Permanently deletes this client, all KYC records, contacts, and uploaded documents. Any linked matters are kept — they'll just lose the link.</p>
      <form method="post" action="<?= url('clients/'.$client['id'].'/delete') ?>" onsubmit="return confirm('Permanently delete this client and all related records?')">
        <?= csrf_field() ?><button class="btn btn-ghost" style="border-color:var(--danger);color:var(--danger)">Delete client</button>
      </form>
    </div>

  </div>
</div>

<script>
(function(){
  function wireToggle(toggleId, panelId, cancelId, closeId){
    var toggle=document.getElementById(toggleId), panel=document.getElementById(panelId);
    if(!toggle||!panel) return;
    toggle.addEventListener('click',function(){ panel.classList.toggle('open'); if(panel.classList.contains('open')) panel.scrollIntoView({behavior:'smooth',block:'center'}); });
    var cancel=document.getElementById(cancelId); if(cancel) cancel.addEventListener('click',function(){ panel.classList.remove('open'); });
    var close=document.getElementById(closeId);   if(close)  close.addEventListener('click',function(){ panel.classList.remove('open'); });
  }
  wireToggle('edit-toggle','edit-panel','edit-cancel','edit-close');
  wireToggle('leader-toggle','leader-panel','leader-cancel',null);
  wireToggle('contact-toggle','contact-panel','contact-cancel',null);
})();
</script>
