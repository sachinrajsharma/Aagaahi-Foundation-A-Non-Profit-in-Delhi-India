// routes/public.js — KEYLESS, READ-ONLY JSON for the website itself (browser fetch from another origin).
//   GET /public/:site/posts          published posts (summaries)
//   GET /public/:site/posts/:slug    one published post (sanitised HTML)
//   GET /public/:site/gallery        published events + photos, grouped by focus area
// Unlike /api (which needs the secret-ish API key and can WRITE), nothing here can change data, so it is safe to call
// from public pages. CORS is open for GET only.
const express = require('express');
const rateLimit = require('express-rate-limit');
const pool = require('../lib/db');
const cfg = require('../lib/config');
const postsLib = require('../lib/posts');
const gallery = require('../lib/gallery');

const router = express.Router();
router.use(rateLimit({ windowMs: 60 * 1000, max: 240, standardHeaders: true, legacyHeaders: false, message: { error: 'Rate limit exceeded.' } }));
router.use((req, res, next) => {
  res.set('Access-Control-Allow-Origin', '*');
  res.set('Access-Control-Allow-Methods', 'GET, OPTIONS');
  res.set('Cache-Control', 'public, max-age=60');
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  if (req.method !== 'GET') return res.status(405).json({ error: 'Read-only.' });
  next();
});
router.use('/:site', async (req, res, next) => {
  try {
    const site = await postsLib.siteBySlug(req.params.site);
    if (!site) return res.status(404).json({ error: 'Unknown site.' });
    req.site = site; next();
  } catch (e) { next(e); }
});

router.get('/:site/posts', async (req, res, next) => {
  try {
    const rows = await postsLib.listPublished(req.site.id, { limit: Math.min(parseInt(req.query.limit, 10) || 50, 100), offset: parseInt(req.query.offset, 10) || 0 });
    res.json({ posts: rows.map((p) => postsLib.publicPost(req.site, p)) });
  } catch (e) { next(e); }
});

router.get('/:site/posts/:slug', async (req, res, next) => {
  try {
    const p = await postsLib.getPublished(req.site.id, req.params.slug);
    if (!p) return res.status(404).json({ error: 'Post not found.' });
    res.json({ post: postsLib.publicPost(req.site, p, { full: true }) });
  } catch (e) { next(e); }
});

router.get('/:site/gallery', async (req, res, next) => {
  try { res.json(await gallery.publicGallery(req.site.id, cfg.baseUrl)); } catch (e) { next(e); }
});

module.exports = router;
