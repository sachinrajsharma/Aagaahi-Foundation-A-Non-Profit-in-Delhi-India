# Aagahi Foundation: A Non-Profit in Delhi, India

Website for **Aagahi Foundation** (founded by Akanksha Bhatia), built on the "Nature Green" template from
[Empowering NGOs Through Technology](https://github.com/sachinrajsharma/Empowering-NGOs-Through-Technology).
Plain PHP 8+, no database, no framework.

## Sections
- **Hero + counters**, then **Our work, in pictures**: past programmes grouped by focus area, each with a photo gallery and lightbox.
  - Skill Development & Women Empowerment: Hunar Ki Dor, POSH Training
  - Healthy Hygiene & Wellbeing: Health Camp, Kids Hygiene, Menstrual Hygiene, Road Safety
  - Financial Literacy & Cyber Security
- Contact form (PHP `mail()`).

## Run
```
php -S localhost:8000
```
A static copy lives in `docs/` for GitHub Pages (Settings → Pages → main → /docs). Rebuild it after editing content:
```
node tools/build-static.js
```

## Edit content
- `includes/config.php`: name, tagline, mission, address, contact details, donate link.
- `includes/events.json`: programmes, descriptions and the photos shown for each.
- Photos: `assets/img/<programme>/NN.webp` (full, max 1400px) and `NN-t.webp` (thumbnail). Originals (~400 MB) were compressed to ~6 MB; EXIF/GPS data stripped.

> Email, phone and the donate link are not filled in yet. Programme descriptions are short summaries based on the folder names; please review them.

## Blog + SEO analytics (BlogForge engine)
`blog-engine/` is the standalone BlogForge dashboard (Node 18+ and MySQL): rich editor, Yoast-style SEO assistant, sitemap/RSS/JSON-LD and first-party analytics. See `blog-engine/README.md`.

It needs a server, so it **cannot run on GitHub Pages**. Host it separately (VPS, Render, Railway, etc.), then connect this site:
1. In the engine: `npm install`, set `.env`, `npm run setup-db`, `npm start`. Open `/admin` → Sites → create a site for Aagahi and copy its slug and API key.
2. Create `includes/config.local.php` (git-ignored) with the keys, and set the engine URL:
   ```php
   <?php
   $ngo['blog']['engine_url'] = 'https://blog.your-domain.org';
   $ngo['blog']['site_slug']  = 'aagahi';
   $ngo['blog']['api_key']    = 'pk_...';
   ```
3. The PHP site then shows a **Blog** nav link, a "Latest from our blog" block on the homepage, `/blog` and `/blog/<slug>` pages in the site's own layout (needs the included `.htaccess` on Apache), and sends anonymous page-view beacons to the engine's analytics.

Until `engine_url` is set, all of this stays hidden. For the static GitHub Pages copy, set only `engine_url`, `site_slug` and `blog_url` in `includes/config.php`, then run `node tools/build-static.js`.

## Study (Class 1–12, powered by Vidaara)
`/study/` lets students learn on this site without being redirected: pick a class → subject → chapter, read the lesson (maths rendered with KaTeX), open worked examples, check themselves with mini quizzes, and take a scored chapter test. Content is fetched live from Vidaara's public API (`https://vidaara.org/api/v1`, set in `includes/config.php` → `study.api_base`). Progress is saved in the student's own browser.

The browser can only call that API if Vidaara sends CORS headers for the partner's origin; see the `feat/partner-cors` change in the Vidaara repo.
