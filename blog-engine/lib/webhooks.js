// lib/webhooks.js — deliver signed events to the integrating site (e.g.
// post.published, post.updated) so a headless site can revalidate its cache or
// re-render. Verify X-BlogForge-Signature = HMAC-SHA256(rawBody, api_secret).
const crypto = require('crypto');

function sign(secret, rawBody) {
  return crypto.createHmac('sha256', secret).update(rawBody).digest('hex');
}

async function dispatch(site, event, data) {
  if (!site || !site.webhook_url) return { skipped: 'no-webhook-url' };
  const payload = { event, sent_at: new Date().toISOString(), site: site.slug, data };
  const raw = JSON.stringify(payload);
  const signature = sign(site.api_secret, raw);
  try {
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), 10000);
    const resp = await fetch(site.webhook_url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-BlogForge-Event': event, 'X-BlogForge-Signature': signature },
      body: raw, signal: ctrl.signal,
    });
    clearTimeout(timer);
    return { ok: resp.status >= 200 && resp.status < 300, statusCode: resp.status };
  } catch (e) { console.error('[webhooks]', e.message); return { ok: false, error: e.message }; }
}

module.exports = { dispatch, sign };
