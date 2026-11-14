// lib/tracking.js — first-party, privacy-friendly page-view logging.
//
// No third-party service, no fingerprinting, no PII. A visitor is a random
// first-party cookie ("bfv"); the IP is used once, offline, to derive a country
// code and then discarded; referrers are reduced to the external hostname only;
// known bots are never recorded. Writes are fire-and-forget — tracking must
// never slow or break a page.
//
// Two ways a view is recorded:
//   1) middleware(): when THIS engine serves the hosted blog pages.
//   2) recordFromApi(): a headless site that renders posts itself POSTs a tiny
//      beacon to /api/track (see public/js/track.js) so those views count too.
const crypto = require('crypto');
const cfg = require('./config');

let geoip = null;
try { geoip = require('geoip-country'); } catch (e) { /* optional — country stays '' */ }

const VISITOR_COOKIE = 'bfv';

const BOT_RE = /bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegram|skypeuripreview|preview|curl|wget|python|axios|node-fetch|go-http|headless|lighthouse|pagespeed|pingdom|uptime|monitor|scanner|scrapy|phantomjs|puppeteer|playwright|selenium|webdriver|okhttp|libwww|httpclient|winhttp|java\/|dataforseo|ahrefs|semrush|mj12|dotbot|petalbot|bytespider|amazonbot|censys|zgrab|masscan|nuclei|nmap|dataprovider|siteaudit/i;

function looksAutomated(req) {
  if (!req.headers['accept-language']) return true;
  if (req.method === 'GET') {
    const accept = req.headers.accept || '';
    if (accept && !accept.includes('text/html') && !accept.includes('*/*')) return true;
  }
  return false;
}

async function pruneOld(pool) {
  await pool.query('DELETE FROM page_views WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
    [cfg.trackingRetentionDays]).catch(() => {});
}

function readVisitorCookie(req) {
  const raw = req.headers.cookie || '';
  const m = raw.match(new RegExp('(?:^|;\\s*)' + VISITOR_COOKIE + '=([a-f0-9]{24})(?:;|$)'));
  return m ? m[1] : null;
}

function referrerHost(req, siteBase) {
  try {
    const ref = req.headers.referer || req.headers.referrer || (req.body && req.body.referrer) || '';
    if (!ref) return '';
    const host = new URL(ref).hostname.toLowerCase();
    const own = String(siteBase || '').replace(/^https?:\/\//, '').replace(/\/.*$/, '');
    if (!host || host === own || host === 'www.' + own || host === 'localhost' || host === '127.0.0.1') return '';
    return host.slice(0, 255);
  } catch (e) { return ''; }
}

function countryOf(req) {
  if (!geoip) return '';
  try {
    let ip = (req.headers['x-forwarded-for'] || '').split(',')[0].trim() || req.ip || '';
    if (ip.startsWith('::ffff:')) ip = ip.slice(7);
    const hit = geoip.lookup(ip);
    return hit && hit.country ? String(hit.country).slice(0, 2).toUpperCase() : '';
  } catch (e) { return ''; }
}

function deviceOf(ua) {
  if (/iPad|Tablet/i.test(ua)) return 'tablet';
  if (/Mobile|Android|iPhone/i.test(ua)) return 'mobile';
  return 'desktop';
}

function ensureVisitor(req, res) {
  let visitor = readVisitorCookie(req);
  if (!visitor) {
    visitor = crypto.randomBytes(12).toString('hex');
    res.cookie(VISITOR_COOKIE, visitor, {
      httpOnly: true, sameSite: 'lax',
      secure: cfg.env === 'production',
      maxAge: cfg.trackingRetentionDays * 24 * 60 * 60 * 1000,
    });
  }
  return visitor;
}

// Low-level insert. Never throws into the request pipeline.
function record(pool, { siteId, pageType, slug, path, visitor, referrer, device, country }) {
  pool.query(
    `INSERT INTO page_views (site_id, page_type, slug, path, visitor, referrer, device, country)
     VALUES (?,?,?,?,?,?,?,?)`,
    [siteId, pageType || 'other', (slug || '').slice(0, 255), (path || '').slice(0, 512),
     visitor || '', referrer || '', device || 'desktop', country || '']
  ).catch((e) => { if (!/doesn't exist/i.test(e.message)) console.error('[tracking]', e.message); });
}

// Record a hosted page view directly (called from the blog route handlers once
// the site + post are known). Bot-filtered; never throws.
function hit(pool, req, res, site, pv) {
  try {
    const ua = req.headers['user-agent'] || '';
    if (!site || !pv || !ua || BOT_RE.test(ua) || looksAutomated(req)) return;
    record(pool, {
      siteId: site.id, pageType: pv.page_type, slug: pv.slug || '', path: req.path,
      visitor: ensureVisitor(req, res), referrer: referrerHost(req, site.base_url),
      device: deviceOf(ua), country: countryOf(req),
    });
  } catch (e) { /* never break the page */ }
}

// Middleware variant: reads res.locals.pageView (set by the route beforehand).
function view(pool) {
  return function (req, res, next) { hit(pool, req, res, req.site, res.locals.pageView); next(); };
}

// Headless beacon: a site that renders posts itself POSTs { slug, path, page_type,
// referrer } to /api/track. Authenticated by API key (req.site set by the route).
function recordFromApi(pool, req, res, site) {
  try {
    const ua = req.headers['user-agent'] || '';
    if (!ua || BOT_RE.test(ua)) return { skipped: 'bot' };
    const b = req.body || {};
    record(pool, {
      siteId: site.id,
      pageType: b.page_type || (b.slug ? 'blog' : 'blog_index'),
      slug: b.slug || '', path: b.path || '',
      visitor: ensureVisitor(req, res),
      referrer: referrerHost(req, site.base_url),
      device: deviceOf(ua), country: countryOf(req),
    });
    return { ok: true };
  } catch (e) { return { error: e.message }; }
}

module.exports = { view, hit, recordFromApi, pruneOld, VISITOR_COOKIE };
