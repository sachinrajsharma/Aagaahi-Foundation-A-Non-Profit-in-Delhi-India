// routes/admin.js — the authoring back office: dashboard, post editor (with the
// Yoast-style SEO assistant), first-party insights, media library, optional AI
// assist, and multi-site / API-key management.
const express = require('express');
const path = require('path');
const fs = require('fs');
const multer = require('multer');
const rateLimit = require('express-rate-limit');
const pool = require('../lib/db');
const cfg  = require('../lib/config');
const postsLib = require('../lib/posts');
const insights = require('../lib/insights');
const seo = require('../lib/seo');
const ai = require('../lib/ai');
const webhooks = require('../lib/webhooks');
const { sha256, slugify, rand } = require('../lib/util');

const router = express.Router();

// ── Auth ────────────────────────────────────────────────────────────────────
function requireAdmin(req, res, next) {
  if (req.session && req.session.admin) return next();
  return res.redirect('/admin/login');
}
router.get('/login', (req, res) => res.render('admin/login', { layout: 'layout', title: 'Sign in', error: null }));
router.post('/login', rateLimit({ windowMs: 15 * 60 * 1000, max: 20 }), async (req, res) => {
  const email = (req.body.email || '').trim().toLowerCase();
  const [[a]] = await pool.query('SELECT * FROM admins WHERE email=? AND is_active=1', [email]);
  if (!a || a.password_hash !== sha256(req.body.password || '')) {
    return res.status(401).render('admin/login', { layout: 'layout', title: 'Sign in', error: 'Invalid email or password.' });
  }
  req.session.admin = { id: a.id, name: a.name, email: a.email, role: a.role };
  req.session.save(() => res.redirect('/admin'));
});
router.post('/logout', (req, res) => req.session.destroy(() => res.redirect('/admin/login')));

router.use(requireAdmin);

// Active-site context (an admin can manage several sites).
router.use(async (req, res, next) => {
  const sites = await postsLib.allSites();
  if (!sites.length) { res.locals.sites = []; res.locals.activeSite = null; return next(); }
  let active = sites.find((s) => s.id === (req.session.siteId || 0)) || sites[0];
  req.session.siteId = active.id;
  req.activeSite = active;
  res.locals.sites = sites;
  res.locals.activeSite = active;
  res.locals.aiEnabled = ai.enabled();
  next();
});
router.post('/switch-site', (req, res) => { req.session.siteId = parseInt(req.body.site_id) || null; res.redirect(req.get('Referer') || '/admin'); });

// ── Dashboard ────────────────────────────────────────────────────────────────
router.get('/', async (req, res) => {
  if (!req.activeSite) return res.redirect('/admin/sites');
  const sid = req.activeSite.id;
  const [[counts]] = await pool.query(
    "SELECT COUNT(*) total, SUM(status='published') published, SUM(status='draft') draft, SUM(status='scheduled') scheduled FROM posts WHERE site_id=?", [sid]);
  const sum = await insights.summary(pool, sid, 30);
  const top = await insights.topPosts(pool, sid, 30, 5);
  const [recent] = await pool.query('SELECT id,title,slug,status,views,updated_at FROM posts WHERE site_id=? ORDER BY updated_at DESC LIMIT 8', [sid]);
  res.render('admin/index', { layout: 'layout', title: 'Dashboard', counts, sum, top, recent });
});

