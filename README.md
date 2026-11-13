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
