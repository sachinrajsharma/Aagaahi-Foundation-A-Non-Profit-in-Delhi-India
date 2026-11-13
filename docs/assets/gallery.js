// Lightbox + "show all photos" toggles. No dependencies.
(function () {
  var lb = document.createElement('div');
  lb.className = 'lb';
  lb.innerHTML = '<button class="x" aria-label="Close">×</button><button class="p" aria-label="Previous">‹</button><img alt=""><button class="n" aria-label="Next">›</button><span class="c"></span>';
  document.body.appendChild(lb);
  var img = lb.querySelector('img'), cap = lb.querySelector('.c'), set = [], i = 0;
  function show() { var a = set[i]; img.src = a.href; img.alt = a.querySelector('img').alt; cap.textContent = (i + 1) + ' / ' + set.length; }
  function open(a) { set = [].slice.call(a.parentNode.querySelectorAll('a')); i = set.indexOf(a); show(); lb.classList.add('on'); }
  function step(d) { i = (i + d + set.length) % set.length; show(); }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('.pics a');
    if (a) { e.preventDefault(); open(a); return; }
    var m = e.target.closest && e.target.closest('.more');
    if (m) { var g = m.previousElementSibling; g.classList.toggle('open'); m.textContent = g.classList.contains('open') ? 'Show fewer photos' : m.getAttribute('data-label'); return; }
    if (e.target === lb || e.target.classList.contains('x')) lb.classList.remove('on');
    if (e.target.classList.contains('p')) step(-1);
    if (e.target.classList.contains('n')) step(1);
  });
  document.addEventListener('keydown', function (e) {
    if (!lb.classList.contains('on')) return;
    if (e.key === 'Escape') lb.classList.remove('on');
    if (e.key === 'ArrowLeft') step(-1);
    if (e.key === 'ArrowRight') step(1);
  });
})();
