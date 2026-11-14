# Database for aagaahifoundation.com (MySQL on Hostinger)

The website works without a database. With one, contact-form messages are saved safely, and the blog, gallery,
settings and staff accounts have a home. The schema is `migrations/001_initial.sql` and `002_forms.sql` (16 tables).

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
6. **Check it worked.** In phpMyAdmin you should see 16 tables, 4 posts, 7 events and 52 event photos. Send a test
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

## The dashboard (https://aagaahifoundation.com/admin/)

Sign in with the administrator you created in the installer. Everything below works from a phone or a laptop.

| Page | What you can do | Who |
|---|---|---|
| **Dashboard** | Numbers at a glance, latest messages and posts | everyone |
| **Messages** | Contact-form inbox: read, mark replied / spam / archived, add private notes, reply by email, download a CSV, delete | editor and up |
| **Blog posts** | Write and edit posts in a visual editor (headings, bold, lists, links, tables, images), add FAQs, set the search-engine title and description, schedule a post, preview a draft, delete. A live **SEO check** scores the post and tells you what to improve | editor and up |
| **Gallery** | Add events under a focus area, upload up to 30 photos at once, describe and reorder them, hide or delete an event | editor and up |
| **Images** | Upload and delete pictures for posts and cover images | editor and up |
| **Staff & access** | Add team members, change roles, reset passwords, disable or delete accounts. New accounts must choose their own password at first sign-in | admin and up |
| **Settings** | Name, tagline, mission, contact details, donate link, social links: the website changes at once | admin and up |
| **Activity log** | Who did what, newest first | admin and up |

Roles: **editor** (posts, gallery, images, messages), **admin** (also staff, settings, activity log), **super admin** (everything, including other super admins; there must always be one).

**What the website now reads from the database:** the blog (list, each post with search-engine markup, `sitemap.xml`), the "Our work, in pictures" gallery, the "From our blog" block, and the Settings (tagline, contact details, donate link in the footer). If the database is ever unreachable the site falls back to the files in the repository, so it never goes blank.

**Safety built in:** sign-in locks for 15 minutes after 5 wrong passwords (and slows one visitor after 10), sessions end after 2 hours idle, every form has a security token, a disabled account loses access on its next click, uploaded images are re-encoded (location data removed, hidden code impossible) and the uploads folder can never run code, and anything typed in the editor is cleaned of scripts.

**Server requirements (Hostinger has them by default):** PHP 8.0 or newer with the `pdo_mysql`, `gd` (with WebP), `fileinfo` and `mbstring` extensions, and the `uploads/` folder must be writable (permission 755).

## Volunteer, newsletter and donation forms

Public pages (PHP, need the database): **/volunteer/**, **/newsletter/** and **/donate/**. Links are on the home page, the navigation bar and the footer.
Run `/install/` (new sites) or `php database/migrate.php` (existing ones) so `migrations/002_forms.sql` is applied: it adds one table (`form_attempts`, spam limits) and the donation settings.

| Form | What happens | Dashboard page (who) |
|---|---|---|
| **Volunteer** | Name, email, optional phone and city, interests, free time, note. Adults only (18+ tick), consent tick, thank-you email to the volunteer, notice to the office | **Volunteers** (editor and up): status new / contacted / active / declined, notes, CSV, delete |
| **Newsletter** | Double opt-in: a confirmation email, then a button (so mail scanners cannot confirm by themselves). A welcome email carries the one-click unsubscribe link. The page answers the same whether or not the address was already known | **Newsletter** (editor and up): counts, the confirmed-only list as CSV with each person's unsubscribe link |
| **Donate** | Amount (presets or your own, Rs 50 to Rs 5,00,000), purpose, name, email, optional phone, optional "tell me about 80G", consent | **Donations** (admin and up): list, filters by status and financial year, mark received / failed / refunded, record offline gifts (cash, cheque, transfer), email the receipt again, CSV |

**Donations, two modes (automatic):**
- *Manual (default).* Put your UPI id and bank details in **Settings**. Donors see them with a reference (`DON-12`); you mark the gift *received* in the dashboard, which issues the receipt and emails it.
- *Online (Razorpay).* Add your keys to `includes/config.local.php` (below). Donors pay on Razorpay's secure window (UPI, cards, net banking); we never see payment details. A payment counts only after Razorpay's signature is verified, from the browser return and/or Razorpay's server-to-server webhook. Both are safe to repeat: one payment gives one receipt. Amount and currency must match the order.

Razorpay setup: create a Razorpay account (KYC is theirs), copy the **Key Id** and **Key Secret**, then in Razorpay > Webhooks add `https://aagaahifoundation.com/donate/webhook.php` with events `payment.captured`, `order.paid`, `payment.failed` and a secret you invent. Put all of it in `config.local.php` (see `config.local.example.php`). Use *test mode* keys first.

Receipts are numbered per financial year (April to March), e.g. `AAG/2026-27/00001`; the prefix is in Settings. The receipt page is an acknowledgement, **not** an 80G certificate: 80G needs your registration details and the donor's PAN, which you handle outside the website (the site never asks for PAN, card numbers or ID documents).

**Spam protection:** a hidden field bots fill in, a signed time token (forms sent in under 2 seconds or after 2 hours are ignored), and a limit per visitor per hour (3 volunteer sign-ups, 5 newsletter attempts, 10 donation starts). Visitors' IPs are kept only as a keyed hash.

**Email:** sent with PHP `mail()` from `no-reply@<your domain>`, replies go to your office address. If messages land in spam, set up SPF/DKIM for the domain in hPanel > Emails. For local testing set `$ngo['mail_transport'] = 'file'` to write messages to `includes/mail.log` instead.

**Privacy and law (India, DPDP Act):** only what is needed is collected, consent is recorded with its time, people can ask for deletion (delete the row in the dashboard), and volunteers must be adults. Have a lawyer or CA review the wording, your privacy notice, and your 80G / 12A / FCRA position before you publish.

## Testing
Developed against MariaDB 11.4 and PHP 8.3 with four automated suites: the schema and seeding (54 checks), the installer (20), the dashboard plus public site over real HTTP (115), and the forms (103): spam protection, double opt-in, unsubscribe tampering, amount parsing, receipts and their numbering, Razorpay signature and webhook checks against a local stand-in server (wrong signature, wrong amount, replays), roles and CSV safety.

## Not built yet
The activity log's search and export, sending newsletters from the dashboard (export the confirmed list and use your mail tool for now), and 80G certificates.
