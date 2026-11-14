<?php
/** Blog integration: talks to the BlogForge engine (blog-engine/). Everything is a no-op until engine_url is set. */
require_once __DIR__ . '/../blog-engine/clients/php/src/BlogForge.php';

function blog_enabled(): bool
{
    global $ngo;
    return !empty($ngo['blog']['engine_url']) && !empty($ngo['blog']['api_key']);
}

function blog_client(): BlogForge
{
    global $ngo;
    return new BlogForge($ngo['blog']['engine_url'], $ngo['blog']['api_key'], $ngo['blog']['api_secret'] ?? '');
}

/** Latest posts, cached for 5 minutes in the system temp dir so the homepage stays fast. */
function blog_latest(int $n = 3): array
{
    global $ngo;
    if (!blog_enabled()) return [];
    $cache = sys_get_temp_dir() . '/aagahi-blog-' . md5($ngo['blog']['engine_url']) . "-$n.json";
    if (is_file($cache) && time() - filemtime($cache) < 300) {
        return json_decode(file_get_contents($cache), true) ?: [];
    }
    try {
        $posts = blog_client()->listPosts(['limit' => $n])['posts'] ?? [];
        file_put_contents($cache, json_encode($posts));
        return $posts;
    } catch (Exception $e) {
        return [];
    }
}

/** First-party analytics beacon (public site slug only; never the secret). */
function blog_tracker(string $pageType = '', string $slug = ''): string
{
    global $ngo;
    $b = $ngo['blog'];
    if (empty($b['engine_url']) || empty($b['site_slug'])) return '';
    $engine = e(rtrim($b['engine_url'], '/'));
    return '<script src="' . $engine . '/js/track.js" data-site="' . e($b['site_slug']) . '" data-engine="' . $engine . '"'
        . ($pageType ? ' data-page-type="' . e($pageType) . '"' : '') . ($slug !== '' ? ' data-slug="' . e($slug) . '"' : '') . ' defer></script>';
}

function blog_latest_html(): string
{
    $posts = blog_latest(3);
    if (!$posts) return '';
    $o = '<section id="blog"><div class="wrap"><h2 class="reveal">Latest from our blog</h2><div class="grid">';
    foreach ($posts as $p) {
        $o .= '<a class="card reveal" href="/blog/' . e($p['slug']) . '"><h3>' . e($p['title']) . '</h3><p>' . e($p['excerpt'] ?? '') . '</p></a>';
    }
    return $o . '</div><p style="margin-top:1.5rem"><a class="btn o" href="/blog">All posts</a></p></div></section>';
}

/** Posts kept in includes/posts.json (used when the BlogForge engine is not connected). Newest first. */
function local_posts(): array
{
    $f = __DIR__ . '/posts.json';
    $d = is_file($f) ? json_decode(file_get_contents($f), true) : null;
    $posts = is_array($d) && isset($d['posts']) && is_array($d['posts']) ? $d['posts'] : [];
    usort($posts, function ($a, $b) { return strcmp($b['date'] ?? '', $a['date'] ?? ''); });
    return $posts;
}
