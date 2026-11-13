<?php require_once __DIR__ . '/includes/helpers.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php head('Home', 'family=Nunito:wght@400;600;800&family=Fraunces:wght@600;800'); ?>
<style>
:root{--g1:#14532d;--g2:#22c55e;--sand:#fbf8ef;--ink:#1c2b21}
body{font-family:'Nunito',sans-serif;background:var(--sand);color:var(--ink)}
h1,h2,h3{font-family:'Fraunces',serif}
nav{padding:1.1rem 0}nav .wrap{display:flex;justify-content:space-between;align-items:center}
.brand{display:flex;gap:.5rem;align-items:center;font:800 1.3rem 'Fraunces';color:var(--g1)}
nav a.m{margin-left:1.5rem;font-weight:600}nav a.m:hover{color:var(--g2)}
.btn{display:inline-block;background:var(--g1);color:#fff;padding:.85rem 1.8rem;border-radius:99px;font-weight:800;transition:.25s}
.btn:hover{background:var(--g2);transform:translateY(-3px)}.btn.o{background:none;color:var(--g1);border:2px solid var(--g1)}.btn.o:hover{background:var(--g1);color:#fff}
.hero{position:relative;padding:3rem 0 9rem;overflow:hidden}
.hero .wrap{display:grid;grid-template-columns:1.1fr 1fr;gap:3rem;align-items:center}
.hero h1{font-size:clamp(2.4rem,5.5vw,4.4rem);line-height:1.05;margin:.6rem 0 1rem;color:var(--g1)}
.hero p{font-size:1.15rem;max-width:48ch}
.tag{background:#dcfce7;color:var(--g1);padding:.35rem 1rem;border-radius:99px;font-weight:800;font-size:.85rem}
.blobwrap{position:relative;aspect-ratio:1}
.blobimg{position:absolute;inset:0;background:linear-gradient(145deg,var(--g2),var(--g1));border-radius:62% 38% 55% 45%/48% 58% 42% 52%;animation:morph 9s ease-in-out infinite;display:grid;place-items:center;font-size:9rem;background-size:cover;background-position:center}
.blobimg.img span{display:none}
@keyframes morph{50%{border-radius:40% 60% 38% 62%/58% 40% 60% 42%}}
.leaf{position:absolute;font-size:2.6rem;animation:sway 4s ease-in-out infinite}.l1{top:5%;left:-2%}.l2{bottom:8%;right:0;animation-delay:-2s}
@keyframes sway{50%{transform:rotate(14deg) translateY(-8px)}}
.wave{position:absolute;bottom:-1px;left:0;width:100%;height:110px}
.strip{background:var(--g1);color:#fff;padding:0 0 4rem}
.strip .wrap{display:grid;grid-template-columns:repeat(4,1fr);text-align:center;gap:1rem}
.strip b{font:800 2.8rem 'Fraunces';color:#86efac;display:block}
section{padding:5rem 0}
h2{font-size:clamp(1.8rem,4vw,2.8rem);color:var(--g1);margin:0 0 .5rem}
.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:1.5rem;margin-top:2.5rem}
.card{background:#fff;padding:2rem;border-radius:30px 30px 30px 6px;box-shadow:0 8px 30px #14532d14;transition:.3s;border-bottom:5px solid var(--g2)}
.card:hover{transform:translateY(-8px) rotate(-.6deg)}
.card i{font-style:normal;font-size:2.4rem}.card h3{margin:.6rem 0 .3rem;color:var(--g1)}.card p{margin:0;color:#4b5f52}
.soil{background:#ecfdf3;border-radius:60px;padding:4rem 3rem;margin:0 1rem}
.q{background:#fff;padding:1.8rem;border-radius:24px;border-left:6px solid var(--g2)}
.q p{margin:0 0 .8rem;font-style:italic}.q small{color:#5d7064}
.contact{display:grid;grid-template-columns:1fr 1fr;gap:3rem}
.cform input,.cform textarea{padding:.95rem 1.2rem;border:2px solid #cdeedb;border-radius:20px;background:#fff}
.cform input:focus,.cform textarea:focus{outline:none;border-color:var(--g2)}
.cform button{background:var(--g1);color:#fff;border:0;border-radius:99px;padding:1rem;font-weight:800}
footer{background:var(--g1);color:#d1fae5;text-align:center;padding:2rem 0;margin-top:3rem}
@media(max-width:800px){.hero .wrap,.contact{grid-template-columns:1fr}.strip .wrap{grid-template-columns:1fr 1fr}nav a.m{display:none}.blobwrap{max-width:320px;margin:auto}}
</style>
</head>
<body>
<nav><div class="wrap"><a class="brand" href="#"><?= logo() ?> <?= e($ngo['name']) ?></a>
  <div><a class="m" href="#grow">Programs</a><a class="m" href="#roots">Stories</a><a class="m" href="#contact">Contact</a> <a class="btn" style="margin-left:1.2rem;padding:.6rem 1.3rem" href="<?= e($ngo['donate_url']) ?>">Donate</a></div></div></nav>

<header class="hero"><div class="wrap">
  <div class="reveal">
    <span class="tag">🌿 A non-profit in Delhi, India</span>
    <h1><?= e($ngo['tagline']) ?></h1>
    <p><?= e($ngo['mission']) ?></p>
    <p style="margin-top:1.8rem"><a class="btn" href="<?= e($ngo['donate_url']) ?>">Plant a seed of hope</a> <a class="btn o" href="#grow">What we do</a></p>
  </div>
  <div class="blobwrap reveal"><div class="blobimg<?= $ngo['hero_image'] ? ' img' : '' ?>" <?= $ngo['hero_image'] ? 'style="background-image:url(' . e($ngo['hero_image']) . ')"' : '' ?>><span><?= e($ngo['logo']) ?></span></div>
    <span class="leaf l1">🍃</span><span class="leaf l2">🌼</span></div>
</div>
<svg class="wave" viewBox="0 0 1440 110" preserveAspectRatio="none"><path fill="#14532d" d="M0 60C300 130 500 0 800 50s480 50 640-20v80H0z"/></svg>
</header>

<div class="strip"><div class="wrap">
<?php foreach ($ngo['stats'] as $s): ?>
  <div><b data-count="<?= (int)$s['value'] ?>" data-suffix="<?= e($s['suffix']) ?>">0</b><?= e($s['label']) ?></div>
<?php endforeach; ?>
</div></div>

<section id="grow"><div class="wrap">
  <h2 class="reveal">Where we help things grow</h2><p>Rooted in the community, led by the community.</p>
  <div class="grid">
  <?php foreach ($ngo['programs'] as $p): ?>
    <div class="card reveal"><i><?= e($p['icon']) ?></i><h3><?= e($p['title']) ?></h3><p><?= e($p['text']) ?></p></div>
  <?php endforeach; ?>
  </div>
</div></section>

<section id="roots"><div class="wrap soil">
  <h2 class="reveal">Stories from the ground</h2>
  <div class="grid">
  <?php foreach ($ngo['stories'] as $s): ?>
    <div class="q reveal"><p>“<?= e($s['quote']) ?>”</p><b><?= e($s['name']) ?></b><br><small><?= e($s['role']) ?></small></div>
  <?php endforeach; ?>
  </div>
</div></section>

<section id="contact"><div class="wrap contact">
  <div><h2>Come grow with us</h2><p>Volunteer, sponsor a child, or bring your skills.</p><p>📧 <?= e($ngo['email']) ?><br>📞 <?= e($ngo['phone']) ?><br>📍 <?= e($ngo['address']) ?></p></div>
  <?= contact_form() ?>
</div></section>
<footer>© <?= date('Y') ?> <?= e($ngo['name']) ?> · <?= e($ngo['reg_note']) ?></footer>
<script src="assets/common.js"></script>
</body>
</html>
