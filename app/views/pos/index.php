<?php
$vat = vat_rate();
$pinActive = Setting::get('discount_pin_hash', '') !== '';
$pinThreshold = (float) Setting::get('discount_pin_threshold', '1000');
?>
<div class="pos-layout">
    <!-- Left: search + product grid -->
    <div class="pos-catalog">
        <div class="pos-top">
            <div class="pos-searchbar">
                <input type="search" id="pos-search" autocomplete="off"
                       placeholder="Scan barcode or type product name, SKU, serial / IMEI…"
                       autofocus>
            </div>
            <div class="pos-status">
                <span id="conn-badge" class="conn-badge online">● Online</span>
                <span id="pending-badge" class="conn-badge pending" style="display:none">0 queued</span>
                <button id="sync-now" class="btn btn-outline btn-sm" style="display:none" type="button">Sync now</button>
            </div>
        </div>
        <div id="pos-results" class="pos-grid">
            <div class="pos-hint">
                <h3>Search to begin</h3>
                <p class="muted">Type a product name or scan a barcode / serial number.
                Press <strong>Enter</strong> to add the first result instantly.</p>
                <p class="muted" style="font-size:13px">If the internet drops, this terminal keeps working
                from its offline catalogue — sales are queued and synced when you're back online.</p>
            </div>
        </div>
    </div>

    <!-- Right: cart -->
    <aside class="pos-cart">
        <div class="pos-cart-head">
            <h2>Current sale</h2>
            <button class="btn btn-ghost btn-sm" id="clear-cart" type="button">Clear</button>
        </div>

        <div id="cart-lines" class="pos-cart-lines">
            <div class="pos-hint muted" style="text-align:center;padding:26px 10px">
                Cart is empty.<br>Add products from the left.
            </div>
        </div>

        <div class="pos-cart-foot">
            <label class="field">
                <span>Customer name <em class="hint">(optional)</em></span>
                <input type="text" id="cust-name" placeholder="Walk-in customer">
            </label>
            <label class="field">
                <span>Phone <em class="hint">(optional)</em></span>
                <input type="text" id="cust-phone" placeholder="07…">
            </label>

            <div class="pos-totals">
                <div class="pos-total-row"><span>Subtotal</span><b id="t-subtotal">KSh 0.00</b></div>
                <div class="pos-total-row discount-row">
                    <span>Discount</span>
                    <span class="discount-inputs">
                        <input type="number" id="discount" min="0" step="0.01" value="0" placeholder="0">
                        <select id="discount-type">
                            <option value="flat">KSh</option>
                            <option value="pct">%</option>
                        </select>
                        <input type="password" id="auth-pin" placeholder="Manager PIN" autocomplete="off" style="display:none;width:118px">
                    </span>
                </div>
                <?php if ($pinActive): ?>
                <div class="pos-total-row"><span class="hint" id="pin-hint" style="display:none">This discount needs a manager PIN</span></div>
                <?php endif; ?>
                <div class="pos-total-row"><span>VAT (<?= e((string) $vat) ?>%)</span><b id="t-vat">KSh 0.00</b></div>
                <div class="pos-total-row total"><span>Total</span><b id="t-total">KSh 0.00</b></div>
            </div>

            <div class="pos-pay">
                <label class="field">
                    <span>Payment method</span>
                    <select id="pay-method">
                        <option value="cash">Cash</option>
                        <option value="mpesa">M-PESA</option>
                        <option value="card">Card</option>
                        <option value="bank">Bank transfer</option>
                    </select>
                </label>
                <label class="field" id="ref-field" style="display:none">
                    <span>Reference code</span>
                    <input type="text" id="pay-ref" placeholder="e.g. M-PESA code">
                </label>
                <label class="field" id="cash-field">
                    <span>Cash received</span>
                    <input type="number" id="cash-received" min="0" step="0.01" placeholder="0.00">
                </label>
                <div class="pos-total-row" id="change-row" style="display:none">
                    <span>Change</span><b id="t-change" style="color:var(--green)">KSh 0.00</b>
                </div>
            </div>

            <button class="btn btn-primary btn-block" id="checkout-btn" type="button" disabled>
                Complete sale
            </button>
            <p class="pos-error" id="pos-error"></p>
            <p class="pos-info" id="pos-info"></p>
        </div>
    </aside>
