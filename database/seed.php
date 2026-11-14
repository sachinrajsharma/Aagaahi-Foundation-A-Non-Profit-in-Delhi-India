<?php
/**
 * database/seed.php — puts the website's starting content into the database, and creates staff accounts.
 * Safe to run more than once: anything that already exists (same slug / key / email) is left alone.
 *
 *   settings   from includes/config.php (name, tagline, contact details, ...) + a generated app secret
 *   categories and posts   from includes/posts.json   (built from includes/posts/*.html by tools/build-static.js)
 *   events and photos      from includes/events.json  (the "Our work, in pictures" gallery)
 *
 * Links and image paths inside imported posts are made root-relative (/study/, /assets/img/...), so the content keeps
 * working when the website moves to another domain.
 */
require_once __DIR__ . '/../includes/db.php';

function slugify_text(string $s): string
{
    $s = strtolower(trim(str_replace('&', ' and ', $s)));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return trim($s, '-') ?: 'item';
}

function ist(string $iso): string
{
    try { return (new DateTime($iso))->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('Y-m-d H:i:s'); }
    catch (Throwable $e) { return date('Y-m-d H:i:s'); }
}

/** @return int number of settings added */
function seed_settings(PDO $pdo, array $ngo): int
{
    $values = [
        'site_name'  => $ngo['name'] ?? '', 'short_name' => $ngo['short'] ?? '', 'tagline' => $ngo['tagline'] ?? '', 'mission' => $ngo['mission'] ?? '',
        'email'      => $ngo['email'] ?? '', 'notify_email' => $ngo['email'] ?? '', 'phone' => $ngo['phone'] ?? '', 'address' => $ngo['address'] ?? '',
        'donate_url' => $ngo['donate_url'] ?? '', 'reg_note' => $ngo['reg_note'] ?? '', 'hero_image' => $ngo['hero_image'] ?? '',
        'site_url'   => $ngo['site_url'] ?? '', 'social' => json_encode($ngo['social'] ?? [], JSON_UNESCAPED_SLASHES),
        'app_secret' => bin2hex(random_bytes(32)),
    ];
    $st = $pdo->prepare('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)');
    $n = 0;
    foreach ($values as $k => $v) { $st->execute([$k, $v]); $n += $st->rowCount(); }
    return $n;
}

/** @return array{categories:int, posts:int, skipped:int} */
function seed_posts(PDO $pdo, string $postsFile, string $siteUrl = ''): array
{
    $out = ['categories' => 0, 'posts' => 0, 'skipped' => 0];
    if (!is_file($postsFile)) return $out;
    $data = json_decode((string)file_get_contents($postsFile), true);
    $posts = is_array($data) && isset($data['posts']) ? $data['posts'] : [];
    // the address that build-static baked into the content (falls back to the configured one)
    $site = rtrim((string)($data['site_url'] ?? '') ?: $siteUrl, '/');
    $catId = $pdo->prepare('SELECT id FROM categories WHERE slug = ?');
    $catIns = $pdo->prepare('INSERT INTO categories (name, slug, sort_order) VALUES (?, ?, ?)');
    $exists = $pdo->prepare('SELECT id FROM posts WHERE slug = ?');
    $ins = $pdo->prepare('INSERT INTO posts (slug, title, excerpt, content, cover_image, cover_alt, category_id, author_name, meta_title, meta_desc, focus_keyword, faqs, status, published_at)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?,\'published\',?)');
    $rel = function (?string $s) use ($site): string {            // absolute URL of this site -> root-relative path
        $s = (string)$s;
        if ($site !== '' && str_starts_with($s, $site . '/')) return substr($s, strlen($site));
        return $s;
    };
    foreach ($posts as $i => $p) {
        $exists->execute([$p['slug']]);
        if ($exists->fetchColumn()) { $out['skipped']++; continue; }
        $categoryId = null;
        if (!empty($p['category'])) {
            $cs = slugify_text($p['category']);
            $catId->execute([$cs]);
            $categoryId = $catId->fetchColumn() ?: null;
            if (!$categoryId) { $catIns->execute([$p['category'], $cs, count($posts) + $out['categories']]); $categoryId = (int)$pdo->lastInsertId(); $out['categories']++; }
        }
        $content = $site !== '' ? str_replace($site . '/', '/', (string)$p['content']) : (string)$p['content'];
        $cover = !empty($p['cover_rel']) ? '/' . ltrim($p['cover_rel'], '/') : $rel($p['cover'] ?? '');
        $ins->execute([
            $p['slug'], $p['title'], $p['excerpt'] ?? null, $content, $cover ?: null, $p['cover_alt'] ?? null, $categoryId, $p['author'] ?? 'Aagahi Foundation',
            $p['meta_title'] ?? null, $p['meta_desc'] ?? null, $p['keyphrase'] ?? null, isset($p['faqs']) ? json_encode($p['faqs'], JSON_UNESCAPED_UNICODE) : null,
            ist((string)$p['date']),
        ]);
        $out['posts']++;
    }
    return $out;
}

