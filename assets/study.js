// Aagahi Study: Class 1-12 lessons and tests powered by the Vidaara content API. No redirects, no dependencies
// (KaTeX is loaded separately for maths). Hash routes:
//   #/                      grade picker          #/g/8/math                 chapters
//   #/g/8                   subject picker        #/g/8/math/<chapter>       lesson
//                                                 #/g/8/math/<chapter>/test  chapter test
//   #/login  #/join (open sign-up with captcha, like vidaara.org)  #/me (my progress, delete account)
(function () {
  var root = document.getElementById('study');
  if (!root) return;
  var API = (root.getAttribute('data-api') || 'https://vidaara.org/api/v1').replace(/\/+$/, '');
  var PARTNER_CODE = (root.getAttribute('data-partner-code') || '').trim(); // this NGO's public class code (optional)
  var cache = {};
  var PROG_KEY = 'aagahi-study-progress-v1';
  var TOK_KEY = 'aagahi-study-token-v1', USER_KEY = 'aagahi-study-user-v1';
  function token() { try { return localStorage.getItem(TOK_KEY) || ''; } catch (e) { return ''; } }
  function me() { try { return JSON.parse(localStorage.getItem(USER_KEY)) || null; } catch (e) { return null; } }
  function setSession(t, u) { try { localStorage.setItem(TOK_KEY, t); localStorage.setItem(USER_KEY, JSON.stringify(u || {})); } catch (e) {} }
  function clearSession() { try { localStorage.removeItem(TOK_KEY); localStorage.removeItem(USER_KEY); } catch (e) {} }
  // Calls the Vidaara partner API. Resolves { ok, status, data, error }; never rejects.
  function papi(method, path, body) {
    var h = { 'Content-Type': 'application/json' };
    if (token()) h.Authorization = 'Bearer ' + token();
    return fetch(API + '/partner' + path, { method: method, headers: h, body: body ? JSON.stringify(body) : undefined })
      .then(function (r) { return r.json().then(function (j) { if (r.status === 401 && token()) clearSession(); return { ok: !!j.ok, status: r.status, data: j.data, error: j.error }; }); })
      .catch(function () { return { ok: false, status: 0, error: 'No internet connection. Please try again.' }; });
  }
  // Fire-and-forget progress saving when signed in (the page keeps working signed out / offline).
  function track(path, body) { if (token()) papi('POST', path, body); }

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
  function bar() {
    var u = me();
    return '<div class="st-bar">' + (token() && u
      ? '<span>Hi, <b>' + esc((u.name || '').split(' ')[0]) + '</b></span><a href="#/me">My progress</a><a href="#" data-logout>Sign out</a>'
      : '<span>Save your progress:</span><a href="#/login">Sign in</a><a href="#/join">Create free account</a>') + '</div>';
  }
  function show(html) { root.innerHTML = bar() + html; math(root); window.scrollTo({ top: root.getBoundingClientRect().top + window.scrollY - 90, behavior: 'smooth' }); }
  root.addEventListener('click', function (e) {
    var lo = e.target.closest && e.target.closest('[data-logout]');
    if (!lo) return;
    e.preventDefault();
    papi('POST', '/logout').then(function () { clearSession(); location.hash = '#/'; route(); });
  });
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
    var serverP = token() ? papi('GET', '/progress?class=' + encodeURIComponent(g) + '&subject=' + encodeURIComponent(sub === 'math' ? 'mathematics' : sub)) : Promise.resolve(null);
    Promise.all([get('/courses/' + g + '/' + sub), serverP]).then(function (both) {
      var d = both[0], sp = both[1] && both[1].ok ? both[1].data.byChapter : {};
      var p = load();
      Object.keys(sp).forEach(function (slug) { var k = g + '/' + sub + '/' + slug; p[k] = Object.assign({}, p[k] || {}, { done: (p[k] && p[k].done) || sp[slug].status === 'completed' }); });
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
      var lessonInfo = { class: g, subject: d.subject || sub, chapter_slug: slug, title: c.title || c.chapter_title, url: d.url };
      track('/progress/lesson', lessonInfo);
      startBeat();
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
      if (md) { if ((load()[key] || {}).done) md.textContent = '✓ Marked as done'; md.addEventListener('click', function () { mark(key, { done: true }); md.textContent = '✓ Marked as done'; track('/progress/lesson', Object.assign({ complete: true }, lessonInfo)); }); }
    }).catch(fail);
  }

  function viewTest(g, sub, slug) {
    loading();
    var key = g + '/' + sub + '/' + slug;
    get('/courses/' + key + '/test/online').then(function (t) {
      var ctx = { class: g, subject: t.subject || sub, chapter_slug: slug, test_type: 'online' };
      var qs = t.questions || [], i = 0, score = 0, startedAt = Date.now();
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
        track('/progress/test', Object.assign({ score: score, total: qs.length, time_taken_s: Math.round((Date.now() - startedAt) / 1000) }, ctx));
        show(crumbs([{ t: 'Study', href: '#/' }, { t: 'Class ' + g, href: '#/g/' + g }, { t: t.chapterTitle || slug, href: '#/g/' + key }, { t: 'Result' }]) +
          '<div class="result"><div class="big-n">' + pct + '%</div><p>You got <b>' + score + '</b> of <b>' + qs.length + '</b> right. ' + (pct >= 80 ? 'Excellent work! 🎉' : pct >= 60 ? 'Good job, keep practising.' : 'Re-read the lesson and try again, you can do it.') + '</p></div>' +
          '<div class="st-actions"><a class="btn" href="#/g/' + key + '/test" onclick="location.reload()">Try again</a>' + back() + '</div>');
      }
      qs.length ? ask() : show('<p class="st-msg">No test questions for this chapter yet.</p>' + back());
    }).catch(fail);
  }

  /* ---------- accounts (Vidaara LMS) ---------- */
  var beat = null;
  function startBeat() {
    stopBeat();
    if (!token()) return;
    beat = setInterval(function () { if (!document.hidden) papi('POST', '/heartbeat', { seconds: 30 }); }, 30000);
  }
  function stopBeat() { if (beat) { clearInterval(beat); beat = null; } }
  function msg(el, text, bad) { if (!el) return; el.className = 'st-note' + (bad ? ' bad' : ''); el.textContent = text; }
  function field(id, label, type, extra) { return '<label for="' + id + '">' + label + '</label><input id="' + id + '" name="' + id + '" type="' + (type || 'text') + '" ' + (extra || '') + ' required>'; }

  function authCard(title, sub, inner) {
    return '<div class="auth-wrap"><div class="auth-card"><div class="auth-logo"><span class="logo-emoji">🌱</span> Aagahi</div>' +
      '<div class="auth-tagline">Free education for every student</div><div class="auth-h">' + title + '</div><div class="auth-sub">' + sub + '</div>' + inner + '</div></div>';
  }
  function captchaRow() {
    return '<div class="form-group"><label class="form-label" for="captcha">Security check</label><div class="captcha-row"><div class="captcha-img" id="captchaImg">Loading…</div>' +
      '<button type="button" class="captcha-refresh" id="captchaRefresh" aria-label="Get a new security image" title="New image">↻</button></div>' +
      '<input class="form-input" type="text" id="captcha" name="captcha" placeholder="Type the characters above" required autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false"></div>';
  }
  var captchaId = '';
  function loadCaptcha() {
    var img = document.getElementById('captchaImg'); if (!img) return;
    img.textContent = 'Loading…'; captchaId = '';
    papi('GET', '/captcha').then(function (r) {
      if (!r.ok) { img.textContent = 'Could not load. Tap ↻'; return; }
      captchaId = r.data.id; img.innerHTML = r.data.svg;
    });
  }
  function bindCaptcha() {
    loadCaptcha();
    var rf = document.getElementById('captchaRefresh');
    if (rf) rf.addEventListener('click', function () { var c = document.getElementById('captcha'); if (c) c.value = ''; loadCaptcha(); });
  }
  function ff(id, label, type, extra) { return '<div class="form-group"><label class="form-label" for="' + id + '">' + label + '</label><input class="form-input" id="' + id + '" name="' + id + '" type="' + (type || 'text') + '" ' + (extra || '') + ' required></div>'; }
  function err(el, text) { if (!el) return; el.hidden = !text; el.textContent = text ? '⚠️ ' + text : ''; }

  function viewLogin() {
    show(authCard('Welcome back', 'Log in to see your progress.',
      '<div id="amsg" class="auth-error" hidden></div><form id="lf">' + ff('email', 'Email address', 'email', 'placeholder="you@example.com" autocomplete="email"') +
      ff('password', 'Password', 'password', 'autocomplete="current-password"') +
      '<div class="forgot"><a href="https://vidaara.org/auth/forgot-password" target="_blank" rel="noopener">Forgot password?</a></div>' + captchaRow() +
      '<button class="btn-auth" type="submit">Log in →</button></form>' +
      '<div class="auth-divider">New here? <a class="auth-link" href="#/join">Create a free account →</a></div>'));
    bindCaptcha();
    document.getElementById('lf').addEventListener('submit', function (e) {
      e.preventDefault();
      var f = e.target, m = document.getElementById('amsg'); err(m, '');
      papi('POST', '/login', { email: f.email.value, password: f.password.value, captcha_id: captchaId, captcha: f.captcha.value }).then(function (r) {
        if (!r.ok) { err(m, r.error || 'Could not log in.'); f.captcha.value = ''; loadCaptcha(); return; }
        setSession(r.data.token, r.data.user); location.hash = '#/me';
      });
    });
  }

  function viewJoin() {
    show(authCard('Create your free account', 'No credit card. No ads. Always free.',
      '<div class="free-badge">🎓 Free Class 1 to 12 lessons, powered by Vidaara</div><div id="amsg" class="auth-error" hidden></div>' +
      '<form id="jf">' + ff('name', 'Full name', 'text', 'maxlength="60" placeholder="Asha Kumari" autocomplete="name"') +
      ff('email', 'Email address', 'email', 'placeholder="you@example.com" autocomplete="email"') +
      ff('password', 'Password', 'password', 'minlength="8" placeholder="Min. 8 characters" autocomplete="new-password"') +
      '<div class="form-group"><label class="form-label" for="grade">Your class</label><select class="form-input" id="grade" name="grade" required><option value="">Choose your class…</option>' +
      [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map(function (n) { return '<option value="' + n + '">Class ' + n + '</option>'; }).join('') + '</select></div>' +
      '<input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">' + captchaRow() +
      '<p class="auth-fine">We save only your name, email, class and learning progress. No ads, nothing sold. You can delete your account any time from <i>My progress</i>.</p>' +
      '<button class="btn-auth" type="submit">Create Free Account →</button></form>' +
      '<div class="auth-divider">Already have an account? <a class="auth-link" href="#/login">Log in →</a></div>'));
    bindCaptcha();
    document.getElementById('jf').addEventListener('submit', function (e) {
      e.preventDefault();
      var f = e.target, m = document.getElementById('amsg'); err(m, '');
      var code = PARTNER_CODE; // the NGO's own class code from the site config (students never see or type it)
      papi('POST', '/register', { name: f.name.value, email: f.email.value, password: f.password.value, grade: f.grade.value, code: code, website: f.website.value, captcha_id: captchaId, captcha: f.captcha.value }).then(function (r) {
        if (!r.ok) { err(m, r.error || 'Could not create the account.'); f.captcha.value = ''; loadCaptcha(); return; }
        setSession(r.data.token, r.data.user); location.hash = '#/me';
      });
    });
  }

  function stat(n, label) { return '<div class="st-stat"><b>' + esc(n) + '</b><small>' + esc(label) + '</small></div>'; }
  function viewMe() {
    if (!token()) { location.hash = '#/login'; return; }
    loading();
    papi('GET', '/me').then(function (r) {
      if (!r.ok) { if (r.status === 401) { location.hash = '#/login'; return; } return fail(r.error); }
      var d = r.data, dash = d.dashboard || {};
      var html = crumbs([{ t: 'Study', href: '#/' }, { t: 'My progress' }]) + '<h2>Hi, ' + esc((d.user.name || '').split(' ')[0]) + '</h2>' +
        (d.classes && d.classes[0] ? '<p class="st-sub">Your class: <b>' + esc(d.classes[0].name) + '</b></p>' : '') +
        '<div class="st-stats">' + stat(dash.streak || 0, 'day streak') + stat(dash.lessonsCompleted || 0, 'chapters done') + stat(dash.testsTaken || 0, 'tests taken') + stat((dash.avgScore || 0) + '%', 'average score') + stat(dash.hours || 0, 'hours learned') + '</div>';
      if ((dash.continueLearning || []).length) {
        html += '<h3 class="st-group">Continue learning</h3><ol class="st-chapters">' + dash.continueLearning.map(function (c) {
          var m = /\/courses\/([a-z-]+)\/class-(\d+)\/([a-z0-9-]+)/.exec(c.url || '');
          var href = m ? '#/g/' + m[2] + '/' + m[1] + '/' + m[3] : '#/g/' + esc(c.class);
          return '<li><a href="' + href + '"><span class="n">▶</span><span class="t">' + esc(c.title || c.chapter_slug) + '</span><span class="sc">' + esc(c.percent) + '%</span></a></li>';
        }).join('') + '</ol>';
      }
      if ((dash.recentTests || []).length) {
        html += '<h3 class="st-group">Recent tests</h3><ol class="st-chapters">' + dash.recentTests.map(function (t) {
          return '<li><a href="#/g/' + esc(t.class) + '"><span class="n">' + esc(t.percentage) + '</span><span class="t">' + esc(t.chapter_slug) + '</span><span class="sc">' + esc(t.score) + '/' + esc(t.total) + '</span></a></li>';
        }).join('') + '</ol>';
      }
      html += '<h3 class="st-group">Delete my account</h3><div id="delbox">' + deleteBox(d.deletion) + '</div>';
      show(html); bindDelete();
    });
  }

  function deleteBox(del) {
    if (del && del.status === 'pending_admin') return '<div class="st-info">Your deletion request has been received. Our team will review it and email you when your account is deleted (always within 30 days).</div><button class="btn o" type="button" data-del="cancel">Cancel my request</button>';
    if (del && del.status === 'awaiting_otp') return otpBox();
    return '<div class="st-warn"><b>Please read this carefully.</b><ul><li>All your data will be deleted for ever: your name, email, progress, test scores and badges.</li><li>This cannot be undone.</li>' +
      '<li><b>You will not be able to create a new account with this same email address.</b></li><li>Our team checks every request first, and you will get an email when it is done.</li></ul>' +
      '<label class="st-check"><input type="checkbox" id="delack"> I understand all of this.</label><div id="delmsg" class="st-note"></div>' +
      '<button class="btn danger" type="button" data-del="request">Email me a code</button></div>';
  }
  function otpBox() {
    return '<div class="st-warn"><p>We emailed you a 6-digit code. It works for 10 minutes.</p><input id="delotp" class="st-otp" inputmode="numeric" maxlength="6" placeholder="······" autocomplete="one-time-code">' +
      '<p><b>Last warning:</b> after you confirm, your request goes to our team. Once approved, everything is erased for ever and this email cannot be used again.</p><div id="delmsg" class="st-note"></div>' +
      '<button class="btn danger" type="button" data-del="confirm">Confirm deletion request</button> <button class="btn o" type="button" data-del="cancel">Cancel</button></div>';
  }
  function bindDelete() {
    var box = document.getElementById('delbox'); if (!box) return;
    box.addEventListener('click', function (e) {
      var b = e.target.closest('[data-del]'); if (!b) return;
      var act = b.getAttribute('data-del'), m = function () { return document.getElementById('delmsg'); };
      if (act === 'request') {
        if (!document.getElementById('delack').checked) return msg(m(), 'Please tick the box to show you understand.', true);
        msg(m(), 'Sending the code…');
        papi('POST', '/me/delete/request', { ack: true }).then(function (r) { r.ok ? (box.innerHTML = otpBox()) : msg(m(), r.error, true); });
      } else if (act === 'confirm') {
        msg(m(), 'Checking…');
        papi('POST', '/me/delete/confirm', { otp: document.getElementById('delotp').value }).then(function (r) { r.ok ? (box.innerHTML = deleteBox({ status: 'pending_admin' })) : msg(m(), r.error, true); });
      } else if (act === 'cancel') {
        papi('POST', '/me/delete/cancel').then(function () { box.innerHTML = deleteBox(null); });
      }
    });
  }

  /* ---------- router ---------- */
  function route() {
    var parts = (location.hash.replace(/^#\/?/, '') || '').split('/').filter(Boolean);
    stopBeat();
    if (parts[0] === 'login') return viewLogin();
    if (parts[0] === 'join') return viewJoin();
    if (parts[0] === 'me') return viewMe();
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
