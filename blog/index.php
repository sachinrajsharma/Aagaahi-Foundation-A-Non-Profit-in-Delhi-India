<?php
/**
 * /blog/ and /blog/<slug>/: the blog, read from the database (or the repo files if the database is off).
 * Needs the .htaccess rewrite in the site root (Apache/LiteSpeed). Staff signed in to the dashboard can add ?preview=1 to see drafts.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/blog.php';

$slug = trim((string)($_GET['slug'] ?? ''), '/');
$preview = !empty($_GET['preview']) && staff() !== null;
$post = null; $posts = [];
if ($slug === '') {
    $posts = content_posts(100);
} else {
    $post = content_post($slug, $preview);
    if (!$post) {
        $to = content_redirect($slug);
        if ($to) { header('Location: /blog/' . rawurlencode($to) . '/', true, 301); exit; }
        http_response_code(404);
    } elseif ($slug !== '' && !str_ends_with(parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '', '/') && !$preview) {
        // one address per post: /blog/my-post -> /blog/my-post/
        header('Location: /blog/' . rawurlencode($slug) . '/', true, 301); exit;
    }
}
$base = site_base_url();
$title = $post ? $post['meta_title'] : ($slug === '' ? 'Blog' : 'Post not found');
$desc = $post ? $post['meta_desc'] : 'Practical guides on online safety, hygiene, road safety and workplace rights from ' . $ngo['name'] . ', a non-profit in Delhi.';
$canonical = $base . '/blog/' . ($post ? $post['slug'] . '/' : '');
$ogImage = $post && $post['cover'] ? (str_starts_with($post['cover'], 'http') ? $post['cover'] : $base . $post['cover']) : '';
$noindex = $preview || ($post && $post['noindex']) || (!$post && $slug !== '');
$absolute = fn(string $c): string => str_starts_with($c, 'http') ? $c : $base . $c;
?><!DOCTYPE html>
<html lang="en">
<head>
<base href="/">
<?php head($title, 'family=Nunito:wght@400;600;800&family=Fraunces:wght@600;800', '', $desc); ?>
<link rel="stylesheet" href="assets/site.css">
<meta name="robots" content="<?= $noindex ? 'noindex, nofollow' : 'index, follow' ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:site_name" content="<?= e($ngo['name']) ?>"><meta property="og:type" content="<?= $post ? 'article' : 'website' ?>">
<meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($desc) ?>"><meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $ogImage ? 'summary_large_image' : 'summary' ?>">
<?php if ($post): $org = ['@type' => 'Organization', 'name' => $ngo['name'], 'url' => $base . '/'];
  $ld = [['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => mb_substr($post['title'], 0, 110), 'description' => $post['meta_desc'],
          'image' => $post['cover'] ? [$absolute($post['cover'])] : null, 'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical], 'datePublished' => $post['date'] ?: null,
          'dateModified' => $post['updated'] ?: ($post['date'] ?: null), 'author' => $org, 'publisher' => $org, 'articleSection' => $post['category'] ?: null, 'keywords' => $post['keyphrase'] ?: null, 'inLanguage' => 'en-IN'],
         ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
           ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $base . '/'], ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => $base . '/blog/'],
           ['@type' => 'ListItem', 'position' => 3, 'name' => $post['title'], 'item' => $canonical]]]];
  if ($post['faqs']) $ld[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(fn($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $post['faqs'])];
  foreach ($ld as $node): ?>
<script type="application/ld+json"><?= str_replace('<', '\\u003c', json_encode(array_filter($node, fn($v) => $v !== null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></script>
<?php endforeach; endif; ?>
</head>
<body>
<nav><div class="wrap"><a class="brand" href="/"><?= logo() ?> <?= e($ngo['name']) ?></a>
  <div><a class="m" href="/#work">Our Work</a><a class="m" href="/study/">Study</a><a class="m" href="/blog/">Blog</a><a class="m" href="/#contact">Contact</a> <span class="navauth"><a class="m auth" data-auth="in" href="/study/#/login">Log in</a><a class="btn" data-auth="in" style="margin-left:1rem;padding:.6rem 1.3rem" href="/study/#/join">Create account</a><a class="btn" data-auth="out" hidden style="margin-left:1rem;padding:.6rem 1.3rem" href="/study/#/me">My progress</a></span></div></div></nav>
<div class="wrap powered-row"><div class="powered">Powered by <a href="https://vidaara.org" target="_blank" rel="noopener">vidaara.org</a></div></div>
<section><div class="wrap" style="max-width:<?= $post ? '820' : '1040' ?>px">
<?php if ($preview && $post && $post['status'] !== 'published'): ?><p class="st-msg err">Preview: this post is a <b><?= e($post['status']) ?></b> and is not visible to visitors.</p><?php endif; ?>
<?php if ($post): ?>
  <nav class="crumbs"><a href="/">Home</a> <i>›</i> <a href="/blog/">Blog</a> <i>›</i> <span><?= e(mb_strimwidth($post['title'], 0, 48, '…')) ?></span></nav>
  <article class="article">
    <?php if ($post['category']): ?><p class="post-cat"><?= e($post['category']) ?></p><?php endif; ?>
    <h1><?= e($post['title']) ?></h1>
    <p class="post-meta">By <?= e($post['author']) ?><?= $post['date'] ? ' · ' . e(date('j F Y', strtotime($post['date']))) : '' ?> · <?= (int)$post['minutes'] ?> min read</p>
    <?php if ($post['cover']): ?><figure class="post-cover"><img src="<?= e($post['cover']) ?>" alt="<?= e($post['cover_alt']) ?>"></figure><?php endif; ?>
    <?= $post['content'] ?>
    <?php if ($post['faqs'] && !str_contains($post['content'], '<h3>' . e($post['faqs'][0]['q']))): foreach ($post['faqs'] as $f): ?><h3><?= e($f['q']) ?></h3><p><?= e($f['a']) ?></p><?php endforeach; endif; ?>
  </article>
  <div class="study-band" style="margin:2.5rem 0 0"><h2>Want to learn more?</h2><p>Students in Class 1 to 12 can study for free on our Study page, and we run sessions for schools and workplaces.</p><a class="btn" href="/study/">Start studying</a></div>
  <?php $more = array_values(array_filter(content_posts(4), fn($x) => $x['slug'] !== $post['slug'])); if ($more): ?>
    <h2 style="margin-top:2.5rem">More from our blog</h2><div class="post-grid"><?php foreach (array_slice($more, 0, 3) as $m) echo blog_card($m, '/blog/' . $m['slug'] . '/'); ?></div>
  <?php endif; ?>
<?php elseif ($slug === ''): ?>
  <h1 style="color:var(--g1)">Blog</h1>
  <?php if ($posts): ?><p class="st-sub">Practical guides from our programmes in Delhi.</p><div class="post-grid"><?php foreach ($posts as $p) echo blog_card($p, '/blog/' . $p['slug'] . '/'); ?></div>
  <?php else: ?><p class="st-sub">Stories and updates from <?= e($ngo['name']) ?> will appear here soon.</p><?php endif; ?>
<?php else: ?>
  <h1 style="color:var(--g1)">We could not find that post</h1><p><a href="/blog/">See all posts</a></p>
<?php endif; ?>
</div></section>
<footer>© <?= date('Y') ?> <?= e($ngo['name']) ?> · <?= e($ngo['reg_note']) ?><?php if (!empty($ngo['donate_url']) && $ngo['donate_url'] !== '#contact'): ?> · <a href="<?= e($ngo['donate_url']) ?>" rel="noopener">Donate</a><?php endif; ?></footer>
<script src="assets/nav.js"></script>
<?= blog_tracker($post ? 'blog' : 'blog_index', $slug) ?>
</body>
</html>