// ── Posts ──────────────────────────────────────────────────────────────────────
router.get('/posts', async (req, res) => {
  const posts = await postsLib.listAll(req.activeSite.id, { status: req.query.status, q: req.query.q });
  res.render('admin/posts', { layout: 'layout', title: 'Posts', posts, status: req.query.status || '', q: req.query.q || '', success: req.query.success || '' });
});
router.get('/posts/new', async (req, res) => {
  const [cats] = await pool.query('SELECT name FROM categories WHERE site_id=? ORDER BY sort_order, name', [req.activeSite.id]);
  res.render('admin/post-form', { layout: 'layout', title: 'New post', post: {}, categories: cats.map((c) => c.name), error: null });
});
router.get('/posts/:id/edit', async (req, res) => {
  const post = await postsLib.getById(req.activeSite.id, req.params.id);
  if (!post) return res.redirect('/admin/posts');
  const [cats] = await pool.query('SELECT name FROM categories WHERE site_id=? ORDER BY sort_order, name', [req.activeSite.id]);
  res.render('admin/post-form', { layout: 'layout', title: 'Edit post', post, categories: cats.map((c) => c.name), error: null });
});
router.post('/posts', async (req, res) => {
  const b = req.body; const sid = req.activeSite.id;
  try {
    let post;
    if (b.id) post = await postsLib.updatePost(sid, b.id, b);
    else post = await postsLib.createPost(sid, b);
    if (post.status === 'published') webhooks.dispatch(req.activeSite, b.id ? 'post.updated' : 'post.published', postsLib.publicPost(req.activeSite, post)).catch(() => {});
    res.redirect('/admin/posts?success=' + encodeURIComponent('Saved: ' + post.title));
  } catch (e) {
    const [cats] = await pool.query('SELECT name FROM categories WHERE site_id=? ORDER BY sort_order, name', [sid]);
    res.render('admin/post-form', { layout: 'layout', title: b.id ? 'Edit post' : 'New post', post: b, categories: cats.map((c) => c.name), error: e.message });
  }
});
router.post('/posts/:id/delete', async (req, res) => { await postsLib.deletePost(req.activeSite.id, req.params.id); res.redirect('/admin/posts?success=Deleted'); });
router.post('/posts/:id/toggle', async (req, res) => {
  const post = await postsLib.getById(req.activeSite.id, req.params.id);
  if (!post) return res.json({ ok: false });
  const status = post.status === 'published' ? 'draft' : 'published';
  const publishedAt = status === 'published' && !post.published_at ? new Date() : post.published_at;
  await pool.query('UPDATE posts SET status=?, published_at=?, updated_at=NOW() WHERE id=?', [status, publishedAt, post.id]);
  if (status === 'published') webhooks.dispatch(req.activeSite, 'post.published', postsLib.publicPost(req.activeSite, { ...post, status })).catch(() => {});
  res.json({ ok: true, status });
});

// ── Insights ────────────────────────────────────────────────────────────────
router.get('/insights', async (req, res) => {
  const days = Math.min(parseInt(req.query.days) || 30, 365);
  const data = await insights.overview(pool, req.activeSite.id, { days });
  res.render('admin/insights', { layout: 'layout', title: 'SEO Analytics', data, days });
});

// ── Media library ─────────────────────────────────────────────────────────────
const upload = multer({
  storage: multer.diskStorage({
    destination: (req, file, cb) => {
      const dir = path.join(__dirname, '..', 'public', 'uploads', String(req.activeSite.id));
      fs.mkdirSync(dir, { recursive: true }); cb(null, dir);
    },
    filename: (req, file, cb) => cb(null, Date.now() + '-' + rand(4) + path.extname(file.originalname).toLowerCase()),
  }),
  limits: { fileSize: cfg.uploadMaxBytes },
  fileFilter: (req, file, cb) => cb(null, /^image\/(png|jpe?g|gif|webp|svg\+xml|avif)$/.test(file.mimetype)),
});
router.get('/media', async (req, res) => {
  const [items] = await pool.query('SELECT * FROM media WHERE site_id=? ORDER BY created_at DESC LIMIT 300', [req.activeSite.id]);
  res.render('admin/media', { layout: 'layout', title: 'Media', items });
});
router.post('/media/upload', upload.single('file'), async (req, res) => {
  if (!req.file) return res.status(400).json({ error: 'No image (allowed: png, jpg, gif, webp, svg, avif).' });
  const url = `/uploads/${req.activeSite.id}/${req.file.filename}`;
  const [r] = await pool.query('INSERT INTO media (site_id, filename, url, mime, size, alt) VALUES (?,?,?,?,?,?)',
    [req.activeSite.id, req.file.filename, url, req.file.mimetype, req.file.size, req.body.alt || '']);
  const absUrl = cfg.baseUrl + url;
  if (req.accepts('json') && !req.body._form) return res.json({ ok: true, id: r.insertId, url, absUrl });
  res.redirect('/admin/media');
});
router.post('/media/:id/delete', async (req, res) => {
  const [[m]] = await pool.query('SELECT * FROM media WHERE id=? AND site_id=?', [req.params.id, req.activeSite.id]);
  if (m) { try { fs.unlinkSync(path.join(__dirname, '..', 'public', 'uploads', String(req.activeSite.id), m.filename)); } catch (e) {} await pool.query('DELETE FROM media WHERE id=?', [m.id]); }
  res.redirect('/admin/media');
});

