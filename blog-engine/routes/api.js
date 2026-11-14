// routes/api.js — headless integration surface.
// Auth: send the site's API key as `X-API-Key: pk_...` (or Authorization: Bearer).
// The exception is POST /api/track, which is a public view beacon keyed by the
// site slug (no secret in page JS) — it only ever inserts an anonymous view row.
const express = require('express');
const rateLimit = require('express-rate-limit');
const pool = require('../lib/db');
const cfg  = require('../lib/config');
const postsLib = require('../lib/posts');
const insights = require('../lib/insights');
const tracking = require('../lib/tracking');
const webhooks = require('../lib/webhooks');
const { slugify } = require('../lib/util');

const router = express.Router();

router.get('/health', (req, res) => res.json({ ok: true, ts: Date.now() }));

// ── Public view beacon (for headless sites rendering posts themselves) ─────────
// CORS: a headless host renders posts on its OWN origin and the beacon fetch is
// therefore cross-origin, which triggers a preflight. Reflect the request Origin
// (so credentials/the first-party cookie round-trip; a "*" origin cannot be used
// with credentials) and answer the preflight. This is the ONLY cross-origin
// surface — the JSON post/get endpoints below are called server-to-server.
function beaconCors(req, res, next) {
  const origin = req.get('Origin');
  if (origin) {
    res.set('Access-Control-Allow-Origin', origin);
    res.set('Access-Control-Allow-Credentials', 'true');
    res.set('Vary', 'Origin');
  }
  res.set('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.set('Access-Control-Allow-Headers', 'Content-Type');
  next();
}

router.options('/track', beaconCors, (req, res) => res.status(204).end());

router.post('/track', beaconCors, rateLimit({ windowMs: 60 * 1000, max: 240 }), express.json(), async (req, res) => {
  try {
    const site = await postsLib.siteBySlug((req.body && req.body.site) || req.query.site || '');
    if (!site) return res.status(204).end();
    tracking.recordFromApi(pool, req, res, site);
    res.status(204).end();
  } catch (e) { res.status(204).end(); }
});

// ── API-key auth for everything below ──────────────────────────────────────────
router.use(rateLimit({ windowMs: 60 * 1000, max: 120, standardHeaders: true, legacyHeaders: false,
  message: { error: 'Rate limit exceeded.' } }));
router.use(async (req, res, next) => {
  const key = req.get('X-API-Key') || (req.get('Authorization') || '').replace(/^Bearer\s+/i, '');
  if (!key) return res.status(401).json({ error: 'Missing API key (X-API-Key).' });
  const site = await postsLib.siteByApiKey(key);
  if (!site) return res.status(401).json({ error: 'Invalid or inactive API key.' });
  req.site = site; next();
});

// List published posts (headless render).
router.get('/posts', async (req, res) => {
  const rows = await postsLib.listPublished(req.site.id, {
    category: req.query.category, limit: Math.min(parseInt(req.query.limit) || 50, 100), offset: parseInt(req.query.offset) || 0,
  });
  res.json({ posts: rows.map((p) => postsLib.publicPost(req.site, p)) });
});

// Single published post with full HTML content + JSON-LD.
router.get('/posts/:slug', async (req, res) => {
  const p = await postsLib.getPublished(req.site.id, req.params.slug);
  if (!p) return res.status(404).json({ error: 'Post not found.' });
  res.json({ post: postsLib.publicPost(req.site, p, { full: true }) });
});

// Upsert a post (idempotent by external_id, else slug) — push content from the site.
router.post('/posts', express.json({ limit: '2mb' }), async (req, res) => {
  const b = req.body || {};
  if (!b.title) return res.status(400).json({ error: 'title is required.' });
  try {
    let existing = null;
    if (b.external_id) existing = (await pool.query('SELECT id FROM posts WHERE site_id=? AND external_id=?', [req.site.id, b.external_id]))[0][0];
    if (!existing && b.slug) existing = await postsLib.getBySlug(req.site.id, slugify(b.slug));
    const post = existing ? await postsLib.updatePost(req.site.id, existing.id, b) : await postsLib.createPost(req.site.id, b);
    if (post.status === 'published') webhooks.dispatch(req.site, existing ? 'post.updated' : 'post.published', postsLib.publicPost(req.site, post)).catch(() => {});
    res.json({ ok: true, post: postsLib.publicPost(req.site, post, { full: true }), created: !existing });
  } catch (e) { res.status(400).json({ error: e.message }); }
});

router.get('/categories', async (req, res) => {
  const [rows] = await pool.query('SELECT name, slug, description FROM categories WHERE site_id=? ORDER BY sort_order, name', [req.site.id]);
  res.json({ categories: rows });
});

// Headline analytics so a site can show its own numbers.
router.get('/analytics', async (req, res) => {
  const days = Math.min(parseInt(req.query.days) || 30, 365);
  const [sum, top, sources] = await Promise.all([
    insights.summary(pool, req.site.id, days),
    insights.topPosts(pool, req.site.id, days, 10),
    insights.referrers(pool, req.site.id, days, 8),
  ]);
  res.json({ days, summary: sum, top_posts: top, referrers: sources });
});

module.exports = router;
