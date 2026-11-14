// lib/insights.js — first-party visitor analytics for the dashboard, read from
// the page_views table (lib/tracking.js) and joined back to posts. Every figure
// is honest first-party data: no estimates, no third-party numbers. All queries
// are scoped to one site.

async function summary(pool, siteId, days) {
  const [[all]] = await pool.query(
    `SELECT COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors, MIN(created_at) AS since
       FROM page_views WHERE site_id=?`, [siteId]);
  const [[range]] = await pool.query(
    `SELECT COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors,
            SUM(page_type IN ('blog','article')) AS blog_views
       FROM page_views WHERE site_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)`, [siteId, days]);
  const [[today]] = await pool.query(
    `SELECT COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors
       FROM page_views WHERE site_id=? AND created_at >= CURDATE()`, [siteId]);
  return {
    since: all.since,
    allViews: Number(all.views), allVisitors: Number(all.visitors),
    views: Number(range.views), visitors: Number(range.visitors),
    blogViews: Number(range.blog_views || 0),
    todayViews: Number(today.views), todayVisitors: Number(today.visitors),
  };
}

// One row per calendar day, zero-padded so quiet days still show.
async function daily(pool, siteId, days) {
  const [rows] = await pool.query(
    `SELECT DATE(created_at) AS d, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors
       FROM page_views WHERE site_id=? AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
      GROUP BY d ORDER BY d`, [siteId, days - 1]);
  const map = new Map(rows.map((r) => [new Date(r.d).toISOString().slice(0, 10), r]));
  const out = [];
  const start = new Date(); start.setDate(start.getDate() - (days - 1));
  for (let i = 0; i < days; i++) {
    const d = new Date(start); d.setDate(start.getDate() + i);
    const key = d.toISOString().slice(0, 10);
    const r = map.get(key);
    out.push({ day: key, views: r ? Number(r.views) : 0, visitors: r ? Number(r.visitors) : 0 });
  }
  return out;
}

async function monthlyReach(pool, siteId, monthsBack) {
  const [rows] = await pool.query(
    `SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, COUNT(DISTINCT visitor) AS visitors, COUNT(*) AS views
       FROM page_views WHERE site_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
      GROUP BY ym ORDER BY ym`, [siteId, monthsBack]);
  return rows.map((r) => ({ ym: r.ym, visitors: Number(r.visitors), views: Number(r.views) }));
}

// Which posts are actually read (join filters out junk slugs).
async function topPosts(pool, siteId, days, limit) {
  const [rows] = await pool.query(
    `SELECT p.id, p.title, p.slug, p.status, p.views AS all_time, v.views, v.readers
       FROM (SELECT slug, COUNT(*) AS views, COUNT(DISTINCT visitor) AS readers
               FROM page_views
              WHERE site_id=? AND page_type IN ('blog','article')
                AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
              GROUP BY slug) v
       JOIN posts p ON p.slug = v.slug AND p.site_id=?
      ORDER BY v.views DESC, v.readers DESC LIMIT ?`, [siteId, days, siteId, limit]);
  rows.forEach((r) => { r.views = Number(r.views); r.readers = Number(r.readers); r.all_time = Number(r.all_time); });
  return rows;
}

function bucketReferrer(host) {
  if (!host) return 'Direct / typed';
  if (/google\./.test(host)) return 'Google';
  if (/bing\./.test(host)) return 'Bing';
  if (/duckduckgo|yahoo|yandex|baidu/.test(host)) return 'Other search';
  if (/facebook|fb\.com|instagram/.test(host)) return 'Facebook / Instagram';
  if (/whatsapp/.test(host)) return 'WhatsApp';
  if (/youtube|youtu\.be/.test(host)) return 'YouTube';
  if (/t\.co|twitter|x\.com/.test(host)) return 'X (Twitter)';
  if (/linkedin/.test(host)) return 'LinkedIn';
  if (/reddit/.test(host)) return 'Reddit';
  if (/telegram|t\.me/.test(host)) return 'Telegram';
  return host;
}

async function referrers(pool, siteId, days, limit) {
  const [rows] = await pool.query(
    `SELECT referrer, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors
       FROM page_views WHERE site_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
      GROUP BY referrer`, [siteId, days]);
  const buckets = new Map();
  rows.forEach((r) => {
    const name = bucketReferrer(r.referrer);
    const b = buckets.get(name) || { source: name, views: 0, visitors: 0 };
    b.views += Number(r.views); b.visitors += Number(r.visitors);
    buckets.set(name, b);
  });
  const out = [...buckets.values()].sort((a, b) => b.views - a.views);
  const total = out.reduce((s, r) => s + r.views, 0);
  out.forEach((r) => { r.share = total ? (r.views / total) * 100 : 0; });
  return out.slice(0, limit);
}

async function devices(pool, siteId, days) {
  const [rows] = await pool.query(
    `SELECT device, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors
       FROM page_views WHERE site_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
      GROUP BY device ORDER BY views DESC`, [siteId, days]);
  const total = rows.reduce((s, r) => s + Number(r.views), 0);
  return rows.map((r) => ({ device: r.device, views: Number(r.views), visitors: Number(r.visitors),
    share: total ? (Number(r.views) / total) * 100 : 0 }));
}

let countryDN = null;
function countryName(code) {
  if (!code) return 'Unknown / local';
  try { if (!countryDN) countryDN = new Intl.DisplayNames(['en'], { type: 'region' }); return countryDN.of(code) || code; }
  catch (e) { return code; }
}

async function countries(pool, siteId, days, limit) {
  const [rows] = await pool.query(
    `SELECT country, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visitors
       FROM page_views WHERE site_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
      GROUP BY country ORDER BY visitors DESC, views DESC`, [siteId, days]);
  const known = rows.filter((r) => r.country);
  const total = known.reduce((s, r) => s + Number(r.visitors), 0);
  const unknownRow = rows.find((r) => !r.country);
  const list = known.slice(0, limit).map((r) => ({
    code: r.country, name: countryName(r.country),
    views: Number(r.views), visitors: Number(r.visitors),
    share: total ? (Number(r.visitors) / total) * 100 : 0,
  }));
  list.unknown = unknownRow ? Number(unknownRow.visitors) : 0;
  return list;
}

async function pageTypes(pool, siteId, days) {
  const LABELS = { home: 'Home', blog_index: 'Blog index', blog: 'Blog posts', article: 'Blog posts (legacy)', page: 'Info pages', other: 'Other' };
  const [rows] = await pool.query(
    `SELECT page_type, COUNT(*) AS views FROM page_views
      WHERE site_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
      GROUP BY page_type ORDER BY views DESC`, [siteId, days]);
  const total = rows.reduce((s, r) => s + Number(r.views), 0);
  return rows.map((r) => ({ label: LABELS[r.page_type] || r.page_type, views: Number(r.views),
    share: total ? (Number(r.views) / total) * 100 : 0 }));
}

// Everything the insights page needs, in one call.
async function overview(pool, siteId, { days = 30 } = {}) {
  const [sum, series, reach, posts, sources, deviceSplit, types, geo] = await Promise.all([
    summary(pool, siteId, days), daily(pool, siteId, Math.min(days, 90)), monthlyReach(pool, siteId, 12),
    topPosts(pool, siteId, days, 25), referrers(pool, siteId, days, 12), devices(pool, siteId, days),
    pageTypes(pool, siteId, days), countries(pool, siteId, days, 25),
  ]);
  return { sum, series, reach, posts, sources, devices: deviceSplit, types, countries: geo, days };
}

module.exports = { overview, summary, daily, monthlyReach, topPosts, referrers, devices, pageTypes, countries, countryName };
