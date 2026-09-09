/* ============================================================================
   Khamis Computers — POS offline engine.
   - Catalog cache (IndexedDB → localStorage → in-memory, graceful fallback)
   - Offline sale queue with client_ref + device_id (dedup-safe)
   - Auto-sync when the connection returns
   ========================================================================== */
(function () {
  'use strict';

  var CAT = 'catalog';
  var QUEUE = 'queue';
  var memoryCatalog = [];

  /* ---------- best-effort KV store (IndexedDB → localStorage → memory) ------ */
  var kv = (function () {
    var db = null, mode = 'mem', ready = null, mem = {};

    function lsGet(k) { try { return JSON.parse(localStorage.getItem('kc_' + k) || 'null'); } catch (e) { return null; } }
    function lsSet(k, v) { try { localStorage.setItem('kc_' + k, JSON.stringify(v)); } catch (e) {} }

    function open() {
      if (ready) { return ready; }
      ready = new Promise(function (resolve) {
        var memMode = function () { mode = 'mem'; resolve(); };
        var lsMode = function () { mode = 'ls'; resolve(); };
        if (!('indexedDB' in window)) { lsMode(); return; }
        try {
          var req = indexedDB.open('kc_offline', 1);
          req.onupgradeneeded = function (e) { e.target.result.createObjectStore('kv', { keyPath: 'k' }); };
          req.onsuccess = function () { db = req.result; mode = 'idb'; resolve(); };
          req.onerror = function () { lsMode(); };
          req.onblocked = function () { lsMode(); };
        } catch (e) { lsMode(); }
      });
      return ready;
    }

    return {
      get: function (k) {
        return open().then(function () {
          if (mode === 'idb') {
            return new Promise(function (res) {
              try {
                var r = db.transaction('kv', 'readonly').objectStore('kv').get(k);
                r.onsuccess = function () { res(r.result ? r.result.v : (mem[k] || null)); };
                r.onerror = function () { res(mem[k] || null); };
              } catch (e) { res(mem[k] || null); }
            });
          }
          if (mode === 'ls') {
            var v = lsGet(k);
            return Promise.resolve(v !== null ? v : (mem[k] || null));
          }
          return Promise.resolve(mem[k] || null);
        });
      },
      set: function (k, v) {
        mem[k] = v;
        return open().then(function () {
          if (mode === 'idb') {
            return new Promise(function (res) {
              try {
                var t = db.transaction('kv', 'readwrite').objectStore('kv').put({ k: k, v: v });
                t.onsuccess = function () { res(); };
                t.onerror = function () { res(); };
              } catch (e) { res(); }
            });
          }
          if (mode === 'ls') { lsSet(k, v); }
          return Promise.resolve();
        });
      }
    };
  })();

  function isOnline() { return navigator.onLine !== false; }

  function deviceId() {
    try {
      var d = localStorage.getItem('kc_device_id');
      if (d) { return d; }
      d = 'dev-' + Math.random().toString(36).slice(2, 10) + '-' + Date.now().toString(36);
      localStorage.setItem('kc_device_id', d);
      return d;
    } catch (e) {
      return 'dev-' + Math.random().toString(36).slice(2, 10);
    }
  }

  function isoNow() {
    var d = new Date();
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' +
           p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
  }

  /* ---------- catalog cache ------------------------------------------------ */
  function cacheCatalog(list) {
    memoryCatalog = list || [];
    kv.set(CAT, memoryCatalog);
  }
  function mergeCatalog(list) {
    (list || []).forEach(function (p) {
      var i = memoryCatalog.findIndex(function (x) { return x.id === p.id; });
      if (i >= 0) { memoryCatalog[i] = p; } else { memoryCatalog.push(p); }
    });
    kv.set(CAT, memoryCatalog);
  }

  function searchLocal(q) {
    q = (q || '').toLowerCase();
    return kv.get(CAT).then(function (stored) {
      var list = (stored && stored.length) ? stored : memoryCatalog;
      if (!q) { return list.slice(0, 30); }
      // Exact serial / IMEI match across cached units.
      for (var i = 0; i < list.length; i++) {
        var p = list[i];
        if (p.serialized && p.units) {
          for (var j = 0; j < p.units.length; j++) {
            if (String(p.units[j].serial).toLowerCase() === q) {
              var c = JSON.parse(JSON.stringify(p));
              c.units = [p.units[j]];
              c.stock = 1;
              return [c];
            }
          }
        }
      }
      var out = list.filter(function (p) {
        return (p.name || '').toLowerCase().indexOf(q) !== -1 ||
               (p.sku || '').toLowerCase().indexOf(q) !== -1 ||
               String(p.barcode || '').toLowerCase().indexOf(q) !== -1;
      });
      return out.slice(0, 30);
    });
  }

  /* ---------- offline sale queue ------------------------------------------- */
  function pendingCount() {
    return kv.get(QUEUE).then(function (q) { return (q || []).length; });
  }

  function queueSale(payload) {
    var ref = 'off-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 6);
    var item = { client_ref: ref, device_id: deviceId(), created_at: isoNow(), payload: payload };
    return kv.get(QUEUE).then(function (q) {
      q = q || [];
      q.push(item);
      return kv.set(QUEUE, q).then(function () { return item; });
    });
  }

  function syncQueue() {
    if (!isOnline()) {
      return Promise.resolve({ synced: 0, failed: 0, auth: false });
    }
    return kv.get(QUEUE).then(function (q) {
      if (!q || !q.length) { return { synced: 0, failed: 0, auth: false }; }
      var cfg = window.KC_OFFLINE || {};
      return fetch(cfg.sync, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf },
        body: JSON.stringify({ items: q })
      }).then(function (r) {
        if (r.status === 401 || r.status === 419) { return { auth: true, results: [] }; }
        return r.json();
      }).then(function (data) {
        if (data.auth) { return { synced: 0, failed: q.length, auth: true }; }
        var done = {};
        (data.results || []).forEach(function (res) {
          if (res.ok || res.duplicate) { done[res.client_ref] = res; }
        });
        var remaining = q.filter(function (it) { return !done[it.client_ref]; });
        return kv.set(QUEUE, remaining).then(function () {
          return { synced: Object.keys(done).length, failed: remaining.length, auth: false, results: data.results || [] };
        });
      }).catch(function () {
        return { synced: 0, failed: q.length, auth: false };
      });
    });
  }

  /* ---------- boot ---------------------------------------------------------- */
  function loadCatalog() {
    var cfg = window.KC_OFFLINE || {};
    if (!cfg.catalog) { return Promise.resolve(); }
    return fetch(cfg.catalog)
      .then(function (r) { return r.json(); })
      .then(cacheCatalog)
      .catch(function () {});
  }

  function registerSW() {
    var cfg = window.KC_OFFLINE || {};
    if (!cfg.sw || !('serviceWorker' in navigator)) { return Promise.resolve(false); }
    var base = cfg.base || '';
    var swUrl = cfg.sw + (base ? '?base=' + encodeURIComponent(base) : '');
    return navigator.serviceWorker.register(swUrl).then(function () { return true; }).catch(function () { return false; });
  }

  function init() {
    var p = isOnline() ? loadCatalog() : Promise.resolve();
    return p.then(function () { return registerSW(); });
  }

  window.KCPosOffline = {
    init: init,
    searchLocal: searchLocal,
    cacheCatalog: cacheCatalog,
    mergeCatalog: mergeCatalog,
    queueSale: queueSale,
    pendingCount: pendingCount,
    syncQueue: syncQueue,
    isOnline: isOnline,
    deviceId: deviceId
  };
})();