/** @return array{events:int, photos:int, skipped:int} */
function seed_events(PDO $pdo, string $eventsFile): array
{
    $out = ['events' => 0, 'photos' => 0, 'skipped' => 0];
    if (!is_file($eventsFile)) return $out;
    $data = json_decode((string)file_get_contents($eventsFile), true);
    $areas = is_array($data) && isset($data['areas']) ? $data['areas'] : [];
    $exists = $pdo->prepare('SELECT id FROM events WHERE slug = ?');
    $insE = $pdo->prepare('INSERT INTO events (area, area_order, area_intro, title, slug, description, sort_order, is_published) VALUES (?,?,?,?,?,?,?,1)');
    $insP = $pdo->prepare('INSERT INTO event_photos (event_id, path, thumb_path, width, height, alt, sort_order) VALUES (?,?,?,?,?,?,?)');
    foreach ($areas as $ai => $area) {
        foreach ($area['programs'] as $pi => $prog) {
            $exists->execute([$prog['slug']]);
            if ($exists->fetchColumn()) { $out['skipped']++; continue; }
            $insE->execute([$area['title'], $ai + 1, $area['intro'] ?? null, $prog['title'], $prog['slug'], $prog['text'] ?? null, $pi]);
            $eid = (int)$pdo->lastInsertId();
            $out['events']++;
            foreach ($prog['images'] as $k => $im) {
                $base = 'assets/img/' . $prog['slug'] . '/' . $im['n'];
                $insP->execute([$eid, '/' . $base . '.webp', '/' . $base . '-t.webp', (int)($im['w'] ?? 0), (int)($im['h'] ?? 0), $im['alt'] ?? $prog['title'], $k + 1]);
                $out['photos']++;
            }
        }
    }
    return $out;
}

/** @return array{settings:int, categories:int, posts:int, events:int, photos:int} */
function seed_all(PDO $pdo, array $ngo, ?string $includesDir = null): array
{
    $dir = $includesDir ?? __DIR__ . '/../includes';
    $s = seed_settings($pdo, $ngo);
    $p = seed_posts($pdo, $dir . '/posts.json', (string)($ngo['site_url'] ?? ''));
    $e = seed_events($pdo, $dir . '/events.json');
    return ['settings' => $s, 'categories' => $p['categories'], 'posts' => $p['posts'], 'events' => $e['events'], 'photos' => $e['photos']];
}

/** Creates a staff account. Throws InvalidArgumentException with a plain-language message when the input is not acceptable. */
function create_staff(PDO $pdo, string $name, string $email, string $password, string $role = 'editor'): int
{
    $name = trim($name); $email = strtolower(trim($email));
    if ($name === '' || mb_strlen($name) > 120) throw new InvalidArgumentException('Please enter a name (up to 120 characters).');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) throw new InvalidArgumentException('Please enter a valid email address.');
    if (!in_array($role, ['super_admin', 'admin', 'editor'], true)) throw new InvalidArgumentException('Unknown role.');
    if (mb_strlen($password) < 10) throw new InvalidArgumentException('The password must be at least 10 characters.');
    $dup = $pdo->prepare('SELECT id FROM staff_users WHERE email = ?');
    $dup->execute([$email]);
    if ($dup->fetchColumn()) throw new InvalidArgumentException('That email already has an account.');
    $pdo->prepare('INSERT INTO staff_users (name, email, password_hash, role) VALUES (?,?,?,?)')
        ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role]);
    return (int)$pdo->lastInsertId();
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        global $ngo;
        $r = seed_all(db_connect(false), $ngo);
        echo "Added: {$r['settings']} settings, {$r['categories']} categories, {$r['posts']} posts, {$r['events']} events, {$r['photos']} photos.\n";
    } catch (Throwable $e) { fwrite(STDERR, 'Seeding failed: ' . $e->getMessage() . "\n"); exit(1); }
}
