/* Khamis Computers storefront: catalogue, local cart, staged checkout and order status. */
(function () {
  'use strict';

  var CART_KEY = 'kc_cart';
  var DRAFT_KEY = 'kc_checkout_draft';
  var LAST_CART_KEY = 'kc_last_order_cart';
  var DEVICE_KEY = 'kc_shop_device';
  var REF_KEY = 'kc_shop_checkout_ref';
  var cfg = window.KC_CART_CONFIG || null;

  function esc(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function money(value) {
    var currency = (cfg && cfg.currency) || 'KSh';
    return currency + ' ' + Number(value || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  function makeId(prefix) {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') return prefix + '-' + window.crypto.randomUUID();
    return prefix + '-' + Date.now() + '-' + Math.random().toString(16).slice(2);
  }
  function getCart() {
    try {
      var value = JSON.parse(localStorage.getItem(CART_KEY) || '[]');
      return Array.isArray(value) ? value.filter(function (line) { return Number.isInteger(line.id) && line.id > 0 && Number.isInteger(line.qty) && line.qty > 0; }) : [];
    } catch (e) { return []; }
  }
  function saveCart(cart) { localStorage.setItem(CART_KEY, JSON.stringify(cart)); }
  function cartCount() { return getCart().reduce(function (sum, line) { return sum + line.qty; }, 0); }
  function toast(message, type) { if (window.KC && window.KC.toast) window.KC.toast(message, type); }
  function updateBadge() {
    var badge = document.getElementById('cart-count');
    if (badge) badge.textContent = String(cartCount());
  }
  function gross(net) { return Number(net) * (1 + Number((cfg && cfg.vat) || 0) / 100); }

  window.KC = window.KC || {};
  window.KC.cartCount = cartCount;

  document.addEventListener('click', function (event) {
    var mobileAdd = event.target.closest('[data-mobile-add]');
    if (mobileAdd) {
      var primary = document.getElementById('add-to-cart');
      if (primary) primary.click();
      return;
    }
    var button = event.target.closest('[data-add]');
    if (!button || button.disabled) return;
    event.preventDefault();
    var id = parseInt(button.dataset.add, 10);
    var maximum = parseInt(button.dataset.stock, 10);
    var qtyInput = document.getElementById('qty-input');
    var quantity = qtyInput ? Math.max(1, parseInt(qtyInput.value, 10) || 1) : 1;
    var cart = getCart();
    var line = cart.find(function (item) { return item.id === id; });
    var next = (line ? line.qty : 0) + quantity;
    if (Number.isFinite(maximum) && next > maximum) {
      toast('Only ' + maximum + ' available.', 'error');
      return;
    }
    if (line) line.qty = next;
    else cart.push({ id: id, qty: quantity });
    saveCart(cart);
    updateBadge();
    toast((button.dataset.name || 'Item') + ' added to cart', 'success');
    if (document.getElementById('cart-lines')) renderCartPage();
    button.classList.add('added');
    var original = button.textContent;
    button.textContent = 'Added ✓';
    window.setTimeout(function () { button.classList.remove('added'); button.textContent = original; }, 1200);
  });

  document.querySelectorAll('[data-gallery-src]').forEach(function (button) {
    button.addEventListener('click', function () {
      var main = document.getElementById('main-image');
      if (main) {
        main.src = button.dataset.gallerySrc;
        main.alt = button.dataset.galleryAlt || '';
      }
      document.querySelectorAll('[data-gallery-src]').forEach(function (item) { item.classList.toggle('active', item === button); });
    });
  });

  var filterPanel = document.querySelector('.shop-filters');
  if (filterPanel && window.matchMedia('(max-width: 700px)').matches) filterPanel.removeAttribute('open');
  var productGrid = document.querySelector('[data-product-grid]');
  var loadMore = document.querySelector('[data-load-more]');
  if (productGrid && loadMore) {
    var productCards = Array.prototype.slice.call(productGrid.children);
    var visible = parseInt(productGrid.dataset.pageSize, 10) || 12;
    function revealProducts() {
      productCards.forEach(function (card, index) { card.hidden = index >= visible; });
      loadMore.hidden = visible >= productCards.length;
    }
    loadMore.addEventListener('click', function () { visible += 12; revealProducts(); });
    revealProducts();
  }

  function thumbHTML(product) {
    var initials = String(product.name || 'KC').trim().split(/\s+/).slice(0, 2).map(function (word) { return word.charAt(0).toUpperCase(); }).join('');
    return '<span class="cart-thumb-initials" aria-hidden="true">' + esc(initials) + '</span>';
  }
  function setQty(id, quantity) {
    var cart = getCart();
    var line = cart.find(function (item) { return item.id === id; });
    if (!line) return;
    line.qty = Math.max(1, quantity);
    saveCart(cart);
    updateBadge();
    renderCartPage();
  }
  function removeLine(id) {
    saveCart(getCart().filter(function (item) { return item.id !== id; }));
    updateBadge();
    renderCartPage();
  }

  var cartState = { products: {}, subtotal: 0, vat: 0, fee: 0, total: 0 };
  function selectedFulfilment() {
    var input = document.querySelector('input[name="fulfillment"]:checked');
    return input ? input.value : 'pickup';
  }
  function currentDeliveryFee() {
    var zone = document.getElementById('co-zone');
    if (selectedFulfilment() !== 'delivery' || !zone || !zone.value) return 0;
    var option = zone.options[zone.selectedIndex];
    return option ? parseFloat(option.dataset.fee) || 0 : 0;
  }
  function updateTotals(cart, products) {
    var subtotal = cart.reduce(function (sum, line) { return sum + (products[line.id] ? products[line.id].price * line.qty : 0); }, 0);
    var vat = subtotal * Number(cfg.vat) / 100;
    var fee = currentDeliveryFee();
    cartState = { products: products, subtotal: subtotal, vat: vat, fee: fee, total: subtotal + vat + fee };
    var values = { 'sum-subtotal': subtotal, 'sum-vat': vat, 'sum-delivery': fee, 'sum-total': subtotal + vat + fee };
    Object.keys(values).forEach(function (id) { var el = document.getElementById(id); if (el) el.textContent = money(values[id]); });
    var deliveryRow = document.getElementById('sum-delivery-row');
    if (deliveryRow) deliveryRow.hidden = selectedFulfilment() !== 'delivery';
  }
  function renderCartPage() {
    var lines = document.getElementById('cart-lines');
    if (!cfg || !lines) return;
    var cart = getCart();
    var summary = document.getElementById('cart-summary');
    var recommendations = document.getElementById('cart-recommendations');
    if (!cart.length) {
      lines.innerHTML = '<div class="empty-cart"><div class="empty-cart-icon" aria-hidden="true">⌑</div><h2>Your cart is ready when you are</h2><p>Browse live store stock and add the products you want to reserve.</p><a class="btn btn-primary btn-lg" href="' + cfg.browse + '">Browse products</a></div>';
      if (summary) summary.hidden = true;
      if (recommendations) recommendations.hidden = false;
      return;
    }
    if (summary) summary.hidden = false;
    if (recommendations) recommendations.hidden = true;
    lines.innerHTML = '<div class="cart-loading"><span class="spinner"></span>Checking live stock and prices…</div>';
    fetch(cfg.api + '?ids=' + encodeURIComponent(cart.map(function (line) { return line.id; }).join(',')))
      .then(function (response) { if (!response.ok) throw new Error('cart'); return response.json(); })
      .then(function (products) {
        var map = {};
        products.forEach(function (product) { map[product.id] = product; });
        var valid = cart.filter(function (line) {
          var product = map[line.id];
          if (!product || product.stock < 1) return false;
          line.qty = Math.min(line.qty, product.stock);
          return true;
        });
        saveCart(valid);
        updateBadge();
        if (!valid.length) { renderCartPage(); return; }
        lines.innerHTML = valid.map(function (line) {
          var product = map[line.id];
          return '<article class="cart-line-row" data-id="' + product.id + '">' +
            '<div class="cart-line-thumb">' + thumbHTML(product) + '</div>' +
            '<div class="cart-line-info"><b class="cart-line-name">' + esc(product.name) + '</b><span>' + esc(product.category || 'Technology') + ' · ' + esc(product.sku) + '</span><small>' + (product.serialized ? 'Serial / IMEI assigned at checkout' : product.stock + ' available') + '</small></div>' +
            '<div class="cart-unit-price"><small>VAT included</small><b>' + money(gross(product.price)) + '</b></div>' +
            '<div class="cart-line-actions"><div class="cart-line-qty" aria-label="Quantity"><button type="button" data-qty-change="-1" aria-label="Decrease quantity">−</button><span>' + line.qty + '</span><button type="button" data-qty-change="1" aria-label="Increase quantity">+</button></div><button type="button" class="cart-line-remove" data-remove="' + product.id + '">Remove</button></div>' +
            '<div class="cart-line-total"><small>Line total</small><b>' + money(gross(product.price * line.qty)) + '</b></div>' +
          '</article>';
        }).join('');
        updateTotals(valid, map);
        lines.querySelectorAll('[data-qty-change]').forEach(function (button) {
          button.addEventListener('click', function () {
            var row = button.closest('[data-id]');
            var id = parseInt(row.dataset.id, 10);
            var line = getCart().find(function (item) { return item.id === id; });
            var next = line.qty + parseInt(button.dataset.qtyChange, 10);
            if (next < 1) return;
            if (next > map[id].stock) { toast('Only ' + map[id].stock + ' available.', 'error'); return; }
            setQty(id, next);
          });
        });
        lines.querySelectorAll('[data-remove]').forEach(function (button) { button.addEventListener('click', function () { removeLine(parseInt(button.dataset.remove, 10)); }); });
      })
      .catch(function () { lines.innerHTML = '<div class="cart-load-error" role="alert"><b>We could not refresh your cart.</b><span>Check your connection and try again.</span><button class="btn btn-outline" type="button" data-cart-retry>Retry</button></div>'; var retry = lines.querySelector('[data-cart-retry]'); if (retry) retry.addEventListener('click', renderCartPage); });
  }

  var checkout = document.getElementById('checkout-form');
  if (checkout) {
    var step = 1;
    var next = document.getElementById('checkout-next');
    var back = document.getElementById('checkout-back');
    var submit = document.getElementById('place-order');
    var error = document.getElementById('checkout-error');
    var status = document.getElementById('checkout-status');
    var deliveryFields = document.getElementById('delivery-fields');
    var zone = document.getElementById('co-zone');

    function field(id) { return document.getElementById(id); }
    function clearErrors() { checkout.querySelectorAll('.field-error').forEach(function (el) { el.textContent = ''; }); error.textContent = ''; }
    function setError(id, message) { var el = checkout.querySelector('[data-error-for="' + id + '"]'); if (el) el.textContent = message; var input = field(id); if (input) input.focus(); }
    function validPhone(value) {
      var digits = String(value).replace(/\D/g, '');
      return /^(?:0(?:1|7)\d{8}|254(?:1|7)\d{8}|(?:1|7)\d{8})$/.test(digits);
    }
    function validateStep(number) {
      clearErrors();
      if (number === 1) {
        if (field('co-name').value.trim().length < 2) { setError('co-name', 'Enter your full name.'); return false; }
        if (!validPhone(field('co-phone').value)) { setError('co-phone', 'Enter a valid Kenyan mobile number.'); return false; }
        if (field('co-email').value && !field('co-email').checkValidity()) { setError('co-email', 'Enter a valid email address.'); return false; }
      }
      if (number === 2 && selectedFulfilment() === 'delivery') {
        if (!zone.value) { setError('co-zone', 'Choose a delivery area.'); return false; }
        if (field('co-address').value.trim().length < 5) { setError('co-address', 'Enter a complete delivery address.'); return false; }
      }
      return true;
    }
    function saveDraft() {
      var draft = { name: field('co-name').value, phone: field('co-phone').value, email: field('co-email').value, fulfillment: selectedFulfilment(), zone: zone.value, address: field('co-address').value, payment: (document.querySelector('input[name="payment"]:checked') || {}).value || 'cash' };
      localStorage.setItem(DRAFT_KEY, JSON.stringify(draft));
    }
    function loadDraft() {
      try {
        var draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || '{}');
        field('co-name').value = draft.name || '';
        field('co-phone').value = draft.phone || '';
        field('co-email').value = draft.email || '';
        field('co-address').value = draft.address || '';
        if (draft.zone) zone.value = draft.zone;
        var fulfil = document.querySelector('input[name="fulfillment"][value="' + (draft.fulfillment || 'pickup') + '"]'); if (fulfil) fulfil.checked = true;
        var payment = document.querySelector('input[name="payment"][value="' + (draft.payment || 'cash') + '"]'); if (payment) payment.checked = true;
      } catch (e) { /* ignore invalid local draft */ }
    }
    function refreshChoices() {
      checkout.querySelectorAll('.choice-card').forEach(function (label) { var input = label.querySelector('input'); label.classList.toggle('selected', !!input.checked); });
      deliveryFields.hidden = selectedFulfilment() !== 'delivery';
      updateTotals(getCart(), cartState.products);
      saveDraft();
    }
    function renderReview() {
      var fulfil = selectedFulfilment();
      var payment = (document.querySelector('input[name="payment"]:checked') || {}).value || 'cash';
      var zoneName = zone.value ? zone.options[zone.selectedIndex].textContent.split(' · ')[0] : '';
      document.getElementById('checkout-review').innerHTML =
        '<div><span>Contact</span><b>' + esc(field('co-name').value) + '</b><small>' + esc(field('co-phone').value) + (field('co-email').value ? ' · ' + esc(field('co-email').value) : '') + '</small></div>' +
        '<div><span>Fulfilment</span><b>' + (fulfil === 'delivery' ? 'Delivery · ' + esc(zoneName) : 'Store pickup') + '</b><small>' + (fulfil === 'delivery' ? esc(field('co-address').value) : 'We will call when it is ready') + '</small></div>' +
        '<div><span>Payment</span><b>' + (payment === 'mpesa-now' ? 'M-PESA now' : 'Pay on handover') + '</b><small>' + (payment === 'mpesa-now' ? 'Approve the secure prompt on your phone' : 'Cash or M-PESA accepted') + '</small></div>';
    }
    function showStep(number) {
      step = Math.max(1, Math.min(4, number));
      checkout.querySelectorAll('.checkout-step').forEach(function (panel) { var active = parseInt(panel.dataset.step, 10) === step; panel.hidden = !active; panel.classList.toggle('active', active); });
      document.querySelectorAll('.checkout-progress-step').forEach(function (item) { var n = parseInt(item.dataset.stepTarget, 10); item.classList.toggle('active', n === step); item.classList.toggle('complete', n < step); });
      back.hidden = step === 1;
      next.hidden = step === 4;
      submit.hidden = step !== 4;
      if (step === 4) renderReview();
    }
    checkout.addEventListener('input', saveDraft);
    checkout.addEventListener('change', refreshChoices);
    next.addEventListener('click', function () { if (validateStep(step)) showStep(step + 1); });
    back.addEventListener('click', function () { clearErrors(); showStep(step - 1); });
    document.querySelectorAll('.checkout-progress-step').forEach(function (item) { item.addEventListener('click', function () { var target = parseInt(item.dataset.stepTarget, 10); if (target < step) showStep(target); }); });
    checkout.addEventListener('submit', function (event) {
      event.preventDefault();
      if (!validateStep(1) || !validateStep(2) || !getCart().length) { error.textContent = getCart().length ? 'Check the highlighted details.' : 'Your cart is empty.'; return; }
      var paymentChoice = (document.querySelector('input[name="payment"]:checked') || {}).value || 'cash';
      var deviceId = localStorage.getItem(DEVICE_KEY) || makeId('shop');
      var clientRef = localStorage.getItem(REF_KEY) || makeId('order');
      localStorage.setItem(DEVICE_KEY, deviceId);
      localStorage.setItem(REF_KEY, clientRef);
      var cart = getCart();
      var payload = { lines: cart.map(function (line) { return { product_id: line.id, quantity: line.qty }; }), name: field('co-name').value.trim(), phone: field('co-phone').value.trim(), email: field('co-email').value.trim(), fulfillment: selectedFulfilment(), delivery_zone_id: parseInt(zone.value, 10) || 0, address: field('co-address').value.trim(), payment_method: paymentChoice === 'mpesa-now' ? 'mpesa' : 'cash', pay_now: paymentChoice === 'mpesa-now', device_id: deviceId, client_ref: clientRef };
      clearErrors(); submit.disabled = true; back.disabled = true; status.hidden = false; submit.textContent = paymentChoice === 'mpesa-now' ? 'Sending M-PESA prompt…' : 'Placing order…';
      fetch(cfg.checkout, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf }, body: JSON.stringify(payload) })
        .then(function (response) { return response.json().catch(function () { throw new Error('Invalid response'); }); })
        .then(function (data) {
          if (!data.ok) { if (data.reset_ref) localStorage.removeItem(REF_KEY); throw new Error(data.error || 'Could not place your order.'); }
          localStorage.setItem(LAST_CART_KEY, JSON.stringify(cart));
          localStorage.removeItem(CART_KEY); localStorage.removeItem(DRAFT_KEY); localStorage.removeItem(REF_KEY); updateBadge();
          window.location.assign(data.redirect);
        })
        .catch(function (requestError) { error.textContent = requestError.message === 'Failed to fetch' ? 'We could not reach the store. Your cart is safe—check your connection and try again.' : requestError.message; submit.disabled = false; back.disabled = false; status.hidden = true; submit.textContent = 'Place order'; });
    });
    loadDraft(); refreshChoices(); showStep(1);
  }

  var paymentPanel = document.getElementById('payment-status-panel');
  if (paymentPanel) {
    var countdown = document.getElementById('payment-countdown');
    var title = document.getElementById('payment-status-title');
    var copy = document.getElementById('payment-status-copy');
    var attempts = 0;
    function pollPayment() {
      fetch(paymentPanel.dataset.statusUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.ok && data.status === 'completed') { title.textContent = 'Payment confirmed'; copy.textContent = 'Refreshing your confirmation…'; window.setTimeout(function () { window.location.reload(); }, 700); return; }
          if (data.ok && ['cancelled', 'voided'].indexOf(data.status) !== -1) { window.location.reload(); return; }
          if (++attempts >= 40) { title.textContent = 'Still waiting for confirmation'; copy.textContent = 'If you approved the prompt, keep this order number and contact the store. You can also refresh this page.'; return; }
          startCountdown();
        })
        .catch(function () { if (++attempts < 40) startCountdown(5); else { title.textContent = 'Connection interrupted'; copy.textContent = 'Refresh this page to check the latest payment status.'; } });
    }
    function startCountdown(seconds) {
      var left = seconds || 3;
      if (countdown) countdown.textContent = String(left);
      var timer = window.setInterval(function () { left -= 1; if (countdown) countdown.textContent = String(Math.max(0, left)); if (left <= 0) { window.clearInterval(timer); pollPayment(); } }, 1000);
    }
    startCountdown();
  }

  var restore = document.querySelector('[data-restore-cart]');
  if (restore) restore.addEventListener('click', function () { var saved = localStorage.getItem(LAST_CART_KEY); if (saved) localStorage.setItem(CART_KEY, saved); window.location.assign(restore.dataset.cartUrl); });
  var printButton = document.querySelector('[data-print-order]');
  if (printButton) printButton.addEventListener('click', function () { window.print(); });
  var shareButton = document.querySelector('[data-share-order]');
  if (shareButton) shareButton.addEventListener('click', function () { var text = 'Khamis Computers order ' + shareButton.dataset.order; if (navigator.share) navigator.share({ title: text, text: text, url: window.location.href }).catch(function () {}); else if (navigator.clipboard) navigator.clipboard.writeText(text).then(function () { toast('Order number copied', 'success'); }); });

  updateBadge();
  renderCartPage();
})();
