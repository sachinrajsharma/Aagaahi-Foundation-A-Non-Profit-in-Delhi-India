<?php
/**
 * includes/content.php — what the PUBLIC website shows: blog posts and the event gallery.
 *
 * Source of truth is the database when it is configured and the tables exist (so dashboard edits go live at once).
 * If the database is off or unreachable, the site falls back to the files in this repo (includes/posts.json and
 * includes/events.json), so it never goes blank.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

/** SQL condition for "visible to visitors": published, or scheduled and its time has come. */
const POST_VISIBLE_SQL = "((p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= NOW())) OR (p.status = 'scheduled' AND p.published_at IS NOT NULL AND p.published_at <= NOW()))";

function content_iso(?string $dt): string
{
    if (!$dt) return '';
    $t = strtotime($dt);
    return $t ? date('c', $t) : '';
}

/** Turns a posts row into the shape the templates use. */
function content_normalize_post(array $r): array
{
    $faqs = is_string($r['faqs'] ?? null) ? (json_decode($r['faqs'], true) ?: []) : ($r['faqs'] ?? []);
    return [
        'slug' => $r['slug'], 'title' => $r['title'], 'excerpt' => (string)($r['excerpt'] ?? ''), 'content' => (string)$r['content'],
        'cover' => (string)($r['cover_image'] ?? ''), 'cover_alt' => (string)($r['cover_alt'] ?? ''), 'category' => (string)($r['category'] ?? ''),
        'author' => (string)($r['author_name'] ?? 'Aagahi Foundation'), 'meta_title' => (string)($r['meta_title'] ?: $r['title']), 'meta_desc' => (string)($r['meta_desc'] ?: ($r['excerpt'] ?? '')),
        'keyphrase' => (string)($r['focus_keyword'] ?? ''), 'faqs' => $faqs, 'date' => content_iso($r['published_at'] ?? $r['created_at'] ?? null),
        'updated' => content_iso($r['updated_at'] ?? null), 'noindex' => !empty($r['noindex']), 'status' => (string)($r['status'] ?? 'published'),
        'minutes' => max(1, (int)round(str_word_count(strip_tags((string)$r['content'])) / 200)),
    ];
}

function content_json_posts(): array
{
    $f = __DIR__ . '/posts.json';
    $d = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
    $out = [];
    foreach ((is_array($d) ? ($d['posts'] ?? []) : []) as $p) {
        $out[] = content_normalize_post([
            'slug' => $p['slug'], 'title' => $p['title'], 'excerpt' => $p['excerpt'] ?? '', 'content' => $p['content'] ?? '',
            'cover_image' => !empty($p['cover_rel']) ? '/' . ltrim($p['cover_rel'], '/') : ($p['cover'] ?? ''), 'cover_alt' => $p['cover_alt'] ?? '', 'category' => $p['category'] ?? '',
            'author_name' => $p['author'] ?? '', 'meta_title' => $p['meta_title'] ?? '', 'meta_desc' => $p['meta_desc'] ?? '', 'focus_keyword' => $p['keyphrase'] ?? '',
            'faqs' => $p['faqs'] ?? [], 'published_at' => $p['date'] ?? null, 'updated_at' => $p['date'] ?? null,
        ]);
    }
    usort($out, fn($a, $b) => strcmp($b['date'], $a['date']));
    return $out;
}

/** Visible posts, newest first. */
function content_posts(int $limit = 100): array
{
    $pdo = db_try();
    if ($pdo) {
        try {
            $st = $pdo->query('SELECT p.*, c.name AS category FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE ' . POST_VISIBLE_SQL
                . ' AND p.noindex = 0 ORDER BY p.published_at DESC, p.id DESC LIMIT ' . (int)$limit);
            return array_map('content_normalize_post', $st->fetchAll());
        } catch (Throwable $e) { error_log('[aagahi] posts query failed, using files: ' . $e->getMessage()); }
    }
    return array_slice(content_json_posts(), 0, $limit);
}

