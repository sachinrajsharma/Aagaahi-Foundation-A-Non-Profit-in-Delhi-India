<?php require_once __DIR__ . '/includes/helpers.php'; require_once __DIR__ . '/includes/events.php'; require_once __DIR__ . '/includes/blog.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php head('Home', 'family=Nunito:wght@400;600;800&family=Fraunces:wght@600;800'); ?>
<link rel="stylesheet" href="assets/site.css">
</head>
<body>
<?php $areas = events_data(); $nProg = 0; $nPics = 0; foreach ($areas as $a) { $nProg += count($a["programs"]); foreach ($a["programs"] as $p) $nPics += count($p["images"]); } ?>
<nav><div class="wrap"><a class="brand" href="#"><?= logo() ?> <?= e($ngo["name"]) ?></a>
  <div><a class="m" href="#work">Our Work</a><a class="m" href="study/">Study</a><?php if (blog_enabled()) : ?><a class="m" href="<?= e($ngo["blog"]["blog_url"]) ?>">Blog</a><?php endif; ?><a class="m" href="#contact">Contact</a> <a class="btn" style="margin-left:1.2rem;padding:.6rem 1.3rem" href="<?= e($ngo["donate_url"]) ?>">Support us</a></div></div></nav>
<div class="wrap powered-row"><div class="powered">Powered by <a href="https://vidaara.org" target="_blank" rel="noopener">vidaara.org</a></div></div>

<header class="hero"><div class="wrap">
  <div class="reveal">
    <span class="tag">🌿 A non-profit in Delhi, India</span>
    <h1><?= e($ngo["tagline"]) ?></h1>
    <p><?= e($ngo["mission"]) ?></p>
    <p style="margin-top:1.8rem"><a class="btn" href="#work">See our work</a> <a class="btn o" href="#contact">Get involved</a></p>
  </div>
  <div class="blobwrap reveal"><div class="blobimg<?= $ngo["hero_image"] ? " img" : "" ?>" <?= $ngo["hero_image"] ? "style=\"background-image:url(" . e($ngo["hero_image"]) . ")\"" : "" ?>><span><?= e($ngo["logo"]) ?></span></div>
    <span class="leaf l1">🍃</span><span class="leaf l2">🌼</span></div>
</div>
<svg class="wave" viewBox="0 0 1440 110" preserveAspectRatio="none"><path fill="#14532d" d="M0 60C300 130 500 0 800 50s480 50 640-20v80H0z"/></svg>
</header>

<div class="strip"><div class="wrap">
  <div><b data-count="<?= count($areas) ?>" data-suffix="">0</b>Focus areas</div>
  <div><b data-count="<?= $nProg ?>" data-suffix="">0</b>Programmes &amp; events</div>
  <div><b data-count="<?= $nPics ?>" data-suffix="">0</b>Moments captured</div>
</div></div>

<section><div class="wrap"><div class="study-band"><h2>Free study for Class 1 to 12</h2><p>Lessons, worked examples and practice tests in Maths, Science, English and more. Study right here, no sign-up needed.</p><a class="btn" href="study/">Start studying</a></div></div></section>

<section id="work"><div class="wrap">
  <h2 class="reveal">Our work, in pictures</h2><p>Highlights from programmes and events we have run in the community.</p>
  <?= events_html() ?>
</div></section>

<?= blog_latest_html() ?>

<section id="contact"><div class="wrap contact">
  <div><h2>Come grow with us</h2><p>Volunteer, partner with us, or bring your skills.</p><p><?php if ($ngo["email"]) echo "📧 " . e($ngo["email"]) . "<br>"; if ($ngo["phone"]) echo "📞 " . e($ngo["phone"]) . "<br>"; ?>📍 <?= e($ngo["address"]) ?></p></div>
  <?= contact_form() ?>
</div></section>
<footer>© <?= date("Y") ?> <?= e($ngo["name"]) ?> · <?= e($ngo["reg_note"]) ?></footer>
<script src="assets/common.js"></script>
<script src="assets/gallery.js"></script>
<?= blog_tracker("home") ?>
</body>
</html>
