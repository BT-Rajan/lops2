-- =====================================================================
-- Migration 008 — Invoice payment tracking.
--
-- legalops_invoices only ever had draft/issued/void — no way to record
-- that an issued invoice had actually been paid. Without this, AR
-- aging and "outstanding" reporting can't mean anything (every issued
-- invoice would look permanently unpaid forever). This is a running
-- amount_paid total, not a full payments ledger — enough to know the
-- outstanding balance and whether something's overdue, not a full
-- payment-by-payment history.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE `legalops_invoices`
  ADD COLUMN IF NOT EXISTS `amount_paid` decimal(14,2) NOT NULL DEFAULT '0.00' AFTER `status`,
  ADD COLUMN IF NOT EXISTS `paid_at` datetime DEFAULT NULL AFTER `amount_paid`,
  ADD COLUMN IF NOT EXISTS `payment_reference` varchar(150) DEFAULT NULL AFTER `paid_at`;
