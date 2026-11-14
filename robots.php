<?php
require_once __DIR__ . '/includes/content.php';
header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\nAllow: /\nDisallow: /admin/\nDisallow: /install/\nDisallow: /database/\nDisallow: /includes/\nDisallow: /uploads/tmp/\n\nSitemap: " . site_base_url() . "/sitemap.xml\n";
