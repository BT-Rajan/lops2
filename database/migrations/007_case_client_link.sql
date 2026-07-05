-- =====================================================================
-- Migration 007 — Link matters to client records.
--
-- legalops_cases has always stored the client as a free-text
-- client_name, completely disconnected from legalops_clients (KYC,
-- leadership, contacts, onboarding status). This adds a nullable
-- client_id so a matter CAN be linked to a formal client record —
-- ad-hoc/prospective matters can still just carry client_name with no
-- link, same as before.
--
-- The backfill only links on an EXACT client_name / display_name
-- match, and only skips ambiguous cases — it deliberately does not try
-- fuzzy matching, since silently linking a matter to the wrong client
-- would be worse than leaving it for a human to link from the matter's
-- edit form.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `legalops_cases`
  ADD COLUMN IF NOT EXISTS `client_id` int DEFAULT NULL AFTER `client_name`,
  ADD KEY IF NOT EXISTS `client_id` (`client_id`);

UPDATE `legalops_cases` c
  JOIN `legalops_clients` cl ON cl.display_name = c.client_name
  SET c.client_id = cl.id
  WHERE c.client_id IS NULL;
