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
if (is_file(__DIR__ . '/config.local.php')) require __DIR__ . '/config.local.php';

/** Study section: free Class 1-12 lessons and tests, served by Vidaara's public content API. */
$ngo['study'] = [
    'api_base' => 'https://vidaara.org/api/v1',
];
