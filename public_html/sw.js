/* ============================================================================
   Khamis Computers — Service Worker (offline app shell for the POS)
   Strategy:
     - HTML (navigations):  network-first, fall back to cache, then /pos shell
     - Static assets:       cache-first (stale while revalidate)
     - JSON APIs:           never cached — the JS falls back to IndexedDB itself
   ========================================================================== */

var VERSION = 'kc-v2';

// Subfolder support: the POS registers us as sw.js?base=/sub/path/ and we
// prefix every app-shell asset with that base so offline works anywhere.
var BASE = new URL(self.location.href).searchParams.get('base') || '';
var ASSETS = [
  '/', '/login', '/pos',
  '/assets/css/app.css',
  '/assets/css/shop.css',
  '/assets/js/app.js',
  '/assets/js/shop.js',
  '/assets/js/pos-offline.js'
].map(function (p) { return BASE + p; });

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(VERSION).then(function (cache) {
      return Promise.all(ASSETS.map(function (url) {
        return fetch(url, { credentials: 'include' })
          .then(function (res) { if (res.ok) { return cache.put(url, res); } })
          .catch(function () {});
      }));
    }).then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== VERSION; })
        .map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (e) {
  var url = new URL(e.request.url);
  if (e.request.method !== 'GET') { return; }

  // HTML navigations — network first, cache fallback.
  if (e.request.mode === 'navigate') {
    e.respondWith(
      fetch(e.request).then(function (res) {
        var copy = res.clone();
        caches.open(VERSION).then(function (c) { c.put(url.pathname, copy); });
        return res;
      }).catch(function () {
        return caches.match(url.pathname).then(function (hit) {
          return hit || caches.match(BASE + '/pos');
        });
      })
    );
    return;
  }

  // Static assets — cache first.
  if (/\.(css|js|svg|png|jpg|jpeg|webp|woff2?)$/.test(url.pathname)) {
    e.respondWith(
      caches.match(e.request).then(function (hit) {
        return hit || fetch(e.request).then(function (res) {
          var copy = res.clone();
          caches.open(VERSION).then(function (c) { c.put(e.request, copy); });
          return res;
        });
      })
    );
    return;
  }

  // Everything else (JSON APIs, POSTs) — let the network decide; the POS JS
  // handles failures by falling back to IndexedDB.
});
