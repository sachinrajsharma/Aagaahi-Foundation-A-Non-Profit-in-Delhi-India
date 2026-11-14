/* track.js — drop this on a headless site that renders BlogForge posts in its
   own templates, so those page views still count in the dashboard.

   <script src="https://your-engine.com/js/track.js"
           data-site="your-site-slug"
           data-engine="https://your-engine.com"></script>

   It posts one anonymous beacon per page load (no cookies set by this script;
   the engine sets its own first-party cookie). Bots are filtered server-side. */
(function () {
  var s = document.currentScript || (function () { var a = document.getElementsByTagName('script'); return a[a.length - 1]; })();
  if (!s) return;
  var site = s.getAttribute('data-site');
  var engine = (s.getAttribute('data-engine') || '').replace(/\/+$/, '');
  if (!site || !engine) return;

  // Infer page_type + slug from the path (…/blog or …/blog/:slug). Override with
  // data-page-type / data-slug if your URLs differ.
  var path = location.pathname;
  var slug = s.getAttribute('data-slug');
  var pageType = s.getAttribute('data-page-type');
  if (!slug || !pageType) {
    var m = path.match(/\/blog\/([^\/?#]+)/);
    if (m) { slug = slug || decodeURIComponent(m[1]); pageType = pageType || 'blog'; }
    else { pageType = pageType || 'blog_index'; slug = slug || ''; }
  }

  try {
    fetch(engine + '/api/track', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ site: site, slug: slug, path: path, page_type: pageType, referrer: document.referrer || '' }),
      keepalive: true,
    }).catch(function () {});
  } catch (e) {}
})();
