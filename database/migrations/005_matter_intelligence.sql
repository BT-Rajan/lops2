-- ---------------------------------------------------------------------
-- Matter intelligence: turns the case tracker into a proper Indian
-- litigation matter-management record. All columns are nullable so
-- non-litigation practice areas (Corporate, IP, Estate & Succession…)
-- are unaffected — the fields simply stay empty and hidden behind the
-- "Court & proceedings" disclosure panel in the UI.
-- ---------------------------------------------------------------------

ALTER TABLE `legalops_cases`
  ADD COLUMN `court_type`           varchar(60)  DEFAULT NULL AFTER `practice_area`,
  ADD COLUMN `court_name`           varchar(150) DEFAULT NULL AFTER `court_type`,
  ADD COLUMN `bench`                varchar(150) DEFAULT NULL AFTER `court_name`,
  ADD COLUMN `court_hall`           varchar(60)  DEFAULT NULL AFTER `bench`,
  ADD COLUMN `judge_name`           varchar(150) DEFAULT NULL AFTER `court_hall`,
  ADD COLUMN `jurisdiction`         varchar(150) DEFAULT NULL AFTER `judge_name`,
  ADD COLUMN `opposite_counsel`     varchar(150) DEFAULT NULL AFTER `jurisdiction`,
  ADD COLUMN `police_station`       varchar(150) DEFAULT NULL AFTER `opposite_counsel`,
  ADD COLUMN `fir_number`           varchar(60)  DEFAULT NULL AFTER `police_station`,
  ADD COLUMN `crime_number`         varchar(60)  DEFAULT NULL AFTER `fir_number`,
  ADD COLUMN `case_stage`           varchar(80)  DEFAULT NULL AFTER `crime_number`,
  ADD COLUMN `acts_involved`        text         DEFAULT NULL AFTER `case_stage`,
  ADD COLUMN `sections_involved`    text         DEFAULT NULL AFTER `acts_involved`,
  ADD COLUMN `prayer`               text         DEFAULT NULL AFTER `sections_involved`,
  ADD COLUMN `reliefs_sought`       text         DEFAULT NULL AFTER `prayer`,
  ADD COLUMN `limitation_date`      date         DEFAULT NULL AFTER `due_on`,
  ADD COLUMN `filing_date`          date         DEFAULT NULL AFTER `limitation_date`,
  ADD COLUMN `service_date`         date         DEFAULT NULL AFTER `filing_date`,
  ADD COLUMN `next_hearing_purpose` varchar(150) DEFAULT NULL AFTER `next_hearing_time`,
  ADD COLUMN `disposal_date`        date         DEFAULT NULL AFTER `next_hearing_purpose`,
  ADD COLUMN `result`               text         DEFAULT NULL AFTER `disposal_date`;

-- Connected matters and appeal chains. `link_type` = 'connected' is a
-- symmetric cross-reference (e.g. a batch of matters heard together);
-- 'appeal_of' is directional — case_id is an appeal arising FROM
-- linked_case_id, so the chain can be walked in either direction from
-- either matter's page.
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