// ── AI assist (only if configured) ─────────────────────────────────────────────
const aiLimiter = rateLimit({ windowMs: 60 * 1000, max: 20 });
function requireAi(req, res, next) { if (!ai.enabled()) return res.status(400).json({ error: 'AI is not configured.' }); next(); }
router.post('/ai/meta', aiLimiter, requireAi, async (req, res) => {
  try { res.json({ ok: true, ...(await ai.suggestMeta(req.body)) }); } catch (e) { res.status(502).json({ error: e.message }); }
});
router.post('/ai/improve', aiLimiter, requireAi, async (req, res) => {
  try { res.json({ ok: true, html: await ai.improve(req.body) }); } catch (e) { res.status(502).json({ error: e.message }); }
});
router.post('/ai/draft', aiLimiter, requireAi, async (req, res) => {
  try { res.json({ ok: true, html: await ai.draft(req.body) }); } catch (e) { res.status(502).json({ error: e.message }); }
});

// ── Sites & categories ─────────────────────────────────────────────────────────
router.get('/sites', async (req, res) => {
  const sites = await postsLib.allSites();
  res.render('admin/sites', { layout: 'layout', title: 'Sites & API keys', sites });
});
router.post('/sites', async (req, res) => {
  const name = (req.body.name || '').trim(); if (!name) return res.redirect('/admin/sites');
  await pool.query('INSERT INTO sites (name, slug, domain, base_url, blog_path, brand_name, default_author, api_key, api_secret, webhook_url) VALUES (?,?,?,?,?,?,?,?,?,?)',
    [name, slugify(req.body.slug || name), req.body.domain || null, req.body.base_url || null, req.body.blog_path || '/blog',
     req.body.brand_name || name, req.body.default_author || 'Editorial', 'pk_' + rand(20), 'sk_' + rand(24), req.body.webhook_url || null]);
  res.redirect('/admin/sites');
});
router.post('/sites/:id', async (req, res) => {
  await pool.query('UPDATE sites SET name=?, domain=?, base_url=?, blog_path=?, brand_name=?, logo_url=?, default_author=?, webhook_url=?, is_active=? WHERE id=?',
    [req.body.name, req.body.domain || null, req.body.base_url || null, req.body.blog_path || '/blog', req.body.brand_name || req.body.name,
     req.body.logo_url || null, req.body.default_author || 'Editorial', req.body.webhook_url || null, req.body.is_active ? 1 : 0, req.params.id]);
  res.redirect('/admin/sites');
});
router.post('/sites/:id/rotate', async (req, res) => {
  await pool.query('UPDATE sites SET api_key=?, api_secret=? WHERE id=?', ['pk_' + rand(20), 'sk_' + rand(24), req.params.id]);
  res.redirect('/admin/sites');
});
router.post('/categories', async (req, res) => {
  const name = (req.body.name || '').trim(); if (name) {
    await pool.query('INSERT IGNORE INTO categories (site_id, name, slug, sort_order) VALUES (?,?,?,?)',
      [req.activeSite.id, name, slugify(name), parseInt(req.body.sort_order) || 0]);
  }
  res.redirect('/admin/sites');
});

module.exports = router;
