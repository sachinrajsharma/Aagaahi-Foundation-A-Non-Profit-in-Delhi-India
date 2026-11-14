/* editor.js — rich text editor + live SEO assistant + media upload + AI assist.
   No heavy dependencies: a contenteditable surface with an execCommand toolbar,
   wired to window.SEOAssistant for real-time on-page SEO + readability scoring. */
(function () {
  var CFG = window.BF_EDITOR || {};
  var editor = document.getElementById('editor');
  var hiddenContent = document.getElementById('content-input');
  if (!editor) return;

  // Seed the editor from the saved HTML, then keep the hidden field in sync.
  editor.innerHTML = hiddenContent.value || '';
  function sync() { hiddenContent.value = editor.innerHTML; analyze(); }
  editor.addEventListener('input', sync);
  editor.addEventListener('blur', sync);

  // Toolbar (execCommand is deprecated but universally supported and adequate here).
  document.querySelectorAll('.toolbar button[data-cmd]').forEach(function (b) {
    b.addEventListener('click', function (e) {
      e.preventDefault(); editor.focus();
      var cmd = b.getAttribute('data-cmd'), val = b.getAttribute('data-val') || null;
      if (cmd === 'createLink') { val = prompt('Link URL:', 'https://'); if (!val) return; }
      document.execCommand(cmd, false, val); sync();
    });
  });

  // Image upload → insert at cursor.
  var imgInput = document.getElementById('img-upload');
  var imgBtn = document.querySelector('.toolbar button[data-img]');
  if (imgBtn && imgInput) {
    imgBtn.addEventListener('click', function (e) { e.preventDefault(); imgInput.click(); });
    imgInput.addEventListener('change', function () {
      if (!imgInput.files || !imgInput.files[0]) return;
      var alt = prompt('Alt text for the image (helps SEO + accessibility):', '') || '';
      var fd = new FormData(); fd.append('file', imgInput.files[0]); fd.append('alt', alt);
      fetch('/admin/media/upload', { method: 'POST', body: fd, headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.url) { editor.focus(); document.execCommand('insertHTML', false, '<img src="' + j.url + '" alt="' + alt.replace(/"/g, '&quot;') + '" loading="lazy">'); sync(); }
          else alert(j.error || 'Upload failed.');
          imgInput.value = '';
        });
    });
  }

  // Auto-slug from the title until the slug is edited by hand.
  var titleEl = document.getElementById('f-title'), slugEl = document.getElementById('f-slug');
  var slugTouched = !!(slugEl && slugEl.value);
  if (slugEl) slugEl.addEventListener('input', function () { slugTouched = true; analyze(); });
  function slugify(s) { return (s || '').toLowerCase().replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
  if (titleEl) titleEl.addEventListener('input', function () { if (!slugTouched && slugEl) slugEl.value = slugify(titleEl.value); analyze(); });

  // Char counters for meta fields.
  ['meta_title', 'meta_desc', 'focus_keyword'].forEach(function (n) {
    var el = document.querySelector('[name="' + n + '"]');
    if (el) el.addEventListener('input', analyze);
  });

  // ── Live SEO assistant ──────────────────────────────────────────────────────
  var seoBox = document.getElementById('sa-body');
  var snippetBox = document.getElementById('sa-snippet');
  var scoreBox = document.getElementById('sa-scores');
  var tab = 'seo';
  document.querySelectorAll('.sa-tabs button').forEach(function (b) {
    b.addEventListener('click', function () {
      tab = b.getAttribute('data-tab');
      document.querySelectorAll('.sa-tabs button').forEach(function (x) { x.classList.toggle('active', x === b); });
      analyze();
    });
  });

  function val(n) { var el = document.querySelector('[name="' + n + '"]'); return el ? el.value : ''; }
  function analyze() {
    if (!window.SEOAssistant) return;
    var input = {
      keyphrase: val('focus_keyword'),
      title: titleEl ? titleEl.value : '',
      slug: slugEl ? slugEl.value : '',
      metaTitle: val('meta_title') || (titleEl ? titleEl.value : ''),
      metaDesc: val('meta_desc'),
      html: editor.innerHTML,
      cover: { present: !!val('cover_image'), alt: (titleEl ? titleEl.value : '') },
      siteHost: CFG.siteHost || location.host,
      brand: CFG.brand || 'Blog',
      basePath: (CFG.blogPath || '/blog') + '/',
    };
    var r = window.SEOAssistant.analyze(input);
    if (snippetBox) snippetBox.innerHTML = window.SEOAssistant.snippetHtml(input, false);
    if (scoreBox) scoreBox.innerHTML =
      'SEO ' + window.SEOAssistant.badge(r.seoScore) + ' &nbsp; Readability ' + window.SEOAssistant.badge(r.readScore);
    if (seoBox) seoBox.innerHTML = window.SEOAssistant.listHtml(tab === 'seo' ? r.seo : r.readability);
    var hidden = document.getElementById('seo_score'); if (hidden) hidden.value = r.seoScore;
  }

  // ── AI assist (buttons only render when AI is configured) ────────────────────
  function post(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
  }
  var aiMeta = document.getElementById('ai-meta');
  if (aiMeta) aiMeta.addEventListener('click', function () {
    aiMeta.disabled = true; var t = aiMeta.textContent; aiMeta.textContent = 'Thinking…';
    post('/admin/ai/meta', { title: titleEl ? titleEl.value : '', focusKeyword: val('focus_keyword'), content: editor.innerHTML })
      .then(function (res) {
        if (res.ok) {
          if (res.j.meta_title) document.querySelector('[name=meta_title]').value = res.j.meta_title;
          if (res.j.meta_desc) document.querySelector('[name=meta_desc]').value = res.j.meta_desc;
          analyze();
        } else alert(res.j.error || 'AI failed.');
        aiMeta.disabled = false; aiMeta.textContent = t;
      });
  });
  var aiDraft = document.getElementById('ai-draft');
  if (aiDraft) aiDraft.addEventListener('click', function () {
    var prompt_ = prompt('What should the AI draft? (a topic or outline)'); if (!prompt_) return;
    aiDraft.disabled = true; var t = aiDraft.textContent; aiDraft.textContent = 'Drafting…';
    post('/admin/ai/draft', { prompt: prompt_, keyword: val('focus_keyword') })
      .then(function (res) {
        if (res.ok && res.j.html) { editor.focus(); document.execCommand('insertHTML', false, res.j.html); sync(); }
        else alert(res.j.error || 'AI failed.');
        aiDraft.disabled = false; aiDraft.textContent = t;
      });
  });

  // Make sure content is synced before submit.
  var form = document.getElementById('post-form');
  if (form) form.addEventListener('submit', function () { hiddenContent.value = editor.innerHTML; });

  analyze();
})();
