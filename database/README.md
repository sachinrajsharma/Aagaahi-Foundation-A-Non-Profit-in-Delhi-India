# Database for aagaahifoundation.com (MySQL on Hostinger)

The website works without a database. With one, contact-form messages are saved safely, and the blog, gallery,
settings and staff accounts have a home. The schema is `migrations/001_initial.sql` (15 tables).

## What is stored

| Group | Tables | Used for |
|---|---|---|
| Staff and security | `staff_users`, `login_attempts` | Dashboard logins (passwords hashed), roles `super_admin` / `admin` / `editor`, protection against password guessing |
| Settings | `settings` | Name, tagline, contact details, donate link: editable later without touching code |
| Blog | `categories`, `posts`, `redirects` | Posts with SEO fields (meta title/description, focus keyphrase, FAQs, cover), drafts and scheduling, old addresses that keep working |
| Images | `media`, `events`, `event_photos` | Image library and the "Our work, in pictures" gallery |
| People | `contact_messages`, `volunteer_applications`, `newsletter_subscribers` | The contact form (live now), volunteer sign-ups and a double opt-in newsletter (tables ready) |
| Donations | `donations` | Payments in whole paise, receipt numbers, 80G flag; filled when a payment gateway is connected |
| Audit | `audit_log` | Who changed what (never passwords or message text) |

Privacy by design: visitor IPs are stored only as a keyed hash (used to limit spam), tokens are stored hashed, and
there is nowhere to put ID documents, card numbers or a full PAN. Delete old messages, volunteer and subscriber rows
when you no longer need them.

## Go live in about 10 minutes

1. **Create the database.** hPanel > *Databases* > *MySQL Databases*: create a database, a user and a password.
   Write down the three names exactly as shown (they start with `u` and digits).
2. **Upload the website** to `public_html` (or pull this repository there), as you already do.
3. **Create `includes/config.local.php`** on the server by copying `includes/config.local.example.php` and filling in
   the database name, user, password, your real address (`site_url`) and an install phrase you invent.
   (hPanel > *File Manager*. This file is private and git-ignored.)
4. **Open `https://aagaahifoundation.com/install/`.** Every line in *Checks* should be green. Type your install phrase,
   enter the name, email and a strong password for the first administrator, keep *Add the starting content* ticked, and press *Install*.
5. **Delete the `install/` folder** (hPanel > *File Manager*) as soon as it says *Finished*, and remove the
   `install_token` line from `config.local.php`.
6. **Check it worked.** In phpMyAdmin you should see 15 tables, 4 posts, 7 events and 52 event photos. Send a test
   message through the contact form on the website: it appears in `contact_messages`.

If a check is red: *PDO MySQL driver missing* means enabling it in hPanel > *PHP Configuration* > *Extensions*;
*cannot connect* usually means a typo in the database name, user or password.

### No installer? Use phpMyAdmin
Import `migrations/001_initial.sql` into your database (phpMyAdmin > *Import*). It is safe to run again. Then create the
first administrator from a terminal, if your plan has SSH: `php database/create-admin.php "Your Name" you@example.org`
(prints a one-time password), and load the starting content with `php database/seed.php`.

## For developers
- `includes/db.php`: PDO connection (`db()`, `db_try()`), `setting()`, `ip_hash()`, `audit()`. Nothing runs unless
  the database is configured, and a database problem never breaks a public page (`db_try()` returns `null`).
- `database/migrate.php`: applies `migrations/*.sql` in order, once each. New change = a new numbered file, never edit an old one.
- `database/seed.php`: starting content from `includes/posts.json` and `includes/events.json`, plus `create_staff()`.
  Imported links and image paths become root-relative, so the content survives a change of domain.
- Tested against MariaDB 11.4 with PHP 8.3: schema, idempotent migrations, seeding, constraints and cascades,
  unicode, the contact handler (validation, spam limit, header-injection) and the installer (54 + 20 checks).

## What comes next
The database is ready; these still read from files and are the next pieces of work: the dashboard (login, posts,
gallery, messages inbox), the home page and blog reading from the database, and the volunteer, newsletter and donation forms.
