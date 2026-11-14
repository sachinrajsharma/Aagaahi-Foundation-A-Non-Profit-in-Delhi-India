<?php
/**
 * example-blog.php — render the BlogForge blog inside a plain PHP site, on your
 * OWN domain (best for SEO). Two routes in one file:
 *   /blog            → list published posts
 *   /blog/{slug}     → full post (HTML content + JSON-LD injected in <head>)
 *
 * Point your router/rewrite so both map here. Add the tracking beacon
 * (js/track.js) to your layout so these views count in the dashboard.
 */
require __DIR__ . '/src/BlogForge.php';

$bf = new BlogForge('http://localhost:4200', 'pk_XXXXXXXX', 'sk_XXXXXXXX');

// Naive router: everything after /blog.
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');       // e.g. "blog/my-slug"
$parts = explode('/', $path);
$slug = $parts[1] ?? '';

try {
    if ($slug === '') {
        $data = $bf->listPosts(['limit' => 30]);
        echo "<h1>Blog</h1>";
        foreach ($data['posts'] as $p) {
            printf('<article><h2><a href="/blog/%s">%s</a></h2><p>%s</p></article>',
                htmlspecialchars($p['slug']), htmlspecialchars($p['title']), htmlspecialchars($p['excerpt']));
        }
    } else {
        $data = $bf->getPost($slug);
        $p = $data['post'];
        // In your real layout, put these in <head>:
        echo '<title>' . htmlspecialchars($p['meta']['title'] ?? $p['title']) . "</title>\n";
        echo '<meta name="description" content="' . htmlspecialchars($p['meta']['description'] ?? '') . "\">\n";
        echo '<link rel="canonical" href="' . htmlspecialchars($p['meta']['canonical']) . "\">\n";
        echo $p['json_ld'] . "\n";                               // BlogPosting structured data
        // Body:
        echo '<article><h1>' . htmlspecialchars($p['title']) . '</h1>';
        echo $p['content'];                                      // already sanitized by the engine
        echo '</article>';
    }
} catch (Exception $e) {
    http_response_code(404);
    echo 'Post not found.';
}
