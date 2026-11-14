<?php
require_once __DIR__ . '/config.php';

/** Escape output safely. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Format a number the Indian way (12,500 / 3,42,000). */
function inr($n): string
{
    $n = (string)(int)$n;
    if (strlen($n) <= 3) return $n;
    $last = substr($n, -3);
    $rest = substr($n, 0, -3);
    return preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last;
}

/** Logo (image if set, otherwise emoji). */
function logo(): string
{
    global $ngo;
    if ($ngo['logo_img']) {
        return '<img src="' . e($ngo['logo_img']) . '" alt="' . e($ngo['name']) . '" height="36">';
    }
    return '<span class="logo-emoji">' . e($ngo['logo']) . '</span>';
}

/** Contact form that posts to includes/contact.php. Style it with .cform in each template. */
function contact_form(string $class = 'cform'): string
{
    $sent = isset($_GET['sent']) ? (int)$_GET['sent'] : null;
    $back = e($_SERVER['PHP_SELF'] ?? '');
    $html  = '<form class="' . e($class) . '" method="post" action="includes/contact.php" id="contact">';
    if ($sent === 1) $html .= '<p class="flash ok">Thank you! We will get back to you soon.</p>';
    if ($sent === 0) $html .= '<p class="flash err">Please fill all fields with a valid email.</p>';
    $html .= '<input type="hidden" name="back" value="' . $back . '">';
    $html .= '<input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">';
    $html .= '<input type="text" name="name" placeholder="Your name" required>';
    $html .= '<input type="email" name="email" placeholder="Your email" required>';
    $html .= '<textarea name="message" rows="4" placeholder="How would you like to help?" required></textarea>';
    $html .= '<button type="submit">Send message</button></form>';
    return $html;
}

/** Shared <head> bits. $fonts is a Google Fonts "family=" query fragment. */
function head(string $title, string $fonts, string $prefix = ""): void
{
    global $ngo; ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($ngo['name']) ?> — <?= e($title) ?></title>
<meta name="description" content="<?= e($ngo['mission']) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?<?= $fonts ?>&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $prefix ?>assets/common.css">
<?php }
