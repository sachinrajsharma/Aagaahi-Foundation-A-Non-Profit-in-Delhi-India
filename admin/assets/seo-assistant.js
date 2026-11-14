/* BlogForge SEO Assistant — Yoast-style on-page SEO + readability analysis.
 * Self-contained: no dependencies. Used by the post editor via
 * SEOAssistant.analyze(input), .snippetHtml(), .listHtml(), .badge().
 *
 * input = { keyphrase, title, slug, metaTitle, metaDesc, html, cover,
 *           siteHost, brand, basePath }
 * Every check returns { id, status: 'good'|'ok'|'bad'|'off', label, detail }.
 * Ported from the mathstudy.in SEO assistant; generalised for any blog.
 */
window.SEOAssistant = (function () {
  'use strict';

  /* ── text utilities ──────────────────────────────────────────────── */

  function stripHtml(html) {
    var d = document.createElement('div');
    d.innerHTML = html || '';
    return (d.textContent || '').replace(/\s+/g, ' ').trim();
  }
  function words(text) {
    return (text || '').split(/\s+/).filter(function (w) { return /[A-Za-z0-9]/.test(w); });
  }
  function sentences(text) {
    return (text || '').split(/(?<=[.!?])\s+(?=[A-Z0-9"“])/).map(function (s) { return s.trim(); })
      .filter(function (s) { return words(s).length > 0; });
  }
  function syllables(word) {
    word = word.toLowerCase().replace(/[^a-z]/g, '');
    if (!word) return 0;
    if (word.length <= 3) return 1;
    word = word.replace(/(?:[^laeiouy]es|ed|[^laeiouy]e)$/, '').replace(/^y/, '');
    var m = word.match(/[aeiouy]{1,2}/g);
    return m ? m.length : 1;
  }
  function norm(s) { return (s || '').toLowerCase().replace(/&/g, ' and ').replace(/[^a-z0-9\s-]/g, ' ').replace(/\s+/g, ' ').trim(); }
  function containsPhrase(haystack, phrase) {
    if (!phrase) return false;
    return norm(haystack).indexOf(norm(phrase)) !== -1;
  }
  // Function words that carry no search meaning — ignored when matching the
  // keyphrase word-by-word (Google and Yoast both treat them as optional).
  var STOPWORDS = ['a','an','the','and','or','of','in','on','for','to','with','at','by','from','vs'];
  function contentWords(phrase) {
    return norm(phrase).split(' ').filter(function (w) { return w && STOPWORDS.indexOf(w) === -1; });
  }
  // All content words present, any order / with words in between ("IMO Class 7
  // Maths Integers" satisfies "IMO Class 7 Integers") — how Yoast matches too.
  function containsWords(haystack, phrase) {
    var h = ' ' + norm(haystack) + ' ';
    var ws = contentWords(phrase);
    return ws.length > 0 && ws.every(function (w) { return h.indexOf(' ' + w + ' ') !== -1; });
  }
  function countPhrase(haystack, phrase) {
    if (!phrase) return 0;
    var h = norm(haystack), p = norm(phrase);
    if (!p) return 0;
    var n = 0, i = 0;
    while ((i = h.indexOf(p, i)) !== -1) { n++; i += p.length; }
    return n;
  }

  // Pixel width of text as Google renders it (Arial; 20px titles, 14px descriptions).
  var _canvas = null;
  function pixelWidth(text, px) {
    if (!_canvas) _canvas = document.createElement('canvas');
    var ctx = _canvas.getContext('2d');
    ctx.font = px + 'px Arial, sans-serif';
    return Math.round(ctx.measureText(text || '').width);
  }
  function truncateToWidth(text, px, maxW) {
    if (pixelWidth(text, px) <= maxW) return text;
    var t = text;
    while (t.length > 1 && pixelWidth(t + '…', px) > maxW) t = t.slice(0, -1);
    return t.replace(/\s+\S*$/, '') + ' …';
  }

  var TRANSITIONS = ['also','and','but','because','so','then','next','first','second','third','finally','however','moreover','meanwhile','instead','indeed','further','furthermore','additionally','in addition','for example','for instance','in fact','as a result','on the other hand','in short','above all','after all','in conclusion','to summarise','to summarize','most importantly','similarly','likewise','therefore','thus','hence','consequently','besides','earlier','later','eventually','specifically','in particular','of course','still','yet','even so','that is','namely','overall','notably'];

  var PASSIVE_RE = /\b(?:is|are|was|were|be|been|being|get|gets|got)\s+(?:\w+ed|shown|made|done|kept|held|given|taken|written|found|left|paid|sold|built|sent|known|seen|used|chosen|drawn|driven|grown|thrown|worn|torn|broken|spoken|frozen|hidden|forbidden|forgotten)\b/i;

  /* ── analysis ────────────────────────────────────────────────────── */

  function analyze(input) {
    var kp = (input.keyphrase || '').trim();
    var html = input.html || '';
    var text = stripHtml(html);
    var allWords = words(text);
    var sents = sentences(text);
    var doc = document.createElement('div'); doc.innerHTML = html;
    var headings = [].slice.call(doc.querySelectorAll('h1,h2,h3,h4'));
    var paras = [].slice.call(doc.querySelectorAll('p')).map(function (p) { return p.textContent.trim(); }).filter(Boolean);
    var links = [].slice.call(doc.querySelectorAll('a[href]'));
    var imgs = [].slice.call(doc.querySelectorAll('img'));
    var firstPara = paras[0] || '';
    var siteHost = input.siteHost || location.host;

    var seo = [], read = [];
    function add(list, id, status, label, detail) { list.push({ id: id, status: status, label: label, detail: detail || '' }); }

    /* — SEO checks — */

    if (!kp) {
      add(seo, 'kp', 'bad', 'Focus keyphrase', 'No focus keyphrase set. Choose the search term this page should rank for.');
    } else {
      add(seo, 'kp', 'good', 'Focus keyphrase', '“' + kp + '” (' + words(kp).length + ' words)');

      var mt = input.metaTitle || input.title || '';
      if (containsPhrase(mt, kp)) {
        var early = norm(mt).indexOf(norm(kp)) <= Math.max(0, Math.floor(norm(mt).length / 2) - norm(kp).length / 2) || norm(mt).indexOf(norm(kp)) === 0;
        add(seo, 'kp-title', early ? 'good' : 'ok', 'Keyphrase in SEO title', early ? 'Found near the beginning — exactly where Google gives it the most weight.' : 'Found, but move it closer to the beginning for more weight.');
      } else if (containsWords(mt, kp)) add(seo, 'kp-title', 'good', 'Keyphrase in SEO title', 'All keyphrase words are in the title (slightly reworded form — that is fine).');
      else add(seo, 'kp-title', 'bad', 'Keyphrase in SEO title', 'Not found. Titles containing the keyphrase rank and get clicked far more often.');

      if (containsPhrase(input.metaDesc, kp)) add(seo, 'kp-meta', 'good', 'Keyphrase in meta description', 'Found — Google bolds it in the snippet, lifting click-through.');
      else if (containsWords(input.metaDesc, kp)) add(seo, 'kp-meta', 'good', 'Keyphrase in meta description', 'All keyphrase words appear (reworded form) — Google still bolds them.');
      else add(seo, 'kp-meta', 'bad', 'Keyphrase in meta description', 'Not found. Add it once, naturally.');

      var slugNorm = norm((input.slug || '').replace(/-/g, ' '));
      if (slugNorm.indexOf(norm(kp)) !== -1 || containsWords(slugNorm, kp)) add(seo, 'kp-slug', 'good', 'Keyphrase in URL slug', 'All keyphrase words are in the slug.');
      else {
        var kpW = contentWords(kp);
        var hits = kpW.filter(function (w) { return slugNorm.indexOf(w) !== -1; }).length;
        add(seo, 'kp-slug', hits >= Math.ceil(kpW.length / 2) ? 'ok' : 'bad', 'Keyphrase in URL slug', hits ? 'Partly found (' + hits + ' of ' + kpW.length + ' words).' : 'Not found — use the keyphrase words, hyphen-separated.');
      }

      if (containsPhrase(firstPara, kp)) add(seo, 'kp-intro', 'good', 'Keyphrase in introduction', 'Appears in the first paragraph — tells readers and Google immediately what the page is about.');
      else if (containsWords(firstPara, kp)) add(seo, 'kp-intro', 'good', 'Keyphrase in introduction', 'All keyphrase words appear in the first paragraph.');
      else add(seo, 'kp-intro', 'bad', 'Keyphrase in introduction', 'Not in the first paragraph. Work it into the opening naturally.');

      var hHit = headings.some(function (h) { return containsPhrase(h.textContent, kp) || containsWords(h.textContent, kp); });
      add(seo, 'kp-headings', hHit ? 'good' : (headings.length ? 'ok' : 'bad'), 'Keyphrase in subheadings', hHit ? 'Found in at least one subheading.' : (headings.length ? 'Subheadings exist but none contain the keyphrase.' : 'No subheadings at all — add H2/H3 sections.'));

      // Density counts full-phrase occurrences per 100 words (Yoast convention).
      var occurrences = countPhrase(text, kp);
      var density = allWords.length ? (occurrences / allWords.length) * 100 : 0;
      if (!occurrences) add(seo, 'kp-density', containsWords(text, kp) ? 'ok' : 'bad', 'Keyphrase density', containsWords(text, kp) ? 'The exact phrase never appears, though its words do — use the exact phrase at least once.' : 'The keyphrase never appears in the content.');
      else if (density > 3) add(seo, 'kp-density', 'bad', 'Keyphrase density', density.toFixed(1) + '% (' + occurrences + '×) — that reads as keyword stuffing. Aim for 0.5–2.5%.');
      else if (density < 0.3 && occurrences < 2) add(seo, 'kp-density', 'ok', 'Keyphrase density', occurrences + '× in ' + allWords.length + ' words — a mention or two more would help.');
      else add(seo, 'kp-density', 'good', 'Keyphrase density', density.toFixed(1) + '% (' + occurrences + '× in ' + allWords.length + ' words) — healthy.');

      if (occurrences >= 2 && allWords.length > 60) {
        var third = Math.floor(text.length / 3);
        var zones = [text.slice(0, third), text.slice(third, 2 * third), text.slice(2 * third)];
        var zHits = zones.filter(function (z) { return containsPhrase(z, kp); }).length;
        add(seo, 'kp-dist', zHits >= 2 ? 'good' : 'ok', 'Keyphrase distribution', zHits >= 2 ? 'Spread through the content, not bunched in one place.' : 'All mentions sit in one part of the text — spread them out.');
      }

      // Image check: the product COVER counts as the page image. It is shown
      // on the page and in og:image, with the product title as its alt text.
      var cover = input.cover || {};
      var altHit = imgs.some(function (im) { return containsPhrase(im.getAttribute('alt') || '', kp) || containsWords(im.getAttribute('alt') || '', kp); });
      var coverAltHit = containsPhrase(cover.alt || '', kp) || containsWords(cover.alt || '', kp);
      if (imgs.length && altHit) add(seo, 'img-alt', 'good', 'Images', 'A content image alt contains the keyphrase' + (cover.present ? ', and the cover image is set.' : '.'));
      else if (imgs.length) add(seo, 'img-alt', 'ok', 'Images', imgs.length + ' image(s) in the content — put the keyphrase in one alt text.');
      else if (cover.present && coverAltHit) add(seo, 'img-alt', 'good', 'Images', 'The cover image is set. It is shown on the page and shared as the social image, with the keyphrase in its alt text (the page title).');
      else if (cover.present) add(seo, 'img-alt', 'good', 'Images', 'The cover image is set and is shown on the page. Tip: keeping the keyphrase in the title puts it in the image alt text too.');
      else add(seo, 'img-alt', 'bad', 'Images', 'No cover image and no content images — set a cover image.');
    }

    // length + technical checks (independent of keyphrase)
    var wc = allWords.length;
    if (wc >= 300) add(seo, 'length', 'good', 'Content length', wc + ' words — enough substance for Google to understand the page.');
    else if (wc >= 180) add(seo, 'length', 'ok', 'Content length', wc + ' words — usable, but 300+ performs better.');
    else add(seo, 'length', 'bad', 'Content length', wc + ' words — too thin. Aim for at least 300 words.');

    var mtV = input.metaTitle || '';
    var mtPx = pixelWidth(mtV, 20);
    if (!mtV) add(seo, 'title-len', 'bad', 'SEO title width', 'No meta title set.');
    else if (mtPx > 580) add(seo, 'title-len', 'bad', 'SEO title width', mtPx + 'px — Google truncates above ~580px. Shorten it.');
    else if (mtPx < 200) add(seo, 'title-len', 'ok', 'SEO title width', mtPx + 'px — room to add more compelling words (aim 200–580px).');
    else add(seo, 'title-len', 'good', 'SEO title width', mtPx + 'px of 580px — fits fully in the result.');

    var mdV = input.metaDesc || '';
    if (!mdV) add(seo, 'meta-len', 'bad', 'Meta description length', 'No meta description set — Google will pick random text.');
    else if (mdV.length > 165) add(seo, 'meta-len', 'bad', 'Meta description length', mdV.length + ' chars — Google truncates around 155–160. Shorten it.');
    else if (mdV.length < 70) add(seo, 'meta-len', 'ok', 'Meta description length', mdV.length + ' chars — use more of the available space (aim 120–155 chars).');
    else add(seo, 'meta-len', 'good', 'Meta description length', mdV.length + ' chars — uses the snippet space well.');

    var slugV = input.slug || '';
    if (!slugV) add(seo, 'slug', 'bad', 'URL slug', 'Empty slug.');
    else if (/[A-Z_ ]/.test(slugV)) add(seo, 'slug', 'bad', 'URL slug', 'Use lowercase words separated by hyphens only.');
    else if (slugV.length > 75) add(seo, 'slug', 'ok', 'URL slug', slugV.length + ' chars — shorter slugs are easier to read and share.');
    else add(seo, 'slug', 'good', 'URL slug', 'Clean, lowercase, hyphen-separated.');

    var internal = links.filter(function (a) { var h = a.getAttribute('href') || ''; return h.charAt(0) === '/' || h.indexOf(siteHost) !== -1; }).length;
    var external = links.length - internal;
    add(seo, 'links-int', internal ? 'good' : 'ok', 'Internal links', internal ? internal + ' internal link(s) — helps Google discover related pages.' : 'No internal links. Linking to a related e-book or blog strengthens the whole site.');
    if (external) add(seo, 'links-ext', 'good', 'Outbound links', external + ' outbound link(s).');

    /* — readability checks — */

    if (sents.length) {
      var totalSyll = allWords.reduce(function (s, w) { return s + syllables(w); }, 0);
      var asl = allWords.length / sents.length;
      var asw = totalSyll / Math.max(1, allWords.length);
      var flesch = Math.max(0, Math.min(100, 206.835 - 1.015 * asl - 84.6 * asw));
      var fLabel = flesch >= 80 ? 'very easy' : flesch >= 70 ? 'fairly easy' : flesch >= 60 ? 'plain English' : flesch >= 50 ? 'fairly difficult' : flesch >= 30 ? 'difficult' : 'very difficult';
      add(read, 'flesch', flesch >= 60 ? 'good' : flesch >= 50 ? 'ok' : 'bad', 'Flesch Reading Ease', flesch.toFixed(0) + '/100 — ' + fLabel + ' to read' + (flesch < 60 ? '. Shorter words and sentences will lift this.' : '.'));

      var longSents = sents.filter(function (s) { return words(s).length > 20; }).length;
      var lsPct = Math.round(100 * longSents / sents.length);
      add(read, 'sent-len', lsPct <= 25 ? 'good' : lsPct <= 35 ? 'ok' : 'bad', 'Sentence length', lsPct + '% of sentences are over 20 words (aim ≤25%).');

      var passive = sents.filter(function (s) { return PASSIVE_RE.test(s); }).length;
      var pvPct = Math.round(100 * passive / sents.length);
      add(read, 'passive', pvPct <= 10 ? 'good' : pvPct <= 18 ? 'ok' : 'bad', 'Passive voice', pvPct + '% of sentences sound passive (aim ≤10%). Active voice reads with more energy.');

      var withTrans = sents.filter(function (s) {
        var low = ' ' + s.toLowerCase() + ' ';
        return TRANSITIONS.some(function (t) { return low.indexOf(' ' + t + ' ') !== -1 || low.indexOf(' ' + t + ',') !== -1; });
      }).length;
      var trPct = Math.round(100 * withTrans / sents.length);
      add(read, 'trans', trPct >= 30 ? 'good' : trPct >= 20 ? 'ok' : 'bad', 'Transition words', trPct + '% of sentences use a transition word (aim ≥30%). They make text flow.');

      var maxRun = 1, run = 1;
      for (var i = 1; i < sents.length; i++) {
        var a = (words(sents[i - 1])[0] || '').toLowerCase(), b = (words(sents[i])[0] || '').toLowerCase();
        run = (a && a === b) ? run + 1 : 1;
        if (run > maxRun) maxRun = run;
      }
      add(read, 'starts', maxRun < 3 ? 'good' : 'bad', 'Sentence beginnings', maxRun < 3 ? 'Consecutive sentences start with different words.' : maxRun + ' consecutive sentences start with the same word — vary the openings.');
    } else {
      add(read, 'flesch', 'bad', 'Readability', 'No content to analyse yet.');
    }

    var longParas = paras.filter(function (p) { return words(p).length > 150; }).length;
    if (paras.length) add(read, 'para-len', longParas === 0 ? 'good' : 'bad', 'Paragraph length', longParas ? longParas + ' paragraph(s) over 150 words — split them up.' : 'No overlong paragraphs.');

    if (wc > 300) {
      var blocks = html.split(/<h[1-4][^>]*>/i);
      var big = blocks.filter(function (b) { return words(stripHtml(b)).length > 300; }).length;
      add(read, 'subheads', big === 0 ? 'good' : 'ok', 'Subheading distribution', big ? big + ' stretch(es) of 300+ words without a subheading — add one.' : 'Text is broken up with subheadings at healthy intervals.');
    }

    function score(list) {
      var pts = 0, max = 0;
      list.forEach(function (c) { if (c.status === 'off') return; max += 2; pts += c.status === 'good' ? 2 : c.status === 'ok' ? 1 : 0; });
      return max ? Math.round(100 * pts / max) : 0;
    }
    return { seo: seo, readability: read, seoScore: score(seo), readScore: score(read) };
  }

  /* ── snippet preview ─────────────────────────────────────────────── */

  function snippetHtml(input, mobile) {
    var brand = input.brand || 'Your Site';
    var host = input.siteHost || 'example.com';
    var path = (input.basePath || '/blog/') + (input.slug || 'your-slug');
    var crumb = host + ' ' + path.split('/').filter(Boolean).map(function (p) { return '› ' + p; }).join(' ');
    var title = truncateToWidth(input.metaTitle || input.title || 'SEO title preview', 20, mobile ? 360 : 580);
    var desc = input.metaDesc || 'The meta description will be shown here exactly as Google displays it.';
    var maxDesc = mobile ? 1250 : 1840;                      // ≈ two lines at 14px
    desc = truncateToWidth(desc, 14, maxDesc);
    return '<div class="sa-snippet' + (mobile ? ' sa-mobile' : '') + '">' +
      '<div class="sa-crumb"><span class="sa-favicon">' + (brand.charAt(0) || 'B').toUpperCase() + '</span><span><b>' + brand.replace(/</g, '&lt;') + '</b><br>' + crumb + '</span></div>' +
      '<div class="sa-title">' + title.replace(/</g, '&lt;') + '</div>' +
      '<div class="sa-desc">' + desc.replace(/</g, '&lt;') + '</div>' +
      '</div>';
  }

  /* ── rendering ───────────────────────────────────────────────────── */

  function dot(status) { return '<span class="sa-dot sa-' + status + '"></span>'; }
  function listHtml(list) {
    var order = { bad: 0, ok: 1, good: 2 };
    return '<ul class="sa-list">' + list.slice().sort(function (a, b) { return order[a.status] - order[b.status]; })
      .map(function (c) { return '<li>' + dot(c.status) + '<div><b>' + c.label + '</b><span>' + c.detail + '</span></div></li>'; }).join('') + '</ul>';
  }
  function badge(scoreVal) {
    var cls = scoreVal >= 80 ? 'good' : scoreVal >= 55 ? 'ok' : 'bad';
    var word = scoreVal >= 80 ? 'Excellent' : scoreVal >= 55 ? 'Good' : scoreVal >= 35 ? 'Needs work' : 'Poor';
    return '<span class="sa-badge sa-' + cls + '">' + scoreVal + ' · ' + word + '</span>';
  }

  return { analyze: analyze, snippetHtml: snippetHtml, listHtml: listHtml, badge: badge, pixelWidth: pixelWidth };
})();
