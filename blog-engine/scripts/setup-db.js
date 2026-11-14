// scripts/setup-db.js — create the database + tables and bootstrap an admin and
// one demo "site" (tenant) with an API key. Idempotent; safe to re-run.
require('dotenv').config();
const mysql  = require('mysql2/promise');
const crypto = require('crypto');
const bcrypt = require('bcryptjs');

const DB = process.env.DB_NAME || 'blogforge';
const sha256 = (s) => crypto.createHash('sha256').update(String(s)).digest('hex');
const rand   = (n) => crypto.randomBytes(n).toString('hex');

const SCHEMA = `
USE \`${DB}\`;

-- Integrating websites (tenants). One row per site whose blog this engine runs.
-- domain      : the client's public host (e.g. clientsite.com) — used to resolve
--               the site from the Host header when the client reverse-proxies
--               /blog to this engine.
-- base_url    : the client's public origin (https://clientsite.com) — used for
--               canonical URLs, sitemap, OG tags, RSS. Falls back to this engine.
CREATE TABLE IF NOT EXISTS sites (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(160) NOT NULL,
  slug         VARCHAR(180) NOT NULL UNIQUE,
  domain       VARCHAR(190),
  base_url     VARCHAR(300),
  blog_path    VARCHAR(80)  NOT NULL DEFAULT '/blog',
  brand_name   VARCHAR(160),
  logo_url     VARCHAR(300),
  default_author VARCHAR(160) DEFAULT 'Editorial',
  locale       VARCHAR(12)  NOT NULL DEFAULT 'en',
  api_key      VARCHAR(80)  NOT NULL UNIQUE,
  api_secret   VARCHAR(80)  NOT NULL,
  webhook_url  VARCHAR(400),
  is_active    TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
  KEY k_domain (domain)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admins (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(64)  NOT NULL,
  role          ENUM('editor','admin','super_admin') DEFAULT 'editor',
  is_active     TINYINT(1)   DEFAULT 1,
  created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Which sites a staff member may work on (super admins implicitly have all).
CREATE TABLE IF NOT EXISTS admin_sites (
  admin_id INT NOT NULL,
  site_id  INT NOT NULL,
  PRIMARY KEY (admin_id, site_id),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE,
  FOREIGN KEY (site_id)  REFERENCES sites(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- Event gallery: events (programmes) grouped by focus area, each with photos.
CREATE TABLE IF NOT EXISTS events (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  site_id      INT NOT NULL,
  area         VARCHAR(160) NOT NULL DEFAULT 'General',
  area_order   INT NOT NULL DEFAULT 0,
  area_intro   VARCHAR(400) NULL,
  title        VARCHAR(160) NOT NULL,
  slug         VARCHAR(180) NOT NULL,
  description  VARCHAR(1000) NULL,
  sort_order   INT NOT NULL DEFAULT 0,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_event (site_id, slug),
  KEY k_area (site_id, area_order, area, sort_order),
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS event_photos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  event_id   INT NOT NULL,
  url        VARCHAR(400) NOT NULL,
  thumb_url  VARCHAR(400) NOT NULL,
  width      INT NOT NULL DEFAULT 0,
  height     INT NOT NULL DEFAULT 0,
  alt        VARCHAR(300) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  KEY k_event (event_id, sort_order),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  site_id    INT NOT NULL,
  name       VARCHAR(140) NOT NULL,
  slug       VARCHAR(160) NOT NULL,
  description VARCHAR(400),
  sort_order INT DEFAULT 0,
  UNIQUE KEY uq_cat (site_id, slug),
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Blog posts.
CREATE TABLE IF NOT EXISTS posts (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  site_id          INT NOT NULL,
  external_id      VARCHAR(120),                 -- the site's own id (optional, for sync)
  title            VARCHAR(240) NOT NULL,
  slug             VARCHAR(260) NOT NULL,
  content          LONGTEXT,                     -- sanitized HTML
  excerpt          VARCHAR(600),
  cover_image      VARCHAR(400),
  category         VARCHAR(140),
  author           VARCHAR(160),
  author_url       VARCHAR(300),
  -- SEO
  meta_title       VARCHAR(80),
  meta_desc        VARCHAR(200),
  focus_keyword    VARCHAR(160),
  og_image         VARCHAR(400),
  canonical_url    VARCHAR(400),                 -- override; blank = self canonical
  noindex          TINYINT(1) NOT NULL DEFAULT 0,
  seo_score        INT,                          -- 0-100 snapshot from the SEO assistant
  reading_minutes  INT,
  -- lifecycle
  status           ENUM('draft','scheduled','published') NOT NULL DEFAULT 'draft',
  published_at     DATETIME,
  views            INT DEFAULT 0,
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_post (site_id, slug),
  KEY k_status (site_id, status, published_at),
  KEY k_ext (site_id, external_id),
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Uploaded media (covers + in-content images).
CREATE TABLE IF NOT EXISTS media (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  site_id    INT NOT NULL,
  filename   VARCHAR(260) NOT NULL,
  url        VARCHAR(400) NOT NULL,
  mime       VARCHAR(80),
  size       INT,
  alt        VARCHAR(300),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY k_site (site_id),
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 301 redirects (old slug -> new slug) so URLs never break when a post is renamed.
CREATE TABLE IF NOT EXISTS redirects (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  site_id    INT NOT NULL,
  from_slug  VARCHAR(260) NOT NULL,
  to_slug    VARCHAR(260) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_redir (site_id, from_slug),
  FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- First-party, privacy-friendly page views (see lib/tracking.js).
CREATE TABLE IF NOT EXISTS page_views (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  site_id    INT NOT NULL,
  page_type  VARCHAR(24)  NOT NULL DEFAULT 'blog',   -- blog | blog_index | home | page | other
  slug       VARCHAR(255) NOT NULL DEFAULT '',
  path       VARCHAR(512) NOT NULL DEFAULT '',
  visitor    CHAR(24)     NOT NULL DEFAULT '',        -- random anonymous cookie id
  referrer   VARCHAR(255) NOT NULL DEFAULT '',        -- external hostname only
  device     VARCHAR(12)  NOT NULL DEFAULT 'desktop',
  country    CHAR(2)      NOT NULL DEFAULT '',
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_site_created (site_id, created_at),
  KEY idx_type_slug (site_id, page_type, slug),
  KEY idx_visitor (visitor, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
`;

