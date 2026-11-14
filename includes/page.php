<?php
/** includes/page.php — shared frame (head, navigation, footer) for the form pages: /volunteer/, /newsletter/, /donate/. */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/forms.php';

function page_open(string $title, string $desc = '', bool $index = true): void
{
    global $ngo;
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    ?><!DOCTYPE html>
<html lang="en">
<head>
<base href="/">
<?php head($title, 'family=Nunito:wght@400;600;800&family=Fraunces:wght@600;800', '', $desc); ?>
<link rel="stylesheet" href="assets/site.css">
<link rel="stylesheet" href="assets/forms.css">
<meta name="robots" content="<?= $index ? 'index, follow' : 'noindex, nofollow' ?>">
</head>
<body>
<nav><div class="wrap"><a class="brand" href="/"><?= logo() ?> <?= e($ngo['name']) ?></a>
  <div><a class="m" href="/#work">Our Work</a><a class="m" href="/study/">Study</a><a class="m" href="/blog/">Blog</a><a class="m" href="/volunteer/">Volunteer</a><a class="m" href="/#contact">Contact</a>
  <span class="navauth"><a class="m auth" data-auth="in" href="/study/#/login">Log in</a><a class="btn" data-auth="in" style="margin-left:1rem;padding:.6rem 1.3rem" href="/study/#/join">Create account</a><a class="btn" data-auth="out" hidden style="margin-left:1rem;padding:.6rem 1.3rem" href="/study/#/me">My progress</a></span>
  <a class="btn" style="margin-left:1rem;padding:.6rem 1.3rem" href="/donate/">Donate</a></div></div></nav>
<section class="fpage"><div class="wrap">
<?php }

function page_close(): void
{
    global $ngo; ?>
</div></section>
<footer>© <?= date('Y') ?> <?= e($ngo['name']) ?> · <?= e($ngo['reg_note']) ?> · <a href="/donate/">Donate</a> · <a href="/volunteer/">Volunteer</a> · <a href="/newsletter/">Newsletter</a></footer>
<script src="assets/nav.js"></script>
</body>
</html>
<?php }

/** Area names offered as volunteering interests. */
function volunteer_interests(): array
{
    return ['Teaching and tutoring', 'Hygiene and health camps', 'Road safety', 'Online safety workshops', 'Workplace rights awareness', 'Photography and social media', 'Writing and translation', 'Event help', 'Fundraising'];
}

/** The newsletter box shown in footers. */
function newsletter_box(): string
{
    return '<form class="nl-box" method="post" action="/newsletter/"><label for="nl-email"><b>Get our news</b> <span>a short email now and then</span></label>'
        . form_hidden_fields() . '<div><input id="nl-email" type="email" name="email" placeholder="Your email" required maxlength="190" autocomplete="email"><button type="submit">Subscribe</button></div></form>';
}
