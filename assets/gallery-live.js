// Gallery from the dashboard (BlogForge CMS). If the CMS has published events, replace the built-in "Our work" gallery
// with them; otherwise the page keeps the built-in content. The lightbox and "show all" buttons (gallery.js) work on both.
(function () {
  var sec = document.getElementById('work');
  if (!sec) return;
  var base = (sec.getAttribute('data-cms') || '').replace(/\/+$/, ''), site = sec.getAttribute('data-site') || '';
  if (!base || !site) return;
  var VISIBLE = 6;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function safe(u) { return /^https?:\/\//i.test(u) ? u : ''; }

  fetch(base + '/public/' + encodeURIComponent(site) + '/gallery').then(function (r) { return r.json(); }).then(function (d) {
    var areas = (d && d.areas) || [];
    if (!areas.length) return;
    var html = areas.map(function (a) {
      return '<div class="area in" id="' + esc(a.id) + '"><h3>' + esc(a.title) + '</h3>' + (a.intro ? '<p class="intro">' + esc(a.intro) + '</p>' : '') +
        a.programs.map(function (p) {
          var imgs = (p.images || []).filter(function (i) { return safe(i.url) && safe(i.thumb); });
          var pics = imgs.map(function (i, k) {
            return '<a href="' + esc(i.url) + '"' + (k >= VISIBLE ? ' class="extra"' : '') + '><img src="' + esc(i.thumb) + '"' + (i.w && i.h ? ' width="' + (+i.w) + '" height="' + (+i.h) + '"' : '') + ' alt="' + esc(i.alt || p.title) + '" loading="lazy"></a>';
          }).join('');
          var more = imgs.length > VISIBLE ? '<button class="more" type="button" data-label="Show all ' + imgs.length + ' photos">Show all ' + imgs.length + ' photos</button>' : '';
          return '<div class="event"><h4>' + esc(p.title) + '</h4>' + (p.text ? '<p>' + esc(p.text) + '</p>' : '') + (imgs.length ? '<div class="pics">' + pics + '</div>' + more : '') + '</div>';
        }).join('') + '</div>';
    }).join('');
    var old = sec.querySelectorAll('.area');
    if (!old.length) return;
    var holder = document.createElement('div'); holder.innerHTML = html;
    old[0].parentNode.insertBefore(holder, old[0]);
    old.forEach(function (n) { n.remove(); });
    while (holder.firstChild) holder.parentNode.insertBefore(holder.firstChild, holder);
    holder.remove();
  }).catch(function () { /* keep the built-in gallery */ });
})();
