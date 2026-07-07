<?php
/**
 * LegalOps — seed demo data.
 *
 * Wipes every table this script owns (never phpauth_config or
 * legalops_settings — those are essential config, not data) and
 * rebuilds a deliberately thorough demo dataset: every status enum,
 * every client entity type, every tax profile, both link types, tasks
 * split across two users to test admin-vs-member visibility, and real
 * placeholder files on disk so document downloads actually work
 * end-to-end instead of 404ing on a DB-only record.
 *
 * Safe to re-run — it always starts by clearing its own tables, so you
 * get the same dataset back every time rather than duplicate rows.
 *
 * Usage:
 *   php database/seed_demo.php
 *
 * Login afterwards:
 *   demo@legalops.local       / LegalOps@123  (admin)
 *   associate@legalops.local  / LegalOps@123  (member — use this one to
 *                                               see the member-only view)
 */

require_once __DIR__ . '/lib.php';
lops2_cli_guard();
require_once __DIR__ . '/../config/app.php';

echo "LegalOps — seed demo data\n";
echo str_repeat('-', 40) . "\n";

try {
    $pdo = lops2_pdo();
} catch (Throwable $e) {
    fwrite(STDERR, "Could not connect to the database: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Run `php database/install.php` first if you haven't already.\n");
    exit(1);
}

// Tables this script owns end-to-end. phpauth_config and legalops_settings
// are essential config (installed by schema.sql) and are never touched here.
$ownedTables = [
    'legalops_invoice_items', 'legalops_invoices', 'legalops_invoice_number_sequences',
    'legalops_billing_entities', 'legalops_case_documents', 'legalops_client_documents',
    'legalops_client_contacts', 'legalops_client_leadership', 'legalops_clients',
    'legalops_case_links', 'legalops_tasks', 'legalops_activity', 'legalops_calendar_accounts',
    'legalops_cases', 'phpauth_sessions', 'phpauth_requests', 'phpauth_attempts', 'phpauth_users',
];

