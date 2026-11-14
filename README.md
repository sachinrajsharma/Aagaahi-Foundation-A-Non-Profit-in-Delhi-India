# Aagahi Foundation: A Non-Profit in Delhi, India

> **New:** volunteer sign-up (`/volunteer/`), double opt-in newsletter (`/newsletter/`) and donations (`/donate/`, manual or Razorpay) with dashboard pages. See `database/README.md`.

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

### Student accounts and progress (Vidaara LMS)
Anyone can **create a free account** on the Study page (name, email, password, class, and a security-check image), exactly like vidaara.org, then **log in** to save progress (chapters done, test scores, streaks, time learned) and see it on **My progress**, without leaving this site. Accounts and progress live in Vidaara's LMS through its partner API (`/api/v1/partner`, see `docs/PARTNER-LMS.md` in the Vidaara repo).

- **No coordinator needed to register.** To tie students to this NGO, set `study.partner_code` in `includes/config.php` to the NGO's class code from Vidaara's teacher dashboard: every student who signs up here then joins that class and appears in the coordinator's gradebook. With no code set, students may type one in (optional).
- Students can delete their account from **My progress** (email code, then admin approval).
- Studying without an account still works; progress then stays in the browser only.
- To onboard another NGO, only the API base and (optionally) a class code are needed; there is nothing NGO-specific in the code.

### Navigation and Blog
Every page has **Our Work · Study · Blog · Contact · Log in · Create account** (it turns into **My progress** once a student is signed in; on phones the links wrap under the logo). The **Blog** page lists the posts in `includes/posts.json` (empty until you add the first one; see the `_help` line in that file, then run `node tools/build-static.js`). If the BlogForge engine is hosted and `blog.engine_url` is set in `includes/config.php`, the Blog link points to the engine instead and the homepage shows "Latest from our blog".

### Admin dashboard (staff, blog, gallery, images)
`blog-engine/` is a WordPress-style dashboard: staff accounts with roles and per-site access, a blog editor, a gallery manager (events and photos, auto-resized) and an image library. It needs a Node + MySQL host (it cannot run on GitHub Pages); see `blog-engine/CMS-GUIDE.md` for setup. When `cms.url` in `includes/config.php` is set, the Blog and the "Our work" gallery show what the team publishes there.

### Writing blog posts
Posts live in `includes/posts/<slug>.html`: a small settings block at the top (title, slug, date, category, focus keyphrase, meta title and description, cover image, FAQs) followed by the article HTML. Run `node tools/build-static.js` to rebuild the Blog, post pages (with SEO markup: canonical, Open Graph, structured data), `sitemap.xml` and `robots.txt`. Use `{{SITE}}` in links and images; it becomes the address in `site_url` (`includes/config.php`). Once the dashboard is running, import the posts with `node blog-engine/scripts/import-posts.js aagahi <this folder>` and edit them there.

### Database (MySQL, Hostinger)
`database/` holds the schema (16 tables: staff, settings, blog, gallery, messages, volunteers, newsletter, donations, audit log), a migration runner, a seeder and `install/`, a one-time web installer. Contact-form messages are saved to the database when it is configured. Setup steps for aagaahifoundation.com are in `database/README.md`; private settings go in `includes/config.local.php` (copy `includes/config.local.example.php`).

### Staff dashboard (PHP, /admin/)
After the database is installed, staff sign in at `/admin/` to manage messages, blog posts (with a live SEO check), the gallery, images, staff accounts and site settings. See `database/README.md`.
