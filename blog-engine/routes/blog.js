// routes/blog.js — the hosted, SEO-complete public blog. Works two ways:
//   • Host-based: a client reverse-proxies their /blog to this engine with the
//     Host header preserved; we resolve the site by its `domain` and render with
//     the client's own canonical/OG URLs. (Best for SEO — content on their domain.)
//   • Path-based: /sites/:siteSlug/blog… or /blog?site=slug — handy for local
//     testing and multi-site previews on the engine's own domain.
const express = require('express');
const pool = require('../lib/db');
const postsLib = require('../lib/posts');
const seo = require('../lib/seo');
const tracking = require('../lib/tracking');

const router = express.Router();

async function withSite(req, res, next) {
  try {
    req.site = await postsLib.resolveSiteForRequest(req);
    if (!req.site) return res.status(404).render('error', { title: 'No blog here', message: 'No blog is configured for this address.' });
    next();
  } catch (e) { next(e); }
}

// sitemap + rss + robots (register before the :slug catch-all)
router.get(['/blog/sitemap.xml', '/sites/:siteSlug/blog/sitemap.xml'], withSite, async (req, res) => {
  const posts = await postsLib.listPublished(req.site.id, { limit: 5000 });
  res.type('application/xml').send(seo.sitemapXml(req.site, posts));
});
router.get(['/blog/rss.xml', '/sites/:siteSlug/blog/rss.xml'], withSite, async (req, res) => {
  const posts = await postsLib.listPublished(req.site.id, { limit: 50 });
  res.type('application/rss+xml').send(seo.rssXml(req.site, posts));
});
router.get(['/robots.txt', '/sites/:siteSlug/robots.txt'], withSite, (req, res) => {
  res.type('text/plain').send(seo.robotsTxt(req.site));
});

// Blog index
router.get(['/blog', '/sites/:siteSlug/blog'], withSite, async (req, res, next) => {
  try {
    const posts = await postsLib.listPublished(req.site.id, { category: req.query.cat, limit: 200 });
    const [cats] = await pool.query(
      "SELECT DISTINCT category FROM posts WHERE site_id=? AND status='published' AND category IS NOT NULL AND category!=''", [req.site.id]);
    tracking.hit(pool, req, res, req.site, { page_type: 'blog_index', slug: '' });
    res.render('blog/index', {
      layout: 'layout', publicView: true, site: req.site, posts, categories: cats.map((c) => c.category), activeCat: req.query.cat || '',
      title: seo.brand(req.site) + ' — Blog', metaDesc: 'Latest articles from ' + seo.brand(req.site) + '.',
      canonical: seo.indexUrl(req.site), seo, websiteJsonLd: seo.websiteJsonLd(req.site),
      basePrefix: req.params.siteSlug ? `/sites/${req.params.siteSlug}` : '',
    });
  } catch (e) { next(e); }
});

// Single post
router.get(['/blog/:slug', '/sites/:siteSlug/blog/:slug'], withSite, async (req, res, next) => {
  try {
    // 301 if the slug was renamed.
    const [[moved]] = await pool.query('SELECT to_slug FROM redirects WHERE site_id=? AND from_slug=?', [req.site.id, req.params.slug]);
    if (moved) {
      const prefix = req.params.siteSlug ? `/sites/${req.params.siteSlug}` : '';
      return res.redirect(301, `${prefix}/blog/${moved.to_slug}`);
    }
    const post = await postsLib.getPublished(req.site.id, req.params.slug);
    if (!post) return res.status(404).render('error', { title: 'Post not found', message: 'This post does not exist or is not published.' });

    await pool.query('UPDATE posts SET views=views+1 WHERE id=?', [post.id]).catch(() => {});
    tracking.hit(pool, req, res, req.site, { page_type: 'blog', slug: post.slug });

    const meta = seo.metaFor(req.site, post);
    const [related] = await pool.query(
      "SELECT title, slug, cover_image, excerpt FROM posts WHERE site_id=? AND status='published' AND id<>? AND (category=? OR ?='') ORDER BY COALESCE(published_at,created_at) DESC LIMIT 3",
      [req.site.id, post.id, post.category || '', post.category || '']);

    res.render('blog/post', {
      layout: 'layout', publicView: true, site: req.site, post, related, seo, meta, ogType: 'article',
      title: meta.title, metaDesc: meta.metaDesc, canonical: meta.canonical,
      jsonLd: seo.postJsonLd(req.site, post) + seo.breadcrumbJsonLd(req.site, post),
      basePrefix: req.params.siteSlug ? `/sites/${req.params.siteSlug}` : '',
    });
  } catch (e) { next(e); }
});

module.exports = router;
