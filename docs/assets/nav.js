// Nav: show "My progress" instead of "Log in / Create account" when a student is signed in on the Study page.
(function () {
  var t = '';
  try { t = localStorage.getItem('aagahi-study-token-v1') || ''; } catch (e) {}
  document.querySelectorAll('[data-auth="in"]').forEach(function (el) { el.hidden = !!t; });
  document.querySelectorAll('[data-auth="out"]').forEach(function (el) { el.hidden = !t; });
})();
