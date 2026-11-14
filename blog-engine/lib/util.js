// lib/util.js — shared helpers: ids, escaping, slugs, reading time, excerpts,
// and a conservative server-side HTML sanitizer for post content.
const crypto = require('crypto');

const sha256 = (s) => crypto.createHash('sha256').update(String(s)).digest('hex');
const rand   = (n = 24) => crypto.randomBytes(n).toString('hex');

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function slugify(s) {
  return String(s || '').toLowerCase().trim()
    .replace(/&/g, ' and ')
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 200) || ('post-' + rand(4));
}

function safeEqual(a, b) {
  const ba = Buffer.from(String(a || '')); const bb = Buffer.from(String(b || ''));
  if (ba.length !== bb.length) return false;
  return crypto.timingSafeEqual(ba, bb);
}

// Plain text from HTML (for reading time, excerpts, SEO length checks).
function stripHtml(html) {
  return String(html || '')
    .replace(/<script[\s\S]*?<\/script>/gi, ' ')
    .replace(/<style[\s\S]*?<\/style>/gi, ' ')
    .replace(/<[^>]+>/g, ' ')
    .replace(/&nbsp;/g, ' ').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>')
    .replace(/\s+/g, ' ').trim();
}

function readingMinutes(html) {
  const words = stripHtml(html).split(/\s+/).filter(Boolean).length;
  return Math.max(1, Math.round(words / 200));
}

function makeExcerpt(html, len = 160) {
  const t = stripHtml(html);
  if (t.length <= len) return t;
  return t.slice(0, len - 1).replace(/\s+\S*$/, '') + '…';
}

// ── HTML sanitizer ───────────────────────────────────────────────────────────
// Allow a safe subset of tags/attributes for post bodies. Strips <script>,
// <style>, <iframe>/<object>/<embed>, every on* handler, and javascript:/data:
// URLs. Not a full DOM parser, but conservative and defensive by construction.
const ALLOWED_TAGS = new Set([
  'p','br','hr','h1','h2','h3','h4','h5','h6','blockquote','pre','code',
  'strong','b','em','i','u','s','sub','sup','mark','small',
  'ul','ol','li','dl','dt','dd',
  'a','img','figure','figcaption',
  'table','thead','tbody','tfoot','tr','th','td','caption','colgroup','col',
  'span','div',
]);
const ALLOWED_ATTR = {
  a: ['href', 'title', 'target', 'rel'],
  img: ['src', 'alt', 'title', 'width', 'height', 'loading'],
  th: ['colspan', 'rowspan', 'scope'],
  td: ['colspan', 'rowspan'],
  '*': ['class'],
};

function safeUrl(url) {
  const u = String(url || '').trim();
  if (/^\s*javascript:/i.test(u)) return '';
  if (/^\s*data:(?!image\/)/i.test(u)) return '';   // allow data:image only
  if (/^\s*vbscript:/i.test(u)) return '';
  return u;
}

function sanitizeHtml(html) {
  let out = String(html || '');
  // Remove dangerous elements entirely (with content).
  out = out.replace(/<(script|style|iframe|object|embed|form|input|button|textarea|select|link|meta|noscript)\b[\s\S]*?<\/\1>/gi, '');
  out = out.replace(/<(script|style|iframe|object|embed|form|input|button|textarea|select|link|meta|noscript)\b[^>]*\/?>/gi, '');
  // Walk each tag; drop disallowed tags (keep inner text), scrub attributes.
  out = out.replace(/<(\/?)([a-zA-Z0-9]+)((?:\s+[^<>]*?)?)\s*(\/?)>/g, (m, close, tag, attrs, selfclose) => {
    tag = tag.toLowerCase();
    if (!ALLOWED_TAGS.has(tag)) return '';       // strip tag, keep its text content
    if (close) return `</${tag}>`;
    const allowed = (ALLOWED_ATTR[tag] || []).concat(ALLOWED_ATTR['*']);
    const kept = [];
    const attrRe = /([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*(?:=\s*("([^"]*)"|'([^']*)'|([^\s"'>]+)))?/g;
    let a;
    while ((a = attrRe.exec(attrs)) !== null) {
      const name = a[1].toLowerCase();
      let val = a[3] != null ? a[3] : (a[4] != null ? a[4] : (a[5] != null ? a[5] : ''));
      if (/^on/i.test(name)) continue;           // no event handlers
      if (name === 'style') continue;            // no inline styles (CSP-friendly)
      if (!allowed.includes(name)) continue;
      if ((name === 'href' || name === 'src')) { val = safeUrl(val); if (!val) continue; }
      kept.push(val === '' && (name === 'target') ? name : `${name}="${esc(val)}"`);
    }
    // Force safe rel on links that open a new tab.
    if (tag === 'a') {
      const hasTargetBlank = /target\s*=\s*["']?_blank/i.test(attrs);
      if (hasTargetBlank && !kept.some((k) => k.startsWith('rel='))) kept.push('rel="noopener noreferrer"');
    }
    const voidTag = ['br', 'hr', 'img', 'col'].includes(tag);
    return `<${tag}${kept.length ? ' ' + kept.join(' ') : ''}${voidTag ? ' /' : ''}>`;
  });
  return out;
}

module.exports = { sha256, rand, esc, slugify, safeEqual, stripHtml, readingMinutes, makeExcerpt, sanitizeHtml, safeUrl };
