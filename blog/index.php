<?php
/**
 * /blog and /blog/{slug}: renders BlogForge posts inside the site's own layout (best for SEO: content stays on
 * this domain). Needs the .htaccess rewrite in the repo root (Apache) or an equivalent nginx try_files rule.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/blog.php';

$slug = trim($_GET['slug'] ?? '', '/');
$post = null;
$posts = [];
if (blog_enabled()) {
    try {
        if ($slug === '') $posts = blog_client()->listPosts(['limit' => 30])['posts'] ?? [];
        else $post = blog_client()->getPost($slug)['post'];
    } catch (Exception $e) {
        http_response_code(404);
    }
} else {
    http_response_code(404);
}
$title = $post['meta']['title'] ?? $post['title'] ?? 'Blog';
?><!DOCTYPE html>
<html lang="en">
<head>
<base href="/">
<?php head($title, 'family=Nunito:wght@400;600;800&family=Fraunces:wght@600;800'); ?>
<link rel="stylesheet" href="assets/site.css">
<?php if ($post): ?>
<meta name="description" content="<?= e($post['meta']['description'] ?? '') ?>">
<link rel="canonical" href="<?= e($post['meta']['canonical'] ?? '') ?>">
<?= $post['json_ld'] ?? '' ?>
<?php endif; ?>
</head>
<body>
<div class="powered">Powered by <a href="https://vidaara.org" target="_blank" rel="noopener">vidaara.org</a></div>
<nav><div class="wrap"><a class="brand" href="/"><?= logo() ?> <?= e($ngo['name']) ?></a>
  <div><a class="m" href="/#work">Our Work</a><a class="m" href="/blog">Blog</a><a class="m" href="/#contact">Contact</a></div></div></nav>
<section><div class="wrap" style="max-width:820px">
<?php if ($post): ?>
  <article><h1 style="font-size:2.4rem;color:var(--g1)"><?= e($post['title']) ?></h1><?= $post['content'] ?></article>
<?php elseif ($slug === '' && $posts): ?>
  <h1 style="color:var(--g1)">Blog</h1>
  <div class="grid"><?php foreach ($posts as $p): ?><a class="card" href="/blog/<?= e($p['slug']) ?>"><h3><?= e($p['title']) ?></h3><p><?= e($p['excerpt'] ?? '') ?></p></a><?php endforeach; ?></div>
<?php else: ?>
  <h1 style="color:var(--g1)">Blog</h1><p><?= blog_enabled() ? 'Nothing here yet.' : 'The blog is not connected yet.' ?></p>
<?php endif; ?>
</div></section>
<footer>© <?= date('Y') ?> <?= e($ngo['name']) ?></footer>
<?= blog_tracker($post ? 'blog' : 'blog_index', $slug) ?>
</body>
</html>
