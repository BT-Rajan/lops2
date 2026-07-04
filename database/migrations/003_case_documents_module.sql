-- =====================================================================
-- Migration 003 — Documents module (case-linked document storage).
--
-- Already imported sql/legalops.sql or earlier migrations? Run THIS
-- file instead of re-importing the whole thing — it only adds what's
-- new and won't touch your existing cases/clients/tasks/activity data.
--
-- Mirrors the shape of legalops_client_documents (see migration 002)
-- but keyed to legalops_cases instead, with a doc_type vocabulary suited
-- to matter documents rather than KYC documents. Files are stored on
-- disk under uploads/cases/{case_id}/ — this table only holds metadata.
-- =====================================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `legalops_case_documents` (
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

-- legalops_activity already has a client_id column (migration 002).
-- Add a parallel case_id column so document/task activity tied to a
-- matter shows up filtered on case-view.php, the same way client_id
-- lets client-view.php filter the feed to one client.
ALTER TABLE `legalops_activity`
  ADD COLUMN IF NOT EXISTS `case_id` int DEFAULT NULL AFTER `client_id`,
  ADD KEY IF NOT EXISTS `case_id` (`case_id`);
