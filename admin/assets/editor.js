// Post editor: toolbar, image picker, FAQ rows, slug helper and the live SEO panel.
(function () {
  var form = document.getElementById('post-form');
  if (!form) return;
  var cfg = {};
  try { cfg = JSON.parse(document.getElementById('seo-config').textContent); } catch (e) {}
  var $ = function (id) { return document.getElementById(id); };
  var editor = $('editor'), source = $('source'), content = $('content'), sourceMode = false;

  // ---------- toolbar ----------
  function currentHtml() { return sourceMode ? source.value : editor.innerHTML; }
  function toggleSource(btn) {
    if (!sourceMode) { source.value = editor.innerHTML.replace(/></g, '>\n<'); source.hidden = false; editor.hidden = true; sourceMode = true; btn.classList.add('on'); }
    else { editor.innerHTML = source.value; source.hidden = true; editor.hidden = false; sourceMode = false; btn.classList.remove('on'); }
  }
  $('toolbar').addEventListener('click', function (e) {
    var b = e.target.closest('button'); if (!b) return;
    e.preventDefault();
    var act = b.getAttribute('data-act'), cmd = b.getAttribute('data-cmd');
    if (act === 'source') return toggleSource(b);
    if (sourceMode) return;
    editor.focus();
    if (cmd === 'formatBlock') document.execCommand('formatBlock', false, b.getAttribute('data-val'));
    else if (cmd) document.execCommand(cmd, false, null);
    else if (act === 'link') {
      var url = window.prompt('Link address (https://...)', 'https://');
      if (url && /^(https?:\/\/|mailto:|tel:|\/)/i.test(url)) document.execCommand('createLink', false, url);
      var sel = editor.querySelectorAll('a[href="' + url + '"]'); sel.forEach(function (a) { if (/^https?:/i.test(url)) { a.target = '_blank'; a.rel = 'noopener noreferrer'; } });
    } else if (act === 'table') {
      var n = parseInt(window.prompt('How many columns?', '3'), 10); if (!(n > 0 && n < 8)) return;
      var r = '<tr>' + new Array(n + 1).join('<td>&nbsp;</td>') + '</tr>';
      document.execCommand('insertHTML', false, '<table><thead><tr>' + new Array(n + 1).join('<th>Heading</th>') + '</tr></thead><tbody>' + r + r + '</tbody></table><p></p>');
    } else if (act === 'image') openPicker(function (it) {
      editor.focus();
      document.execCommand('insertHTML', false, '<figure><img src="' + it.path + '" alt="' + (it.alt || '').replace(/"/g, '&quot;') + '"><figcaption>' + (it.alt || '') + '</figcaption></figure><p></p>');
      update();
    });
    update();
  });

  // ---------- image picker (also used for the cover image) ----------
  var picker = $('picker'), grid = $('picker-grid'), onPick = null;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function loadMedia() {
    grid.innerHTML = '<p class="muted">Loading…</p>';
    fetch(cfg.mediaUrl + '&json=1', { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.json(); }).then(function (d) {
      grid.innerHTML = (d.items || []).map(function (it) {
        return '<div class="media-item" data-path="' + esc(it.path) + '" data-alt="' + esc(it.alt) + '"><img src="' + esc(it.thumb) + '" alt=""><small class="muted">' + esc(it.alt || it.path.split('/').pop()) + '</small></div>';
      }).join('') || '<p class="muted">No images yet: upload one above.</p>';
    }).catch(function () { grid.innerHTML = '<p class="muted">Could not load images.</p>'; });
  }
  function openPicker(cb) { onPick = cb; picker.hidden = false; loadMedia(); }
  function closePicker() { picker.hidden = true; onPick = null; }
  picker.addEventListener('click', function (e) {
    if (e.target === picker || e.target.hasAttribute('data-picker-close')) return closePicker();
    var it = e.target.closest('.media-item'); if (it && onPick) { var cb = onPick; closePicker(); cb({ path: it.getAttribute('data-path'), alt: it.getAttribute('data-alt') }); }
  });
  $('picker-upload').addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(e.target); fd.append('csrf', cfg.csrf); fd.append('action', 'upload');
    fetch(cfg.mediaUrl + '&json=1', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); }).then(function (d) { if (!d.ok) return window.alert(d.error || 'Upload failed.'); e.target.reset(); loadMedia(); })
      .catch(function () { window.alert('Upload failed.'); });
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-act]'); if (!b) return;
    var a = b.getAttribute('data-act');
    if (a === 'cover') openPicker(function (it) { $('cover_image').value = it.path; $('cover-preview').innerHTML = '<img src="' + esc(it.path) + '" alt="">'; if (!$('cover_alt').value) $('cover_alt').value = it.alt || ''; update(); });
    if (a === 'cover-clear') { $('cover_image').value = ''; $('cover-preview').innerHTML = ''; update(); }
  });

  // ---------- FAQ rows ----------
  $('faq-add').addEventListener('click', function () {
    var d = document.createElement('div'); d.className = 'faq';
    d.innerHTML = '<input name="faq_q[]" placeholder="Question" maxlength="300"><textarea name="faq_a[]" rows="2" placeholder="Answer: 2 to 4 short sentences" maxlength="1500"></textarea><button type="button" class="btn sm ghost" data-faq-remove>Remove</button>';
    $('faqs').appendChild(d);
  });
  $('faqs').addEventListener('click', function (e) { var b = e.target.closest('[data-faq-remove]'); if (b) { var rows = $('faqs').querySelectorAll('.faq'); if (rows.length > 1) b.closest('.faq').remove(); else rows[0].querySelectorAll('input,textarea').forEach(function (x) { x.value = ''; }); } });

  // ---------- slug, counters, SEO panel ----------
  var slugTouched = !!$('slug').value;
  function slugify(s) { return s.toLowerCase().replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 160); }
  $('slug').addEventListener('input', function () { slugTouched = true; $('slug-preview').textContent = $('slug').value; });
  $('title').addEventListener('input', function () { if (!slugTouched) { $('slug').value = slugify($('title').value); $('slug-preview').textContent = $('slug').value; } });
  var timer = null;
  function update() {
    clearTimeout(timer); timer = setTimeout(run, 250);
  }
  function run() {
    $('mt-count').textContent = $('meta_title').value.length + ' characters (aim for under about 58)';
    $('md-count').textContent = $('meta_desc').value.length + ' characters (aim for 120 to 155)';
    if (!window.SEOAssistant) return;
    var html = currentHtml();
    var faq = [].slice.call(document.querySelectorAll('#faqs .faq')).map(function (f) { var q = f.querySelector('input').value.trim(), a = f.querySelector('textarea').value.trim(); return q && a ? '<h3>' + esc(q) + '</h3><p>' + esc(a) + '</p>' : ''; }).join('');
    var r = window.SEOAssistant.analyze({ keyphrase: $('focus_keyword').value, title: $('title').value, slug: $('slug').value, metaTitle: $('meta_title').value || $('title').value,
      metaDesc: $('meta_desc').value, html: html + faq, cover: { present: !!$('cover_image').value, alt: $('cover_alt').value }, siteHost: cfg.host, brand: cfg.brand, basePath: '/blog' });
    $('seo-score').innerHTML = window.SEOAssistant.badge(r.seoScore);
    $('seo-list').innerHTML = window.SEOAssistant.listHtml(r.seo);
    $('read-list').innerHTML = '<h3 style="margin:.6rem 0 .2rem;font-size:.9rem">Readability ' + window.SEOAssistant.badge(r.readScore) + '</h3>' + window.SEOAssistant.listHtml(r.readability);
  }
  form.addEventListener('input', update);
  editor.addEventListener('input', update);

  // ---------- save: copy the editor into the hidden field ----------
  form.addEventListener('submit', function () { content.value = currentHtml(); });
  run();
})();