(async () => {
  const conn = await mysql.createConnection({
    host: process.env.DB_HOST || '127.0.0.1',
    port: parseInt(process.env.DB_PORT) || 3306,
    user: process.env.DB_USER || 'root',
    password: process.env.DB_PASS || '',
    multipleStatements: true,
  });

  console.log(`→ Creating database \`${DB}\` if needed…`);
  await conn.query(`CREATE DATABASE IF NOT EXISTS \`${DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`);
  await conn.query(SCHEMA);
  console.log('✓ Tables ready.');

  const adminEmail = (process.env.ADMIN_EMAIL || 'admin@example.com').toLowerCase();
  const [[a]] = await conn.query('SELECT id FROM `' + DB + '`.admins WHERE email=?', [adminEmail]);
  if (!a) {
    await conn.query('INSERT INTO `' + DB + '`.admins (name,email,password_hash,role) VALUES (?,?,?,?)',
      [process.env.ADMIN_NAME || 'Blog Admin', adminEmail, await bcrypt.hash(process.env.ADMIN_PASSWORD || 'change-me-now', 12), 'super_admin']);
    console.log(`✓ Admin created: ${adminEmail}`);
  } else console.log(`• Admin already exists: ${adminEmail}`);

  const [[s]] = await conn.query('SELECT id, api_key FROM `' + DB + '`.sites WHERE slug=?', ['demo']);
  if (!s) {
    const apiKey = 'pk_' + rand(20), apiSecret = 'sk_' + rand(24);
    await conn.query(
      'INSERT INTO `' + DB + '`.sites (name,slug,brand_name,base_url,default_author,api_key,api_secret) VALUES (?,?,?,?,?,?,?)',
      ['Demo Site', 'demo', 'Demo Blog', process.env.BASE_URL || 'http://localhost:4200', 'Editorial', apiKey, apiSecret]);
    console.log('\n✓ Demo site created. Integration credentials:');
    console.log(`    API key    : ${apiKey}`);
    console.log(`    API secret : ${apiSecret}`);
  } else console.log(`• Demo site already exists (api_key ${s.api_key}).`);

  await conn.end();
  console.log('\nDone. Start with:  npm start');
  process.exit(0);
})().catch((e) => { console.error('SETUP FAILED:', e.message); process.exit(1); });
