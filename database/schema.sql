-- =====================================================================
-- LegalOps — database schema
-- Import this whole file into a database named `lops2` (or your
-- own name — just update config/config.php to match).
-- Built for MySQL / MariaDB on XAMPP.
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'NO_AUTO_VALUE_ON_ZERO';

-- ---------------------------------------------------------------------
-- PHPAuth core tables
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `phpauth_config`;
CREATE TABLE `phpauth_config` (
  `setting` varchar(100) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  UNIQUE KEY `setting` (`setting`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NOTE: cookie_secure is 0 and uses_session is 1, because this is meant
-- to run on plain http://localhost/legalops under XAMPP. Native PHP
-- sessions sidestep the cookie-path-on-subfolder problems that show up
-- with bearer/cookie auth on Apache+Windows. Flip these once the app
-- is served over HTTPS on a real domain.
INSERT INTO `phpauth_config` (`setting`, `value`) VALUES
  ('attack_mitigation_time', '+30 minutes'),
  ('attempts_before_ban', '30'),
  ('attempts_before_verify', '5'),
  ('bcrypt_cost', '10'),
  ('cookie_domain', NULL),
  ('cookie_forget', '+30 minutes'),
  ('cookie_http', '1'),
  ('cookie_name', 'legalops_session'),
  ('cookie_path', '/'),
  ('cookie_remember', '+30 days'),
  ('cookie_samesite', 'Lax'),
  ('cookie_secure', '0'),
  ('cookie_renew', '+5 minutes'),
  ('allow_concurrent_sessions', '1'),
  ('emailmessage_suppress_activation', '1'),
  ('emailmessage_suppress_reset', '1'),
  ('site_activation_page', 'activate.php'),
  ('site_activation_page_append_code', '0'),
  ('site_email', 'no-reply@legalops.local'),
  ('site_key', 'CHANGE-THIS-RANDOM-STRING-BEFORE-GOING-LIVE'),
  ('site_name', 'LegalOps'),
  ('site_password_reset_page', 'reset-password.php'),
  ('site_password_reset_page_append_code', '1'),
  ('site_timezone', 'Asia/Kolkata'),
  ('site_url', 'http://localhost/legalops'),
  ('site_language', 'en_GB'),
  ('smtp', '0'),
  ('smtp_debug', '0'),
  ('smtp_auth', '1'),
  ('smtp_host', 'smtp.example.com'),
  ('smtp_password', ''),
  ('smtp_port', '587'),
  ('smtp_security', 'tls'),
  ('smtp_username', ''),
  ('table_attempts', 'phpauth_attempts'),
  ('table_requests', 'phpauth_requests'),
  ('table_sessions', 'phpauth_sessions'),
  ('table_users', 'phpauth_users'),
  ('table_emails_banned', 'phpauth_emails_banned'),
  ('table_translations', 'phpauth_translation_dictionary'),
  ('verify_email_max_length', '100'),
  ('verify_email_min_length', '5'),
  ('verify_password_min_length', '8'),
  ('verify_email_use_banlist', '0'),
  ('request_key_expiration', '+1 hour'),
  ('translation_source', 'php'),
  ('custom_datetime_format', 'd M Y, H:i'),
  ('uses_session', '1');

DROP TABLE IF EXISTS `phpauth_attempts`;
CREATE TABLE `phpauth_attempts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip` char(39) NOT NULL,
  `expiredate` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `phpauth_requests`;
CREATE TABLE `phpauth_requests` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `token` char(20) NOT NULL,
  `expire` datetime NOT NULL,
  `type` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `phpauth_sessions`;
CREATE TABLE `phpauth_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `hash` varchar(40) NOT NULL,
  `expiredate` datetime NOT NULL,
  `ip` varchar(39) NOT NULL,
  `device_id` varchar(36) DEFAULT NULL,
  `agent` varchar(200) NOT NULL,
  `cookie_crc` char(40) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `phpauth_users`;
CREATE TABLE `phpauth_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `isactive` smallint NOT NULL DEFAULT '0',
  `dt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  -- LegalOps profile fields (PHPAuth's addUser()/updateUser() write straight
  -- into named columns on this table, so they live here rather than a join)
  `full_name` varchar(150) DEFAULT NULL,
  `job_title` varchar(100) DEFAULT NULL,
  `avatar_color` varchar(7) DEFAULT '#3B6FE0',
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `phpauth_emails_banned`;
CREATE TABLE `phpauth_emails_banned` (
  `id` int NOT NULL AUTO_INCREMENT,
  `domain` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- LegalOps application tables
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `legalops_cases`;
CREATE TABLE `legalops_cases` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_number` varchar(30) NOT NULL,
  `title` varchar(200) NOT NULL,
  `client_name` varchar(150) NOT NULL,
  `client_id` int DEFAULT NULL COMMENT 'Links to legalops_clients when the client has a formal record. Nullable — ad-hoc/prospective matters can still just carry a free-text client_name.',
  `practice_area` varchar(100) DEFAULT NULL,
  `court_type` varchar(60) DEFAULT NULL COMMENT 'Supreme Court, High Court, District Court, Tribunal, etc.',
  `court_name` varchar(150) DEFAULT NULL,
  `bench` varchar(150) DEFAULT NULL,
  `court_hall` varchar(60) DEFAULT NULL,
  `judge_name` varchar(150) DEFAULT NULL,
  `jurisdiction` varchar(150) DEFAULT NULL,
  `opposite_counsel` varchar(150) DEFAULT NULL,
  `police_station` varchar(150) DEFAULT NULL COMMENT 'Criminal matters only',
  `fir_number` varchar(60) DEFAULT NULL,
  `crime_number` varchar(60) DEFAULT NULL,
  `case_stage` varchar(80) DEFAULT NULL COMMENT 'e.g. Admission, Evidence, Arguments, Judgment Reserved, Disposed',
  `acts_involved` text COMMENT 'e.g. Indian Penal Code, Negotiable Instruments Act',
  `sections_involved` text COMMENT 'e.g. Sec 420, Sec 138',
  `prayer` text,
  `reliefs_sought` text,
  `status` enum('open','pending','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `opened_on` date DEFAULT NULL,
  `due_on` date DEFAULT NULL,
  `limitation_date` date DEFAULT NULL,
  `filing_date` date DEFAULT NULL,
  `service_date` date DEFAULT NULL,
  `next_hearing_date` date DEFAULT NULL,
  `next_hearing_time` time DEFAULT NULL,
  `next_hearing_purpose` varchar(150) DEFAULT NULL COMMENT 'e.g. For arguments, For evidence',
  `disposal_date` date DEFAULT NULL,
  `result` text COMMENT 'e.g. Allowed, Dismissed, Partly allowed, Settled',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `case_number` (`case_number`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Connected matters and appeal chains. 'connected' is a symmetric
-- cross-reference; 'appeal_of' is directional (case_id is an appeal
-- arising FROM linked_case_id).
DROP TABLE IF EXISTS `legalops_case_links`;
CREATE TABLE `legalops_case_links` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` int NOT NULL,
  `linked_case_id` int NOT NULL,
  `link_type` enum('connected','appeal_of') NOT NULL DEFAULT 'connected',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `case_link_unique` (`case_id`, `linked_case_id`, `link_type`),
  KEY `case_id` (`case_id`),
  KEY `linked_case_id` (`linked_case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `legalops_tasks`;
CREATE TABLE `legalops_tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` int DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `due_on` date DEFAULT NULL,
  `due_time` time DEFAULT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','hold','done') NOT NULL DEFAULT 'pending',
  `hold_reason` varchar(255) DEFAULT NULL,
  `assigned_to` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `source` enum('manual','hearing_cron','google_import','microsoft_import') NOT NULL DEFAULT 'manual',
  `google_event_id` varchar(255) DEFAULT NULL,
  `microsoft_event_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `case_id` (`case_id`),
  KEY `assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- App-wide settings: hearing reminder offset, Google/Microsoft OAuth app
-- credentials (so an admin can configure these from the UI, no redeploy).
DROP TABLE IF EXISTS `legalops_settings`;
CREATE TABLE `legalops_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `legalops_settings` (`setting_key`, `setting_value`) VALUES
('hearing_reminder_offset_days', '1'),
('google_client_id', ''),
('google_client_secret', ''),
('microsoft_client_id', ''),
('microsoft_client_secret', '');

-- Per-user connected calendar (Google or Microsoft). A user can connect
-- one of each; sync.php / cron/calendar_sync.php act on whichever rows
-- have is_active = 1.
DROP TABLE IF EXISTS `legalops_calendar_accounts`;
CREATE TABLE `legalops_calendar_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int NOT NULL,
  `provider` enum('google','microsoft') NOT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `calendar_id` varchar(255) NOT NULL DEFAULT 'primary',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_synced_at` datetime DEFAULT NULL,
  `connected_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uid_provider` (`uid`, `provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `legalops_activity`;
CREATE TABLE `legalops_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int DEFAULT NULL,
  `client_id` int DEFAULT NULL,
  `case_id` int DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `case_id` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Clients module: onboarding, KYC, leadership (with change history),
-- secondary contacts, and uploaded documents.
-- ---------------------------------------------------------------------

DROP TABLE IF EXISTS `legalops_clients`;
CREATE TABLE `legalops_clients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` enum('individual','family','proprietorship','partnership','opc','private_limited','public_limited','association','trust') NOT NULL DEFAULT 'individual',
  `display_name` varchar(200) NOT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `registration_number` varchar(60) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address_line1` varchar(200) DEFAULT NULL,
  `address_line2` varchar(200) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `pincode` varchar(12) DEFAULT NULL,
  `country` varchar(100) NOT NULL DEFAULT 'India',
  `onboarding_status` enum('draft','kyc_pending','kyc_verified','active','inactive') NOT NULL DEFAULT 'draft',
  `kyc_status` enum('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  `notes` text,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Leadership / KYC persons tied to a client — directors, partners,
-- trustees, the proprietor, the karta, the individual themselves, etc.
-- effective_to + status='removed' is how "change leadership" is tracked:
-- the old record is closed out rather than overwritten, so there's an
-- audit trail of who led the client and when.
DROP TABLE IF EXISTS `legalops_client_leadership`;
CREATE TABLE `legalops_client_leadership` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `role` varchar(60) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `pan` varchar(10) DEFAULT NULL,
  `id_proof_type` varchar(40) DEFAULT NULL,
  `id_proof_number` varchar(60) DEFAULT NULL,
  `din_or_membership_no` varchar(40) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `kyc_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('active','removed') NOT NULL DEFAULT 'active',
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Secondary contacts: people at the client who aren't leadership/KYC
-- subjects but who the firm deals with day to day (accountant, ops POC…)
DROP TABLE IF EXISTS `legalops_client_contacts`;
CREATE TABLE `legalops_client_contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Uploaded KYC / onboarding documents. Files live on disk under
-- uploads/clients/{client_id}/ — this table only stores metadata.
-- leadership_id is set when a document belongs to a specific leader's
-- KYC (e.g. a director's PAN scan) rather than the client as a whole.
DROP TABLE IF EXISTS `legalops_client_documents`;
CREATE TABLE `legalops_client_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `client_id` int NOT NULL,
  `leadership_id` int DEFAULT NULL,
  `doc_type` varchar(80) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `leadership_id` (`leadership_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Documents module: files attached to a matter. Files live on disk
-- under uploads/cases/{case_id}/ — this table only stores metadata.
-- Parallel in shape to legalops_client_documents above, but doc_type
-- uses a matter-document vocabulary (see case_doc_types() in
-- includes/case_types.php) rather than a KYC one.
DROP TABLE IF EXISTS `legalops_case_documents`;
CREATE TABLE `legalops_case_documents` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` int NOT NULL,
  `doc_type` varchar(80) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` int DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `uploaded_by` int DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `case_id` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `legalops_billing_entities`;
CREATE TABLE `legalops_billing_entities` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `country` char(2) NOT NULL COMMENT 'ISO 3166-1 alpha-2: IN, AE, SA, BH, OM, KW, QA…',
  `entity_type` enum('IN_GST','GCC_VAT','NO_VAT') NOT NULL DEFAULT 'NO_VAT',
  `tax_reg_no` varchar(30) DEFAULT NULL COMMENT 'GSTIN for IN_GST, TRN for GCC_VAT',
  `state_or_emirate` varchar(100) DEFAULT NULL COMMENT 'Used to decide intra-state vs inter-state GST for IN_GST entities',
  `address` text,
  `default_currency` char(3) NOT NULL DEFAULT 'INR',
  `invoice_prefix` varchar(20) NOT NULL DEFAULT 'INV',
  `bank_details` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `legalops_invoice_number_sequences`;
CREATE TABLE `legalops_invoice_number_sequences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `billing_entity_id` int NOT NULL,
  `period_key` varchar(20) NOT NULL COMMENT 'e.g. 2526 for Indian FY2025-26, or 2026 for a calendar year',
  `last_number` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `entity_period` (`billing_entity_id`, `period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `legalops_invoices`;
CREATE TABLE `legalops_invoices` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(40) NOT NULL,
  `billing_entity_id` int NOT NULL,
  `case_id` int DEFAULT NULL,
  `client_name` varchar(150) NOT NULL,
  `client_country` char(2) DEFAULT NULL,
  `client_tax_reg_no` varchar(30) DEFAULT NULL COMMENT 'Client GSTIN / TRN, when known',
  `client_address` text,
  `tax_profile_key` varchar(30) NOT NULL COMMENT 'Key into config/tax_profiles.php at time of issue',
  `place_of_supply` varchar(100) DEFAULT NULL COMMENT 'State (India) or Emirate/country (GCC) — required for GST/VAT invoices',
  `currency` char(3) NOT NULL DEFAULT 'INR',
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_breakdown` text COMMENT 'JSON: {"CGST":{"rate":9,"amount":180}, ...}',
  `notes` text,
  `status` enum('draft','issued','void') NOT NULL DEFAULT 'draft',
  `compliance_status` enum('not_required','pending','cleared') NOT NULL DEFAULT 'not_required' COMMENT 'Reserved for IRN (India) / ZATCA clearance (KSA) once wired up',
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `issued_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `billing_entity_id` (`billing_entity_id`),
  KEY `case_id` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `legalops_invoice_items`;
CREATE TABLE `legalops_invoice_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `description` varchar(255) NOT NULL,
  `hsn_sac` varchar(15) DEFAULT NULL COMMENT 'India only — HSN/SAC code per line item',
  `quantity` decimal(10,2) NOT NULL DEFAULT '1.00',
  `unit_price` decimal(14,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT '0.00',
  `line_subtotal` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_tax` decimal(14,2) NOT NULL DEFAULT '0.00',
  `line_total` decimal(14,2) NOT NULL DEFAULT '0.00',
  `sort_order` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- Demo data — lets you log in immediately without registering first.
-- Email:    demo@legalops.local
-- Password: LegalOps@123
-- ---------------------------------------------------------------------

INSERT INTO `phpauth_users` (`email`, `password`, `isactive`, `full_name`, `job_title`, `avatar_color`, `role`) VALUES
('demo@legalops.local', '$2b$10$K0Apvp0t19nqq2bBUtbs/.EbxuJDXMzywvt.mUSIDYbAlG5fMVs5S', 1, 'Aishwarya Krishnan', 'Managing Partner', '#3B6FE0', 'admin');

INSERT INTO `legalops_cases` (`case_number`, `title`, `client_name`, `practice_area`, `status`, `priority`, `opened_on`, `due_on`, `next_hearing_date`, `created_by`) VALUES
('LO-2026-014', 'Sundaram Textiles — Commercial Lease Renewal', 'Sundaram Textiles Pvt Ltd', 'Real Estate', 'open', 'high', '2026-04-02', '2026-07-10', NULL, 1),
('LO-2026-021', 'Krishnan vs. Coastal Logistics', 'R. Krishnan', 'Civil Litigation', 'open', 'medium', '2026-05-11', '2026-08-01', '2026-07-08', 1),
('LO-2026-009', 'Velan Foods — Trademark Opposition', 'Velan Foods Ltd', 'Intellectual Property', 'pending', 'high', '2026-03-18', '2026-07-05', '2026-07-15', 1),
('LO-2026-027', 'Estate of M. Subramaniam — Probate', 'Subramaniam Family', 'Estate & Succession', 'open', 'low', '2026-06-02', '2026-09-15', NULL, 1),
('LO-2026-002', 'Anand Constructions — Arbitration', 'Anand Constructions', 'Arbitration', 'closed', 'medium', '2026-01-09', '2026-04-30', NULL, 1),
('LO-2026-033', 'Meridian Capital — Share Purchase Agreement', 'Meridian Capital Partners', 'Corporate', 'open', 'high', '2026-06-15', '2026-07-20', NULL, 1);

INSERT INTO `legalops_tasks` (`case_id`, `title`, `due_on`, `priority`, `status`, `assigned_to`, `created_by`, `source`) VALUES
(1, 'Review revised lease clauses with client', '2026-07-02', 'high', 'in_progress', 1, 1, 'manual'),
(2, 'File rejoinder with district court', '2026-07-04', 'high', 'pending', 1, 1, 'manual'),
(3, 'Respond to TM opposition board notice', '2026-07-06', 'medium', 'pending', 1, 1, 'manual'),
(6, 'Draft SPA disclosure schedules', '2026-07-01', 'high', 'in_progress', 1, 1, 'manual'),
(4, 'Collect succession certificates from family', '2026-07-12', 'low', 'pending', 1, 1, 'manual'),
(5, 'Close out arbitration billing file', '2026-06-30', 'medium', 'done', 1, 1, 'manual');

INSERT INTO `legalops_activity` (`uid`, `action`, `description`) VALUES
(1, 'case_created', 'Opened case LO-2026-033 — Meridian Capital — Share Purchase Agreement'),
(1, 'task_completed', 'Marked "Close out arbitration billing file" as done'),
(1, 'case_status', 'Moved LO-2026-002 — Anand Constructions — Arbitration to closed'),
(1, 'task_created', 'Added task "Draft SPA disclosure schedules"'),
(1, 'login', 'Signed in to LegalOps');

INSERT INTO `legalops_clients`
  (`id`, `entity_type`, `display_name`, `pan`, `registration_number`, `email`, `phone`,
   `address_line1`, `address_line2`, `city`, `state`, `pincode`, `onboarding_status`, `kyc_status`, `created_by`) VALUES
(1, 'private_limited', 'Sundaram Textiles Pvt Ltd', 'AABCS1234D', 'U17110TN2014PTC098765', 'contact@sundaramtextiles.in', '+91 44 2345 6789',
  'Plot 12, SIDCO Industrial Estate', 'Guindy', 'Chennai', 'Tamil Nadu', '600032', 'active', 'verified', 1),
(2, 'individual', 'R. Krishnan', 'BFKPK4567L', NULL, 'r.krishnan@example.com', '+91 98400 12345',
  '14 Lake View Road', 'Nungambakkam', 'Chennai', 'Tamil Nadu', '600034', 'active', 'verified', 1),
(3, 'private_limited', 'Velan Foods Ltd', 'AAFCV6789K', 'U15400TN2011PLC076543', 'legal@velanfoods.in', '+91 422 234 5678',
  'No. 8, Avinashi Road', NULL, 'Coimbatore', 'Tamil Nadu', '641018', 'kyc_verified', 'verified', 1),
(4, 'family', 'Subramaniam Family (HUF)', 'AAHHS3456M', NULL, 'subramaniam.family@example.com', '+91 98410 65432',
  '21 Temple Street', 'Mylapore', 'Chennai', 'Tamil Nadu', '600004', 'kyc_pending', 'pending', 1),
(5, 'partnership', 'Anand Constructions', 'AAJFA2345N', 'TN/ROF/2009/4521', 'info@anandconstructions.in', '+91 44 4567 8901',
  '56 Anna Salai', NULL, 'Chennai', 'Tamil Nadu', '600002', 'active', 'verified', 1),
(6, 'trust', 'Meridian Capital Charitable Trust', 'AAATM7890P', 'TN/TRUST/2018/1123', 'trustoffice@meridiancapital.in', '+91 44 6789 0123',
  '101 Apex Towers, OMR', NULL, 'Chennai', 'Tamil Nadu', '600096', 'draft', 'pending', 1);

-- Link the seeded matters to their matching client records. Two are left
-- unlinked on purpose: "Subramaniam Family" (case) vs "Subramaniam Family
-- (HUF)" (client) is a near-miss a person should confirm by hand, and
-- "Meridian Capital Partners" (case) is a different entity entirely from
-- "Meridian Capital Charitable Trust" (client) despite the similar name.
UPDATE `legalops_cases` c JOIN `legalops_clients` cl ON cl.display_name = c.client_name SET c.client_id = cl.id;

INSERT INTO `legalops_client_leadership`
  (`client_id`, `role`, `full_name`, `pan`, `id_proof_type`, `id_proof_number`, `din_or_membership_no`, `email`, `phone`, `kyc_verified`, `status`, `effective_from`, `effective_to`) VALUES
(1, 'Managing Director', 'Aishwarya Krishnan', 'AABCS1111D', 'Aadhaar', 'XXXX-XXXX-4521', 'DIN08123456', 'aishwarya@sundaramtextiles.in', '+91 98400 11111', 1, 'active', '2014-08-01', NULL),
(1, 'Director', 'Karthik Sundaram', 'AABCS2222E', 'Passport', 'P1234567', 'DIN08234567', 'karthik@sundaramtextiles.in', '+91 98400 22222', 1, 'active', '2014-08-01', NULL),
(1, 'Director', 'Geetha Ramaswamy', 'AABCS3333F', 'Aadhaar', 'XXXX-XXXX-7788', 'DIN08001122', 'geetha@sundaramtextiles.in', NULL, 1, 'removed', '2014-08-01', '2025-11-30'),
(2, 'Individual', 'R. Krishnan', 'BFKPK4567L', 'Aadhaar', 'XXXX-XXXX-9012', NULL, 'r.krishnan@example.com', '+91 98400 12345', 1, 'active', '2020-01-01', NULL),
(3, 'Director', 'Velan Murugesan', 'AAFCV1111K', 'Aadhaar', 'XXXX-XXXX-3344', 'DIN07112233', 'velan@velanfoods.in', '+91 422 200 1111', 1, 'active', '2011-05-01', NULL),
(4, 'Karta', 'M. Subramaniam', 'AAHHS3456M', 'Aadhaar', 'XXXX-XXXX-5566', NULL, 'subramaniam.family@example.com', '+91 98410 65432', 0, 'active', '2015-01-01', NULL),
(5, 'Managing Partner', 'Anand Vellaichamy', 'AAJFA1111N', 'Aadhaar', 'XXXX-XXXX-1212', NULL, 'anand@anandconstructions.in', '+91 44 4567 1111', 1, 'active', '2009-03-01', NULL),
(5, 'Partner', 'Suresh Babu', 'AAJFA2222P', 'Voter ID', 'TN/AB1234567', NULL, 'suresh@anandconstructions.in', '+91 44 4567 2222', 1, 'active', '2009-03-01', NULL);

INSERT INTO `legalops_client_contacts` (`client_id`, `full_name`, `designation`, `email`, `phone`, `notes`) VALUES
(1, 'Priya Natarajan', 'Company Secretary', 'priya.cs@sundaramtextiles.in', '+91 98400 33333', 'Primary point of contact for filings'),
(1, 'Mohan Raj', 'Finance Manager', 'mohan@sundaramtextiles.in', '+91 98400 44444', NULL),
(3, 'Lakshmi Iyer', 'Legal Counsel (in-house)', 'lakshmi@velanfoods.in', '+91 422 200 2222', 'Coordinates on IP matters'),
(5, 'Divya Anand', 'Site Office Coordinator', 'divya@anandconstructions.in', '+91 44 4567 3333', NULL);

INSERT INTO `legalops_billing_entities` (`name`, `country`, `entity_type`, `tax_reg_no`, `state_or_emirate`, `address`, `default_currency`, `invoice_prefix`, `bank_details`) VALUES
('LegalOps India Pvt Ltd', 'IN', 'IN_GST', '33AAAAA0000A1Z5', 'Tamil Nadu', 'No. 12, Anna Salai, Chennai, Tamil Nadu 600002, India', 'INR', 'LO-IN', 'Bank: HDFC Bank · A/C: 50100123456789 · IFSC: HDFC0000123'),
('LegalOps DMCC', 'AE', 'GCC_VAT', '100123456700003', 'Dubai', 'Unit 14, Jewellery & Gemplex 3, DMCC, Dubai, UAE', 'AED', 'LO-AE', 'Bank: Emirates NBD · IBAN: AE070260001234567890123 · SWIFT: EBILAEAD');

INSERT INTO `legalops_invoice_number_sequences` (`billing_entity_id`, `period_key`, `last_number`) VALUES
(1, '2627', 1);

INSERT INTO `legalops_invoices`
  (`invoice_no`, `billing_entity_id`, `case_id`, `client_name`, `client_country`, `client_tax_reg_no`, `client_address`, `tax_profile_key`, `place_of_supply`, `currency`, `invoice_date`, `due_date`, `subtotal`, `tax_total`, `grand_total`, `tax_breakdown`, `notes`, `status`, `created_by`, `issued_at`)
VALUES
  ('LO-IN/2627/0001', 1, 1, 'Sundaram Textiles Pvt Ltd', 'IN', '33BBBBB1111B1Z2', 'Plot 45, Guindy Industrial Estate, Chennai, Tamil Nadu 600032', 'IN_GST_domestic', 'Tamil Nadu', 'INR', '2026-06-15', '2026-07-15', 75000.00, 13500.00, 88500.00,
   '{"CGST":{"rate":9,"amount":6750},"SGST":{"rate":9,"amount":6750}}',
   'Professional fees for lease renewal advisory — June 2026.', 'issued', 1, '2026-06-15 10:00:00');

INSERT INTO `legalops_invoice_items` (`invoice_id`, `description`, `hsn_sac`, `quantity`, `unit_price`, `tax_rate`, `line_subtotal`, `line_tax`, `line_total`, `sort_order`) VALUES
(1, 'Legal advisory — commercial lease renewal review', '9982', 1, 50000.00, 18, 50000.00, 9000.00, 59000.00, 0),
(1, 'Drafting amended lease deed', '9982', 1, 25000.00, 18, 25000.00, 4500.00, 29500.00, 1);

SET FOREIGN_KEY_CHECKS = 1;
