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
    return String(html || '').replace(/<\s*(script|style|iframe|object|embed|link|meta)[\s\S]*?(<\/\s*\1\s*>|>)/gi, '').replace(/\son[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '').replace(/javascript:/gi, '');
  }
  function abs(u) { return !u ? '' : /^https?:/i.test(u) ? u : base + u; }
  function date(d) { try { return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'long', year: 'numeric' }); } catch (e) { return ''; } }

  function list() {
    fetch(api + '/posts').then(function (r) { return r.json(); }).then(function (d) {
      var posts = (d && d.posts) || [];
      if (!posts.length) { root.innerHTML = fallback; return; }
      root.innerHTML = '<h1 style="color:var(--g1)">Blog</h1><div class="grid">' + posts.map(function (p) {
        return '<a class="card" href="#/' + esc(p.slug) + '">' + (p.cover_image ? '<img src="' + esc(abs(p.cover_image)) + '" alt="" loading="lazy" style="width:100%;aspect-ratio:16/9;object-fit:cover;border-radius:14px;margin-bottom:.6rem">' : '') +
          '<h3>' + esc(p.title) + '</h3><p>' + esc(p.excerpt || '') + '</p><small>' + esc(date(p.published_at)) + (p.reading_minutes ? ' · ' + esc(p.reading_minutes) + ' min read' : '') + '</small></a>';
      }).join('') + '</div>';
    }).catch(function () { root.innerHTML = fallback; });
  }

  function one(slug) {
    fetch(api + '/posts/' + encodeURIComponent(slug)).then(function (r) { if (!r.ok) throw new Error('nf'); return r.json(); }).then(function (d) {
      var p = d.post;
      document.title = p.title + ' — ' + document.title.split(' — ').pop();
      root.innerHTML = '<p><a href="#/">← All posts</a></p><article><h1 style="font-size:2.2rem;color:var(--g1)">' + esc(p.title) + '</h1><p class="st-sub">' + esc(date(p.published_at)) +
        (p.author ? ' · ' + esc(p.author) : '') + '</p>' + (p.cover_image ? '<img src="' + esc(abs(p.cover_image)) + '" alt="" style="width:100%;border-radius:18px;margin-bottom:1rem">' : '') + clean(p.content) + '</article>';
      window.scrollTo(0, 0);
    }).catch(function () { root.innerHTML = '<p class="st-msg">We could not find that post. <a href="#/">See all posts</a></p>'; });
  }

  function route() { var m = /^#\/([a-z0-9-]+)/i.exec(location.hash); m ? one(m[1]) : list(); }
  window.addEventListener('hashchange', route);
  route();
})();
