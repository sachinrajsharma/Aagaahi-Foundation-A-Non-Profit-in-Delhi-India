// scripts/import-gallery.js — one-time import of the website's existing gallery (includes/events.json + assets/img) into the dashboard.
//   node scripts/import-gallery.js <site-slug> <path-to-website-repo> [--force]
// Photos are copied as they are (they are already compressed WebP) into public/uploads/<siteId>/gallery/.
// Refuses to run if the site already has events, unless --force is given.
require('dotenv').config();
const fs = require('fs');
const path = require('path');
const pool = require('../lib/db');
const { slugify } = require('../lib/util');

(async () => {
  const [slug, repo] = process.argv.slice(2).filter((a) => !a.startsWith('--'));
  const force = process.argv.includes('--force');
  if (!slug || !repo) { console.error('Usage: node scripts/import-gallery.js <site-slug> <path-to-website-repo> [--force]'); process.exit(1); }
  const eventsFile = path.join(repo, 'includes', 'events.json'), imgRoot = path.join(repo, 'assets', 'img');
  if (!fs.existsSync(eventsFile)) { console.error('Cannot find ' + eventsFile); process.exit(1); }
  const [[site]] = await pool.query('SELECT id, name FROM sites WHERE slug = ?', [slug]);
  if (!site) { console.error('No site with slug "' + slug + '". Create it first on the Sites page.'); process.exit(1); }
  const [[have]] = await pool.query('SELECT COUNT(*) n FROM events WHERE site_id = ?', [site.id]);
  if (have.n && !force) { console.error('This site already has ' + have.n + ' events. Use --force to add the import anyway (it may create duplicates).'); process.exit(1); }

  const data = JSON.parse(fs.readFileSync(eventsFile, 'utf8'));
  const outDir = path.join(__dirname, '..', 'public', 'uploads', String(site.id), 'gallery');
  fs.mkdirSync(outDir, { recursive: true });
  let events = 0, photos = 0, missing = 0;
  for (let ai = 0; ai < data.areas.length; ai++) {
    const area = data.areas[ai];
    for (let pi = 0; pi < area.programs.length; pi++) {
      const prog = area.programs[pi];
      let evSlug = slugify(prog.slug || prog.title), n = 2;
      while ((await pool.query('SELECT id FROM events WHERE site_id = ? AND slug = ?', [site.id, evSlug]))[0].length) evSlug = slugify(prog.slug || prog.title) + '-' + n++;
      const [ins] = await pool.query(
        'INSERT INTO events (site_id, area, area_order, area_intro, title, slug, description, sort_order, is_published) VALUES (?,?,?,?,?,?,?,?,1)',
        [site.id, area.title, ai + 1, area.intro || null, prog.title, evSlug, prog.text || null, pi]);
      events++;
      for (let k = 0; k < prog.images.length; k++) {
        const im = prog.images[k];
        const full = path.join(imgRoot, prog.slug, im.n + '.webp'), thumb = path.join(imgRoot, prog.slug, im.n + '-t.webp');
        if (!fs.existsSync(full) || !fs.existsSync(thumb)) { missing++; continue; }
        const base = 'imp-' + evSlug + '-' + im.n;
        fs.copyFileSync(full, path.join(outDir, base + '.webp'));
        fs.copyFileSync(thumb, path.join(outDir, base + '-t.webp'));
        const rel = '/uploads/' + site.id + '/gallery/';
        await pool.query('INSERT INTO event_photos (event_id, url, thumb_url, width, height, alt, sort_order) VALUES (?,?,?,?,?,?,?)',
          [ins.insertId, rel + base + '.webp', rel + base + '-t.webp', im.w || 0, im.h || 0, im.alt || prog.title, k + 1]);
        photos++;
      }
    }
  }
  console.log('Imported ' + events + ' events and ' + photos + ' photos into "' + site.name + '"' + (missing ? ' (' + missing + ' image files were missing)' : '') + '.');
  process.exit(0);
})().catch((e) => { console.error('IMPORT FAILED:', e.message); process.exit(1); });
