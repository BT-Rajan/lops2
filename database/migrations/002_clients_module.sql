-- =====================================================================
-- Migration 002 — Clients module (onboarding, KYC, leadership, contacts,
-- documents).
--
-- Already imported sql/legalops.sql before? Run THIS file instead of
-- re-importing the whole thing — it only adds what's new and won't
-- touch your existing cases/tasks/activity data.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `legalops_activity`
  ADD COLUMN IF NOT EXISTS `client_id` int DEFAULT NULL AFTER `uid`,
  ADD KEY IF NOT EXISTS `client_id` (`client_id`);

CREATE TABLE IF NOT EXISTS `legalops_clients` (
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

CREATE TABLE IF NOT EXISTS `legalops_client_leadership` (
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

CREATE TABLE IF NOT EXISTS `legalops_client_contacts` (
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

CREATE TABLE IF NOT EXISTS `legalops_client_documents` (
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

-- Optional: a couple of demo clients so the module isn't empty on first
-- look. Safe to skip/delete this block if you'd rather start clean.
INSERT INTO `legalops_clients`
  (`entity_type`, `display_name`, `pan`, `registration_number`, `email`, `phone`,
   `address_line1`, `city`, `state`, `pincode`, `onboarding_status`, `kyc_status`, `created_by`) VALUES
('private_limited', 'Sundaram Textiles Pvt Ltd', 'AABCS1234D', 'U17110TN2014PTC098765', 'contact@sundaramtextiles.in', '+91 44 2345 6789',
  'Plot 12, SIDCO Industrial Estate', 'Chennai', 'Tamil Nadu', '600032', 'active', 'verified', 1),
('individual', 'R. Krishnan', 'BFKPK4567L', NULL, 'r.krishnan@example.com', '+91 98400 12345',
  '14 Lake View Road', 'Chennai', 'Tamil Nadu', '600034', 'active', 'verified', 1);
