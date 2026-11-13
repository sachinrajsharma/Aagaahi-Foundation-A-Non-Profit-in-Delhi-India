# Aagahi Foundation: A Non-Profit in Delhi, India

Website for **Aagahi Foundation** (founded by Akanksha Bhatia), built on the "Nature Green" homepage template from
[Empowering NGOs Through Technology](https://github.com/sachinrajsharma/Empowering-NGOs-Through-Technology).

Plain PHP 8+, no database, no build step.

## Run
```
php -S localhost:8000
```
Then open http://localhost:8000. A static copy of the page is in `docs/` (serve it with GitHub Pages: Settings → Pages → main → /docs).

## Edit content
Everything (name, tagline, mission, stats, programmes, stories, contact, donate link, social links) is in `includes/config.php`.

> **Placeholders to replace:** email, phone, donate link, stats, programmes and stories are sample content from the template.
> Replace them with Aagahi Foundation's real details before publishing. If you edit `config.php`, mirror the
> changes in `docs/index.html` (the static copy is not auto-generated).
