// scripts/import-posts.js — put the website's authored blog posts into the dashboard so staff can edit them there.
//   node scripts/import-posts.js <site-slug> <path-to-website-repo> [--update]
// Reads includes/posts/*.html from the website repo (via tools/posts-lib.js). New posts are created as PUBLISHED with their
// title, address (slug), meta title/description, focus keyphrase, cover image, category and date. Posts that already exist
// (same slug) are skipped, or overwritten with --update.
require('dotenv').config();
const fs = require('fs');
const path = require('path');
const pool = require('../lib/db');
const postsLib = require('../lib/posts');
const { slugify } = require('../lib/util');

(async () => {
  const [siteSlug, repo] = process.argv.slice(2).filter((a) => !a.startsWith('--'));
  const update = process.argv.includes('--update');
  if (!siteSlug || !repo) { console.error('Usage: node scripts/import-posts.js <site-slug> <path-to-website-repo> [--update]'); process.exit(1); }
  const libFile = path.join(repo, 'tools', 'posts-lib.js');
  if (!fs.existsSync(libFile)) { console.error('Cannot find ' + libFile + '. Is that the website repo folder?'); process.exit(1); }
  const cfgFile = path.join(repo, 'includes', 'config.php');
  const m = fs.existsSync(cfgFile) ? /\$ngo\['site_url'\]\s*=\s*'([^']*)'/.exec(fs.readFileSync(cfgFile, 'utf8')) : null;
  const siteUrl = m ? m[1] : '';
  const { loadPosts } = require(libFile);
  const posts = loadPosts(repo, siteUrl);
  if (!posts.length) { console.error('No posts found in ' + path.join(repo, 'includes', 'posts')); process.exit(1); }

  const [[site]] = await pool.query('SELECT id, name FROM sites WHERE slug = ?', [siteSlug]);
  if (!site) { console.error('No site with slug "' + siteSlug + '". Create it first on the Sites page.'); process.exit(1); }

  let created = 0, updated = 0, skipped = 0;
  for (const p of posts) {
    if (p.category) await pool.query('INSERT IGNORE INTO categories (site_id, name, slug) VALUES (?,?,?)', [site.id, p.category, slugify(p.category)]);
    const fields = {
      title: p.title, slug: p.slug, content: p.content, excerpt: p.excerpt, cover_image: p.cover, category: p.category || null, author: p.author,
      meta_title: p.meta_title, meta_desc: p.meta_desc, focus_keyword: p.keyphrase, status: 'published', published_at: new Date(p.date), external_id: 'site:' + p.slug,
    };
    const existing = await postsLib.getBySlug(site.id, p.slug);
    if (existing && !update) { skipped++; continue; }
    if (existing) { await postsLib.updatePost(site.id, existing.id, fields); updated++; } else { await postsLib.createPost(site.id, fields); created++; }
  }
  console.log('Posts in "' + site.name + '": ' + created + ' created, ' + updated + ' updated, ' + skipped + ' already there.');
  console.log('Open the dashboard → Posts to edit them. Images in the posts stay on the website; add new images through Media.');
  process.exit(0);
})().catch((e) => { console.error('IMPORT FAILED:', e.message); process.exit(1); });
