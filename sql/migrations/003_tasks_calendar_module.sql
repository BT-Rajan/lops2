-- =====================================================================
-- Migration 003 — Tasks & Calendar module
--
-- Adds: admin/member roles, court-hearing date on cases, hold status +
-- calendar-sync columns on tasks, an app settings table, and per-user
-- connected calendar accounts (Google / Microsoft).
--
-- Safe to run on top of an existing database — doesn't touch your
-- existing cases/tasks/clients/billing data.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `phpauth_users`
  ADD COLUMN IF NOT EXISTS `role` enum('admin','member') NOT NULL DEFAULT 'member';

-- Promote whoever the oldest account is to admin, so there's always at
-- least one admin after this migration runs. Change roles afterwards
-- from Settings → Team & roles.
UPDATE `phpauth_users` SET `role` = 'admin'
  WHERE `id` = (SELECT MIN(id) FROM (SELECT id FROM `phpauth_users`) AS x)
  AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM `phpauth_users` WHERE role = 'admin') AS y);

ALTER TABLE `legalops_cases`
  ADD COLUMN IF NOT EXISTS `next_hearing_date` date DEFAULT NULL AFTER `due_on`,
  ADD COLUMN IF NOT EXISTS `next_hearing_time` time DEFAULT NULL AFTER `next_hearing_date`;

ALTER TABLE `legalops_activity`
  ADD COLUMN IF NOT EXISTS `case_id` int DEFAULT NULL AFTER `client_id`,
  ADD KEY IF NOT EXISTS `case_id` (`case_id`);

ALTER TABLE `legalops_tasks`
  ADD COLUMN IF NOT EXISTS `notes` varchar(500) DEFAULT NULL AFTER `title`,
  ADD COLUMN IF NOT EXISTS `due_time` time DEFAULT NULL AFTER `due_on`,
  ADD COLUMN IF NOT EXISTS `hold_reason` varchar(255) DEFAULT NULL AFTER `status`,
  ADD COLUMN IF NOT EXISTS `source` enum('manual','hearing_cron','google_import','microsoft_import') NOT NULL DEFAULT 'manual',
  ADD COLUMN IF NOT EXISTS `google_event_id` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `microsoft_event_id` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD KEY IF NOT EXISTS `assigned_to` (`assigned_to`);

-- Widen the status enum to include 'hold' (re-running this is harmless).
ALTER TABLE `legalops_tasks`
  MODIFY COLUMN `status` enum('pending','in_progress','hold','done') NOT NULL DEFAULT 'pending';

CREATE TABLE IF NOT EXISTS `legalops_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `legalops_settings` (`setting_key`, `setting_value`) VALUES
('hearing_reminder_offset_days', '1'),
('google_client_id', ''),
('google_client_secret', ''),
('microsoft_client_id', ''),
('microsoft_client_secret', '')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

CREATE TABLE IF NOT EXISTS `legalops_calendar_accounts` (
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
