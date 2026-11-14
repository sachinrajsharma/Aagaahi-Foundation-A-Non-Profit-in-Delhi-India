<?php
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/content.php';
$base = site_base_url();
header('Content-Type: application/xml; charset=utf-8');
$urls = [[$base . '/', null], [$base . '/study/', null], [$base . '/blog/', null]];
foreach (content_posts(1000) as $p) $urls[] = [$base . '/blog/' . $p['slug'] . '/', ($p['updated'] ?: $p['date']) ? date('Y-m-d', strtotime($p['updated'] ?: $p['date'])) : null];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$u, $m]) echo '  <url><loc>' . htmlspecialchars($u, ENT_XML1) . '</loc>' . ($m ? '<lastmod>' . $m . '</lastmod>' : '') . '</url>' . "\n";
echo '</urlset>' . "\n";
