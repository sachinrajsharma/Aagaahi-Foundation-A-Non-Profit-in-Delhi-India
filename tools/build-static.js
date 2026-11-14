// Builds the static GitHub Pages copy (docs/index.html) from includes/config.php + includes/events.json.
// Run from the repo root:  node tools/build-static.js
const fs = require('fs'), path = require('path');
const root = path.join(__dirname, '..'), docs = path.join(root, 'docs');
const cfg = fs.readFileSync(root + '/includes/config.php', 'utf8');
function val(key) {
  const m = cfg.match(new RegExp("'" + key + "'\\s*=>\\s*([\\s\\S]*?),\\s*(?://[^\\n]*)?\\n"));
  if (!m) return '';
  return [...m[1].matchAll(/'((?:[^'\\]|\\.)*)'/g)].map(x => x[1].replace(/\\'/g, "'")).join('');
}
const grab = k => (cfg.match(new RegExp("'" + k + "'\\s*=>\\s*'([^']*)'")) || [])[1] || '';
const engineUrl = grab('engine_url').replace(/\/+$/, ''), siteSlug = grab('site_slug'), blogPath = grab('blog_url') || '/blog';
const ngo = Object.fromEntries(['name', 'logo', 'tagline', 'mission', 'hero_image', 'email', 'phone', 'address', 'donate_url', 'reg_note'].map(k => [k, val(k)]));
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
const VISIBLE = 6;
const areas = JSON.parse(fs.readFileSync(root + '/includes/events.json', 'utf8')).areas;
let nProg = 0, nPics = 0;
const events = areas.map(a => {
  const progs = a.programs.map(p => {
    nProg++; nPics += p.images.length;
    const pics = p.images.map((im, k) => {
      const b = `assets/img/${p.slug}/${im.n}`;
      return `<a href="${b}.webp"${k >= VISIBLE ? ' class="extra"' : ''}><img src="${b}-t.webp" width="${im.w}" height="${im.h}" alt="${esc(im.alt)}" loading="lazy"></a>`;
    }).join('');
    const more = p.images.length > VISIBLE ? `<button class="more" type="button" data-label="Show all ${p.images.length} photos">Show all ${p.images.length} photos</button>` : '';
    return `<div class="event"><h4>${esc(p.title)}</h4><p>${esc(p.text)}</p><div class="pics">${pics}</div>${more}</div>`;
  }).join('');
  return `<div class="area reveal" id="${a.id}"><h3>${esc(a.title)}</h3><p class="intro">${esc(a.intro)}</p>${progs}</div>`;
}).join('\n');
const contactLines = (ngo.email ? `📧 ${esc(ngo.email)}<br>` : '') + (ngo.phone ? `📞 ${esc(ngo.phone)}<br>` : '') + `📍 ${esc(ngo.address)}`;
const html = `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>${esc(ngo.name)} — Home</title>
<meta name="description" content="${esc(ngo.mission)}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;800&family=Fraunces:wght@600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/common.css">
<link rel="stylesheet" href="assets/site.css">
</head>
<body>
<div class="powered">Powered by <a href="https://vidaara.org" target="_blank" rel="noopener">vidaara.org</a></div>
<nav><div class="wrap"><a class="brand" href="#"><span class="logo-emoji">${esc(ngo.logo)}</span> ${esc(ngo.name)}</a>
  <div><a class="m" href="#work">Our Work</a>${engineUrl ? `<a class="m" href="${esc(blogPath)}">Blog</a>` : ''}<a class="m" href="#contact">Contact</a> <a class="btn" style="margin-left:1.2rem;padding:.6rem 1.3rem" href="${esc(ngo.donate_url)}">Support us</a></div></div></nav>

<header class="hero"><div class="wrap">
  <div class="reveal">
    <span class="tag">🌿 A non-profit in Delhi, India</span>
    <h1>${esc(ngo.tagline)}</h1>
    <p>${esc(ngo.mission)}</p>
    <p style="margin-top:1.8rem"><a class="btn" href="#work">See our work</a> <a class="btn o" href="#contact">Get involved</a></p>
  </div>
  <div class="blobwrap reveal"><div class="blobimg${ngo.hero_image ? ' img' : ''}"${ngo.hero_image ? ` style="background-image:url(${esc(ngo.hero_image)})"` : ''}><span>${esc(ngo.logo)}</span></div>
    <span class="leaf l1">🍃</span><span class="leaf l2">🌼</span></div>
</div>
<svg class="wave" viewBox="0 0 1440 110" preserveAspectRatio="none"><path fill="#14532d" d="M0 60C300 130 500 0 800 50s480 50 640-20v80H0z"/></svg>
</header>

<div class="strip"><div class="wrap">
  <div><b data-count="${areas.length}" data-suffix="">0</b>Focus areas</div>
  <div><b data-count="${nProg}" data-suffix="">0</b>Programmes &amp; events</div>
  <div><b data-count="${nPics}" data-suffix="">0</b>Moments captured</div>
</div></div>

<section id="work"><div class="wrap">
  <h2 class="reveal">Our work, in pictures</h2><p>Highlights from programmes and events we have run in the community.</p>
${events}
</div></section>

<section id="contact"><div class="wrap contact">
  <div><h2>Come grow with us</h2><p>Volunteer, partner with us, or bring your skills.</p><p>${contactLines}</p></div>
  <form class="cform" method="post" action="#contact" onsubmit="alert('Demo preview only. The contact form works on the PHP version.');return false;" id="contact-form">
    <input type="text" name="name" placeholder="Your name" required>
    <input type="email" name="email" placeholder="Your email" required>
    <textarea name="message" rows="4" placeholder="How would you like to help?" required></textarea>
    <button type="submit">Send message</button></form>
</div></section>
<footer>© ${new Date().getFullYear()} ${esc(ngo.name)} · ${esc(ngo.reg_note)}</footer>
<script src="assets/common.js"></script>
<script src="assets/gallery.js"></script>
${engineUrl && siteSlug ? `<script src="${esc(engineUrl)}/js/track.js" data-site="${esc(siteSlug)}" data-engine="${esc(engineUrl)}" data-page-type="home" defer></script>` : ''}
</body>
</html>
`;
fs.mkdirSync(docs, { recursive: true });
function copyDir(s, d) { fs.mkdirSync(d, { recursive: true }); for (const f of fs.readdirSync(s)) { const sp = path.join(s, f), dp = path.join(d, f); fs.statSync(sp).isDirectory() ? copyDir(sp, dp) : fs.copyFileSync(sp, dp); } }
copyDir(root + '/assets', docs + '/assets');
fs.writeFileSync(docs + '/index.html', html);
console.log('docs/index.html built:', areas.length, 'areas,', nProg, 'programmes,', nPics, 'photos');
