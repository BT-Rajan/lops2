-- =====================================================================
-- LegalOps — database schema
-- Import this whole file into a database named `legalops` (or your
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
  `practice_area` varchar(100) DEFAULT NULL,
  `status` enum('open','pending','closed') NOT NULL DEFAULT 'open',
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `opened_on` date DEFAULT NULL,
  `due_on` date DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `case_number` (`case_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `legalops_tasks`;
CREATE TABLE `legalops_tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `case_id` int DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `due_on` date DEFAULT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `status` enum('pending','in_progress','done') NOT NULL DEFAULT 'pending',
  `assigned_to` int DEFAULT NULL,
  `created_by` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `case_id` (`case_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `legalops_activity`;
CREATE TABLE `legalops_activity` (
  `id` int NOT NULL AUTO_INCREMENT,
  `uid` int DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
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

INSERT INTO `phpauth_users` (`email`, `password`, `isactive`, `full_name`, `job_title`, `avatar_color`) VALUES
('demo@legalops.local', '$2b$10$K0Apvp0t19nqq2bBUtbs/.EbxuJDXMzywvt.mUSIDYbAlG5fMVs5S', 1, 'Aishwarya Krishnan', 'Managing Partner', '#3B6FE0');

INSERT INTO `legalops_cases` (`case_number`, `title`, `client_name`, `practice_area`, `status`, `priority`, `opened_on`, `due_on`, `created_by`) VALUES
('LO-2026-014', 'Sundaram Textiles — Commercial Lease Renewal', 'Sundaram Textiles Pvt Ltd', 'Real Estate', 'open', 'high', '2026-04-02', '2026-07-10', 1),
('LO-2026-021', 'Krishnan vs. Coastal Logistics', 'R. Krishnan', 'Civil Litigation', 'open', 'medium', '2026-05-11', '2026-08-01', 1),
('LO-2026-009', 'Velan Foods — Trademark Opposition', 'Velan Foods Ltd', 'Intellectual Property', 'pending', 'high', '2026-03-18', '2026-07-05', 1),
('LO-2026-027', 'Estate of M. Subramaniam — Probate', 'Subramaniam Family', 'Estate & Succession', 'open', 'low', '2026-06-02', '2026-09-15', 1),
('LO-2026-002', 'Anand Constructions — Arbitration', 'Anand Constructions', 'Arbitration', 'closed', 'medium', '2026-01-09', '2026-04-30', 1),
('LO-2026-033', 'Meridian Capital — Share Purchase Agreement', 'Meridian Capital Partners', 'Corporate', 'open', 'high', '2026-06-15', '2026-07-20', 1);

INSERT INTO `legalops_tasks` (`case_id`, `title`, `due_on`, `priority`, `status`, `assigned_to`, `created_by`) VALUES
(1, 'Review revised lease clauses with client', '2026-07-02', 'high', 'in_progress', 1, 1),
(2, 'File rejoinder with district court', '2026-07-04', 'high', 'pending', 1, 1),
(3, 'Respond to TM opposition board notice', '2026-07-06', 'medium', 'pending', 1, 1),
(6, 'Draft SPA disclosure schedules', '2026-07-01', 'high', 'in_progress', 1, 1),
(4, 'Collect succession certificates from family', '2026-07-12', 'low', 'pending', 1, 1),
(5, 'Close out arbitration billing file', '2026-06-30', 'medium', 'done', 1, 1);

INSERT INTO `legalops_activity` (`uid`, `action`, `description`) VALUES
(1, 'case_created', 'Opened case LO-2026-033 — Meridian Capital — Share Purchase Agreement'),
(1, 'task_completed', 'Marked "Close out arbitration billing file" as done'),
(1, 'case_status', 'Moved LO-2026-002 — Anand Constructions — Arbitration to closed'),
(1, 'task_created', 'Added task "Draft SPA disclosure schedules"'),
(1, 'login', 'Signed in to LegalOps');

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
