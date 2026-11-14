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

/** One blog card (engine posts have cover_image, local posts have cover_rel). */
function blog_card(array $p, string $href): string
{
    $img = $p['cover_image'] ?? ($p['cover_rel'] ?? '');
    $cat = $p['category'] ?? '';
    $date = !empty($p['date']) ? date('j F Y', strtotime($p['date'])) : (!empty($p['published_at']) ? date('j F Y', strtotime($p['published_at'])) : '');
    return '<a class="post-card" href="' . e($href) . '">' . ($img ? '<img src="' . e($img) . '" alt="' . e($p['cover_alt'] ?? '') . '" loading="lazy">' : '')
        . '<div class="pc-body">' . ($cat ? '<span class="post-cat">' . e($cat) . '</span>' : '') . '<h3>' . e($p['title']) . '</h3><p>' . e($p['excerpt'] ?? '') . '</p>'
        . ($date ? '<small>' . e($date) . '</small>' : '') . '</div></a>';
}

function blog_latest_html(): string
{
    $posts = blog_latest(4);
    if (!$posts) $posts = array_slice(local_posts(), 0, 4);   // engine not connected: use the posts kept in this repo
    if (!$posts) return '';
    $o = '<section id="blog"><div class="wrap"><h2 class="reveal">From our blog</h2><p>Practical guides from our programmes: money and online safety, hygiene, road safety and workplace rights.</p><div class="post-grid">';
    foreach ($posts as $p) $o .= blog_card($p, '/blog/' . $p['slug']);
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
