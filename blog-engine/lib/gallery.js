// lib/gallery.js — event gallery: events (programmes) with photos, grouped by "area" (focus area).
// Photos are shrunk on upload (WebP, max 1400px + 480px thumbnail, metadata such as GPS stripped) when the optional
// `sharp` package is installed; otherwise the original file is stored as is.
const fs = require('fs');
const path = require('path');
const pool = require('./db');
const { slugify, rand } = require('./util');

let sharp = null;
try { sharp = require('sharp'); } catch (e) { /* optional: uploads are stored unmodified */ }

const UPLOAD_ROOT = path.join(__dirname, '..', 'public', 'uploads');
const EXT = { 'image/png': '.png', 'image/jpeg': '.jpg', 'image/webp': '.webp', 'image/gif': '.gif', 'image/avif': '.avif' };

// Save one uploaded image buffer. Returns { url, thumb, w, h } (urls are relative to this engine).
async function saveImage(siteId, buffer, mime) {
  const dir = path.join(UPLOAD_ROOT, String(siteId), 'gallery');
  fs.mkdirSync(dir, { recursive: true });
  const base = Date.now() + '-' + rand(4);
  const rel = '/uploads/' + siteId + '/gallery/';
  if (sharp) {
    const img = sharp(buffer, { failOn: 'none' }).rotate();
    await img.clone().resize({ width: 1400, height: 1400, fit: 'inside', withoutEnlargement: true }).webp({ quality: 72 }).toFile(path.join(dir, base + '.webp'));
    const th = await img.clone().resize({ width: 480, height: 480, fit: 'inside', withoutEnlargement: true }).webp({ quality: 68 }).toFile(path.join(dir, base + '-t.webp'));
    return { url: rel + base + '.webp', thumb: rel + base + '-t.webp', w: th.width, h: th.height };
  }
  const file = base + (EXT[mime] || '.jpg');
  fs.writeFileSync(path.join(dir, file), buffer);
  return { url: rel + file, thumb: rel + file, w: 0, h: 0 };
}

function removeFiles(urls) {
  urls.filter(Boolean).forEach(function (u) {
    const f = path.join(UPLOAD_ROOT, u.replace(/^\/uploads\//, ''));
    if (f.startsWith(UPLOAD_ROOT)) { try { fs.unlinkSync(f); } catch (e) { /* already gone */ } }
  });
}

async function uniqueSlug(siteId, desired, excludeId) {
  let base = slugify(desired), s = base, n = 2;
  for (;;) {
    const [[r]] = await pool.query('SELECT id FROM events WHERE site_id = ? AND slug = ? AND id <> ?', [siteId, s, excludeId || 0]);
    if (!r) return s;
    s = base + '-' + n++;
  }
}

// Admin: all events with photo counts, in display order.
async function listEvents(siteId) {
  const [rows] = await pool.query(
    'SELECT e.*, (SELECT COUNT(*) FROM event_photos p WHERE p.event_id = e.id) AS photo_count, ' +
    '(SELECT thumb_url FROM event_photos p WHERE p.event_id = e.id ORDER BY p.sort_order, p.id LIMIT 1) AS cover ' +
    'FROM events e WHERE e.site_id = ? ORDER BY e.area_order, e.area, e.sort_order, e.id', [siteId]);
  return rows;
}

// Public JSON, shaped like the static site's events.json: areas -> programs -> images.
async function publicGallery(siteId, baseUrl) {
  const [events] = await pool.query('SELECT * FROM events WHERE site_id = ? AND is_published = 1 ORDER BY area_order, area, sort_order, id', [siteId]);
  if (!events.length) return { areas: [] };
  const [photos] = await pool.query(
    'SELECT * FROM event_photos WHERE event_id IN (?) ORDER BY sort_order, id', [events.map((e) => e.id)]);
  const abs = (u) => (/^https?:/i.test(u) ? u : baseUrl + u);
  const byEvent = {};
  photos.forEach((p) => { (byEvent[p.event_id] = byEvent[p.event_id] || []).push({ url: abs(p.url), thumb: abs(p.thumb_url), w: p.width || 0, h: p.height || 0, alt: p.alt || '' }); });
  const areas = [], idx = {};
  events.forEach((e) => {
    if (!(e.area in idx)) { idx[e.area] = areas.length; areas.push({ id: slugify(e.area), title: e.area, intro: e.area_intro || '', programs: [] }); }
    const a = areas[idx[e.area]];
    if (!a.intro && e.area_intro) a.intro = e.area_intro;
    a.programs.push({ slug: e.slug, title: e.title, text: e.description || '', images: byEvent[e.id] || [] });
  });
  return { areas };
}

module.exports = { saveImage, removeFiles, uniqueSlug, listEvents, publicGallery, hasSharp: !!sharp };
