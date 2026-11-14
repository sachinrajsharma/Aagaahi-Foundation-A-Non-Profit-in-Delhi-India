// Dashboard basics (no inline scripts: the page's security policy forbids them).
(function () {
  // "Are you sure?" on any form or button carrying data-confirm
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
  // click-to-select for read-only path boxes
  document.addEventListener('focusin', function (e) { if (e.target.hasAttribute && e.target.hasAttribute('data-select-all')) e.target.select(); });
})();
