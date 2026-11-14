<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/blog.php';
require_once __DIR__ . '/../includes/study.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<?php head('Free Study, Class 1 to 12', 'family=Nunito:wght@400;600;800&family=Fraunces:wght@600;800', '../'); ?>
<link rel="stylesheet" href="../assets/site.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.css">
</head>
<body>
<nav><div class="wrap"><a class="brand" href="../"><?= logo() ?> <?= e($ngo['name']) ?></a>
  <div><a class="m" href="../#work">Our Work</a><a class="m" href="./">Study</a><a class="m" href="../#contact">Contact</a></div></div></nav>
<div class="wrap powered-row"><div class="powered">Powered by <a href="https://vidaara.org" target="_blank" rel="noopener">vidaara.org</a></div></div>
<main class="wrap study-hero"><span class="tag">🎓 Free for every student</span><h1>Study with Aagahi</h1>
  <p class="st-sub">Lessons, worked examples and practice tests for Class 1 to 12: Maths, Science, Physics, Chemistry, Biology, English and more. Study freely, or create a free account to save your progress.</p>
  <div id="study" data-api="<?= e(study_api()) ?>" data-partner-code="<?= e($ngo['study']['partner_code'] ?? '') ?>"><p class="st-msg">Loading…</p></div></main>
<footer>© <?= date('Y') ?> <?= e($ngo['name']) ?> · Study content by <a href="https://vidaara.org" target="_blank" rel="noopener">vidaara.org</a></footer>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/katex.min.js"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/KaTeX/0.16.9/contrib/auto-render.min.js"></script>
<script defer src="../assets/study.js"></script>
<?= blog_tracker('study') ?>
</body>
</html>