try {
    echo "Clearing existing demo data ...\n";
    foreach ($ownedTables as $t) {
        $pdo->exec("TRUNCATE TABLE `{$t}`");
    }

    // ── Users — one admin, one member, so admin-sees-everything vs ────────
    // member-sees-own-work is actually testable, not just theoretical.
    echo "Users ...\n";
    $userStmt = $pdo->prepare(
        'INSERT INTO phpauth_users (id, email, password, isactive, full_name, job_title, avatar_color, role) VALUES (?,?,?,1,?,?,?,?)'
    );
    $pwHash = password_hash('LegalOps@123', PASSWORD_BCRYPT);
    $userStmt->execute([1, 'demo@legalops.local', $pwHash, 'Aishwarya Krishnan', 'Managing Partner', '#3B6FE0', 'admin']);
    $userStmt->execute([2, 'associate@legalops.local', $pwHash, 'Arjun Mehta', 'Associate', '#C9A227', 'member']);

    // ── Billing entities — one of each entity_type ─────────────────────────
    echo "Billing entities ...\n";
    $entityStmt = $pdo->prepare(
        'INSERT INTO legalops_billing_entities
         (id, name, country, entity_type, tax_reg_no, state_or_emirate, address, default_currency, invoice_prefix, bank_details)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $entityStmt->execute([1, 'LegalOps India Pvt Ltd', 'IN', 'IN_GST', '33AAAAA0000A1Z5', 'Tamil Nadu',
        'No. 12, Anna Salai, Chennai, Tamil Nadu 600002, India', 'INR', 'LO-IN',
        'Bank: HDFC Bank · A/C: 50100123456789 · IFSC: HDFC0000123']);
    $entityStmt->execute([2, 'LegalOps DMCC', 'AE', 'GCC_VAT', '100123456700003', 'Dubai',
        'Unit 14, Jewellery & Gemplex 3, DMCC, Dubai, UAE', 'AED', 'LO-AE',
        'Bank: Emirates NBD · IBAN: AE070260001234567890123 · SWIFT: EBILAEAD']);
    $entityStmt->execute([3, 'LegalOps Advisory (International Desk)', 'GB', 'NO_VAT', null, null,
        '1 Fleet Street, London EC4Y 1AA, United Kingdom', 'USD', 'LO-XX',
        'Bank: Wise · Account: 8801234567 · SWIFT: TRWIGB2L']);

    // ── Clients — one of every entity_type, every onboarding_status, ──────
    // every kyc_status.
    echo "Clients ...\n";
    $clientStmt = $pdo->prepare(
        'INSERT INTO legalops_clients
         (id, entity_type, display_name, pan, registration_number, email, phone,
          address_line1, address_line2, city, state, pincode, onboarding_status, kyc_status, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $clients = [
        [1, 'private_limited', 'Sundaram Textiles Pvt Ltd', 'AABCS1234D', 'U17110TN2014PTC098765', 'contact@sundaramtextiles.in', '+91 44 2345 6789', 'Plot 12, SIDCO Industrial Estate', 'Guindy', 'Chennai', 'Tamil Nadu', '600032', 'active', 'verified'],
        [2, 'individual', 'R. Krishnan', 'BFKPK4567L', null, 'r.krishnan@example.com', '+91 98400 12345', '14 Lake View Road', 'Nungambakkam', 'Chennai', 'Tamil Nadu', '600034', 'active', 'verified'],
        [3, 'public_limited', 'Velan Foods Ltd', 'AAFCV6789K', 'U15400TN2011PLC076543', 'legal@velanfoods.in', '+91 422 234 5678', 'No. 8, Avinashi Road', null, 'Coimbatore', 'Tamil Nadu', '641018', 'kyc_verified', 'verified'],
        [4, 'family', 'Subramaniam Family (HUF)', 'AAHHS3456M', null, 'subramaniam.family@example.com', '+91 98410 65432', '21 Temple Street', 'Mylapore', 'Chennai', 'Tamil Nadu', '600004', 'kyc_pending', 'pending'],
        [5, 'partnership', 'Anand Constructions', 'AAJFA2345N', 'TN/ROF/2009/4521', 'info@anandconstructions.in', '+91 44 4567 8901', '56 Anna Salai', null, 'Chennai', 'Tamil Nadu', '600002', 'active', 'verified'],
        [6, 'trust', 'Meridian Capital Charitable Trust', 'AAATM7890P', 'TN/TRUST/2018/1123', 'trustoffice@meridiancapital.in', '+91 44 6789 0123', '101 Apex Towers, OMR', null, 'Chennai', 'Tamil Nadu', '600096', 'draft', 'pending'],
        [7, 'proprietorship', 'Ganesh Traders', 'AAIPG5566Q', 'GST/33AAIPG5566Q1ZR', 'ganesh@ganeshtraders.in', '+91 422 445 5678', '23 Big Bazaar Street', null, 'Coimbatore', 'Tamil Nadu', '641001', 'kyc_pending', 'rejected'],
        [8, 'opc', 'Nova Innovations (OPC) Pvt Ltd', 'AACCN7788R', 'U72900KA2021OPC145632', 'founder@novainnovations.in', '+91 80 4123 5566', '4th Floor, Prestige Tech Park', 'Bellandur', 'Bengaluru', 'Karnataka', '560103', 'active', 'verified'],
        [9, 'association', 'Tamil Nadu Traders Welfare Association', 'AAGAT9900S', 'SOC/TN/1998/00456', 'secretary@tntwa.org.in', '+91 44 2890 1234', '12 Mount Road', null, 'Chennai', 'Tamil Nadu', '600006', 'inactive', 'rejected'],
    ];
    foreach ($clients as $c) { $clientStmt->execute([...$c, 1]); }

    // ── Cases — every status, every priority, one fully-populated with ────
    // litigation/court fields, two demo case_links (connected + appeal_of),
    // and two deliberately left unlinked to a client (near-miss name, and
    // a genuinely different entity) to show that state in the UI too.
    echo "Matters ...\n";
    $caseStmt = $pdo->prepare(
        'INSERT INTO legalops_cases
         (id, case_number, title, client_name, client_id, practice_area, status, priority,
          opened_on, due_on, next_hearing_date, next_hearing_time, next_hearing_purpose,
          court_type, court_name, bench, court_hall, judge_name, jurisdiction, opposite_counsel, case_stage,
          filing_date, limitation_date, disposal_date, result, created_by)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $caseStmt->execute([1, 'LO-2026-014', 'Sundaram Textiles — Commercial Lease Renewal', 'Sundaram Textiles Pvt Ltd', 1, 'Real Estate', 'open', 'high',
        '2026-04-02', '2026-07-10', null, null, null,
        null, null, null, null, null, null, null, null, null, null, null, null, 1]);
    $caseStmt->execute([2, 'LO-2026-021', 'Krishnan vs. Coastal Logistics', 'R. Krishnan', 2, 'Civil Litigation', 'open', 'medium',
        '2026-05-11', '2026-08-01', '2026-07-08', '11:00:00', 'For evidence',
        'District Court', 'Chennai City Civil Court', 'Court No. 4', 'Hall B', "Hon'ble Judge R. Venkataraman", 'Chennai',
        'M/s. Coastal Logistics — Adv. S. Narayanan', 'Evidence', '2026-05-15', '2028-05-11', null, null, 1]);
    $caseStmt->execute([3, 'LO-2026-009', 'Velan Foods — Trademark Opposition', 'Velan Foods Ltd', 3, 'Intellectual Property', 'pending', 'high',
        '2026-03-18', '2026-07-05', '2026-07-15', null, 'Hearing on opposition',
        'Tribunal (Other)', 'Trademark Registry, Chennai', null, null, null, 'Chennai', 'IPR Associates', 'Notice/Summons Issued',
        null, null, null, null, 1]);
    $caseStmt->execute([4, 'LO-2026-027', 'Estate of M. Subramaniam — Probate', 'Subramaniam Family', null, 'Estate & Succession', 'open', 'low',
        '2026-06-02', '2026-09-15', null, null, null,
        null, null, null, null, null, null, null, null, null, null, null, null, 1]);
    $caseStmt->execute([5, 'LO-2026-002', 'Anand Constructions — Arbitration', 'Anand Constructions', 5, 'Arbitration', 'closed', 'medium',
        '2026-01-09', '2026-04-30', null, null, null,
        'Arbitration', 'Chennai Arbitration Centre', null, null, 'Sole Arbitrator: R. Balasubramaniam', 'Chennai', 'Ramani & Associates', 'Disposed',
        '2026-01-15', null, '2026-04-28', 'Awarded in favour of the client — ₹18.5L with costs', 1]);
    $caseStmt->execute([6, 'LO-2026-033', 'Meridian Capital — Share Purchase Agreement', 'Meridian Capital Partners', null, 'Corporate', 'open', 'high',
        '2026-06-15', '2026-07-20', null, null, null,
        null, null, null, null, null, null, null, null, null, null, null, null, 1]);
    $caseStmt->execute([7, 'LO-2026-041', 'Ganesh Traders — GST Show-Cause Notice Reply', 'Ganesh Traders', 7, 'Tax & GST', 'pending', 'low',
        '2026-06-20', '2026-07-18', null, null, null,
        'Tribunal (Other)', 'GST Appellate Authority, Coimbatore', null, null, null, 'Coimbatore', null, 'Written Statement/Reply',
        null, null, null, null, 1]);
    $caseStmt->execute([8, 'LO-2026-045', 'Nova Innovations — Founder Exit & Share Buyback', 'Nova Innovations (OPC) Pvt Ltd', 8, 'Corporate', 'open', 'medium',
        '2026-05-28', '2026-07-25', null, null, null,
        null, null, null, null, null, null, null, null, null, null, null, null, 1]);
    $caseStmt->execute([9, 'LO-2026-046', 'Nova Innovations — Employment Dispute (HR)', 'Nova Innovations (OPC) Pvt Ltd', 8, 'Employment', 'pending', 'medium',
        '2026-06-10', '2026-07-22', null, null, null,
        'Labour Court / Industrial Tribunal', 'Labour Court, Bengaluru', null, null, null, 'Bengaluru', "Workman's counsel: K. Suresh", 'Filed',
        '2026-06-12', null, null, null, 1]);
    $caseStmt->execute([10, 'LO-2026-055', 'Krishnan vs. Coastal Logistics — Appeal', 'R. Krishnan', 2, 'Civil Litigation', 'open', 'medium',
        '2026-06-25', '2026-08-30', null, null, null,
        'High Court', 'Madras High Court', null, null, null, 'Chennai', 'M/s. Coastal Logistics — Adv. S. Narayanan', 'Filed',
        '2026-06-26', null, null, null, 1]);

    $pdo->exec('ALTER TABLE legalops_cases AUTO_INCREMENT = 11');

    echo "Matter links (connected + appeal) ...\n";
    $linkStmt = $pdo->prepare('INSERT INTO legalops_case_links (case_id, linked_case_id, link_type, created_by) VALUES (?,?,?,?)');
    $linkStmt->execute([8, 9, 'connected', 1]);
    $linkStmt->execute([10, 2, 'appeal_of', 1]);

    // ── Tasks — every status, split across both users ─────────────────────
    echo "Tasks ...\n";
    $taskStmt = $pdo->prepare(
        'INSERT INTO legalops_tasks (case_id, title, due_on, priority, status, assigned_to, created_by, source) VALUES (?,?,?,?,?,?,?,?)'
    );
    $taskStmt->execute([1, 'Review revised lease clauses with client', '2026-07-08', 'high', 'in_progress', 1, 1, 'manual']);
    $taskStmt->execute([2, 'File rejoinder with district court', '2026-07-08', 'high', 'pending', 1, 1, 'manual']);
    $taskStmt->execute([3, 'Respond to TM opposition board notice', '2026-07-10', 'medium', 'pending', 2, 1, 'manual']);
    $taskStmt->execute([8, 'Draft SPA disclosure schedules', '2026-07-09', 'high', 'in_progress', 2, 1, 'manual']);
    $taskStmt->execute([4, 'Collect succession certificates from family', '2026-07-15', 'low', 'pending', 1, 1, 'manual']);
    $taskStmt->execute([5, 'Close out arbitration billing file', '2026-06-30', 'medium', 'done', 1, 1, 'manual']);
    $taskStmt->execute([7, 'Follow up on GST notice response with client', '2026-07-14', 'medium', 'hold', 2, 1, 'manual']);
    $taskStmt->execute([9, 'Schedule client call — Nova HR dispute strategy', '2026-07-16', 'low', 'hold', 1, 2, 'manual']);
    $taskStmt->execute([10, 'File appeal memorandum', '2026-07-20', 'high', 'pending', 2, 1, 'manual']);

    // ── Activity feed ───────────────────────────────────────────────────
    echo "Activity feed ...\n";
    $actStmt = $pdo->prepare('INSERT INTO legalops_activity (uid, action, description) VALUES (?,?,?)');
    $actStmt->execute([1, 'case_created', 'Opened matter LO-2026-055 — Krishnan vs. Coastal Logistics — Appeal']);
    $actStmt->execute([2, 'task_completed', 'Marked "Close out arbitration billing file" as done']);
    $actStmt->execute([1, 'case_status', 'Moved LO-2026-002 — Anand Constructions — Arbitration to closed']);
    $actStmt->execute([2, 'task_created', 'Added task "Draft SPA disclosure schedules"']);
    $actStmt->execute([1, 'invoice_issued', 'Issued invoice LO-IN/2627/0002 — Nova Innovations (OPC) Pvt Ltd']);
    $actStmt->execute([1, 'login', 'Signed in to LegalOps']);
    $actStmt->execute([2, 'login', 'Signed in to LegalOps']);

    // ── Client leadership — covers every entity type's leadership shape,
    // including a removed director (Sundaram) to test historical leaders.
    echo "Client leadership ...\n";
    $leadStmt = $pdo->prepare(
        'INSERT INTO legalops_client_leadership
         (client_id, role, full_name, pan, id_proof_type, id_proof_number, din_or_membership_no, email, phone, kyc_verified, status, effective_from, effective_to)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $leadStmt->execute([1, 'Managing Director', 'Aishwarya Krishnan', 'AABCS1111D', 'Aadhaar', 'XXXX-XXXX-4521', 'DIN08123456', 'aishwarya@sundaramtextiles.in', '+91 98400 11111', 1, 'active', '2014-08-01', null]);
    $leadStmt->execute([1, 'Director', 'Karthik Sundaram', 'AABCS2222E', 'Passport', 'P1234567', 'DIN08234567', 'karthik@sundaramtextiles.in', '+91 98400 22222', 1, 'active', '2014-08-01', null]);
    $leadStmt->execute([1, 'Director', 'Geetha Ramaswamy', 'AABCS3333F', 'Aadhaar', 'XXXX-XXXX-7788', 'DIN08001122', 'geetha@sundaramtextiles.in', null, 1, 'removed', '2014-08-01', '2025-11-30']);
    $leadStmt->execute([2, 'Individual', 'R. Krishnan', 'BFKPK4567L', 'Aadhaar', 'XXXX-XXXX-9012', null, 'r.krishnan@example.com', '+91 98400 12345', 1, 'active', '2020-01-01', null]);
    $leadStmt->execute([3, 'Director', 'Velan Murugesan', 'AAFCV1111K', 'Aadhaar', 'XXXX-XXXX-3344', 'DIN07112233', 'velan@velanfoods.in', '+91 422 200 1111', 1, 'active', '2011-05-01', null]);
    $leadStmt->execute([3, 'Chairperson', 'Meena Velan', 'AAFCV4444L', 'Passport', 'P7654321', 'DIN07223344', 'meena@velanfoods.in', '+91 422 200 3333', 1, 'active', '2011-05-01', null]);
    $leadStmt->execute([4, 'Karta', 'M. Subramaniam', 'AAHHS3456M', 'Aadhaar', 'XXXX-XXXX-5566', null, 'subramaniam.family@example.com', '+91 98410 65432', 0, 'active', '2015-01-01', null]);
    $leadStmt->execute([5, 'Managing Partner', 'Anand Vellaichamy', 'AAJFA1111N', 'Aadhaar', 'XXXX-XXXX-1212', null, 'anand@anandconstructions.in', '+91 44 4567 1111', 1, 'active', '2009-03-01', null]);
    $leadStmt->execute([5, 'Partner', 'Suresh Babu', 'AAJFA2222P', 'Voter ID', 'TN/AB1234567', null, 'suresh@anandconstructions.in', '+91 44 4567 2222', 1, 'active', '2009-03-01', null]);
    $leadStmt->execute([6, 'Managing Trustee', 'R. Venkatesan', 'AAATM1111Q', 'Aadhaar', 'XXXX-XXXX-8899', null, 'venkatesan@meridiancapital.in', '+91 44 6789 4444', 0, 'active', '2018-02-01', null]);
    $leadStmt->execute([7, 'Proprietor', 'Ganesh Kumar', 'AAIPG5566Q', 'Aadhaar', 'XXXX-XXXX-2233', null, 'ganesh@ganeshtraders.in', '+91 422 445 5678', 0, 'active', '2016-04-01', null]);
    $leadStmt->execute([8, 'Director', 'Divya Rajan', 'AACCN7788R', 'Passport', 'P2233445', 'DIN09112233', 'divya@novainnovations.in', '+91 80 4123 5566', 1, 'active', '2021-09-01', null]);
    $leadStmt->execute([8, 'Nominee Director', 'Karthik Nair', 'AACCN8899S', 'Aadhaar', 'XXXX-XXXX-6677', 'DIN09223344', 'karthik.nair@novainnovations.in', '+91 80 4123 5567', 1, 'active', '2021-09-01', null]);
    $leadStmt->execute([9, 'President', 'Selvam Raja', 'AAGAT1122T', 'Aadhaar', 'XXXX-XXXX-3399', null, 'president@tntwa.org.in', '+91 44 2890 1111', 0, 'active', '1998-06-01', null]);
    $leadStmt->execute([9, 'Secretary', 'Meena Kumari', 'AAGAT2233U', 'Voter ID', 'TN/CD9876543', null, 'secretary@tntwa.org.in', '+91 44 2890 1234', 0, 'active', '2020-06-01', null]);

    // ── Client contacts ─────────────────────────────────────────────────
    echo "Client contacts ...\n";
    $contactStmt = $pdo->prepare('INSERT INTO legalops_client_contacts (client_id, full_name, designation, email, phone, notes) VALUES (?,?,?,?,?,?)');
    $contactStmt->execute([1, 'Priya Natarajan', 'Company Secretary', 'priya.cs@sundaramtextiles.in', '+91 98400 33333', 'Primary point of contact for filings']);
    $contactStmt->execute([1, 'Mohan Raj', 'Finance Manager', 'mohan@sundaramtextiles.in', '+91 98400 44444', null]);
    $contactStmt->execute([3, 'Lakshmi Iyer', 'Legal Counsel (in-house)', 'lakshmi@velanfoods.in', '+91 422 200 2222', 'Coordinates on IP matters']);
    $contactStmt->execute([5, 'Divya Anand', 'Site Office Coordinator', 'divya@anandconstructions.in', '+91 44 4567 3333', null]);
    $contactStmt->execute([8, 'Rahul Menon', 'Company Secretary (outsourced)', 'rahul@csfirm.in', '+91 80 4123 9999', 'Handles ROC compliance for Nova']);

    // ── Documents — real placeholder files on disk so downloads actually ──
    // work end-to-end, not just DB rows that 404.
    echo "Documents (with real placeholder files on disk) ...\n";
    $clientDocStmt = $pdo->prepare(
        'INSERT INTO legalops_client_documents (client_id, doc_type, stored_name, original_name, mime_type, file_size, uploaded_by) VALUES (?,?,?,?,?,?,?)'
    );
    $caseDocStmt = $pdo->prepare(
        'INSERT INTO legalops_case_documents (case_id, doc_type, stored_name, original_name, mime_type, notes, uploaded_by) VALUES (?,?,?,?,?,?,?)'
    );

    $writeDemoFile = function (string $scope, int $ownerId, string $storedName, string $contents): int {
        $dir = rtrim(STORAGE_PATH, '/') . "/uploads/{$scope}/{$ownerId}";
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }
        file_put_contents("{$dir}/{$storedName}", $contents);
        return strlen($contents);
    };

    $clientDocs = [
        [1, 'Registration Certificate', 'sundaram-incorporation.txt', 'Sundaram Textiles - Certificate of Incorporation.txt', "Demo placeholder file.\nCertificate of Incorporation — Sundaram Textiles Pvt Ltd\nCIN: U17110TN2014PTC098765\n"],
        [1, 'PAN Card', 'sundaram-pan.txt', 'Sundaram Textiles - PAN Card.txt', "Demo placeholder file.\nPAN: AABCS1234D\n"],
        [2, 'Aadhaar / ID Proof', 'krishnan-aadhaar.txt', 'R Krishnan - Aadhaar.txt', "Demo placeholder file.\nAadhaar on file for R. Krishnan (masked).\n"],
        [6, 'Trust Deed', 'meridian-trust-deed.txt', 'Meridian Capital - Trust Deed.txt', "Demo placeholder file.\nTrust Deed — Meridian Capital Charitable Trust, registered 2018.\n"],
        [7, 'Registration Certificate', 'ganesh-gst-cert.txt', 'Ganesh Traders - GST Registration.txt', "Demo placeholder file.\nGSTIN: 33AAIPG5566Q1ZR — pending re-verification.\n"],
        [8, 'MOA', 'nova-moa.txt', 'Nova Innovations - MOA.txt', "Demo placeholder file.\nMemorandum of Association — Nova Innovations (OPC) Pvt Ltd.\n"],
    ];
    foreach ($clientDocs as [$clientId, $docType, $storedName, $originalName, $contents]) {
        $size = $writeDemoFile('clients', $clientId, $storedName, $contents);
        $clientDocStmt->execute([$clientId, $docType, $storedName, $originalName, 'text/plain', $size, 1]);
    }

    $caseDocs = [
        [1, 'Pleading', 'lease-renewal-draft.txt', 'Draft Lease Renewal Deed.txt', 'Draft shared with client for review', "Demo placeholder file.\nDraft lease renewal deed — Sundaram Textiles.\n"],
        [2, 'Evidence', 'coastal-logistics-invoice-bundle.txt', 'Invoice Bundle - Exhibit A.txt', 'Filed as Exhibit A with evidence affidavit', "Demo placeholder file.\nInvoice bundle — Exhibit A, Krishnan vs. Coastal Logistics.\n"],
        [3, 'Notice', 'tm-opposition-notice.txt', 'Trademark Opposition Notice.txt', null, "Demo placeholder file.\nNotice of opposition — Velan Foods trademark application.\n"],
        [9, 'Pleading', 'nova-hr-complaint.txt', 'Employee Complaint - Original.txt', 'Original complaint filed by workman', "Demo placeholder file.\nOriginal HR complaint — Nova Innovations employment dispute.\n"],
    ];
    foreach ($caseDocs as [$caseId, $docType, $storedName, $originalName, $notes, $contents]) {
        $size = $writeDemoFile('cases', $caseId, $storedName, $contents);
        $caseDocStmt->execute([$caseId, $docType, $storedName, $originalName, 'text/plain', $notes, 1]);
    }

    // ── Invoices — every status (draft/issued/void), spread across all ────
    // three billing entities and six of the eight tax profiles.
    echo "Invoices ...\n";
    $invStmt = $pdo->prepare(
        'INSERT INTO legalops_invoices
         (id, invoice_no, billing_entity_id, case_id, client_name, client_country, client_tax_reg_no, client_address,
          tax_profile_key, place_of_supply, currency, invoice_date, due_date, subtotal, tax_total, grand_total,
          tax_breakdown, notes, status, created_by, issued_at)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $itemStmt = $pdo->prepare(
        'INSERT INTO legalops_invoice_items
         (invoice_id, description, hsn_sac, quantity, unit_price, tax_rate, line_subtotal, line_tax, line_total, sort_order)
         VALUES (?,?,?,?,?,?,?,?,?,0)'
    );

    // 1 — issued, India, intra-state (CGST+SGST)
    $invStmt->execute([1, 'LO-IN/2627/0001', 1, 1, 'Sundaram Textiles Pvt Ltd', 'IN', '33BBBBB1111B1Z2',
        'Plot 45, Guindy Industrial Estate, Chennai, Tamil Nadu 600032', 'IN_GST_domestic', 'Tamil Nadu', 'INR',
        '2026-06-15', '2026-07-15', 75000.00, 13500.00, 88500.00,
        '{"CGST":{"rate":9,"amount":6750},"SGST":{"rate":9,"amount":6750}}',
        'Professional fees for lease renewal advisory — June 2026.', 'issued', 1, '2026-06-15 10:00:00']);
    $itemStmt->execute([1, 'Legal advisory — commercial lease renewal review', '9982', 1, 50000.00, 18, 50000.00, 9000.00, 59000.00]);
    $itemStmt->execute([1, 'Drafting amended lease deed', '9982', 1, 25000.00, 18, 25000.00, 4500.00, 29500.00]);

    // 2 — issued, India, inter-state (IGST) — client in Karnataka vs entity in Tamil Nadu
    $invStmt->execute([2, 'LO-IN/2627/0002', 1, 8, 'Nova Innovations (OPC) Pvt Ltd', 'IN', '29AACCN7788R1ZP',
        '4th Floor, Prestige Tech Park, Bellandur, Bengaluru, Karnataka 560103', 'IN_GST_interstate', 'Karnataka', 'INR',
        '2026-06-20', '2026-07-20', 40000.00, 7200.00, 47200.00,
        '{"IGST":{"rate":18,"amount":7200}}',
        'Retainer — corporate advisory, Q2 2026.', 'issued', 1, '2026-06-20 14:30:00']);
    $itemStmt->execute([2, 'Retainer — founder exit structuring advisory', '9982', 1, 40000.00, 18, 40000.00, 7200.00, 47200.00]);

    // 3 — draft, India, export of services (zero-rated under LUT)
    $invStmt->execute([3, 'DRAFT-3', 1, null, 'Global Textiles Sourcing Inc.', 'US', null,
        '8 Market Street, Suite 400, San Francisco, CA 94105, USA', 'IN_GST_export', null, 'INR',
        '2026-07-01', '2026-07-31', 120000.00, 0.00, 120000.00, '{}',
        'Cross-border compliance advisory for US sourcing office.', 'draft', 1, null]);
    $itemStmt->execute([3, 'Cross-border compliance advisory — export of services', '9982', 1, 120000.00, 0, 120000.00, 0.00, 120000.00]);

    // 4 — issued, UAE VAT
    $invStmt->execute([4, 'LO-AE/2026/0001', 2, null, 'Al Manara Trading LLC', 'AE', '100234567800004',
        'Office 2201, Al Manara Tower, Sheikh Zayed Road, Dubai, UAE', 'AE_VAT', 'Dubai', 'AED',
        '2026-06-18', '2026-07-18', 60000.00, 3000.00, 63000.00,
        '{"VAT":{"rate":5,"amount":3000}}',
        'Legal advisory retainer — Q2 2026.', 'issued', 1, '2026-06-18 09:15:00']);
    $itemStmt->execute([4, 'Legal advisory retainer — Q2 2026', null, 1, 60000.00, 5, 60000.00, 3000.00, 63000.00]);

    // 5 — draft, GCC zero-rated export
    $invStmt->execute([5, 'DRAFT-5', 2, null, 'Continental Freight FZE', 'SA', null,
        'PO Box 88213, Jeddah Islamic Port, Jeddah, Saudi Arabia', 'GCC_VAT_zero_rated', null, 'AED',
        '2026-07-02', '2026-08-01', 45000.00, 0.00, 45000.00,
        '{"VAT":{"rate":0,"amount":0}}',
        'Cross-border advisory — confirm place-of-supply treatment before issuing.', 'draft', 1, null]);
    $itemStmt->execute([5, 'Cross-border advisory — zero-rated export', null, 1, 45000.00, 0, 45000.00, 0.00, 45000.00]);

    // 6 — void, India, domestic (demonstrates: number is retained, never reused)
    $invStmt->execute([6, 'LO-IN/2627/0003', 1, 5, 'Anand Constructions', 'IN', '33CCCCC2222C1Z9',
        '56 Anna Salai, Chennai, Tamil Nadu 600002', 'IN_GST_domestic', 'Tamil Nadu', 'INR',
        '2026-05-02', '2026-06-01', 100000.00, 18000.00, 118000.00,
        '{"CGST":{"rate":9,"amount":9000},"SGST":{"rate":9,"amount":9000}}',
        'Voided — reissued as part of final settlement invoice (not modelled here).', 'void', 1, '2026-05-02 11:00:00']);
    $itemStmt->execute([6, 'Arbitration proceedings — final billing', '9982', 1, 100000.00, 18, 100000.00, 18000.00, 118000.00]);

    // 7 — draft, no VAT (unregistered international desk)
    $invStmt->execute([7, 'DRAFT-7', 3, null, 'Northside Startup Advisory Co.', 'GB', null,
        '221 Baker Street, London NW1 6XE, United Kingdom', 'NO_VAT', null, 'USD',
        '2026-07-03', '2026-08-02', 25000.00, 0.00, 25000.00, '{}',
        'Pilot engagement — company incorporation advisory.', 'draft', 1, null]);
    $itemStmt->execute([7, 'Company incorporation advisory — pilot engagement', null, 1, 25000.00, 0, 25000.00, 0.00, 25000.00]);

    $pdo->exec('ALTER TABLE legalops_invoices AUTO_INCREMENT = 8');
    $pdo->exec('ALTER TABLE legalops_invoice_items AUTO_INCREMENT = 12');

    $seqStmt = $pdo->prepare('INSERT INTO legalops_invoice_number_sequences (billing_entity_id, period_key, last_number) VALUES (?,?,?)');
    $seqStmt->execute([1, '2627', 3]); // India entity, FY2026-27 — invoices 1, 2, 6
    $seqStmt->execute([2, '2026', 1]); // UAE entity, calendar year — invoice 4

} catch (Throwable $e) {
    fwrite(STDERR, "\nSeeding failed partway through: " . $e->getMessage() . "\n");
    fwrite(STDERR, "Just run this script again — it always clears its tables first, so a partial run is safe to retry.\n");
    exit(1);
}

echo "\nDone. Demo dataset loaded:\n";
echo "  2 users (1 admin, 1 member) - 9 clients (every entity type) - 10 matters\n";
echo "  9 tasks (every status) - 7 invoices (draft/issued/void across 3 billing entities)\n";
echo "  10 documents with real placeholder files on disk\n\n";
echo "Log in at /login with:\n";
echo "  demo@legalops.local       / LegalOps@123  (admin - sees everything)\n";
echo "  associate@legalops.local  / LegalOps@123  (member - sees only their own tasks)\n";
