// routes/admin-gallery.js — gallery manager: add / edit / delete events (programmes) and their photos.
// Mounted inside routes/admin.js (admin signed in, req.activeSite set).
const express = require('express');
const multer = require('multer');
const pool = require('../lib/db');
const auth = require('../lib/auth');
const gallery = require('../lib/gallery');
const cfg = require('../lib/config');

const router = express.Router();

const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: Math.max(cfg.uploadMaxBytes, 15 * 1024 * 1024), files: 30 },
  fileFilter: (req, file, cb) => cb(null, /^image\/(png|jpe?g|webp|gif|avif)$/.test(file.mimetype)),
});
const clean = (v, n) => String(v == null ? '' : v).trim().slice(0, n);
async function ownEvent(req) {
  const [[e]] = await pool.query('SELECT * FROM events WHERE id = ? AND site_id = ?', [parseInt(req.params.id, 10) || 0, req.activeSite.id]);
  return e || null;
}

router.get('/gallery', async (req, res) => {
  const events = await gallery.listEvents(req.activeSite.id);
  const areas = [], idx = {};
  events.forEach((e) => { if (!(e.area in idx)) { idx[e.area] = areas.length; areas.push({ title: e.area, events: [] }); } areas[idx[e.area]].events.push(e); });
  res.render('admin/gallery', { layout: 'layout', title: 'Gallery', areas, areaNames: areas.map((a) => a.title), success: req.query.success || '', hasSharp: gallery.hasSharp });
});

router.post('/gallery/events', async (req, res) => {
  const title = clean(req.body.title, 160), area = clean(req.body.area, 160) || 'General';
  if (!title) return res.redirect('/admin/gallery');
  const slug = await gallery.uniqueSlug(req.activeSite.id, title);
  const [[ao]] = await pool.query('SELECT MIN(area_order) AS o FROM events WHERE site_id = ? AND area = ?', [req.activeSite.id, area]);
  const [[mx]] = await pool.query('SELECT COALESCE(MAX(area_order), 0) AS m FROM events WHERE site_id = ?', [req.activeSite.id]);
  const [r] = await pool.query('INSERT INTO events (site_id, area, area_order, title, slug, description, is_published) VALUES (?,?,?,?,?,?,1)',
    [req.activeSite.id, area, ao && ao.o != null ? ao.o : mx.m + 1, title, slug, clean(req.body.description, 1000)]);
  res.redirect('/admin/gallery/' + r.insertId);
});

router.get('/gallery/:id', async (req, res) => {
  const event = await ownEvent(req);
  if (!event) return res.redirect('/admin/gallery');
  const [photos] = await pool.query('SELECT * FROM event_photos WHERE event_id = ? ORDER BY sort_order, id', [event.id]);
  const [areas] = await pool.query('SELECT DISTINCT area FROM events WHERE site_id = ? ORDER BY area', [req.activeSite.id]);
  res.render('admin/gallery-edit', { layout: 'layout', title: event.title, event, photos, areas: areas.map((a) => a.area), success: req.query.success || '', error: req.query.error || '', hasSharp: gallery.hasSharp });
});

router.post('/gallery/:id', async (req, res) => {
  const event = await ownEvent(req);
  if (!event) return res.redirect('/admin/gallery');
  const title = clean(req.body.title, 160) || event.title, area = clean(req.body.area, 160) || event.area;
  await pool.query('UPDATE events SET title=?, area=?, area_intro=?, description=?, sort_order=?, is_published=? WHERE id=?',
    [title, area, clean(req.body.area_intro, 400) || null, clean(req.body.description, 1000), parseInt(req.body.sort_order, 10) || 0, req.body.is_published ? 1 : 0, event.id]);
  if (area !== event.area) { // keep the area's position: reuse an existing area's order if there is one
    const [[ao]] = await pool.query('SELECT MIN(area_order) AS o FROM events WHERE site_id = ? AND area = ? AND id <> ?', [req.activeSite.id, area, event.id]);
    if (ao && ao.o != null) await pool.query('UPDATE events SET area_order = ? WHERE id = ?', [ao.o, event.id]);
  }
  res.redirect('/admin/gallery/' + event.id + '?success=' + encodeURIComponent('Saved.'));
});

router.post('/gallery/:id/photos', upload.array('photos', 30), async (req, res) => {
  const event = await ownEvent(req);
  if (!event) return res.redirect('/admin/gallery');
  const files = req.files || [];
  if (!files.length) return res.redirect('/admin/gallery/' + event.id + '?error=' + encodeURIComponent('Please choose at least one image (png, jpg, webp, gif or avif).'));
  const [[mx]] = await pool.query('SELECT COALESCE(MAX(sort_order), 0) AS m FROM event_photos WHERE event_id = ?', [event.id]);
  let order = mx.m, saved = 0;
  for (const f of files) {
    try {
      const img = await gallery.saveImage(req.activeSite.id, f.buffer, f.mimetype);
      await pool.query('INSERT INTO event_photos (event_id, url, thumb_url, width, height, alt, sort_order) VALUES (?,?,?,?,?,?,?)',
        [event.id, img.url, img.thumb, img.w, img.h, clean(req.body.alt, 300) || event.title, ++order]);
      saved++;
    } catch (e) { console.error('[gallery] upload failed', e.message); }
  }
  res.redirect('/admin/gallery/' + event.id + '?success=' + encodeURIComponent(saved + ' photo' + (saved === 1 ? '' : 's') + ' added.'));
});

router.post('/gallery/:id/photos/:pid', async (req, res) => {
  const event = await ownEvent(req);
  if (!event) return res.redirect('/admin/gallery');
  await pool.query('UPDATE event_photos SET alt = ?, sort_order = ? WHERE id = ? AND event_id = ?',
    [clean(req.body.alt, 300), parseInt(req.body.sort_order, 10) || 0, parseInt(req.params.pid, 10) || 0, event.id]);
  res.redirect('/admin/gallery/' + event.id);
});

router.post('/gallery/:id/photos/:pid/delete', async (req, res) => {
  const event = await ownEvent(req);
  if (!event) return res.redirect('/admin/gallery');
  const [[p]] = await pool.query('SELECT * FROM event_photos WHERE id = ? AND event_id = ?', [parseInt(req.params.pid, 10) || 0, event.id]);
  if (p) { await pool.query('DELETE FROM event_photos WHERE id = ?', [p.id]); gallery.removeFiles([p.url, p.thumb_url !== p.url ? p.thumb_url : null]); }
  res.redirect('/admin/gallery/' + event.id + '?success=' + encodeURIComponent('Photo deleted.'));
});

router.post('/gallery/:id/delete', async (req, res) => {
  const event = await ownEvent(req);
  if (event) {
    const [photos] = await pool.query('SELECT url, thumb_url FROM event_photos WHERE event_id = ?', [event.id]);
    await pool.query('DELETE FROM events WHERE id = ?', [event.id]); // photos cascade
    gallery.removeFiles(photos.flatMap((p) => [p.url, p.thumb_url !== p.url ? p.thumb_url : null]));
  }
  res.redirect('/admin/gallery?success=' + encodeURIComponent('Event deleted.'));
});

module.exports = router;
