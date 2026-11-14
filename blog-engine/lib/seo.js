// lib/seo.js — programmatic SEO for the hosted blog: meta/OG/Twitter, JSON-LD
// (BlogPosting, BreadcrumbList, WebSite/Organization), sitemap.xml, RSS and
// robots.txt. Admin-set meta_title/meta_desc/canonical always win over fallbacks.
const cfg = require('./config');
const { esc, stripHtml, makeExcerpt } = require('./util');

function siteBase(site) { return (site.base_url || cfg.baseUrl).replace(/\/+$/, ''); }
function blogPath(site) { return (site.blog_path || '/blog').replace(/\/+$/, '') || '/blog'; }
function brand(site) { return site.brand_name || site.name; }

function abs(site, u) {
  if (!u) return '';
  return /^https?:\/\//i.test(u) ? u : siteBase(site) + (u.charAt(0) === '/' ? u : '/' + u);
}
function postUrl(site, post) { return siteBase(site) + blogPath(site) + '/' + post.slug; }
function indexUrl(site) { return siteBase(site) + blogPath(site); }
function cut(s, n) { s = String(s || '').trim(); return s.length <= n ? s : s.slice(0, n - 1).replace(/\s+\S*$/, '') + '…'; }
function jsonLd(obj) { return '<script type="application/ld+json">' + JSON.stringify(obj).replace(/</g, '\\u003c') + '</script>'; }

// ── Per-post meta ─────────────────────────────────────────────────────────────
function metaFor(site, post) {
  const title = (post.meta_title && post.meta_title.trim())
    || cut(post.title + ' | ' + brand(site), 60);
  const desc = (post.meta_desc && post.meta_desc.trim())
    || makeExcerpt(post.excerpt || post.content, 155);
  const canonical = (post.canonical_url && post.canonical_url.trim()) || postUrl(site, post);
  const ogImage = abs(site, post.og_image || post.cover_image) || '';
  return {
    title, metaDesc: desc, canonical, ogImage,
    robots: post.noindex ? 'noindex, follow' : 'index, follow',
    ogType: 'article',
  };
}

function postJsonLd(site, post) {
  const node = {
    '@context': 'https://schema.org',
    '@type': 'BlogPosting',
    headline: cut(post.title, 110),
    description: cut(post.meta_desc || makeExcerpt(post.excerpt || post.content, 200), 300),
    image: post.cover_image ? [abs(site, post.cover_image)] : undefined,
    mainEntityOfPage: { '@type': 'WebPage', '@id': postUrl(site, post) },
    datePublished: post.published_at ? new Date(post.published_at).toISOString() : (post.created_at ? new Date(post.created_at).toISOString() : undefined),
    dateModified: post.updated_at ? new Date(post.updated_at).toISOString() : undefined,
    author: { '@type': 'Person', name: post.author || site.default_author || brand(site), url: post.author_url || undefined },
    publisher: {
      '@type': 'Organization', name: brand(site),
      logo: site.logo_url ? { '@type': 'ImageObject', url: abs(site, site.logo_url) } : undefined,
    },
    wordCount: stripHtml(post.content).split(/\s+/).filter(Boolean).length || undefined,
    articleSection: post.category || undefined,
    keywords: post.focus_keyword || undefined,
  };
  return jsonLd(node);
}

function breadcrumbJsonLd(site, post) {
  return jsonLd({
    '@context': 'https://schema.org', '@type': 'BreadcrumbList',
    itemListElement: [
      { '@type': 'ListItem', position: 1, name: 'Home', item: siteBase(site) + '/' },
      { '@type': 'ListItem', position: 2, name: 'Blog', item: indexUrl(site) },
      { '@type': 'ListItem', position: 3, name: post.title, item: postUrl(site, post) },
    ],
  });
}

function websiteJsonLd(site) {
  return jsonLd({
    '@context': 'https://schema.org',
    '@graph': [
      { '@type': 'Organization', '@id': siteBase(site) + '/#org', name: brand(site), url: siteBase(site) + '/',
        logo: site.logo_url ? abs(site, site.logo_url) : undefined },
      { '@type': 'WebSite', '@id': siteBase(site) + '/#site', url: siteBase(site) + '/', name: brand(site),
        publisher: { '@id': siteBase(site) + '/#org' } },
    ],
  });
}

// ── sitemap / rss / robots ────────────────────────────────────────────────────
function sitemapXml(site, posts) {
  const urls = [indexUrl(site), ...posts.map((p) => postUrl(site, p))];
  const lastmods = [null, ...posts.map((p) => p.updated_at || p.published_at)];
  let xml = '<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n';
  urls.forEach((u, i) => {
    xml += '  <url><loc>' + esc(u) + '</loc>';
    if (lastmods[i]) xml += '<lastmod>' + new Date(lastmods[i]).toISOString().slice(0, 10) + '</lastmod>';
    xml += '</url>\n';
  });
  return xml + '</urlset>\n';
}

function rssXml(site, posts) {
  const now = new Date().toUTCString();
  let xml = '<?xml version="1.0" encoding="UTF-8"?>\n<rss version="2.0"><channel>\n';
  xml += '  <title>' + esc(brand(site)) + ' — Blog</title>\n';
  xml += '  <link>' + esc(indexUrl(site)) + '</link>\n';
  xml += '  <description>' + esc('Latest posts from ' + brand(site)) + '</description>\n';
  xml += '  <lastBuildDate>' + now + '</lastBuildDate>\n';
  posts.slice(0, 50).forEach((p) => {
    xml += '  <item>\n';
    xml += '    <title>' + esc(p.title) + '</title>\n';
    xml += '    <link>' + esc(postUrl(site, p)) + '</link>\n';
    xml += '    <guid isPermaLink="true">' + esc(postUrl(site, p)) + '</guid>\n';
    if (p.published_at) xml += '    <pubDate>' + new Date(p.published_at).toUTCString() + '</pubDate>\n';
    xml += '    <description>' + esc(p.excerpt || makeExcerpt(p.content, 200)) + '</description>\n';
    xml += '  </item>\n';
  });
  return xml + '</channel></rss>\n';
}

function robotsTxt(site) {
  return 'User-agent: *\nAllow: /\n\nSitemap: ' + siteBase(site) + blogPath(site) + '/sitemap.xml\n';
}

module.exports = {
  siteBase, blogPath, brand, abs, postUrl, indexUrl,
  metaFor, postJsonLd, breadcrumbJsonLd, websiteJsonLd, sitemapXml, rssXml, robotsTxt,
};
