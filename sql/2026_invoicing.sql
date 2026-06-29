-- =====================================================================
-- LegalOps — invoicing engine migration.
--
-- Run this ONLY if you already have an existing `legalops` database
-- (i.e. you imported sql/legalops.sql before this feature existed).
-- It's safe to re-run — every statement is idempotent.
--
-- Fresh installs don't need this file: sql/legalops.sql already
-- includes these tables.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `legalops_billing_entities` (
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

CREATE TABLE IF NOT EXISTS `legalops_invoice_number_sequences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `billing_entity_id` int NOT NULL,
  `period_key` varchar(20) NOT NULL COMMENT 'e.g. 2526 for Indian FY2025-26, or 2026 for a calendar year',
  `last_number` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `entity_period` (`billing_entity_id`, `period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `legalops_invoices` (
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

CREATE TABLE IF NOT EXISTS `legalops_invoice_items` (
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

-- Seed at least one billing entity so the Billing screen isn't empty on
-- first run. Edit these values (or add more) from Billing → Entities.
INSERT INTO `legalops_billing_entities` (`name`, `country`, `entity_type`, `tax_reg_no`, `state_or_emirate`, `address`, `default_currency`, `invoice_prefix`)
SELECT 'Your Firm — India', 'IN', 'IN_GST', 'PASTE-GSTIN-HERE', 'Tamil Nadu', 'Firm address', 'INR', 'LO-IN'
WHERE NOT EXISTS (SELECT 1 FROM `legalops_billing_entities`);

SET FOREIGN_KEY_CHECKS = 1;
