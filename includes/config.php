<?php
/**
 * ONE FILE TO EDIT PER NGO. Event photos/descriptions live in includes/events.json.
 */
$ngo = [
    'name'     => 'Aagahi Foundation',
    'short'    => 'Aagahi',
    'logo'     => '🌱',                       // emoji, or leave and use 'logo_img'
    'logo_img' => '',                         // e.g. 'assets/logo.png' (optional)
    'tagline'  => 'Awareness, skills and wellbeing for every community.',
    'mission'  => 'Aagahi Foundation is a Delhi-based non-profit working on financial literacy and cyber safety, '
                . 'health and hygiene, road safety, and skill development for women.',
    'hero_image' => 'assets/img/kids-hygiene/03.webp',
    'email'    => '',                         // shown on the page only when filled in
    'phone'    => '',
    'address'  => 'Delhi, India',
    'donate_url' => '#contact',               // replace with a UPI / Razorpay / PayPal link
    'reg_note' => 'Non-profit organisation • Delhi, India',
    'social' => [],
];

/**
 * Blog + SEO analytics (BlogForge engine, see /blog-engine).
 * Leave engine_url empty to hide the blog everywhere. Put real keys in includes/config.local.php
 * (git-ignored) so they are never committed:   $ngo['blog']['api_key'] = 'pk_...';
 */
$ngo['blog'] = [
    'engine_url' => '',   // e.g. https://blog.aagahi.org  (where blog-engine is hosted)
    'site_slug'  => '',   // site slug from the engine admin > Sites (public; used by the view beacon)
    'api_key'    => '',   // pk_... key from admin > Sites (server-side only; set in config.local.php)
    'api_secret' => '',   // sk_... (only needed for webhooks)
    'blog_url'   => '/blog',
];

/** Study section: free Class 1-12 lessons and tests, served by Vidaara's public content API. */
$ngo['study'] = [
    'api_base' => 'https://vidaara.org/api/v1',
    // This NGO's PUBLIC class code from Vidaara (teacher dashboard). Students who sign up on this site join that class
    // automatically, so the coordinator sees them in the gradebook. Leave empty to let students enter a code themselves.
    'partner_code' => '',
];

/**
 * Dashboard (BlogForge CMS, see /blog-engine): when 'url' is set, the Blog page and the "Our work" gallery show what the
 * team publishes in the dashboard. Leave 'url' empty to keep using includes/posts.json and includes/events.json.
 *   url  : where the dashboard runs, e.g. https://cms.yourdomain.org  (no trailing slash)
 *   site : the site's slug in the dashboard (Sites page)
 */
$ngo['cms'] = [
    'url'  => '',
    'site' => 'aagahi',
];

/** Public address of this website (no trailing slash): canonical URLs, sitemap.xml and links inside blog posts use it.
 *  Change it when you move to your own domain, then run: node tools/build-static.js */
$ngo['site_url'] = 'https://sachinrajsharma.github.io/Aagaahi-Foundation-A-Non-Profit-in-Delhi-India';

/**
 * Database (MySQL on Hostinger). Leave empty and the site runs without a database.
 * Put the real values in includes/config.local.php (git-ignored), never here:
 *   $ngo['db'] = ['host' => 'localhost', 'name' => 'u123456789_aagahi', 'user' => 'u123456789_aagahi', 'pass' => '...'];
 *   $ngo['install_token'] = 'a long phrase you invent';   // only needed while you run install/
 */
$ngo['db'] = ['host' => '', 'name' => '', 'user' => '', 'pass' => ''];   // host: leave empty for "localhost"
$ngo['install_token'] = '';

// Private overrides (passwords, keys, the real domain). Loaded last on purpose so it wins over everything above.
if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';
