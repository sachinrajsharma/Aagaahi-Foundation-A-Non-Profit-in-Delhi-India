// lib/posts.js — data helpers for sites and posts + a public serializer.
const pool = require('./db');
const { slugify, sanitizeHtml, makeExcerpt, readingMinutes, stripHtml } = require('./util');

// ── Sites ─────────────────────────────────────────────────────────────────────
async function siteById(id)       { const [[s]] = await pool.query('SELECT * FROM sites WHERE id=?', [id]); return s || null; }
async function siteBySlug(slug)   { const [[s]] = await pool.query('SELECT * FROM sites WHERE slug=? AND is_active=1', [slug]); return s || null; }
async function siteByApiKey(key)  { const [[s]] = await pool.query('SELECT * FROM sites WHERE api_key=? AND is_active=1', [key]); return s || null; }
async function siteByDomain(host) {
  if (!host) return null;
  const bare = host.replace(/:\d+$/, '').replace(/^www\./, '').toLowerCase();
  const [[s]] = await pool.query('SELECT * FROM sites WHERE is_active=1 AND (domain=? OR domain=?) LIMIT 1', [bare, 'www.' + bare]);
  return s || null;
}
async function allSites() { const [rows] = await pool.query('SELECT * FROM sites ORDER BY created_at DESC'); return rows; }

// Resolve which site a hosted request belongs to: explicit ?site=slug or /sites/:slug
// path param wins (useful locally); otherwise match the Host header to a domain.
async function resolveSiteForRequest(req) {
  if (req.params && req.params.siteSlug) return siteBySlug(req.params.siteSlug);
  if (req.query && req.query.site) return siteBySlug(req.query.site);
  return siteByDomain(req.headers.host);
}

// ── Posts ─────────────────────────────────────────────────────────────────────
async function listPublished(siteId, { category, limit = 100, offset = 0 } = {}) {
  const params = [siteId];
  let q = `SELECT id,title,slug,excerpt,cover_image,category,author,meta_desc,reading_minutes,published_at,created_at,views
             FROM posts WHERE site_id=? AND status='published' AND (published_at IS NULL OR published_at <= NOW())`;
  if (category) { q += ' AND category=?'; params.push(category); }
  q += ' ORDER BY COALESCE(published_at, created_at) DESC LIMIT ? OFFSET ?';
  params.push(Number(limit), Number(offset));
  const [rows] = await pool.query(q, params);
  return rows;
}

async function getPublished(siteId, slug) {
  const [[p]] = await pool.query(
    `SELECT * FROM posts WHERE site_id=? AND slug=? AND status='published' AND (published_at IS NULL OR published_at <= NOW())`,
    [siteId, slug]);
  return p || null;
}

async function getById(siteId, id)   { const [[p]] = await pool.query('SELECT * FROM posts WHERE site_id=? AND id=?', [siteId, id]); return p || null; }
async function getBySlug(siteId, slug) { const [[p]] = await pool.query('SELECT * FROM posts WHERE site_id=? AND slug=?', [siteId, slug]); return p || null; }

async function listAll(siteId, { status, q } = {}) {
  const params = [siteId]; let sql = 'SELECT id,title,slug,status,category,author,views,seo_score,published_at,created_at,updated_at FROM posts WHERE site_id=?';
  if (status) { sql += ' AND status=?'; params.push(status); }
  if (q) { sql += ' AND title LIKE ?'; params.push('%' + q + '%'); }
  sql += ' ORDER BY updated_at DESC LIMIT 500';
  const [rows] = await pool.query(sql, params);
  return rows;
}

async function uniqueSlug(siteId, desired, excludeId = null) {
  let slug = slugify(desired);
  const [rows] = await pool.query('SELECT id FROM posts WHERE site_id=? AND slug=?' + (excludeId ? ' AND id<>?' : ''),
    excludeId ? [siteId, slug, excludeId] : [siteId, slug]);
  if (rows.length) slug = slug + '-' + Date.now().toString().slice(-4);
  return slug;
}

