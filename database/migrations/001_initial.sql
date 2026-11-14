-- ============================================================================
-- Aagahi Foundation website: initial database schema (MySQL 5.7+/8 and MariaDB 10.3+)
--
-- Safe to run more than once (every statement is IF NOT EXISTS). You can import this file in phpMyAdmin
-- (Hostinger hPanel > Databases > phpMyAdmin > select your database > Import) or let install/ run it.
--
-- Groups:  1 staff + security   2 settings   3 blog   4 media + gallery   5 people who contact us
--          6 donations   7 audit trail
-- Personal data: messages, volunteers, subscribers and donations hold names/emails. Keep them only as long as needed,
-- and never store ID documents, card numbers or full PAN here.
-- ============================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 0. Bookkeeping
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS schema_migrations (
  name       VARCHAR(190) NOT NULL PRIMARY KEY,
  applied_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 1. Staff accounts (the people who log in to the dashboard) and sign-in security
-- ---------------------------------------------------------------------------
-- Roles: super_admin = everything (staff, settings, danger zone); admin = staff (not super admins), content,
--        messages, donations; editor = posts, gallery, media, messages.
CREATE TABLE IF NOT EXISTS staff_users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL COMMENT 'PHP password_hash() output (bcrypt/argon2), never plain text',
  role          ENUM('super_admin','admin','editor') NOT NULL DEFAULT 'editor',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login_at DATETIME     NULL,
  failed_logins SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  locked_until  DATETIME     NULL COMMENT 'temporary lock after repeated wrong passwords',
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_staff_email (email),
  KEY idx_staff_role (role, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per sign-in attempt; used to slow down password guessing. Old rows can be deleted after a few days.
CREATE TABLE IF NOT EXISTS login_attempts (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email      VARCHAR(190) NOT NULL,
  ip_hash    CHAR(64)     NOT NULL COMMENT 'HMAC of the visitor IP, not the IP itself',
  success    TINYINT(1)   NOT NULL DEFAULT 0,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_ip (ip_hash, created_at),
  KEY idx_login_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Site settings (editable from the dashboard instead of includes/config.php)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value TEXT         NULL,
  updated_by    INT UNSIGNED NULL,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_settings_staff FOREIGN KEY (updated_by) REFERENCES staff_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. Blog
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(140) NOT NULL,
  slug       VARCHAR(160) NOT NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  UNIQUE KEY uq_category_slug (slug),
  UNIQUE KEY uq_category_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(190) NOT NULL COMMENT 'the address: /blog/<slug>/',
  title         VARCHAR(220) NOT NULL,
  excerpt       VARCHAR(400) NULL,
  content       MEDIUMTEXT   NOT NULL COMMENT 'sanitised HTML body (FAQ questions included)',
  cover_image   VARCHAR(400) NULL,
  cover_alt     VARCHAR(300) NULL,
  category_id   INT UNSIGNED NULL,
  author_name   VARCHAR(160) NOT NULL DEFAULT 'Aagahi Foundation',
  meta_title    VARCHAR(120) NULL,
  meta_desc     VARCHAR(220) NULL,
  focus_keyword VARCHAR(160) NULL,
  faqs          TEXT         NULL COMMENT 'JSON list of {q, a}, used for FAQ structured data',
  status        ENUM('draft','scheduled','published') NOT NULL DEFAULT 'draft',
  published_at  DATETIME     NULL,
  noindex       TINYINT(1)   NOT NULL DEFAULT 0,
  views         INT UNSIGNED NOT NULL DEFAULT 0,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_post_slug (slug),
  KEY idx_post_list (status, published_at),
  KEY idx_post_category (category_id),
  CONSTRAINT fk_post_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_post_staff    FOREIGN KEY (created_by)  REFERENCES staff_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- When a post's address changes, the old address keeps working (permanent redirect).
CREATE TABLE IF NOT EXISTS redirects (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  from_slug  VARCHAR(190) NOT NULL,
  to_slug    VARCHAR(190) NOT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_redirect_from (from_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Image library and event gallery
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  path        VARCHAR(400) NOT NULL COMMENT 'relative to the website root, e.g. uploads/2026/11/photo.webp',
  mime        VARCHAR(80)  NULL,
  size_bytes  INT UNSIGNED NOT NULL DEFAULT 0,
  width       INT UNSIGNED NOT NULL DEFAULT 0,
  height      INT UNSIGNED NOT NULL DEFAULT 0,
  alt         VARCHAR(300) NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_media_path (path),
  KEY idx_media_created (created_at),
  CONSTRAINT fk_media_staff FOREIGN KEY (uploaded_by) REFERENCES staff_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A past event / programme, shown on the home page under its focus area ("Our work, in pictures").
CREATE TABLE IF NOT EXISTS events (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  area         VARCHAR(160) NOT NULL DEFAULT 'General' COMMENT 'focus area heading',
  area_order   INT          NOT NULL DEFAULT 0,
  area_intro   VARCHAR(400) NULL,
  title        VARCHAR(160) NOT NULL,
  slug         VARCHAR(180) NOT NULL,
  description  VARCHAR(1000) NULL,
  sort_order   INT          NOT NULL DEFAULT 0,
  is_published TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event_slug (slug),
  KEY idx_event_order (is_published, area_order, area, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS event_photos (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_id   INT UNSIGNED NOT NULL,
  path       VARCHAR(400) NOT NULL COMMENT 'full-size image, relative to the website root',
  thumb_path VARCHAR(400) NOT NULL,
  width      INT UNSIGNED NOT NULL DEFAULT 0,
  height     INT UNSIGNED NOT NULL DEFAULT 0,
  alt        VARCHAR(300) NULL,
  sort_order INT          NOT NULL DEFAULT 0,
  KEY idx_photo_event (event_id, sort_order),
  CONSTRAINT fk_photo_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. People who contact us
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(120)  NOT NULL,
  email      VARCHAR(190)  NOT NULL,
  phone      VARCHAR(30)   NULL,
  message    TEXT          NOT NULL,
  source     VARCHAR(40)   NOT NULL DEFAULT 'website' COMMENT 'which form it came from',
  ip_hash    CHAR(64)      NOT NULL DEFAULT '' COMMENT 'HMAC of the IP, used only to limit spam',
  status     ENUM('new','read','replied','spam','archived') NOT NULL DEFAULT 'new',
  notes      TEXT          NULL COMMENT 'internal notes by staff',
  handled_by INT UNSIGNED  NULL,
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  replied_at DATETIME      NULL,
  KEY idx_msg_inbox (status, created_at),
  KEY idx_msg_ip (ip_hash, created_at),
  KEY idx_msg_email (email),
  CONSTRAINT fk_msg_staff FOREIGN KEY (handled_by) REFERENCES staff_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS volunteer_applications (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(120) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  phone        VARCHAR(30)  NULL,
  city         VARCHAR(100) NULL,
  interests    VARCHAR(255) NULL COMMENT 'comma-separated focus areas',
  availability VARCHAR(120) NULL,
  message      TEXT         NULL,
  consent_at   DATETIME     NOT NULL COMMENT 'when they agreed that we may contact them',
  ip_hash      CHAR(64)     NOT NULL DEFAULT '',
  status       ENUM('new','contacted','active','declined') NOT NULL DEFAULT 'new',
  notes        TEXT         NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_vol_status (status, created_at),
  KEY idx_vol_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Double opt-in: a person is only "confirmed" after clicking the link we email them. Tokens are stored hashed.
CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email              VARCHAR(190) NOT NULL,
  name               VARCHAR(120) NULL,
  status             ENUM('pending','confirmed','unsubscribed') NOT NULL DEFAULT 'pending',
  confirm_token_hash CHAR(64)     NULL,
  unsubscribe_token_hash CHAR(64) NULL,
  consent_at         DATETIME     NOT NULL,
  confirmed_at       DATETIME     NULL,
  unsubscribed_at    DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_subscriber_email (email),
  KEY idx_subscriber_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 6. Donations (filled when a payment gateway such as Razorpay is connected)
-- Amounts are in paise (1 rupee = 100 paise) so no rounding errors. No card details, no full PAN.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS donations (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  donor_name         VARCHAR(120) NOT NULL,
  donor_email        VARCHAR(190) NOT NULL,
  donor_phone        VARCHAR(30)  NULL,
  amount_paise       BIGINT UNSIGNED NOT NULL,
  currency           CHAR(3)      NOT NULL DEFAULT 'INR',
  purpose            VARCHAR(160) NULL COMMENT 'e.g. general, education, a campaign name',
  gateway            ENUM('razorpay','upi','bank_transfer','cash','other') NOT NULL DEFAULT 'razorpay',
  gateway_order_id   VARCHAR(100) NULL,
  gateway_payment_id VARCHAR(100) NULL,
  status             ENUM('created','paid','failed','refunded') NOT NULL DEFAULT 'created',
  receipt_no         VARCHAR(40)  NULL COMMENT 'our own receipt number, set when paid',
  wants_80g          TINYINT(1)   NOT NULL DEFAULT 0,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at            DATETIME     NULL,
  UNIQUE KEY uq_donation_receipt (receipt_no),
  UNIQUE KEY uq_donation_payment (gateway_payment_id),
  KEY idx_donation_status (status, created_at),
  KEY idx_donation_email (donor_email),
  KEY idx_donation_order (gateway_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 7. Audit trail: who changed what (no passwords or message bodies are ever written here)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  staff_id   INT UNSIGNED NULL,
  action     VARCHAR(60)  NOT NULL COMMENT 'e.g. post.update, user.create, login',
  entity     VARCHAR(40)  NULL,
  entity_id  VARCHAR(40)  NULL,
  detail     VARCHAR(500) NULL,
  ip_hash    CHAR(64)     NOT NULL DEFAULT '',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_time (created_at),
  KEY idx_audit_staff (staff_id, created_at),
  KEY idx_audit_entity (entity, entity_id),
  CONSTRAINT fk_audit_staff FOREIGN KEY (staff_id) REFERENCES staff_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
