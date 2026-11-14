// Aagahi Study: Class 1-12 lessons and tests powered by the Vidaara content API. No redirects, no dependencies
// (KaTeX is loaded separately for maths). Hash routes:
//   #/                      grade picker          #/g/8/math                 chapters
//   #/g/8                   subject picker        #/g/8/math/<chapter>       lesson
//                                                 #/g/8/math/<chapter>/test  chapter test
(function () {
  var root = document.getElementById('study');
  if (!root) return;
  var API = (root.getAttribute('data-api') || 'https://vidaara.org/api/v1').replace(/\/+$/, '');
  var cache = {};
  var PROG_KEY = 'aagahi-study-progress-v1';

  /* ---------- helpers ---------- */
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  // Content comes from our own Vidaara API, but still strip anything executable before inserting HTML.
  function clean(html) {
    return String(html == null ? '' : html)
      .replace(/<\s*(script|style|iframe|object|embed|link|meta)[\s\S]*?(<\/\s*\1\s*>|>)/gi, '')
      .replace(/\son[a-z]+\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '')
      .replace(/javascript:/gi, '');
  }
  function get(path) {
    if (cache[path]) return Promise.resolve(cache[path]);
    return fetch(API + path).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    }).then(function (j) {
      if (!j.ok) throw new Error(j.message || 'Error');
      cache[path] = j.data; return j.data;
    });
  }
  function load(p) { try { return JSON.parse(localStorage.getItem(PROG_KEY)) || {}; } catch (e) { return {}; } }
  function save(p) { try { localStorage.setItem(PROG_KEY, JSON.stringify(p)); } catch (e) {} }
  function mark(key, patch) { var p = load(); p[key] = Object.assign(p[key] || {}, patch); save(p); }
  function math(el) {
    if (window.renderMathInElement) {
      try { window.renderMathInElement(el, { delimiters: [{ left: '$$', right: '$$', display: true }, { left: '\\[', right: '\\]', display: true }, { left: '\\(', right: '\\)', display: false }, { left: '$', right: '$', display: false }], throwOnError: false }); } catch (e) {}
    }
  }
  function show(html) { root.innerHTML = html; math(root); window.scrollTo({ top: root.getBoundingClientRect().top + window.scrollY - 90, behavior: 'smooth' }); }
  function crumbs(parts) {
    return '<nav class="crumbs">' + parts.map(function (p) { return p.href ? '<a href="' + p.href + '">' + esc(p.t) + '</a>' : '<span>' + esc(p.t) + '</span>'; }).join(' <i>›</i> ') + '</nav>';
  }
  function loading() { root.innerHTML = '<p class="st-msg">Loading…</p>'; }
  function fail(e) {
    root.innerHTML = '<div class="st-msg err"><b>Could not load the lessons right now.</b><br>Please check your internet connection and <a href="" onclick="location.reload();return false">try again</a>.</div>';
    if (window.console) console.error('[study]', e);
  }
  var ICONS = { math: '➗', mathematics: '➗', science: '🔬', physics: '⚛️', chemistry: '🧪', biology: '🧬', english: '📖', reading: '📖', humanities: '🌍', 'computer-science': '💻', economics: '📈', accountancy: '🧾', arts: '🎨' };
  function icon(slug) { return ICONS[slug] || '📘'; }

  /* ---------- views ---------- */
  function viewGrades() {
    loading();
    get('/catalog').then(function (d) {
      var groups = {};
      d.classes.forEach(function (c) { if (/^\d+$/.test(c.id)) (groups[c.group] = groups[c.group] || []).push(c); });
      var done = Object.keys(load()).filter(function (k) { return load()[k].done; }).length;
      var html = '<h2>Choose your class</h2><p class="st-sub">Free lessons, worked examples and practice tests for Class 1 to 12.' + (done ? ' You have completed <b>' + done + '</b> chapter' + (done > 1 ? 's' : '') + '. Keep going!' : '') + '</p>';
      Object.keys(groups).forEach(function (g) {
        html += '<h3 class="st-group">' + esc(g) + '</h3><div class="st-grid">' + groups[g].map(function (c) {
          var n = c.subjects.filter(function (s) { return s.api; }).length;
          return '<a class="st-card" href="#/g/' + c.id + '"><b>' + esc(c.label) + '</b><small>' + n + ' subjects</small></a>';
        }).join('') + '</div>';
      });
      show(html);
    }).catch(fail);
  }

  function viewSubjects(g) {
    loading();
    get('/catalog').then(function (d) {
      var c = d.classes.filter(function (x) { return x.id === g; })[0];
      if (!c) return viewGrades();
      var subs = c.subjects.filter(function (s) { return s.api; });
      show(crumbs([{ t: 'Study', href: '#/' }, { t: c.label }]) + '<h2>' + esc(c.label) + '</h2><p class="st-sub">Pick a subject.</p><div class="st-grid">' +
        subs.map(function (s) { return '<a class="st-card" href="#/g/' + g + '/' + s.slug + '"><span class="ic">' + icon(s.slug) + '</span><b>' + esc(s.name) + '</b></a>'; }).join('') + '</div>');
    }).catch(fail);
  }

  function viewChapters(g, sub) {
    loading();
    get('/courses/' + g + '/' + sub).then(function (d) {
      var p = load();
      show(crumbs([{ t: 'Study', href: '#/' }, { t: 'Class ' + g, href: '#/g/' + g }, { t: d.subjectLabel }]) +
        '<h2>' + esc(d.subjectLabel) + ' · Class ' + esc(g) + '</h2><p class="st-sub">' + d.chapters.length + ' chapters</p><ol class="st-chapters">' +
        d.chapters.filter(function (c) { return c.available; }).map(function (c) {
          var k = g + '/' + sub + '/' + c.slug, pr = p[k] || {};
          return '<li><a href="#/g/' + k + '"><span class="n">' + esc(c.number) + '</span><span class="t">' + esc(c.title) + '</span>' +
            (pr.done ? '<span class="ok">✓ done</span>' : pr.score != null ? '<span class="sc">best ' + pr.score + '%</span>' : '') + '</a></li>';
        }).join('') + '</ol>');
    }).catch(fail);
  }

  function steps(text) {
    var lines = String(text || '').split(/\n+/).map(function (l) { return l.trim(); }).filter(Boolean);
    return '<div class="solution">' + lines.map(function (l) { return '<p>' + clean(l.replace(/^[-*]\s+/, '')) + '</p>'; }).join('') + '</div>';
  }

  function topicHtml(t) {
    var h = '<h3>' + esc(t.title) + '</h3>';
    if (t.concept) h += '<div class="concept">' + clean(t.concept) + '</div>';
    if (t.visual && typeof t.visual === 'string') h += '<div class="visual">' + clean(t.visual) + '</div>';
    if (Array.isArray(t.examples) && t.examples.length) {
      h += '<h4>Worked examples</h4>' + t.examples.map(function (e) {
        return '<div class="example"><div class="q">' + clean(e.problem) + '</div><details><summary>Show solution</summary>' + steps(e.solution) + '</details></div>';
      }).join('');
    }
    if (Array.isArray(t.recap) && t.recap.length) h += '<div class="recap"><b>Quick recap</b><ul>' + t.recap.map(function (r) { return '<li>' + clean(r) + '</li>'; }).join('') + '</ul></div>';
    if (Array.isArray(t.activities) && t.activities.length) h += '<div class="recap"><b>Activities</b><ul>' + t.activities.map(function (a) { return '<li>' + clean(typeof a === 'string' ? a : (a.title || a.text || '')) + '</li>'; }).join('') + '</ul></div>';
    return h;
  }

  function quizHtml(quiz, idPrefix) {
    return '<div class="mini-quiz" data-q="' + idPrefix + '"><h4>Check yourself</h4>' + quiz.map(function (q, i) {
      return '<div class="qz" data-correct="' + q.correct + '"><p><b>' + (i + 1) + '.</b> ' + clean(q.q || q.text) + '</p>' + (q.options || []).map(function (o, j) {
        return '<button type="button" class="opt" data-j="' + j + '">' + clean(o) + '</button>';
      }).join('') + '<div class="why" hidden>' + clean(q.explanation || '') + '</div></div>';
    }).join('') + '</div>';
  }

  function bindQuiz(scope) {
    scope.addEventListener('click', function (e) {
      var b = e.target.closest('.opt'); if (!b) return;
      var qz = b.closest('.qz'); if (qz.getAttribute('data-done')) return;
      qz.setAttribute('data-done', '1');
      var c = +qz.getAttribute('data-correct');
      qz.querySelectorAll('.opt').forEach(function (o) {
        var j = +o.getAttribute('data-j'); o.disabled = true;
        if (j === c) o.classList.add('right'); else if (o === b) o.classList.add('wrong');
      });
      var w = qz.querySelector('.why'); if (w && w.textContent.trim()) w.hidden = false;
    });
  }

  function viewLesson(g, sub, slug) {
    loading();
    get('/courses/' + g + '/' + sub + '/' + slug).then(function (d) {
      var c = d.chapter, topics = c.topics || [];
      var key = g + '/' + sub + '/' + slug;
      mark(key, { seen: Date.now() });
      var html = crumbs([{ t: 'Study', href: '#/' }, { t: 'Class ' + g, href: '#/g/' + g }, { t: d.subjectLabel, href: '#/g/' + g + '/' + sub }, { t: c.title || c.chapter_title }]) +
        '<h2>' + esc(c.chapter_number || '') + (c.chapter_number ? '. ' : '') + esc(c.title || c.chapter_title) + '</h2>';
      if (topics.length > 1) html += '<ul class="st-toc">' + topics.map(function (t, i) { return '<li><a href="#" data-top="' + i + '">' + esc(t.title) + '</a></li>'; }).join('') + '</ul>';
      topics.forEach(function (t, i) {
        html += '<section class="topic" id="top' + i + '">' + topicHtml(t) + (Array.isArray(t.quiz) && t.quiz.length ? quizHtml(t.quiz, 'q' + i) : '') + '</section>';
      });
      if (!topics.length) html += '<p class="st-msg">This chapter has no lesson content yet.</p>';
      html += '<div class="st-actions">' + (d.tests && d.tests.online ? '<a class="btn" href="#/g/' + key + '/test">Take the chapter test (' + d.tests.online.count + ' questions)</a>' : '') +
        '<button class="btn o" type="button" id="markdone">Mark chapter as done</button>' +
        (d.next ? ' <a class="btn o" href="#/g/' + g + '/' + sub + '/' + esc(d.next.slug) + '">Next: ' + esc(d.next.title) + ' →</a>' : '') + '</div>';
      show(html);
      bindQuiz(root);
      root.querySelectorAll('[data-top]').forEach(function (a) {
        a.addEventListener('click', function (ev) { ev.preventDefault(); document.getElementById('top' + a.getAttribute('data-top')).scrollIntoView({ behavior: 'smooth', block: 'start' }); });
      });
      var md = document.getElementById('markdone');
      if (md) { if ((load()[key] || {}).done) md.textContent = '✓ Marked as done'; md.addEventListener('click', function () { mark(key, { done: true }); md.textContent = '✓ Marked as done'; }); }
    }).catch(fail);
  }

  function viewTest(g, sub, slug) {
    loading();
    var key = g + '/' + sub + '/' + slug;
    get('/courses/' + key + '/test/online').then(function (t) {
      var qs = t.questions || [], i = 0, score = 0;
      function back() { return '<a class="btn o" href="#/g/' + key + '">← Back to lesson</a>'; }
      function ask() {
        var q = qs[i];
        show(crumbs([{ t: 'Study', href: '#/' }, { t: 'Class ' + g, href: '#/g/' + g }, { t: t.chapterTitle || slug, href: '#/g/' + key }, { t: 'Test' }]) +
          '<div class="progress"><span style="width:' + Math.round(i / qs.length * 100) + '%"></span></div><p class="st-sub">Question ' + (i + 1) + ' of ' + qs.length + '</p>' +
          '<div class="qz big" data-correct="' + q.correct + '"><p>' + clean(q.text || q.q) + '</p>' + (q.options || []).map(function (o, j) { return '<button type="button" class="opt" data-j="' + j + '">' + clean(o) + '</button>'; }).join('') +
          '<div class="why" hidden>' + clean(q.explanation || '') + '</div></div><div class="st-actions"><button class="btn" id="nextq" hidden type="button">' + (i + 1 === qs.length ? 'See my score' : 'Next question →') + '</button></div>');
        var qz = root.querySelector('.qz');
        qz.addEventListener('click', function (e) {
          var b = e.target.closest('.opt'); if (!b || qz.getAttribute('data-done')) return;
          qz.setAttribute('data-done', '1');
          var c = +qz.getAttribute('data-correct'), j0 = +b.getAttribute('data-j');
          if (j0 === c) score++;
          qz.querySelectorAll('.opt').forEach(function (o) { var j = +o.getAttribute('data-j'); o.disabled = true; if (j === c) o.classList.add('right'); else if (o === b) o.classList.add('wrong'); });
          var w = qz.querySelector('.why'); if (w && w.textContent.trim()) { w.hidden = false; math(w); }
          document.getElementById('nextq').hidden = false;
        });
        document.getElementById('nextq').addEventListener('click', function () { i++; i < qs.length ? ask() : finish(); });
      }
      function finish() {
        var pct = Math.round(score / qs.length * 100), prev = (load()[key] || {}).score || 0;
        mark(key, { score: Math.max(pct, prev), done: pct >= 60 || (load()[key] || {}).done });
        show(crumbs([{ t: 'Study', href: '#/' }, { t: 'Class ' + g, href: '#/g/' + g }, { t: t.chapterTitle || slug, href: '#/g/' + key }, { t: 'Result' }]) +
          '<div class="result"><div class="big-n">' + pct + '%</div><p>You got <b>' + score + '</b> of <b>' + qs.length + '</b> right. ' + (pct >= 80 ? 'Excellent work! 🎉' : pct >= 60 ? 'Good job, keep practising.' : 'Re-read the lesson and try again, you can do it.') + '</p></div>' +
          '<div class="st-actions"><a class="btn" href="#/g/' + key + '/test" onclick="location.reload()">Try again</a>' + back() + '</div>');
      }
      qs.length ? ask() : show('<p class="st-msg">No test questions for this chapter yet.</p>' + back());
    }).catch(fail);
  }

  /* ---------- router ---------- */
  function route() {
    var parts = (location.hash.replace(/^#\/?/, '') || '').split('/').filter(Boolean);
    if (parts[0] !== 'g') return viewGrades();
    if (parts.length === 2) return viewSubjects(parts[1]);
    if (parts.length === 3) return viewChapters(parts[1], parts[2]);
    if (parts.length === 4) return viewLesson(parts[1], parts[2], parts[3]);
    if (parts.length === 5 && parts[4] === 'test') return viewTest(parts[1], parts[2], parts[3]);
    return viewGrades();
  }
  window.addEventListener('hashchange', route);
  route();
})();