</div>

<!-- Serial picker modal -->
<div class="modal-backdrop" id="serial-modal" style="display:none">
    <div class="modal">
        <h3 id="modal-title">Select serial numbers</h3>
        <p class="muted" id="modal-sub"></p>
        <div id="modal-units" class="modal-units"></div>
        <div class="modal-actions">
            <button class="btn btn-ghost" id="modal-cancel" type="button">Cancel</button>
            <button class="btn btn-primary" id="modal-add" type="button">Add to cart</button>
        </div>
    </div>
</div>

<!-- Offline receipt modal -->
<div class="modal-backdrop" id="offline-modal" style="display:none">
    <div class="modal modal-receipt">
        <h3>Sale recorded offline</h3>
        <p class="muted">This sale is saved on this device and will sync to the main system
           as soon as the connection returns.</p>
        <div id="offline-body" class="offline-receipt"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" id="offline-print" type="button">Print receipt</button>
            <button class="btn btn-primary" id="offline-close" type="button">New sale</button>
        </div>
    </div>
</div>

<!-- Hidden printable copy of the offline receipt -->
<div id="offline-print-area"></div>

<script src="<?= e(url('assets/js/pos-offline.js')) ?>"></script>
<script>
window.KC_CSRF    = '<?= e(Csrf::token()) ?>';
window.KC_SEARCH  = '<?= e(url('pos/search')) ?>';
window.KC_CHECKOUT = '<?= e(url('pos/checkout')) ?>';
window.KC_OFFLINE = {
    catalog: '<?= e(url('pos/catalog')) ?>',
    sync:    '<?= e(url('pos/sync')) ?>',
    sw:      '<?= e(url('sw.js')) ?>',
    base:    '<?= e(config('app.base_path')) ?>',
    csrf:    '<?= e(Csrf::token()) ?>'
};
</script>
<script>
(function () {
  'use strict';
  var VAT = <?= $vat ?>;
  var money = function (n) {
    return 'KSh ' + Number(n || 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  /* ---------------- state ---------------- */
  var cart = [];
  var results = [];
  var pendingSerial = null;

  /* ---------------- dom ---------------- */
  var $search = document.getElementById('pos-search');
  var $results = document.getElementById('pos-results');
  var $cartLines = document.getElementById('cart-lines');
  var $checkout = document.getElementById('checkout-btn');
  var $error = document.getElementById('pos-error');
  var $info = document.getElementById('pos-info');

  var $subtotal = document.getElementById('t-subtotal');
  var $vat = document.getElementById('t-vat');
  var $total = document.getElementById('t-total');
  var $changeRow = document.getElementById('change-row');
  var $change = document.getElementById('t-change');

  var $discount = document.getElementById('discount');
  var $discountType = document.getElementById('discount-type');
  var $authPin = document.getElementById('auth-pin');
  var $pinHint = document.getElementById('pin-hint');
  var DISCOUNT_PIN_ACTIVE = <?= $pinActive ? 'true' : 'false' ?>;
  var DISCOUNT_PIN_THRESHOLD = <?= json_encode($pinThreshold) ?>;
  var $payMethod = document.getElementById('pay-method');
  var $cashField = document.getElementById('cash-field');
  var $refField = document.getElementById('ref-field');
  var $cashReceived = document.getElementById('cash-received');

  var $connBadge = document.getElementById('conn-badge');
  var $pendingBadge = document.getElementById('pending-badge');
  var $syncNow = document.getElementById('sync-now');

  /* ---------------- search ---------------- */
  var timer = null;
  function doSearch(q) {
    if (!q) { renderResults([]); return; }
    if (!KCPosOffline.isOnline()) {
      KCPosOffline.searchLocal(q).then(renderResults);
      return;
    }
    fetch(window.KC_SEARCH + '?q=' + encodeURIComponent(q))
      .then(function (r) {
        if (r.status >= 500) { throw new Error('server'); }
        return r.json();
      })
      .then(function (data) {
        KCPosOffline.mergeCatalog(data); // keep the offline cache warm
        renderResults(data);
      })
      .catch(function () {
        KCPosOffline.searchLocal(q).then(renderResults);
      });
  }

  $search.addEventListener('input', function () {
    clearTimeout(timer);
    var q = $search.value.trim();
    timer = setTimeout(function () { doSearch(q); }, 180);
  });

  $search.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      if (results.length) { addProduct(results[0]); }
    }
  });

  function renderResults(list) {
    results = list || [];
    if (!results.length) {
      $results.innerHTML = '<div class="pos-hint muted" style="text-align:center;padding:26px">No products match.</div>';
      return;
    }
    $results.innerHTML = results.map(function (p, i) {
      var stockTxt = p.serialized ? (p.stock + ' unit' + (p.stock === 1 ? '' : 's')) : (p.stock + ' in stock');
      var out = p.stock > 0 ? '' : ' out';
      return '<button type="button" class="pos-tile' + out + '" data-i="' + i + '">' +
        '<span class="pos-tile-name">' + esc(p.name) + '</span>' +
        '<span class="pos-tile-sku">' + esc(p.sku) + (p.serialized ? ' · serial' : '') + '</span>' +
        '<span class="pos-tile-price">' + money(p.sell_price) + '</span>' +
        '<span class="pos-tile-stock">' + stockTxt + '</span>' +
      '</button>';
    }).join('');
    $results.querySelectorAll('.pos-tile').forEach(function (btn) {
      btn.addEventListener('click', function () { addProduct(results[parseInt(btn.dataset.i, 10)]); });
    });
  }

  /* ---------------- cart ---------------- */
  function addProduct(p) {
    if (p.stock <= 0) { setError('"' + p.name + '" is out of stock.'); return; }
    if (p.serialized) {
      openSerialModal(p);
      return;
    }
    var line = cart.find(function (l) { return l.id === p.id && !l.serialized; });
    if (line) {
      if (line.qty >= p.stock) { setError('Only ' + p.stock + ' in stock for "' + p.name + '".'); return; }
      line.qty++;
    } else {
      cart.push({ id: p.id, name: p.name, sku: p.sku, price: p.sell_price, serialized: false, qty: 1, units: [], stock: p.stock });
    }
    $search.value = ''; $search.focus(); renderResults([]);
    renderCart();
  }

  function openSerialModal(p) {
    pendingSerial = p;
    document.getElementById('modal-title').textContent = 'Serial numbers — ' + p.name;
    document.getElementById('modal-sub').textContent = 'Pick the exact unit(s) to sell.';
    var box = document.getElementById('modal-units');
    box.innerHTML = p.units.map(function (u) {
      return '<label class="modal-unit"><input type="checkbox" value="' + u.id + '" data-serial="' + esc(u.serial) + '">' +
             '<span>' + esc(u.serial) + '</span></label>';
    }).join('');
    document.getElementById('serial-modal').style.display = 'flex';
  }

  document.getElementById('modal-cancel').addEventListener('click', function () {
    document.getElementById('serial-modal').style.display = 'none';
    pendingSerial = null;
  });

  document.getElementById('modal-add').addEventListener('click', function () {
    var checks = document.querySelectorAll('#modal-units input:checked');
    if (!checks.length) { setError('Select at least one serial number.'); return; }
    var chosen = Array.prototype.map.call(checks, function (c) {
      return { id: parseInt(c.value, 10), serial: c.dataset.serial };
    });
    var existing = cart.find(function (l) { return l.id === pendingSerial.id && l.serialized; });
    if (existing) {
      chosen.forEach(function (u) {
        if (!existing.units.some(function (x) { return x.id === u.id; })) { existing.units.push(u); }
      });
    } else {
      cart.push({ id: pendingSerial.id, name: pendingSerial.name, sku: pendingSerial.sku, price: pendingSerial.sell_price, serialized: true, qty: chosen.length, units: chosen });
    }
    document.getElementById('serial-modal').style.display = 'none';
    pendingSerial = null;
    $search.value = ''; $search.focus(); renderResults([]);
    renderCart();
  });

  function renderCart() {
    if (!cart.length) {
      $cartLines.innerHTML = '<div class="pos-hint muted" style="text-align:center;padding:26px 10px">Cart is empty.<br>Add products from the left.</div>';
    } else {
      $cartLines.innerHTML = cart.map(function (line) {
        var lineTotal = line.price * line.qty;
        var body = '';
        if (line.serialized) {
          body = '<div class="cart-serial-chips">' + line.units.map(function (u) {
            return '<span class="unit-chip">' + esc(u.serial) + '<a href="#" class="chip-x" data-unit="' + u.id + '">✕</a></span>';
          }).join('') + '</div>';
        } else {
          body = '<div class="cart-qty">' +
            '<button type="button" class="qty-btn" data-d="-1">−</button>' +
            '<span>' + line.qty + '</span>' +
            '<button type="button" class="qty-btn" data-d="1">+</button>' +
          '</div>';
        }
        return '<div class="cart-line" data-id="' + line.id + '">' +
          '<div class="cart-line-main">' +
            '<div class="cart-line-name">' + esc(line.name) + '</div>' +
            '<div class="cart-line-sku">' + esc(line.sku) + ' · ' + money(line.price) + '</div>' +
            body +
          '</div>' +
          '<div class="cart-line-side">' +
            '<div class="cart-line-total">' + money(lineTotal) + '</div>' +
            '<a href="#" class="cart-line-remove">remove</a>' +
          '</div>' +
        '</div>';
      }).join('');

      $cartLines.querySelectorAll('.qty-btn').forEach(function (b) {
        b.addEventListener('click', function () {
          var line = cart.find(function (l) { return l.id === parseInt(b.closest('.cart-line').dataset.id, 10) && !l.serialized; });
          var d = parseInt(b.dataset.d, 10);
          if (!line) return;
          var next = line.qty + d;
          if (next < 1) return;
          if (next > (line.stock || 9999)) { setError('Only ' + line.stock + ' in stock for "' + line.name + '".'); return; }
          line.qty = next;
          renderCart();
        });
      });
      $cartLines.querySelectorAll('.chip-x').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          var line = cart.find(function (l) { return l.id === parseInt(a.closest('.cart-line').dataset.id, 10) && l.serialized; });
          if (!line) return;
          line.units = line.units.filter(function (u) { return u.id !== parseInt(a.dataset.unit, 10); });
          line.qty = line.units.length;
          if (!line.units.length) { cart = cart.filter(function (l) { return l !== line; }); }
          renderCart();
        });
      });
      $cartLines.querySelectorAll('.cart-line-remove').forEach(function (a) {
        a.addEventListener('click', function (e) {
          e.preventDefault();
          var id = parseInt(a.closest('.cart-line').dataset.id, 10);
          cart = cart.filter(function (l) { return l.id !== id; });
          renderCart();
        });
      });
    }
    renderTotals();
  }

  function discountAmount() {
    var v = parseFloat($discount.value) || 0;
    if ($discountType.value === 'pct') {
      return subtotal() * Math.min(100, Math.max(0, v)) / 100;
    }
    return Math.max(0, v);
  }
  function needsPin() {
    if (!DISCOUNT_PIN_ACTIVE) { return false; }
    var d = discountAmount();
    return d > 0 && d >= DISCOUNT_PIN_THRESHOLD;
  }
  function subtotal() {
    return cart.reduce(function (s, l) { return s + l.price * l.qty; }, 0);
  }

  function renderTotals() {
    var sub = subtotal();
    var disc = Math.min(discountAmount(), sub);
    var tax = (sub - disc) * VAT / 100;
    var total = sub - disc + tax;
    $subtotal.textContent = money(sub);
    $vat.textContent = money(tax);
    $total.textContent = money(total);
    $checkout.disabled = cart.length === 0;

    var pin = needsPin();
    if ($authPin) { $authPin.style.display = pin ? '' : 'none'; }
    if ($pinHint) { $pinHint.style.display = pin ? '' : 'none'; }

    if ($payMethod.value === 'cash') {
      var tendered = parseFloat($cashReceived.value) || 0;
      if (tendered > 0) {
        $changeRow.style.display = '';
        $change.textContent = money(Math.max(0, tendered - total));
      } else {
        $changeRow.style.display = 'none';
      }
    }
  }

  /* ---------------- events ---------------- */
  $discount.addEventListener('input', renderTotals);
  $discountType.addEventListener('change', renderTotals);
  $cashReceived.addEventListener('input', renderTotals);

  $payMethod.addEventListener('change', function () {
    $cashField.style.display = $payMethod.value === 'cash' ? '' : 'none';
    $refField.style.display = $payMethod.value === 'cash' ? 'none' : '';
    renderTotals();
  });

  document.getElementById('clear-cart').addEventListener('click', function () {
    if (!cart.length) return;
    if (confirm('Clear the current cart?')) { cart = []; renderCart(); }
  });

  /* ---------------- checkout (online + offline) ---------------- */
  function buildPayload() {
    return {
      lines: cart.map(function (l) {
        return { product_id: l.id, quantity: l.qty, unit_ids: l.units.map(function (u) { return u.id; }) };
      }),
      discount: discountAmount(),
      discount_pin: needsPin() ? $authPin.value : '',
      payment_method: $payMethod.value,
      payment_ref: document.getElementById('pay-ref').value,
      customer_name: document.getElementById('cust-name').value,
      customer_phone: document.getElementById('cust-phone').value
    };
  }
  function buildOfflinePayload() {
    // Offline: send serial *numbers*, not unit ids (ids can go stale).
    return {
      lines: cart.map(function (l) {
        if (l.serialized) {
          return { product_id: l.id, quantity: l.units.length, serial_numbers: l.units.map(function (u) { return u.serial; }) };
        }
        return { product_id: l.id, quantity: l.qty };
      }),
      discount: discountAmount(),
      discount_pin: needsPin() ? $authPin.value : '',
      payment_method: $payMethod.value,
      payment_ref: document.getElementById('pay-ref').value,
      customer_name: document.getElementById('cust-name').value,
      customer_phone: document.getElementById('cust-phone').value
    };
  }

  $checkout.addEventListener('click', function () {
    setError('');
    setInfo('');
    if (needsPin() && !$authPin.value.trim()) {
      setError('Manager PIN required for discounts of ' + money(DISCOUNT_PIN_THRESHOLD) + ' or more.');
      $authPin.focus();
      return;
    }
    var payload = buildPayload();
    $checkout.disabled = true;
    $checkout.textContent = 'Processing…';
    fetch(window.KC_CHECKOUT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.KC_CSRF },
      body: JSON.stringify(payload)
    })
    .then(function (r) {
      if (r.status >= 500) { throw new Error('server'); }
      return r.json();
    })
    .then(function (data) {
      if (data.ok) { window.location.href = data.redirect; }
      else { setError(data.error || 'Could not complete the sale.'); resetBtn(); }
    })
    .catch(function () {
      offlineCheckout();
    });
  });

  function offlineCheckout() {
    KCPosOffline.queueSale(buildOfflinePayload()).then(function (item) {
      var snap = buildReceiptSnapshot(item.client_ref);
      cart = [];
      renderCart();
      resetBtn();
      showOfflineReceipt(snap);
      updatePending();
      setInfo('Offline sale queued — it will sync automatically when online.');
    });
  }

  function buildReceiptSnapshot(ref) {
    var sub = subtotal();
    var disc = Math.min(discountAmount(), sub);
    var tax = (sub - disc) * VAT / 100;
    return {
      ref: ref,
      items: cart.map(function (l) {
        return { name: l.name, sku: l.sku, qty: l.qty, price: l.price, serials: l.serialized ? l.units.map(function (u) { return u.serial; }) : [] };
      }),
      subtotal: sub, discount: disc, vat: tax, total: sub - disc + tax,
      payment: $payMethod.value,
      customer: document.getElementById('cust-name').value || 'Walk-in customer'
    };
  }

  function receiptHTML(snap) {
    var rows = snap.items.map(function (i) {
      return '<div class="orow"><div><b>' + esc(i.name) + '</b>' +
        (i.serials.length ? '<div class="osn">' + i.serials.map(esc).join('<br>') + '</div>' : '') +
        '</div><div class="orow-qty">× ' + i.qty + '</div><div class="orow-total">' + money(i.price * i.qty) + '</div></div>';
    }).join('');
    return '<div class="oref">Temp ref: ' + esc(snap.ref) + ' · ' + esc(snap.payment.toUpperCase()) + '</div>' +
      '<div class="ocust">' + esc(snap.customer) + '</div>' +
      rows +
      '<div class="osum"><div><span>Subtotal</span><b>' + money(snap.subtotal) + '</b></div>' +
      (snap.discount > 0 ? '<div><span>Discount</span><b>− ' + money(snap.discount) + '</b></div>' : '') +
      '<div><span>VAT (' + VAT + '%)</span><b>' + money(snap.vat) + '</b></div>' +
      '<div class="ogrand"><span>Total</span><b>' + money(snap.total) + '</b></div></div>' +
      '<div class="onote">Offline sale — pending sync</div>';
  }

  function showOfflineReceipt(snap) {
    var html = receiptHTML(snap);
    document.getElementById('offline-body').innerHTML = html;
    document.getElementById('offline-print-area').innerHTML = '<div class="offline-receipt">' + html + '</div>';
    document.getElementById('offline-modal').style.display = 'flex';
  }

  document.getElementById('offline-close').addEventListener('click', function () {
    document.getElementById('offline-modal').style.display = 'none';
    $search.focus();
  });
  document.getElementById('offline-print').addEventListener('click', function () {
    document.body.classList.add('printing-offline');
    window.print();
    setTimeout(function () { document.body.classList.remove('printing-offline'); }, 500);
  });

  /* ---------------- offline status + sync ---------------- */
  function setConn(on) {
    $connBadge.textContent = on ? '● Online' : '● Offline';
    $connBadge.className = 'conn-badge ' + (on ? 'online' : 'offline');
  }
  function updatePending() {
    KCPosOffline.pendingCount().then(function (n) {
      $pendingBadge.style.display = n > 0 ? '' : 'none';
      $pendingBadge.textContent = n + ' queued';
      $syncNow.style.display = n > 0 ? '' : 'none';
    });
  }
  function doSync() {
    KCPosOffline.syncQueue().then(function (r) {
      if (r.auth) { setError('Your session expired — sign in again to sync offline sales.'); }
      else if (r.synced > 0) {
        var nums = (r.results || []).filter(function (x) { return x.ok; }).map(function (x) { return x.sale_number; });
        setInfo(r.synced + ' offline sale(s) synced' + (nums.length ? ' (' + nums.join(', ') + ')' : '') + '.');
      }
      updatePending();
    });
  }

  $syncNow.addEventListener('click', doSync);
  window.addEventListener('online', function () { setConn(true); doSync(); });
  window.addEventListener('offline', function () { setConn(false); });
  setInterval(function () {
    if (KCPosOffline.isOnline()) {
      KCPosOffline.pendingCount().then(function (n) { if (n > 0) { doSync(); } });
    }
  }, 30000);

  /* ---------------- helpers ---------------- */
  function resetBtn() { $checkout.disabled = cart.length === 0; $checkout.textContent = 'Complete sale'; }
  function setError(msg) { $error.textContent = msg; }
  function setInfo(msg) { $info.textContent = msg; }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------------- boot ---------------- */
  setConn(KCPosOffline.isOnline());
  KCPosOffline.init().then(updatePending);
})();
</script>
