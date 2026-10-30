(function () {
  var body = document.body;
  function closeNav() { body.classList.remove('nav-open'); }

  var menuBtn = document.getElementById('menuBtn');
  if (menuBtn) menuBtn.addEventListener('click', function () { body.classList.toggle('nav-open'); });
  var overlay = document.getElementById('overlay');
  if (overlay) overlay.addEventListener('click', closeNav);
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeNav(); });
  window.addEventListener('pageshow', closeNav);

  // Accordion sub menus
  document.querySelectorAll('.nav-toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var g = btn.closest('.nav-group');
      var open = !g.classList.contains('open');
      document.querySelectorAll('.nav-group.open').forEach(function (x) {
        if (x !== g) { x.classList.remove('open'); x.querySelector('.nav-toggle').setAttribute('aria-expanded', 'false'); }
      });
      g.classList.toggle('open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });

  // Tapping any real menu link folds the mobile menu away
  document.querySelectorAll('#sidebar a').forEach(function (a) { a.addEventListener('click', closeNav); });

  // Confirm prompts
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-confirm]');
    if (t && !window.confirm(t.getAttribute('data-confirm'))) e.preventDefault();
  });

  // Card layout labels for tables on small screens
  document.querySelectorAll('table.stack').forEach(function (t) {
    var heads = Array.prototype.map.call(t.querySelectorAll('thead th'), function (x) { return x.textContent.trim(); });
    t.querySelectorAll('tbody tr, tfoot tr').forEach(function (tr) {
      Array.prototype.forEach.call(tr.children, function (td, i) {
        if (heads[i] && !td.hasAttribute('colspan')) td.setAttribute('data-label', heads[i]);
      });
    });
  });

  // Show / hide password
  document.querySelectorAll('[data-toggle-pw]').forEach(function (b) {
    b.addEventListener('click', function () {
      var i = document.getElementById(b.getAttribute('data-toggle-pw'));
      if (!i) return;
      i.type = i.type === 'password' ? 'text' : 'password';
      b.textContent = i.type === 'password' ? 'Show' : 'Hide';
    });
  });

  // Select all checkboxes
  var all = document.getElementById('checkAll');
  if (all) all.addEventListener('change', function () {
    document.querySelectorAll('input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; });
  });

  // Dismiss success messages after a while
  setTimeout(function () {
    document.querySelectorAll('.alert.ok').forEach(function (a) { a.style.display = 'none'; });
  }, 6000);
})();
