# BlogForge Engine

An **independent, drop-in Blog Dashboard + first-party SEO Analytics engine** you
build once and plug into any website — **PHP, Laravel, or Node**. Ships a hosted
blog (reverse-proxy friendly for full on-domain SEO), a **headless REST API**,
per-site `sitemap.xml` / `rss.xml` / `robots.txt`, structured data (JSON-LD, OG,
Twitter), a rich editor with a **Yoast-style on-page SEO assistant**, and a
privacy-first analytics dashboard.

Built from the working mathstudy.in blog + SEO + analytics stack and generalised
into a standalone, multi-tenant service. Companion to the PayCommerce payment
engine — same stack and conventions.

---

## Why this exists

Every client site needs a blog and wants to rank. Instead of rebuilding the
editor, SEO tags, sitemap and analytics each time, run **one** engine. Each
website becomes a "site" (tenant) with its own API key, domain and branding. The
engine owns authoring, SEO output and analytics; your site just shows the posts.

---

## What's inside

- **Blog dashboard** — rich contenteditable editor, media library (image uploads),
  categories, drafts/scheduling, multi-site switcher.
- **SEO assistant** — live, Yoast-style scoring as you write: focus keyphrase in
  title/slug/meta/H1/first-paragraph, density + distribution, meta pixel-width,
  Google snippet preview, Flesch readability, transitions, passive voice,
  paragraph length, subheading distribution, links and image alt.
- **First-party analytics** — cookie-only, no-PII, bot-filtered page views →
  visitors, top posts, referrers (bucketed), devices, countries, daily/monthly
  reach. No third-party trackers, nothing an ad-blocker drops.
- **Technical SEO** — BlogPosting + BreadcrumbList + WebSite/Organization JSON-LD,
  canonical, OG/Twitter, per-site `sitemap.xml`, `rss.xml`, `robots.txt`, and
  automatic 301s when a slug changes.
- **AI writing assist (optional)** — draft, improve and meta-suggest buttons
  powered by Claude; hidden entirely unless `ANTHROPIC_API_KEY` is set.
- **Integration** — hosted (reverse proxy) **and** headless (REST/JSON) with
  copy-paste client SDKs for Node, PHP and Laravel.

---

## Two ways to serve the blog

**A) Hosted + reverse proxy (best SEO — content on the client's domain).**
The engine renders the full blog. The client proxies their `/blog` to the engine
with the `Host` header preserved; the engine resolves the site by its `domain`
and renders canonical/OG using the client's own URLs.

```nginx
location /blog { proxy_pass http://blog-engine.internal:4200; proxy_set_header Host $host; }
```

**B) Headless (render in your own templates).**
Fetch posts as JSON from the API and render them in your stack. Full HTML content
and ready-made JSON-LD/meta come back per post. Add `js/track.js` so those views
still count. See [`clients/`](clients/) and [`docs/INTEGRATION.md`](docs/INTEGRATION.md).

For local testing without a proxy, every site is also reachable at
`/sites/<slug>/blog` and `/blog?site=<slug>`.

---

## Quick start

Requires **Node 18+** and **MySQL/MariaDB**.

```bash
cd "D:/BLOG DASHBOAD WITH SEO ANALYTICS"
npm install
cp .env.example .env        # edit DB, admin, (optional) ANTHROPIC_API_KEY
npm run setup-db            # tables + admin + a demo site (prints an API key)
npm run seed                # optional: demo posts + categories
npm start                   # http://localhost:4200
```

Sign in at `http://localhost:4200/admin`. Visit **Sites** for each website's API
key, domain and branding. The demo blog is at `/sites/demo/blog`.

> Country analytics need the optional `geoip-country` package (installed by
> default via optionalDependencies; if it is skipped, the country column simply
> stays empty and everything else works).

---

## Security

- API calls authenticate with `X-API-Key`; outbound webhooks are signed with
  `HMAC-SHA256(body, api_secret)` in `X-BlogForge-Signature` — always verify it.
- Post HTML is **sanitized server-side** (scripts, styles, iframes, event
  handlers and `javascript:` URLs stripped) before it is ever stored or served.
- The public view beacon (`/api/track`) is keyed by the public site slug, never
  the secret — safe to include in page JS. It only inserts an anonymous row.
- Helmet + CSP, rate limiting on API/login/AI, `httpOnly`/`secure` admin cookies,
  no stack traces to the browser, image-only uploads with a size cap.
- Analytics store no IP and no PII — only a random first-party cookie id and a
  country code derived offline then discarded.

---

## Project layout

```
server.js              app wiring (helmet, sessions, routes)
lib/                   config, db, util(+sanitizer), posts, seo, tracking, insights, webhooks, ai
routes/                api.js (headless), blog.js (hosted+sitemap/rss), admin.js
views/                 admin dashboard + editor + public blog (EJS)
public/js/             seo-assistant.js, editor.js, track.js
scripts/               setup-db.js, seed-demo.js
clients/               node / php / laravel SDKs + examples
docs/INTEGRATION.md    full API + webhook + proxy reference
```
