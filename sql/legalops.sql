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
  `client_id` int DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `description` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
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

SET FOREIGN_KEY_CHECKS = 1;
