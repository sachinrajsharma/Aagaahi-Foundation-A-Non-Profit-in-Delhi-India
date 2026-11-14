-- ============================================================================
-- 002: public forms (volunteer sign-up, newsletter, donations)
--   form_attempts : one row per public form submission, used only to limit spam per visitor (IP stored as a keyed hash)
--   settings      : new editable details for donations and receipts
-- The volunteer_applications, newsletter_subscribers and donations tables already exist (001_initial.sql).
-- Safe to run more than once.
-- ============================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS form_attempts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  form       VARCHAR(30) NOT NULL,
  ip_hash    CHAR(64)    NOT NULL,
  created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_attempt (form, ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
  ('upi_id', ''),
  ('bank_details', ''),
  ('receipt_prefix', 'AAG'),
  ('receipt_footer', ''),
  ('donation_note', '');
