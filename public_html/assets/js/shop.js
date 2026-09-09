/* Khamis Computers — storefront cart (localStorage) + checkout. */
(function () {
  'use strict';

  var KEY = 'kc_cart';
  var money = function (n) {
    var cur = (window.KC_CART_CONFIG && window.KC_CART_CONFIG.currency) || 'KSh';
    return cur + ' ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- cart storage ---------- */
  function getCart() { try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; } }
  function saveCart(c) { localStorage.setItem(KEY, JSON.stringify(c)); }
  function cartCount() { return getCart().reduce(function (s, l) { return s + l.qty; }, 0); }

  window.KC = window.KC || {};
  window.KC.cartCount = cartCount;
  window.KC.setQty = setQty;
  window.KC.removeLine = removeLine;

  /* ---------- toast ---------- */
  var toastEl = null;
  function toast(msg) {
    if (!toastEl) { toastEl = document.createElement('div'); toastEl.className = 'kc-toast'; document.body.appendChild(toastEl); }
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toast._t);
    toast._t = setTimeout(function () { toastEl.classList.remove('show'); }, 1600);
  }

  /* ---------- add to cart (event delegation) ---------- */
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-add]');
    if (!btn || btn.disabled) return;
    e.preventDefault();

    var id = parseInt(btn.getAttribute('data-add'), 10);
    var serialized = btn.getAttribute('data-serialized') === '1';
    var maxRaw = btn.getAttribute('data-stock');
    var max = (maxRaw === null || maxRaw === '' || isNaN(parseInt(maxRaw, 10))) ? null : parseInt(maxRaw, 10);

    // Product detail page may have a quantity input.
    var qty = 1;
    var qtyInput = document.getElementById('qty-input');
    if (qtyInput) { qty = Math.max(1, parseInt(qtyInput.value, 10) || 1); }

    var cart = getCart();
    var line = cart.find(function (l) { return l.id === id; });
    var target = (line ? line.qty : 0) + qty;
    if (max !== null && target > max) { toast('Only ' + max + ' in stock.'); return; }
    if (line) { line.qty = target; } else { cart.push({ id: id, qty: qty }); }
    saveCart(cart);
    updateBadge();
    toast('Added to cart');
  });

  function setQty(id, qty) {
    var cart = getCart();
    var line = cart.find(function (l) { return l.id === id; });
    if (!line) return;
    if (qty <= 0) { cart = cart.filter(function (l) { return l.id !== id; }); }
    else { line.qty = qty; }
    saveCart(cart);
    updateBadge();
    renderCartPage();
  }
  function removeLine(id) {
    var cart = getCart().filter(function (l) { return l.id !== id; });
    saveCart(cart);
    updateBadge();
    renderCartPage();
  }

  function updateBadge() {
    var el = document.getElementById('cart-count');
    if (el) el.textContent = cartCount();
  }

  /* ---------- JS thumbnails (cart lines) ---------- */
  var palettes = [['#e8f1fc', '#cfe1fb'], ['#e7f8ee', '#c9f0db'], ['#fff3e0', '#ffe0b8'], ['#f0ecff', '#ddd2ff'], ['#fdecea', '#f7cdca']];
  function thumbHTML(p, size) {
    var c = palettes[p.id % palettes.length];
    var words = (p.name || '').trim().split(/\s+/);
    var m = words.slice(0, 2).map(function (w) { return (w[0] || '').toUpperCase(); }).join('') || 'KC';
    var s = size || 96;
    var gid = 'ct' + p.id;
    return '<svg width="' + s + '" height="' + s + '" viewBox="0 0 ' + s + ' ' + s + '">' +
      '<defs><linearGradient id="' + gid + '" x1="0" y1="0" x2="1" y2="1">' +
      '<stop offset="0" stop-color="' + c[0] + '"/><stop offset="1" stop-color="' + c[1] + '"/></linearGradient></defs>' +
      '<rect width="' + s + '" height="' + s + '" fill="url(#' + gid + ')"/>' +
      '<text x="50%" y="55%" font-family="system-ui, sans-serif" font-size="' + Math.round(s * 0.3) + '" font-weight="700" fill="#1d1d1f" text-anchor="middle" dominant-baseline="middle">' + esc(m) + '</text>' +
      '</svg>';
  }

  /* ---------- cart page ---------- */
  function renderCartPage() {
    var cfg = window.KC_CART_CONFIG;
    var linesEl = document.getElementById('cart-lines');
    if (!cfg || !linesEl) return;

    var cart = getCart();
    var summary = document.getElementById('cart-summary');

    if (!cart.length) {
      linesEl.innerHTML = '<div class="empty-state"><h2>Your cart is empty</h2>' +
        '<p class="muted">Browse the shop and add something you like.</p>' +
        '<a class="btn btn-primary" href="' + cfg.browse + '">Shop products</a></div>';
      if (summary) summary.style.display = 'none';
      return;
    }
    if (summary) summary.style.display = '';

    var ids = cart.map(function (l) { return l.id; }).join(',');
    fetch(cfg.api + '?ids=' + encodeURIComponent(ids))
      .then(function (r) { return r.json(); })
      .then(function (products) {
        var map = {};
        products.forEach(function (p) { map[p.id] = p; });

        var html = '';
        cart.forEach(function (line) {
          var p = map[line.id];
          if (!p) return; // unavailable → dropped below
          var limited = p.serialized ? null : p.stock;
          if (limited !== null && line.qty > limited) { line.qty = limited; }
          var stockTxt = p.serialized ? (p.stock + ' unit' + (p.stock === 1 ? '' : 's')) : (p.stock + ' in stock');
          html += '<div class="cart-line-row" data-id="' + p.id + '">' +
            '<div class="cart-line-thumb">' + thumbHTML(p) + '</div>' +
            '<div class="cart-line-info">' +
              '<div class="cart-line-name">' + esc(p.name) + '</div>' +
              '<div class="cart-line-price">' + money(p.price) + ' each · excl. VAT' + (p.serialized ? ' · serial-tracked' : '') + '</div>' +
              '<div class="muted" style="font-size:12px">' + stockTxt + '</div>' +
            '</div>' +
            (p.serialized
              ? '<div class="cart-line-qty"><span>× ' + line.qty + '</span></div>'
              : '<div class="cart-line-qty"><button type="button" class="qty-btn" data-d="-1">−</button><span>' + line.qty + '</span><button type="button" class="qty-btn" data-d="1">+</button></div>') +
            '<div class="cart-line-total">' + money(p.price * line.qty) + '</div>' +
            '<a href="#" class="cart-line-remove" data-remove="' + p.id + '">remove</a>' +
          '</div>';
        });

        // Drop products that are no longer available and re-save.
        var valid = cart.filter(function (l) { return map[l.id]; });
        saveCart(valid);
        updateBadge();
        linesEl.innerHTML = html;

        // Totals
        var sub = valid.reduce(function (s, l) { return s + map[l.id].price * l.qty; }, 0);
        var vat = sub * cfg.vat / 100;
        var fee = currentDeliveryFee();
        document.getElementById('sum-subtotal').textContent = money(sub);
        document.getElementById('sum-vat').textContent = money(vat);
        document.getElementById('sum-delivery').textContent = money(fee);
        document.getElementById('sum-total').textContent = money(sub + vat + fee);

        // Events
        linesEl.querySelectorAll('.qty-btn').forEach(function (b) {
          b.addEventListener('click', function () {
            var id = parseInt(b.closest('.cart-line-row').dataset.id, 10);
            var line = getCart().find(function (l) { return l.id === id; });
            if (!line) return;
            var next = line.qty + parseInt(b.dataset.d, 10);
            if (next < 1) return;
            if (map[id].stock !== null && !map[id].serialized && next > map[id].stock) { toast('Only ' + map[id].stock + ' in stock.'); return; }
            setQty(id, next);
          });
        });
        linesEl.querySelectorAll('.cart-line-remove').forEach(function (a) {
          a.addEventListener('click', function (e) { e.preventDefault(); removeLine(parseInt(a.dataset.remove, 10)); });
        });
      })
      .catch(function () {
        linesEl.innerHTML = '<div class="alert alert-error">Could not load your cart. Check your connection and refresh.</div>';
      });
  }

  /* ---------- delivery fee ---------- */
  function currentDeliveryFee() {
    var fulfill = document.getElementById('co-fulfillment');
    var zoneSel = document.getElementById('co-zone');
    if (!fulfill || fulfill.value !== 'delivery' || !zoneSel) return 0;
    var opt = zoneSel.options[zoneSel.selectedIndex];
    return opt ? (parseFloat(opt.dataset.fee) || 0) : 0;
  }

  /* ---------- checkout ---------- */
  var form = document.getElementById('checkout-form');
  if (form) {
    var fulfill = document.getElementById('co-fulfillment');
    var addrField = document.getElementById('address-field');
    var zoneField = document.getElementById('zone-field');
    var zoneSel = document.getElementById('co-zone');
    var deliveryRow = document.getElementById('sum-delivery-row');

    function refreshFulfilmentUI() {
      var isDelivery = fulfill.value === 'delivery';
      addrField.style.display = isDelivery ? '' : 'none';
      if (zoneField) zoneField.style.display = isDelivery ? '' : 'none';
      if (deliveryRow) deliveryRow.style.display = isDelivery ? '' : 'none';
      renderCartPage();
    }
    fulfill.addEventListener('change', refreshFulfilmentUI);
    if (zoneSel) zoneSel.addEventListener('change', function () { renderCartPage(); });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var err = document.getElementById('checkout-error');
      err.textContent = '';
      var btn = document.getElementById('place-order');
      var cart = getCart();
      if (!cart.length) { err.textContent = 'Your cart is empty.'; return; }

      var paySel = document.getElementById('co-payment');
      var payOpt = paySel.options[paySel.selectedIndex];
      var payNow = !!(payOpt && payOpt.dataset && payOpt.dataset.paynow);
      var payload = {
        lines: cart.map(function (l) { return { product_id: l.id, quantity: l.qty }; }),
        name: document.getElementById('co-name').value,
        phone: document.getElementById('co-phone').value,
        email: document.getElementById('co-email').value,
        fulfillment: fulfill.value,
        delivery_zone_id: zoneSel ? parseInt(zoneSel.value, 10) || 0 : 0,
        address: document.getElementById('co-address').value,
        payment_method: paySel.value,
        pay_now: payNow
      };

      btn.disabled = true;
      btn.textContent = 'Placing order…';
      fetch(window.KC_CART_CONFIG.checkout, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.KC_CART_CONFIG.csrf },
        body: JSON.stringify(payload)
      })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.ok) {
          localStorage.removeItem(KEY);
          updateBadge();
          window.location.href = data.redirect;
        } else {
          err.textContent = data.error || 'Could not place your order.';
          btn.disabled = false;
          btn.textContent = 'Place order';
        }
      })
      .catch(function () {
        err.textContent = 'Network error. Please try again.';
        btn.disabled = false;
        btn.textContent = 'Place order';
      });
    });
  }

  /* ---------- init ---------- */
  updateBadge();
  renderCartPage();
})();