function normalizeFields(b) {
  const content = sanitizeHtml(b.content || '');
  return {
    title: (b.title || '').trim(),
    content,
    excerpt: (b.excerpt || '').trim() || makeExcerpt(content, 180),
    cover_image: b.cover_image || null,
    category: b.category || null,
    author: b.author || null,
    author_url: b.author_url || null,
    meta_title: (b.meta_title || '').slice(0, 80) || null,
    meta_desc: (b.meta_desc || '').slice(0, 200) || null,
    focus_keyword: (b.focus_keyword || '').slice(0, 160) || null,
    og_image: b.og_image || null,
    canonical_url: b.canonical_url || null,
    noindex: b.noindex ? 1 : 0,
    seo_score: (b.seo_score === '' || b.seo_score == null) ? null : parseInt(b.seo_score),
    reading_minutes: readingMinutes(content),
    status: ['draft', 'scheduled', 'published'].includes(b.status) ? b.status : 'draft',
    external_id: b.external_id || null,
  };
}

async function createPost(siteId, b) {
  const f = normalizeFields(b);
  if (!f.title) throw new Error('Title is required.');
  if (!stripHtml(f.content)) throw new Error('Content cannot be empty.');
  const slug = await uniqueSlug(siteId, b.slug || f.title);
  const publishedAt = f.status === 'published' ? (b.published_at || new Date()) : (f.status === 'scheduled' ? (b.published_at || null) : null);
  const cols = { site_id: siteId, slug, published_at: publishedAt, ...f };
  const keys = Object.keys(cols);
  const [r] = await pool.query('INSERT INTO posts (' + keys.join(',') + ') VALUES (' + keys.map(() => '?').join(',') + ')',
    keys.map((k) => cols[k]));
  return getById(siteId, r.insertId);
}

async function updatePost(siteId, id, b) {
  const current = await getById(siteId, id);
  if (!current) throw new Error('Post not found.');
  const f = normalizeFields(b);
  if (!f.title) throw new Error('Title is required.');
  let slug = current.slug;
  if (b.slug && slugify(b.slug) !== current.slug) slug = await uniqueSlug(siteId, b.slug, id);
  // Record a 301 if the slug changed.
  if (slug !== current.slug) {
    await pool.query('INSERT INTO redirects (site_id, from_slug, to_slug) VALUES (?,?,?) ON DUPLICATE KEY UPDATE to_slug=VALUES(to_slug)',
      [siteId, current.slug, slug]).catch(() => {});
  }
  let publishedAt = current.published_at;
  if (f.status === 'published' && !current.published_at) publishedAt = b.published_at || new Date();
  if (f.status === 'scheduled') publishedAt = b.published_at || current.published_at;
  const cols = { slug, published_at: publishedAt, ...f };
  const keys = Object.keys(cols);
  await pool.query('UPDATE posts SET ' + keys.map((k) => `${k}=?`).join(',') + ', updated_at=NOW() WHERE site_id=? AND id=?',
    [...keys.map((k) => cols[k]), siteId, id]);
  return getById(siteId, id);
}

async function deletePost(siteId, id) { await pool.query('DELETE FROM posts WHERE site_id=? AND id=?', [siteId, id]); }

// Public serializer for the headless API / webhooks.
function publicPost(site, p, { full = false } = {}) {
  const seo = require('./seo');
  const out = {
    id: p.id, external_id: p.external_id, title: p.title, slug: p.slug,
    excerpt: p.excerpt, cover_image: seo.abs(site, p.cover_image) || p.cover_image || null,
    category: p.category, author: p.author || site.default_author,
    reading_minutes: p.reading_minutes, status: p.status,
    url: seo.postUrl(site, p),
    published_at: p.published_at, updated_at: p.updated_at,
    meta: { title: (p.meta_title || null), description: (p.meta_desc || null), canonical: seo.postUrl(site, p), og_image: seo.abs(site, p.og_image || p.cover_image) || null, noindex: !!p.noindex },
  };
  if (full) { out.content = p.content; out.json_ld = seo.postJsonLd(site, p); }
  return out;
}

module.exports = {
  siteById, siteBySlug, siteByApiKey, siteByDomain, allSites, resolveSiteForRequest,
  listPublished, getPublished, getById, getBySlug, listAll, uniqueSlug,
  createPost, updatePost, deletePost, publicPost,
};
