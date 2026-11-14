// tools/posts-lib.js — loads the blog posts authored in includes/posts/<slug>.html.
//
// Each file starts with an HTML comment holding the post's settings as JSON, then the article body:
//   <!-- { "title": "...", "slug": "...", "date": "2026-11-14T10:00:00+05:30", "category": "...", "keyphrase": "...",
//          "meta_title": "...", "meta_desc": "...", "excerpt": "...", "cover": "assets/img/x/01.webp", "cover_alt": "...",
//          "faqs": [ {"q": "...", "a": "..."} ] } -->
//   <p>...</p> ...
// "{{SITE}}" in the body is replaced by the website address (config 'site_url'), so links and images are absolute.
// The FAQ list is turned into HTML (and into FAQ structured data by the page builder).
const fs = require('fs');
const path = require('path');

const esc = (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

function faqHtml(faqs) {
  return (faqs || []).map((f) => `<h3>${esc(f.q)}</h3>\n<p>${esc(f.a)}</p>`).join('\n');
}

function wordCount(html) {
  return String(html).replace(/<[^>]+>/g, ' ').replace(/&[a-z]+;/g, ' ').split(/\s+/).filter(Boolean).length;
}

function loadPosts(root, siteUrl) {
  const dir = path.join(root, 'includes', 'posts');
  if (!fs.existsSync(dir)) return [];
  const site = String(siteUrl || '').replace(/\/+$/, '');
  const posts = [];
  for (const f of fs.readdirSync(dir).filter((x) => x.endsWith('.html')).sort()) {
    const raw = fs.readFileSync(path.join(dir, f), 'utf8').replace(/^﻿/, '');
    const m = /^<!--\s*(\{[\s\S]*?\})\s*-->\s*/.exec(raw);
    if (!m) throw new Error(f + ': missing the <!-- { ... } --> settings block at the top');
    let meta;
    try { meta = JSON.parse(m[1]); } catch (e) { throw new Error(f + ': settings block is not valid JSON (' + e.message + ')'); }
    for (const k of ['title', 'slug', 'date', 'meta_desc']) if (!meta[k]) throw new Error(f + ': "' + k + '" is required');
    if (!/^[a-z0-9-]+$/.test(meta.slug)) throw new Error(f + ': slug must be lowercase letters, numbers and hyphens');
    const body = raw.slice(m[0].length).replace(/\{\{SITE\}\}/g, site);
    const absolute = (u) => (!u ? '' : /^https?:\/\//i.test(u) ? u : site + '/' + u.replace(/^\/+/, ''));
    posts.push({
      slug: meta.slug, title: meta.title, date: meta.date, category: meta.category || '', keyphrase: meta.keyphrase || '',
      meta_title: meta.meta_title || meta.title, meta_desc: meta.meta_desc, excerpt: meta.excerpt || meta.meta_desc,
      cover: absolute(meta.cover), cover_rel: meta.cover || '', cover_alt: meta.cover_alt || meta.title,
      author: meta.author || 'Aagahi Foundation', faqs: meta.faqs || [],
      body, content: body + '\n' + faqHtml(meta.faqs),
      words: wordCount(body) + wordCount(faqHtml(meta.faqs)),
    });
  }
  return posts.sort((a, b) => String(b.date).localeCompare(String(a.date)));
}

module.exports = { loadPosts, faqHtml, esc };
