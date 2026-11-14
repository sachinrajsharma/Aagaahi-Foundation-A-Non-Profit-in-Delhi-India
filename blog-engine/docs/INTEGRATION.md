# BlogForge Engine — Integration Reference

Authenticate every API call with the site key:

```
X-API-Key: pk_xxxxxxxxxxxxxxxx
```

Base URL = the engine's `BASE_URL` (e.g. `https://blog-engine.com`).

---

## Option A — Hosted blog via reverse proxy (recommended for SEO)

The engine renders the whole blog; you proxy your `/blog` path to it so the
content lives on **your** domain. Set the site's **domain** and **base URL** in
admin → Sites; the engine resolves the site from the incoming `Host` header and
emits canonical/OG/sitemap using your base URL.

nginx:
```nginx
location /blog {
  proxy_pass http://blog-engine.internal:4200;
  proxy_set_header Host $host;
  proxy_set_header X-Forwarded-For $remote_addr;
}
```

Apache:
```apache
ProxyPass        /blog http://blog-engine.internal:4200/blog
ProxyPassReverse /blog http://blog-engine.internal:4200/blog
ProxyPreserveHost On
```

That's the whole integration — the editor, analytics, `sitemap.xml`, `rss.xml`
and `robots.txt` all live under your `/blog`. Nothing to render yourself.

---

## Option B — Headless (render in your own templates)

### List published posts
`GET /api/posts?category=&limit=&offset=`
```json
{ "posts": [ {
  "id": 3, "title": "…", "slug": "how-to-write-seo-friendly-blog-posts",
  "excerpt": "…", "cover_image": "https://…", "category": "SEO",
  "author": "Editorial", "reading_minutes": 4, "url": "https://yoursite.com/blog/…",
  "published_at": "…",
  "meta": { "title": null, "description": "…", "canonical": "https://…", "og_image": "…", "noindex": false }
} ] }
```

### Single post (full HTML + JSON-LD)
`GET /api/posts/:slug`
```json
{ "post": {
  "…": "…(all summary fields)…",
  "content": "<h2>…</h2><p>…</p>",       // sanitized HTML, ready to print
  "json_ld": "<script type=\"application/ld+json\">…BlogPosting…</script>",
  "meta": { "title": "…", "description": "…", "canonical": "https://yoursite.com/blog/slug", "og_image": "…", "noindex": false }
} }
```
Render `content` in your template and inject `meta` + `json_ld` into `<head>`.

### Push content the other way (upsert)
`POST /api/posts` — idempotent by `external_id`, else `slug`. Use this to author
in your own CMS and mirror into the engine (so analytics + sitemap still work).
```json
{ "external_id": "cms-42", "title": "…", "slug": "…", "content": "<p>…</p>",
  "excerpt": "…", "category": "SEO", "focus_keyword": "…",
  "meta_title": "…", "meta_desc": "…", "cover_image": "https://…", "status": "published" }
```

### Categories / analytics
`GET /api/categories` · `GET /api/analytics?days=30` (summary, top posts, referrers).

### Count headless views
Add the beacon to your blog layout so views render in the dashboard:
```html
<script src="https://blog-engine.com/js/track.js" data-site="your-site-slug"
        data-engine="https://blog-engine.com"></script>
```
It infers `page_type`/`slug` from the URL (`…/blog/:slug`); override with
`data-page-type` / `data-slug` if your URLs differ. Keyed by the **public** slug —
safe in page JS.

---

## Webhooks (engine → your site)

Set a **webhook URL** in admin → Sites. On publish/update the engine POSTs JSON:

```
X-BlogForge-Event: post.published
X-BlogForge-Signature: <hex HMAC-SHA256(rawBody, api_secret)>
```

| Event            | When                          |
|------------------|-------------------------------|
| `post.published` | a post becomes published      |
| `post.updated`   | a published post is edited    |

Always verify the signature over the raw body:

**Node** (`express.raw({ type: '*/*' })` to get the raw bytes):
```js
const expected = crypto.createHmac('sha256', API_SECRET).update(rawBody).digest('hex');
if (!crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(req.header('X-BlogForge-Signature')))) return res.sendStatus(400);
```
**PHP**:
```php
$expected = hash_hmac('sha256', $rawBody, $API_SECRET);
if (!hash_equals($expected, $_SERVER['HTTP_X_BLOGFORGE_SIGNATURE'] ?? '')) { http_response_code(400); exit; }
```

Ready-made clients: [`clients/node`](../clients/node), [`clients/php`](../clients/php),
[`clients/laravel`](../clients/laravel).

---

## SEO output reference (hosted blog)

- Per post: `<title>`, `<meta name=description>`, `<link rel=canonical>`, OG +
  Twitter tags, `BlogPosting` + `BreadcrumbList` JSON-LD, `noindex` when set.
- Sitewide: `WebSite` + `Organization` JSON-LD on the index.
- `GET /blog/sitemap.xml`, `GET /blog/rss.xml`, `GET /robots.txt` (host-resolved),
  or `/sites/:slug/blog/sitemap.xml` in path mode.
- Renaming a slug records a 301 automatically so old links never break.
