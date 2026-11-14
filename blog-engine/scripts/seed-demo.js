// scripts/seed-demo.js — add a couple of demo posts + categories to the demo
// site so the blog, editor and analytics have something to show immediately.
require('dotenv').config();
const pool = require('../lib/db');
const postsLib = require('../lib/posts');

const POSTS = [
  {
    title: 'How to Write SEO-Friendly Blog Posts That Actually Rank',
    focus_keyword: 'seo-friendly blog posts', category: 'SEO',
    excerpt: 'A practical, no-fluff walkthrough of writing posts that rank — keyphrase placement, structure, internal links and readability.',
    meta_desc: 'Learn to write seo-friendly blog posts that rank: keyphrase placement, headings, internal links and readability. A practical guide.',
    content: '<h2>Start with one clear keyphrase</h2><p>Every strong post targets a single <strong>focus keyphrase</strong>. Put it in your title, your first paragraph, one subheading and the URL slug — then write naturally for people, not robots.</p><h2>Structure for skimming</h2><p>Readers skim before they read. Use <em>H2</em> and <em>H3</em> subheadings every few paragraphs, keep paragraphs short, and lead with the answer.</p><h3>Internal links matter</h3><p>Link to two or three related posts. It helps readers and gives search engines a map of your site.</p><h2>Readability wins clicks</h2><p>Shorter sentences, active voice and a few transition words lift both comprehension and rankings. Aim for a plain-English reading level.</p>',
    status: 'published',
  },
  {
    title: 'First-Party Analytics: Why We Stopped Trusting Third-Party Trackers',
    focus_keyword: 'first-party analytics', category: 'Analytics',
    excerpt: 'Cookie-only, no-PII, bot-filtered analytics tell you the truth about your traffic — without the bloat and blind spots of third-party scripts.',
    meta_desc: 'First-party analytics give honest traffic numbers without third-party bloat or ad-blocker blind spots. Here is why it matters and how it works.',
    content: '<h2>Third-party trackers lie by omission</h2><p>Ad blockers, privacy modes and consent banners quietly drop a big slice of your traffic before a third-party script ever loads. Your dashboard looks precise, but it is missing people.</p><h2>What first-party tracking sees</h2><p>Logging views on your own server — with a single anonymous cookie and no IP stored — counts everyone, filters bots, and never leaks data to anyone else.</p><h2>The honest numbers</h2><p>Fewer vanity metrics, more decisions you can trust: which posts are actually read, where readers come from, and which countries your content reaches.</p>',
    status: 'published',
  },
];

(async () => {
  const [[site]] = await pool.query('SELECT id FROM sites WHERE slug=?', ['demo']);
  if (!site) { console.error('No demo site — run `npm run setup-db` first.'); process.exit(1); }

  for (const name of ['SEO', 'Analytics', 'Writing']) {
    await pool.query('INSERT IGNORE INTO categories (site_id, name, slug) VALUES (?,?,?)',
      [site.id, name, name.toLowerCase()]).catch(() => {});
  }

  for (const p of POSTS) {
    const [[exists]] = await pool.query('SELECT id FROM posts WHERE site_id=? AND slug=?', [site.id, require('../lib/util').slugify(p.title)]);
    if (exists) { console.log('• exists:', p.title); continue; }
    await postsLib.createPost(site.id, p);
    console.log('✓ added:', p.title);
  }
  console.log('\nDone. Open /admin → Posts, or the demo blog at /sites/demo/blog');
  process.exit(0);
})().catch((e) => { console.error(e); process.exit(1); });