/** One post by address. $preview lets signed-in staff see drafts and scheduled posts. */
function content_post(string $slug, bool $preview = false): ?array
{
    $pdo = db_try();
    if ($pdo) {
        try {
            $st = $pdo->prepare('SELECT p.*, c.name AS category FROM posts p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ?' . ($preview ? '' : ' AND ' . POST_VISIBLE_SQL));
            $st->execute([$slug]);
            $r = $st->fetch();
            return $r ? content_normalize_post($r) : null;
        } catch (Throwable $e) { error_log('[aagahi] post query failed, using files: ' . $e->getMessage()); }
    }
    foreach (content_json_posts() as $p) if ($p['slug'] === $slug) return $p;
    return null;
}

/** Where an old post address should now point (or null). */
function content_redirect(string $slug): ?string
{
    $pdo = db_try();
    if (!$pdo) return null;
    try { $st = $pdo->prepare('SELECT to_slug FROM redirects WHERE from_slug = ?'); $st->execute([$slug]); $t = $st->fetchColumn(); return $t ? (string)$t : null; }
    catch (Throwable $e) { return null; }
}

function content_json_events(): array
{
    $d = json_decode((string)@file_get_contents(__DIR__ . '/events.json'), true);
    $areas = [];
    foreach (($d['areas'] ?? []) as $a) {
        $progs = [];
        foreach ($a['programs'] as $p) {
            $imgs = [];
            foreach ($p['images'] as $im) { $b = '/assets/img/' . $p['slug'] . '/' . $im['n']; $imgs[] = ['src' => $b . '.webp', 'thumb' => $b . '-t.webp', 'w' => (int)$im['w'], 'h' => (int)$im['h'], 'alt' => $im['alt'] ?? $p['title']]; }
            $progs[] = ['slug' => $p['slug'], 'title' => $p['title'], 'text' => $p['text'] ?? '', 'images' => $imgs];
        }
        $areas[] = ['id' => $a['id'], 'title' => $a['title'], 'intro' => $a['intro'] ?? '', 'programs' => $progs];
    }
    return $areas;
}

/** Gallery areas -> programmes -> images. */
function content_events(): array
{
    $pdo = db_try();
    if ($pdo) {
        try {
            $ev = $pdo->query('SELECT * FROM events WHERE is_published = 1 ORDER BY area_order, area, sort_order, id')->fetchAll();
            $ph = $pdo->query('SELECT p.* FROM event_photos p JOIN events e ON e.id = p.event_id WHERE e.is_published = 1 ORDER BY p.sort_order, p.id')->fetchAll();
            $by = [];
            foreach ($ph as $p) $by[$p['event_id']][] = ['src' => $p['path'], 'thumb' => $p['thumb_path'], 'w' => (int)$p['width'], 'h' => (int)$p['height'], 'alt' => (string)($p['alt'] ?? '')];
            $areas = []; $idx = [];
            foreach ($ev as $e) {
                if (!isset($idx[$e['area']])) { $idx[$e['area']] = count($areas); $areas[] = ['id' => slugify_text_safe($e['area']), 'title' => $e['area'], 'intro' => (string)($e['area_intro'] ?? ''), 'programs' => []]; }
                $a = &$areas[$idx[$e['area']]];
                if ($a['intro'] === '' && !empty($e['area_intro'])) $a['intro'] = $e['area_intro'];
                $a['programs'][] = ['slug' => $e['slug'], 'title' => $e['title'], 'text' => (string)($e['description'] ?? ''), 'images' => $by[$e['id']] ?? []];
                unset($a);
            }
            return $areas;
        } catch (Throwable $e) { error_log('[aagahi] gallery query failed, using files: ' . $e->getMessage()); }
    }
    return content_json_events();
}

function slugify_text_safe(string $s): string
{
    $s = strtolower(trim(str_replace('&', ' and ', $s)));
    return trim(preg_replace('/[^a-z0-9]+/', '-', $s) ?? '', '-') ?: 'area';
}

/** The public address of this website (no trailing slash): config first, then the request. */
function site_base_url(): string
{
    global $ngo;
    $u = rtrim((string)($ngo['site_url'] ?? ''), '/');
    if ($u !== '') return $u;
    return (is_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
