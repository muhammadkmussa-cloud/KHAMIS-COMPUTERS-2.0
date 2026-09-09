/* Khamis Computers — shared front-end helpers.
   Additional scripts (POS, shop, offline sync) are added in later pieces. */
(function () {
  'use strict';

  // Auto-dismiss flash alerts after 5 seconds (but keep them hoverable).
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .5s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 600);
    }, 5000);
  });
})();
