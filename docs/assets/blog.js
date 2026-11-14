// Blog from the dashboard (BlogForge CMS). Replaces the page's built-in blog content with live posts when the CMS has any;
// otherwise the page keeps what it already shows. Routes: #/ (list)  #/<slug> (one post).
(function () {
  var root = document.getElementById('cms-blog');
  if (!root) return;
  var base = (root.getAttribute('data-cms') || '').replace(/\/+$/, ''), site = root.getAttribute('data-site') || '';
  if (!base || !site) return;
  var api = base + '/public/' + encodeURIComponent(site), fallback = root.innerHTML;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function clean(html) { // the server already sanitises; strip anything executable once more
    return String(html || '').replace(/(src|href)="\/uploads\//gi, '$1="' + base + '/uploads/').replace(/<\s*(script|style|iframe|object|embed|link|meta)[\s\S]*?(<\/\s*\1\s*>|>)/gi, '').replace(/\son[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '').replace(/javascript:/gi, '');
  }
  function abs(u) { return !u ? '' : /^https?:/i.test(u) ? u : base + u; }
  function date(d) { try { return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' }); } catch (e) { return ''; } }

  function list() {
    fetch(api + '/posts').then(function (r) { return r.json(); }).then(function (d) {
      var posts = (d && d.posts) || [];
      if (!posts.length) { root.innerHTML = fallback; return; }
      root.innerHTML = '<h1 style="color:var(--g1)">Blog</h1><p class="st-sub">Practical guides from our programmes in Delhi.</p><div class="post-grid">' + posts.map(function (p) {
        return '<a class="post-card" href="#/' + esc(p.slug) + '">' + (p.cover_image ? '<img src="' + esc(abs(p.cover_image)) + '" alt="' + esc(p.title) + '" loading="lazy">' : '') +
          '<div class="pc-body">' + (p.category ? '<span class="post-cat">' + esc(p.category) + '</span>' : '') + '<h3>' + esc(p.title) + '</h3><p>' + esc(p.excerpt || '') + '</p><small>' + esc(date(p.published_at)) + (p.reading_minutes ? ' · ' + esc(p.reading_minutes) + ' min read' : '') + '</small></div></a>';
      }).join('') + '</div>';
    }).catch(function () { root.innerHTML = fallback; });
  }

  function one(slug) {
    fetch(api + '/posts/' + encodeURIComponent(slug)).then(function (r) { if (!r.ok) throw new Error('nf'); return r.json(); }).then(function (d) {
      var p = d.post;
      document.title = p.title + ' — ' + document.title.split(' — ').pop();
      root.innerHTML = '<p><a href="#/">← All posts</a></p><article class="article">' + (p.category ? '<p class="post-cat">' + esc(p.category) + '</p>' : '') + '<h1>' + esc(p.title) + '</h1><p class="post-meta">' +
        (p.author ? 'By ' + esc(p.author) + ' · ' : '') + esc(date(p.published_at)) + (p.reading_minutes ? ' · ' + esc(p.reading_minutes) + ' min read' : '') + '</p>' +
        (p.cover_image ? '<figure class="post-cover"><img src="' + esc(abs(p.cover_image)) + '" alt="' + esc(p.title) + '"></figure>' : '') + clean(p.content) + '</article>';
      window.scrollTo(0, 0);
    }).catch(function () { root.innerHTML = '<p class="st-msg">We could not find that post. <a href="#/">See all posts</a></p>'; });
  }

  function route() { var m = /^#\/([a-z0-9-]+)/i.exec(location.hash); m ? one(m[1]) : list(); }
  window.addEventListener('hashchange', route);
  route();
})();
