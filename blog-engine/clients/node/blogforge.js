// clients/node/blogforge.js — tiny Node client for the BlogForge Engine.
// No dependencies (global fetch + crypto). Copy into your Node site.
//
//   const BlogForge = require('./blogforge');
//   const bf = new BlogForge({ baseUrl: 'https://blog-engine.com', apiKey: 'pk_...', apiSecret: 'sk_...' });
//   const { posts } = await bf.listPosts();
//   const { post }  = await bf.getPost('my-slug');   // full HTML + JSON-LD
const crypto = require('crypto');

class BlogForge {
  constructor({ baseUrl, apiKey, apiSecret }) {
    this.baseUrl = String(baseUrl || '').replace(/\/+$/, '');
    this.apiKey = apiKey; this.apiSecret = apiSecret;
  }
  async _req(method, path, body) {
    const resp = await fetch(this.baseUrl + path, {
      method, headers: { 'Content-Type': 'application/json', 'X-API-Key': this.apiKey },
      body: body ? JSON.stringify(body) : undefined,
    });
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok) throw new Error(data.error || ('BlogForge ' + resp.status));
    return data;
  }
  listPosts(opts = {})   { const q = new URLSearchParams(opts).toString(); return this._req('GET', '/api/posts' + (q ? '?' + q : '')); }
  getPost(slug)          { return this._req('GET', '/api/posts/' + encodeURIComponent(slug)); }
  upsertPost(post)       { return this._req('POST', '/api/posts', post); }
  categories()           { return this._req('GET', '/api/categories'); }
  analytics(days = 30)   { return this._req('GET', '/api/analytics?days=' + days); }

  // Verify an inbound webhook (raw body + X-BlogForge-Signature header).
  verifyWebhook(rawBody, signatureHeader) {
    const expected = crypto.createHmac('sha256', this.apiSecret).update(rawBody).digest('hex');
    const a = Buffer.from(expected), b = Buffer.from(String(signatureHeader || ''));
    if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) throw new Error('Invalid webhook signature.');
    return JSON.parse(rawBody);
  }
}
module.exports = BlogForge;
