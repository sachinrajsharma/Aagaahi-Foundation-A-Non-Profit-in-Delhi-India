// lib/ai.js — optional AI writing assist via the Claude API. Entirely disabled
// unless ANTHROPIC_API_KEY is set; the editor hides the AI buttons in that case.
// Uses fetch only (no SDK dependency).
const cfg = require('./config');

async function claude(system, user, maxTokens = 1200) {
  if (!cfg.ai.enabled) throw new Error('AI is not configured (set ANTHROPIC_API_KEY).');
  const resp = await fetch('https://api.anthropic.com/v1/messages', {
    method: 'POST',
    headers: {
      'x-api-key': cfg.ai.apiKey,
      'anthropic-version': '2023-06-01',
      'content-type': 'application/json',
    },
    body: JSON.stringify({
      model: cfg.ai.model,
      max_tokens: maxTokens,
      system,
      messages: [{ role: 'user', content: user }],
    }),
  });
  const data = await resp.json();
  if (!resp.ok) throw new Error('Claude API: ' + (data.error && data.error.message || resp.status));
  return (data.content || []).map((b) => b.text || '').join('').trim();
}

// Suggest an SEO meta title + description for a post.
async function suggestMeta({ title, focusKeyword, content }) {
  const text = String(content || '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').slice(0, 4000);
  const out = await claude(
    'You are an SEO editor. Return ONLY compact JSON: {"meta_title": "...", "meta_desc": "..."}. ' +
    'meta_title <= 60 chars, includes the focus keyword near the front. meta_desc 120-155 chars, ' +
    'includes the focus keyword once, ends with a soft call to action. No markdown, no explanation.',
    `Focus keyword: ${focusKeyword || '(none)'}\nTitle: ${title}\nArticle:\n${text}`,
    400);
  try { const j = JSON.parse(out.match(/\{[\s\S]*\}/)[0]); return { meta_title: j.meta_title, meta_desc: j.meta_desc }; }
  catch (e) { return { meta_title: '', meta_desc: out.slice(0, 155) }; }
}

// Improve/rewrite a chunk of HTML for clarity while keeping the tags.
async function improve({ html, instruction }) {
  return claude(
    'You are a careful copy editor. Improve the given HTML fragment for clarity, flow and readability. ' +
    'Keep it valid HTML using only these tags: p, h2, h3, ul, ol, li, strong, em, a, blockquote. ' +
    'Do NOT add <script>, styles, or images. Return ONLY the HTML fragment.',
    `Instruction: ${instruction || 'Improve readability and fix grammar.'}\n\nHTML:\n${String(html || '').slice(0, 8000)}`,
    2000);
}

// Draft an outline or a short section from a prompt.
async function draft({ prompt, keyword }) {
  return claude(
    'You are a knowledgeable blog writer. Write clear, human, non-generic HTML using only these tags: ' +
    'h2, h3, p, ul, ol, li, strong, em, a, blockquote. Avoid filler like "in today\'s world". ' +
    'Return ONLY the HTML fragment (no <html>/<body>).',
    `Focus keyword: ${keyword || '(none)'}\nWrite about: ${prompt}`,
    2500);
}

module.exports = { enabled: () => cfg.ai.enabled, suggestMeta, improve, draft };
