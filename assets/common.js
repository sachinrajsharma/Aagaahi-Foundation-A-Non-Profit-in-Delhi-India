// Scroll-reveal + animated counters + mobile menu. No dependencies.
(function () {
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (en) {
      if (!en.isIntersecting) return;
      en.target.classList.add('in');
      if (en.target.hasAttribute('data-count')) count(en.target);
      io.unobserve(en.target);
    });
  }, { threshold: 0.2 });

  function count(el) {
    var end = parseInt(el.getAttribute('data-count'), 10) || 0;
    var suffix = el.getAttribute('data-suffix') || '';
    var t0 = null, dur = 1600;
    function step(t) {
      if (!t0) t0 = t;
      var p = Math.min((t - t0) / dur, 1);
      el.textContent = Math.floor(end * (1 - Math.pow(1 - p, 3))).toLocaleString('en-IN') + suffix;
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  var inFrame = window.self !== window.top; // gallery thumbnails: show final state at once
  document.querySelectorAll('.reveal,[data-count]').forEach(function (el) {
    if (inFrame) {
      el.classList.add('in');
      if (el.hasAttribute('data-count')) {
        el.textContent = (parseInt(el.getAttribute('data-count'), 10) || 0).toLocaleString('en-IN') + (el.getAttribute('data-suffix') || '');
      }
    } else io.observe(el);
  });

  var burger = document.querySelector('[data-burger]');
  var menu = document.querySelector('[data-menu]');
  if (burger && menu) burger.addEventListener('click', function () { menu.classList.toggle('open'); });
})();
