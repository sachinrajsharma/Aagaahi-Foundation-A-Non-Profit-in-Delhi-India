# Aagahi dashboard (BlogForge CMS)

A WordPress-style back office for the Aagahi website. Staff sign in at `/admin` and can:

| Area | What staff can do |
|---|---|
| **Posts** | Write, edit, schedule, publish and delete blog posts (rich editor, SEO assistant, categories, cover image) |
| **Gallery** | Add events/programmes under a focus area, upload many photos at once (auto-resized), reorder, delete |
| **Media** | Upload and delete images for use inside posts |
| **Analytics** | Privacy-friendly page-view statistics |
| **Staff** | Add team members, give each a role and the sites they may work on, reset passwords, disable or delete accounts |
| **Sites** | (super admin) Add websites and manage API keys |

## Roles
| Role | Can do |
|---|---|
| **Super admin** | Everything, including sites, API keys and other super admins. There must always be one. |
| **Admin** | Staff (not super admins), posts, gallery, media, analytics, on their sites |
| **Editor** | Posts, gallery, media, on their sites |

Passwords are stored with bcrypt. A deactivated or demoted account loses access on its very next click.
New staff get a generated temporary password that is shown **once**; share it privately and ask them to change it under *My account*.

## Put it online (Hostinger)
This is a Node.js + MySQL app, so it runs as its own app (for example on a subdomain such as `cms.yourdomain.org`), **not** on GitHub Pages.

1. In hPanel create a **MySQL database** and user, and a **Node.js app** on the subdomain, with this folder (`blog-engine/`) as the app root and `server.js` as the start file.
2. Set these environment variables (or a `.env` file, never commit it):
   `NODE_ENV=production`, `BASE_URL=https://cms.yourdomain.org`, `SESSION_SECRET=<long random string>`,
   `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`,
   `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` (the first super admin, **choose a strong password**).
3. `npm install`, then once: `npm run setup-db` (creates the tables and the first admin), then start the app.
4. Sign in at `https://cms.yourdomain.org/admin` → **Sites** → create a site named *Aagahi Foundation* with slug **`aagahi`**.
5. Move the existing photos in (one time, from the website repo folder):
   `node scripts/import-gallery.js aagahi /path/to/Aagaahi-Foundation-A-Non-Profit-in-Delhi-India`
6. Add your team under **Staff** and give them the *Aagahi* site.

## Connect the website
In the website repo, `includes/config.php`:
```php
$ngo['cms'] = [ 'url' => 'https://cms.yourdomain.org', 'site' => 'aagahi' ];
```
then `node tools/build-static.js`, commit and push. From then on the **Blog** page and the **Our work** gallery show whatever the team publishes in the dashboard (the old built-in content is the fallback if the dashboard is unreachable or empty).

## Things to know
- The website reads from `/public/<site>/…`, a **read-only, key-less** API. The write API under `/api` still needs the site's secret key, which must never be put in website code.
- Uploaded photos live on the server disk (`public/uploads`). **Back up that folder and the database.**
- Install `sharp` (it is an optional dependency and normally installs by itself) so photos are shrunk on upload; without it the Gallery page shows a warning and stores photos unchanged.
- Never reuse the demo/`change-me-now` password; after the first login change yours under *My account*.
