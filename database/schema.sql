-- =====================================================================
-- LegalOps — database schema (structure only, no data)
--
-- This file creates every table LegalOps needs and nothing else — no
-- demo user, no sample cases/clients/invoices. After importing this,
-- register your first account at /register and you have a genuinely
-- empty, production-shaped database.
--
-- Recommended: run `php database/install.php` instead of importing
-- this by hand — it creates the database if missing and runs this file
-- for you. Prefer raw SQL? `mysql -u root lops2 < database/schema.sql`
-- works the same as it always has.
--
-- Want realistic data to click around and test every status/condition
-- with? Run `php database/seed_demo.php` after this.
--
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
  `amount_paid` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'Running total received against this invoice — not a full payments ledger, just enough to know the outstanding balance for AR/aging reporting',
  `paid_at` datetime DEFAULT NULL COMMENT 'When the invoice was last recorded as paid (partially or in full)',
  `payment_reference` varchar(150) DEFAULT NULL COMMENT 'Free text: cheque/UTR/transaction reference for the most recent payment',
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

SET FOREIGN_KEY_CHECKS = 1;
