<?php
$vat = vat_rate();
$pinActive = Setting::get('discount_pin_hash', '') !== '';
$pinThreshold = (float) Setting::get('discount_pin_threshold', '1000');
$recentEnquiries = $recentWhatsappEnquiries ?? [];
?>
<div class="pos-workspace-head">
    <div>
        <span class="section-kicker">Cashier workspace</span>
        <h1>New sale</h1>
        <p>Scan a barcode or search the catalogue. Your active cart is saved on this device. Use Sale Source to track WhatsApp / walk-in / phone enquiries.</p>
    </div>
    <div class="pos-status" aria-label="Register status">
        <span id="conn-badge" class="conn-badge online">● Online</span>
        <button id="pending-badge" class="conn-badge pending" style="display:none" type="button">0 queued</button>
        <button id="sync-now" class="btn btn-outline btn-sm" style="display:none" type="button">Sync now</button>
    </div>
</div>

<?php if (!empty($recentEnquiries)): ?>
<div class="card" style="margin-bottom:14px; padding:14px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
        <div><span class="section-kicker">Recent WhatsApp enquiries</span><h3 style="margin:4px 0 0;">WhatsApp leads</h3></div>
        <button class="btn btn-ghost btn-sm" type="button" id="toggle-enquiries">Show / Hide</button>
    </div>
    <div id="recent-enquiries-list" style="display:none;">
        <div style="display:grid; gap:8px; max-height:220px; overflow:auto;">
        <?php foreach ($recentEnquiries as $enq): ?>
            <div class="zone-card" style="padding:10px; font-size:13px;">
                <div style="display:flex; justify-content:space-between; gap:10px;">
                    <div><b><?= e($enq['product_name'] ?? 'Product #' . $enq['product_id']) ?></b> <?= !empty($enq['variant_label']) ? '<span class="badge badge-gray">' . e($enq['variant_label']) . '</span>' : '' ?> <?= !empty($enq['condition_type']) ? '<span class="badge badge-blue">' . e(ucfirst($enq['condition_type'])) . '</span>' : '' ?></div>
                    <small class="muted"><?= e($enq['created_at']) ?></small>
                </div>
                <div class="muted" style="margin-top:4px;"><?= e($enq['customer_phone'] ?? '') ?> · <?= e($enq['customer_name'] ?? '') ?> · <?= e($enq['product_url'] ?? '') ?></div>
                <div style="margin-top:6px;"><button class="btn btn-outline btn-sm" type="button" data-enquiry-id="<?= (int)$enq['id'] ?>" data-enquiry-product="<?= (int)$enq['product_id'] ?>" data-enquiry-variant="<?= (int)($enq['variant_id'] ?? 0) ?>">Use in POS</button></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="pos-layout">
    <!-- Left: search + product grid -->
    <div class="pos-catalog">
        <div class="pos-top">
            <div class="pos-searchbar">
                <label class="sr-only" for="pos-search">Search products, barcodes, or serial numbers</label>
                <input type="search" id="pos-search" autocomplete="off"
                       placeholder="Scan barcode or type product name, SKU, serial / IMEI…"
                       autofocus>
                <kbd>/</kbd>
            </div>
        </div>
        <div id="pos-search-status" class="pos-search-status" role="status" aria-live="polite"></div>
        <div id="pos-results" class="pos-grid" aria-label="Product results"></div>
    </div>

    <!-- Right: cart -->
    <aside class="pos-cart">
        <div class="pos-cart-head">
            <div><h2>Current sale</h2><span id="cart-count-label" class="muted">0 items</span></div>
            <button class="btn btn-ghost btn-sm" id="clear-cart" type="button" disabled>Clear cart</button>
        </div>

        <div id="cart-lines" class="pos-cart-lines">
            <div class="pos-hint muted" style="text-align:center;padding:26px 10px">
                Cart is empty.<br>Add products from the left.
            </div>
        </div>

        <div class="pos-cart-foot">
            <details class="pos-customer-details" id="customer-details">
                <summary>Customer details <span>Optional</span></summary>
                <div class="pos-customer-row">
                    <label class="field">
                        <span>Customer name</span>
                        <input type="text" id="cust-name" placeholder="Walk-in customer" autocomplete="name">
                    </label>
                    <label class="field">
                        <span>Phone</span>
                        <input type="tel" id="cust-phone" placeholder="07…" autocomplete="tel" inputmode="tel">
                    </label>
                </div>
                <div class="pos-customer-row" style="margin-top:10px;">
                    <label class="field">
                        <span>Sale source</span>
                        <select id="sale-source">
                            <option value="walk-in" selected>Walk-in</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="phone">Phone</option>
                            <option value="other">Other</option>
                        </select>
                        <small class="hint">Tracks whether sale originated from WhatsApp enquiry, phone, etc.</small>
                    </label>
                    <label class="field">
                        <span>WhatsApp enquiry ID <em class="hint">optional</em></span>
                        <input type="number" id="whatsapp-enquiry-id" placeholder="Link to enquiry if applicable" min="1" step="1">
                        <small class="hint">Fill when completing a WhatsApp order from catalogue.</small>
                    </label>
                </div>
            </details>

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
                <div class="pos-pay-row">
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
                </div>
                <div class="pos-total-row" id="change-row" style="display:none">
                    <span>Change due</span><b id="t-change">KSh 0.00</b>
                </div>
            </div>

            <button class="btn btn-primary btn-block" id="checkout-btn" type="button" disabled>
                Review and complete sale
            </button>
            <p class="pos-error" id="pos-error" role="alert"></p>
            <p class="pos-info" id="pos-info" role="status"></p>
        </div>
    </aside>
</div>

<!-- Serial picker modal -->
<div class="modal-backdrop" id="serial-modal" style="display:none">
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-sub">
        <h3 id="modal-title">Select serial numbers</h3>
        <p class="muted" id="modal-sub"></p>
        <div class="serial-picker-tools">
            <label class="sr-only" for="serial-search">Search serial numbers</label>
            <input type="search" id="serial-search" placeholder="Search serial / IMEI…" autocomplete="off">
            <span id="serial-count" class="badge badge-blue">0 selected</span>
        </div>
        <div id="modal-units" class="modal-units"></div>
        <div class="modal-actions">
            <button class="btn btn-ghost" id="modal-cancel" type="button">Cancel</button>
            <button class="btn btn-primary" id="modal-add" type="button" disabled>Add selected units</button>
        </div>
    </div>
</div>

<!-- Offline receipt modal -->
<div class="modal-backdrop" id="offline-modal" style="display:none">
    <div class="modal modal-receipt" role="dialog" aria-modal="true" aria-labelledby="offline-title">
        <h3 id="offline-title">Sale saved on this device</h3>
        <p class="muted">This sale is saved on this device and will sync to the main system
           as soon as the connection returns.</p>
        <div id="offline-body" class="offline-receipt"></div>
        <div class="modal-actions">
            <button class="btn btn-outline" id="offline-print" type="button">Print receipt</button>
            <button class="btn btn-primary" id="offline-close" type="button">New sale</button>
        </div>
    </div>
</div>

<!-- Final sale review -->
<div class="modal-backdrop" id="checkout-review" style="display:none">
    <div class="modal checkout-review-card" role="dialog" aria-modal="true" aria-labelledby="review-title" aria-describedby="review-summary">
        <span class="section-kicker">Final check</span>
        <h3 id="review-title">Review sale</h3>
        <div id="review-summary" class="review-summary"></div>
        <p class="muted review-note">Stock changes immediately when this sale completes.</p>
        <div class="modal-actions">
            <button class="btn btn-outline" id="review-back" type="button">Back</button>
            <button class="btn btn-primary" id="review-confirm" type="button">Complete sale</button>
        </div>
    </div>
</div>

<!-- Offline queue -->
<div class="queue-backdrop" id="queue-backdrop" style="display:none">
    <aside class="queue-drawer" id="queue-drawer" role="dialog" aria-modal="true" aria-labelledby="queue-title">
        <div class="queue-head">
            <div><span class="section-kicker">This register</span><h2 id="queue-title">Offline queue</h2></div>
            <button class="btn btn-ghost btn-sm" id="queue-close" type="button">Close</button>
        </div>
        <p class="muted">Sales saved on this device remain here until the main system confirms them.</p>
        <div id="queue-list" class="queue-list"></div>
        <button class="btn btn-primary btn-block" id="queue-retry" type="button">Retry all</button>
    </aside>
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
  var catalog = [];
  var pendingSerial = null;
  var checkoutBusy = false;
  var checkoutRef = '';
  var DRAFT_KEY = 'kc_pos_draft_v2';

  /* ---------------- dom ---------------- */
  var $search = document.getElementById('pos-search');
  var $results = document.getElementById('pos-results');
  var $searchStatus = document.getElementById('pos-search-status');
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
  var $clearCart = document.getElementById('clear-cart');
  var $cartCountLabel = document.getElementById('cart-count-label');

  var $saleSource = document.getElementById('sale-source');
  var $waEnquiryId = document.getElementById('whatsapp-enquiry-id');

  /* WhatsApp enquiries toggle */
  var $toggleEnq = document.getElementById('toggle-enquiries');
  var $recentList = document.getElementById('recent-enquiries-list');
  if ($toggleEnq && $recentList) {
    $toggleEnq.addEventListener('click', function(){ $recentList.style.display = $recentList.style.display === 'none' ? '' : 'none'; });
    document.querySelectorAll('[data-enquiry-id]').forEach(function(btn){
      btn.addEventListener('click', function(){
        var id = parseInt(btn.getAttribute('data-enquiry-id'),10);
        if ($waEnquiryId) { $waEnquiryId.value = String(id); $saleSource.value = 'whatsapp'; }
        document.getElementById('customer-details').open = true;
        if ($recentList) $recentList.style.display = 'none';
        saveDraft();
        window.KC && KC.toast && KC.toast('Linked to WhatsApp enquiry #' + id + ' (source set to WhatsApp). Search product to add.', 'success');
      });
    });
  }

  /* ---------------- search ---------------- */
  var timer = null;
  var searchSeq = 0;
  var lastResultsQuery = '';
  var enterBusy = false;

  function runSearch(q) {
    q = q || '';
    if (!q) { return Promise.resolve({ list: [], source: 'start' }); }
    if (!KCPosOffline.isOnline()) {
      return KCPosOffline.searchLocal(q).then(function (list) { return { list: list, source: 'offline' }; });
    }
    return fetch(window.KC_SEARCH + '?q=' + encodeURIComponent(q))
      .then(function (r) {
        if (!r.ok) { throw new Error('server'); }
        return r.json();
      })
      .then(function (data) {
        setConn(true);
        KCPosOffline.mergeCatalog(data);
        return { list: data, source: 'online' };
      })
      .catch(function () {
        return KCPosOffline.searchLocal(q).then(function (list) {
          return { list: list, source: 'cached', failed: true };
        });
      });
  }

  function doSearch(q) {
    var my = ++searchSeq;
    if (!q) { renderStart(); return; }
    renderSearchLoading();
    runSearch(q).then(function (result) {
      if (my !== searchSeq) { return; }
      lastResultsQuery = q;
      renderResults(result.list || [], result, q);
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
      var q = $search.value.trim();
      if (!q) { return; }
      clearTimeout(timer);
      if (results.length && lastResultsQuery === q) { addProduct(results[0]); return; }
      if (enterBusy) { return; }
      enterBusy = true;
      var box = $search;
      runSearch(q).then(function (result) {
        enterBusy = false;
        var list = result.list || [];
        if (!list.length) { return; }
        if (box.value.trim() !== q) { return; }
        addProduct(list[0]);
      });
    }
  });

  function renderSearchLoading() {
    $searchStatus.textContent = 'Searching catalogue…';
    $results.innerHTML = '<div class="pos-skeleton"></div><div class="pos-skeleton"></div><div class="pos-skeleton"></div>';
  }

  function initials(name) {
    return (name || 'KC').split(/\s+/).slice(0, 2).map(function (word) { return word.charAt(0); }).join('').toUpperCase();
  }

  function renderStart() {
    results = [];
    $searchStatus.textContent = 'Scanner ready · Press / to focus search';
    var available = catalog.filter(function (p) { return p.stock > 0; }).slice(0, 6);
    var categories = [];
    catalog.forEach(function (p) { if (p.category && categories.indexOf(p.category) === -1) categories.push(p.category); });
    var quick = available.map(function (p) {
      return '<button class="pos-quick" type="button" data-product="' + p.id + '"><strong>' + esc(p.name) + '</strong><span>' + money(p.sell_price) + ' · ' + p.stock + ' available</span></button>';
    }).join('');
    $results.innerHTML = '<section class="pos-start">' +
      '<div class="pos-start-main"><span class="pos-ready">Scanner and catalogue ready</span><h2>Start the next sale</h2>' +
      '<p class="muted">Scan a barcode or serial number for the fastest checkout, or choose a frequently used item.</p>' +
      '<div class="pos-quick-title">Quick picks</div><div class="pos-quick-grid">' + (quick || '<span class="muted">Catalogue is loading…</span>') + '</div>' +
      '<div class="pos-category-shortcuts">' + categories.slice(0, 6).map(function (category) { return '<button type="button" data-category="' + esc(category) + '\">' + esc(category) + '</button>'; }).join('') + '</div></div>' +
      '<div class="pos-start-side"><h3>Keyboard shortcuts</h3><dl class="pos-shortcuts">' +
      '<div><dt>Focus search</dt><dd><kbd>/</kbd></dd></div><div><dt>Add first result</dt><dd><kbd>Enter</kbd></dd></div>' +
      '<div><dt>Review sale</dt><dd><kbd>F2</kbd></dd></div><div><dt>Clear search</dt><dd><kbd>Esc</kbd></dd></div>' +
      '</dl></div></section>';
    $results.querySelectorAll('[data-product]').forEach(function (button) {
      button.addEventListener('click', function () {
        var product = catalog.find(function (p) { return p.id === parseInt(button.dataset.product, 10); });
        if (product) addProduct(product);
      });
    });
    $results.querySelectorAll('[data-category]').forEach(function (button) {
      button.addEventListener('click', function () {
        var category = button.dataset.category;
        $search.value = category;
        renderResults(catalog.filter(function (product) { return product.category === category; }), { source: 'category' }, category);
        $search.focus();
      });
    });
  }

  function renderResults(list, meta, query) {
    results = list || [];
    if (!results.length) {
      $searchStatus.textContent = meta && meta.failed ? 'The live catalogue could not be reached.' : 'No matches for “' + query + '”.';
      $results.innerHTML = '<div class="pos-hint pos-result-empty"><h3>No product found</h3><p>Check the barcode, SKU, serial number, or product name.</p>' +
        '<div class="pos-result-actions"><button class="btn btn-outline btn-sm" id="retry-search" type="button">Retry search</button><button class="btn btn-ghost btn-sm" id="clear-search" type="button">Clear</button></div></div>';
      document.getElementById('retry-search').addEventListener('click', function () { doSearch(query); });
      document.getElementById('clear-search').addEventListener('click', function () { $search.value = ''; renderStart(); $search.focus(); });
      return;
    }
    var cached = meta && (meta.source === 'offline' || meta.source === 'cached');
    $searchStatus.textContent = results.length + ' result' + (results.length === 1 ? '' : 's') + (cached ? ' · using saved catalogue' : '');
    $results.innerHTML = results.map(function (p, i) {
      var stockTxt = p.serialized ? (p.stock + ' unit' + (p.stock === 1 ? '' : 's')) : (p.stock + ' in stock');
      var out = p.stock > 0 ? '' : ' out';
      var exact = p.exact_match || String(p.sku || '').toLowerCase() === query.toLowerCase() || String(p.barcode || '').toLowerCase() === query.toLowerCase();
      return '<button type="button" class="pos-tile' + out + (exact ? ' exact' : '') + '" data-i="' + i + '"' + (p.stock <= 0 ? ' aria-disabled="true"' : '') + '>' +
        '<span class="pos-tile-top"><span class="pos-tile-thumb" aria-hidden="true">' + esc(initials(p.name)) + '</span><span class="pos-tile-copy"><span class="pos-tile-name">' + esc(p.name) + '</span>' +
        '<span class="pos-tile-sku">' + esc(p.sku) + '</span><span class="pos-tile-badges">' +
        (p.category ? '<span class="badge badge-gray">' + esc(p.category) + '</span>' : '') + (p.serialized ? '<span class="badge badge-blue">Serial tracked</span>' : '') + '</span></span></span>' +
        (exact ? '<span class="pos-tile-match">Exact ' + esc(p.matched_by || 'code') + ' match</span>' : '') +
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
    $search.value = ''; $search.focus(); renderStart();
    renderCart();
  }

  function openSerialModal(p) {
    pendingSerial = p;
    document.getElementById('modal-title').textContent = 'Serial numbers — ' + p.name;
    document.getElementById('modal-sub').textContent = p.stock + ' available · Pick the exact unit or units to sell.';
    var alreadySelected = [];
    var existing = cart.find(function (line) { return line.id === p.id && line.serialized; });
    if (existing) alreadySelected = existing.units.map(function (unit) { return unit.id; });
    var box = document.getElementById('modal-units');
    box.innerHTML = p.units.map(function (u) {
      var warranty = u.warranty_expires ? 'Warranty until ' + u.warranty_expires : 'Warranty not recorded';
      var unavailable = alreadySelected.indexOf(u.id) !== -1;
      var checked = p.exact_match && p.units.length === 1 && !unavailable;
      return '<label class="modal-unit' + (unavailable ? ' is-selected' : '') + '" data-search="' + esc(String(u.serial).toLowerCase()) + '"><input type="checkbox" value="' + u.id + '" data-serial="' + esc(u.serial) + '"' + (checked ? ' checked' : '') + (unavailable ? ' disabled' : '') + '>' +
             '<span class="modal-unit-copy"><strong>' + esc(u.serial) + '</strong><span>' + esc(unavailable ? 'Already in cart' : warranty) + '</span></span>' +
             '<span class="badge ' + (unavailable ? 'badge-gray' : 'badge-green') + '">' + (unavailable ? 'Selected' : 'Available') + '</span></label>';
    }).join('');
    document.getElementById('serial-search').value = '';
    document.getElementById('serial-modal').style.display = 'flex';
    updateSerialSelection();
    document.getElementById('serial-search').focus();
  }

  function updateSerialSelection() {
    var count = document.querySelectorAll('#modal-units input:checked').length;
    document.getElementById('serial-count').textContent = count + ' selected';
    document.getElementById('modal-add').disabled = count === 0;
  }

  document.getElementById('serial-search').addEventListener('input', function () {
    var query = this.value.trim().toLowerCase();
    document.querySelectorAll('#modal-units .modal-unit').forEach(function (unit) {
      unit.classList.toggle('is-hidden', query !== '' && unit.dataset.search.indexOf(query) === -1);
    });
  });
  document.getElementById('modal-units').addEventListener('change', updateSerialSelection);

  document.getElementById('modal-cancel').addEventListener('click', function () {
    closeSerialModal();
  });

  document.getElementById('modal-add').addEventListener('click', function () {
    var checks = document.querySelectorAll('#modal-units input:checked');
    if (!checks.length) { setError('Select at least one serial number.'); return; }
    var chosen = Array.prototype.map.call(checks, function (c) {
      var source = pendingSerial.units.find(function (unit) { return unit.id === parseInt(c.value, 10); });
      return { id: parseInt(c.value, 10), serial: c.dataset.serial, warranty_expires: source ? source.warranty_expires : null };
    });
    var existing = cart.find(function (l) { return l.id === pendingSerial.id && l.serialized; });
    if (existing) {
      chosen.forEach(function (u) {
        if (!existing.units.some(function (x) { return x.id === u.id; })) { existing.units.push(u); }
      });
      existing.qty = existing.units.length;
    } else {
      cart.push({ id: pendingSerial.id, name: pendingSerial.name, sku: pendingSerial.sku, price: pendingSerial.sell_price, serialized: true, qty: chosen.length, units: chosen });
    }
    $search.value = '';
    renderStart();
    closeSerialModal();
    renderCart();
  });

  function saveDraft() {
    try {
      if (!cart.length) { localStorage.removeItem(DRAFT_KEY); return; }
      localStorage.setItem(DRAFT_KEY, JSON.stringify({
        cart: cart,
        checkout_ref: checkoutRef,
        customer_name: document.getElementById('cust-name').value,
        customer_phone: document.getElementById('cust-phone').value,
        sale_source: $saleSource ? $saleSource.value : 'walk-in',
        whatsapp_enquiry_id: $waEnquiryId ? $waEnquiryId.value : '',
        discount: $discount.value,
        discount_type: $discountType.value,
        payment_method: $payMethod.value,
        payment_ref: document.getElementById('pay-ref').value,
        cash_received: $cashReceived.value,
        saved_at: Date.now()
      }));
    } catch (e) {}
  }

  function loadDraft() {
    try {
      var draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
      if (!draft || !Array.isArray(draft.cart) || !draft.cart.length) return false;
      cart = draft.cart;
      checkoutRef = draft.checkout_ref || '';
      document.getElementById('cust-name').value = draft.customer_name || '';
      document.getElementById('cust-phone').value = draft.customer_phone || '';
      if ($saleSource) $saleSource.value = draft.sale_source || 'walk-in';
      if ($waEnquiryId) $waEnquiryId.value = draft.whatsapp_enquiry_id || '';
      $discount.value = draft.discount || '0';
      $discountType.value = draft.discount_type || 'flat';
      $payMethod.value = draft.payment_method || 'cash';
      document.getElementById('pay-ref').value = draft.payment_ref || '';
      $cashReceived.value = draft.cash_received || '';
      document.getElementById('customer-details').open = !!(draft.customer_name || draft.customer_phone || draft.sale_source || draft.whatsapp_enquiry_id);
      refreshPaymentFields();
      return true;
    } catch (e) {
      localStorage.removeItem(DRAFT_KEY);
      return false;
    }
  }

  function renderCart() {
    if (!cart.length) {
      $cartLines.innerHTML = '<div class="pos-hint muted" style="text-align:center;padding:26px 10px"><strong>No items yet</strong><br>Scan or choose a product to begin.</div>';
    } else {
      $cartLines.innerHTML = cart.map(function (line) {
        var lineTotal = line.price * line.qty;
        var body = '';
        if (line.serialized) {
          body = '<div class="cart-serial-chips">' + line.units.map(function (u) {
            return '<span class="unit-chip">' + esc(u.serial) + '<button type="button" class="chip-x" data-unit="' + u.id + '" aria-label="Remove serial ' + esc(u.serial) + '">×</button></span>';
          }).join('') + '</div>';
        } else {
          body = '<div class="cart-qty">' +
            '<button type="button" class="qty-btn" data-d="-1" aria-label="Decrease ' + esc(line.name) + ' quantity">−</button>' +
            '<span aria-label="Quantity">' + line.qty + '</span>' +
            '<button type="button" class="qty-btn" data-d="1" aria-label="Increase ' + esc(line.name) + ' quantity">+</button>' +
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
            '<button type="button" class="cart-line-remove">Remove</button>' +
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
    var itemCount = cart.reduce(function (sum, line) { return sum + line.qty; }, 0);
    $cartCountLabel.textContent = itemCount + ' item' + (itemCount === 1 ? '' : 's') + (cart.length ? ' · Draft saved' : '');
    $clearCart.disabled = cart.length === 0;
    renderTotals();
    saveDraft();
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
    var d = Math.min(discountAmount(), subtotal());
    return d > 0 && d >= DISCOUNT_PIN_THRESHOLD;
  }
  function subtotal() {
    return cart.reduce(function (s, l) { return s + l.price * l.qty; }, 0);
  }
  function saleTotal() {
    var sub = subtotal();
    return sub - Math.min(discountAmount(), sub) + ((sub - Math.min(discountAmount(), sub)) * VAT / 100);
  }

  function renderTotals() {
    var sub = subtotal();
    var disc = Math.min(discountAmount(), sub);
    var tax = (sub - disc) * VAT / 100;
    var total = sub - disc + tax;
    $subtotal.textContent = money(sub);
    $vat.textContent = money(tax);
    $total.textContent = money(total);
    $checkout.disabled = cart.length === 0 || checkoutBusy;

    var pin = needsPin();
    if ($authPin) { $authPin.style.display = pin ? '' : 'none'; }
    if ($pinHint) { $pinHint.style.display = pin ? '' : 'none'; }

    var isCash   = $payMethod.value === 'cash';
    var tendered = isCash ? (parseFloat($cashReceived.value) || 0) : 0;
    if (isCash && tendered > 0) {
      $changeRow.style.display = '';
      $change.textContent = money(Math.max(0, tendered - total));
    } else {
      $changeRow.style.display = 'none';
    }
  }

  /* ---------------- events ---------------- */
  $discount.addEventListener('input', renderTotals);
  $discountType.addEventListener('change', renderTotals);
  $cashReceived.addEventListener('input', renderTotals);

  function refreshPaymentFields() {
    var isCash = $payMethod.value === 'cash';
    $cashField.style.display = isCash ? '' : 'none';
    $refField.style.display = isCash ? 'none' : '';
    document.getElementById('pay-ref').required = !isCash;
    document.getElementById('pay-ref').placeholder = $payMethod.value === 'mpesa' ? 'M-PESA confirmation code' : 'Transaction reference';
    renderTotals();
  }

  $payMethod.addEventListener('change', function () {
    setError('');
    refreshPaymentFields();
  });

  [$discount, $discountType, $cashReceived, $payMethod, document.getElementById('pay-ref'), document.getElementById('cust-name'), document.getElementById('cust-phone'), $saleSource, $waEnquiryId].forEach(function (control) {
    if (!control) return;
    control.addEventListener('input', saveDraft);
    control.addEventListener('change', saveDraft);
  });

  document.getElementById('clear-cart').addEventListener('click', function () {
    if (!cart.length) return;
    window.KC.confirm({
      title: 'Clear cart?',
      message: 'Remove every item from the current cart?',
      action: 'Clear cart',
      trigger: this
    }).then(function (confirmed) {
      if (confirmed) { cart = []; checkoutRef = ''; renderCart(); $search.focus(); }
    });
  });

  /* ---------------- checkout (online + offline) ---------------- */
  function ensureCheckoutRef() {
    if (!checkoutRef) {
      checkoutRef = 'pos-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
      saveDraft();
    }
    return checkoutRef;
  }
  function buildPayload() {
    return {
      lines: cart.map(function (l) {
        return { product_id: l.id, quantity: l.qty, unit_ids: l.units.map(function (u) { return u.id; }) };
      }),
      discount: discountAmount(),
      discount_pin: needsPin() ? $authPin.value : '',
      payment_method: $payMethod.value,
      payment_ref: document.getElementById('pay-ref').value,
      cash_received: parseFloat($cashReceived.value) || 0,
      customer_name: document.getElementById('cust-name').value,
      customer_phone: document.getElementById('cust-phone').value,
      sale_source: $saleSource ? $saleSource.value : 'walk-in',
      whatsapp_enquiry_id: $waEnquiryId && $waEnquiryId.value ? parseInt($waEnquiryId.value,10) : null,
      device_id: KCPosOffline.deviceId(),
      client_ref: ensureCheckoutRef()
    };
  }
  function buildOfflinePayload() {
    return {
      lines: cart.map(function (l) {
        if (l.serialized) {
          return { product_id: l.id, quantity: l.units.length, serial_numbers: l.units.map(function (u) { return u.serial; }) };
        }
        return { product_id: l.id, quantity: l.qty };
      }),
      discount: discountAmount(),
      discount_pin: '',
      payment_method: $payMethod.value,
      payment_ref: document.getElementById('pay-ref').value,
      cash_received: parseFloat($cashReceived.value) || 0,
      customer_name: document.getElementById('cust-name').value,
      customer_phone: document.getElementById('cust-phone').value,
      sale_source: $saleSource ? $saleSource.value : 'walk-in',
      whatsapp_enquiry_id: $waEnquiryId && $waEnquiryId.value ? parseInt($waEnquiryId.value,10) : null
    };
  }

  function validateSale() {
    setError('');
    setInfo('');
    document.querySelectorAll('.pos-cart [aria-invalid="true"]').forEach(function (field) { field.removeAttribute('aria-invalid'); });
    if (!cart.length) { setError('Add at least one product before checkout.'); $search.focus(); return false; }
    if (needsPin() && !$authPin.value.trim()) {
      setError('Manager PIN required for discounts of ' + money(DISCOUNT_PIN_THRESHOLD) + ' or more.');
      $authPin.setAttribute('aria-invalid', 'true');
      $authPin.focus();
      return false;
    }
    var phone = document.getElementById('cust-phone');
    if (phone.value.trim() && phone.value.replace(/\D/g, '').length < 9) {
      setError('Enter a complete customer phone number or leave it blank.');
      document.getElementById('customer-details').open = true;
      phone.setAttribute('aria-invalid', 'true'); phone.focus(); return false;
    }
    var ref = document.getElementById('pay-ref');
    if ($payMethod.value !== 'cash' && ref.value.trim().length < 3) {
      setError('Enter the ' + ($payMethod.value === 'mpesa' ? 'M-PESA confirmation code' : 'payment reference') + '.');
      ref.setAttribute('aria-invalid', 'true'); ref.focus(); return false;
    }
    if ($payMethod.value === 'cash' && (parseFloat($cashReceived.value) || 0) < saleTotal()) {
      setError('Cash received must cover the sale total.');
      $cashReceived.setAttribute('aria-invalid', 'true'); $cashReceived.focus(); return false;
    }
    return true;
  }

  var reviewModal = document.getElementById('checkout-review');
  function openReview() {
    if (!validateSale()) return;
    var itemCount = cart.reduce(function (sum, line) { return sum + line.qty; }, 0);
    var customer = document.getElementById('cust-name').value.trim() || 'Walk-in customer';
    var payment = $payMethod.options[$payMethod.selectedIndex].text;
    var source = $saleSource ? $saleSource.options[$saleSource.selectedIndex].text : 'Walk-in';
    document.getElementById('review-summary').innerHTML =
      '<div class="review-row"><span>Items</span><b>' + itemCount + '</b></div>' +
      '<div class="review-row"><span>Customer</span><b>' + esc(customer) + '</b></div>' +
      '<div class="review-row"><span>Payment</span><b>' + esc(payment) + '</b></div>' +
      '<div class="review-row"><span>Source</span><b>' + esc(source) + '</b></div>' +
      '<div class="review-row total"><span>Total</span><b>' + money(saleTotal()) + '</b></div>';
    reviewModal.style.display = 'flex';
    document.getElementById('review-confirm').focus();
  }
  function closeReview() {
    reviewModal.style.display = 'none';
    if (!checkoutBusy) $checkout.focus();
  }

  $checkout.addEventListener('click', openReview);
  document.getElementById('review-back').addEventListener('click', closeReview);
  document.getElementById('review-confirm').addEventListener('click', function () {
    if (checkoutBusy) return;
    checkoutBusy = true;
    closeReview();
    var payload = buildPayload();
    $checkout.disabled = true;
    $checkout.innerHTML = '<span class="button-spinner" aria-hidden="true"></span>Completing sale…';
    $checkout.setAttribute('aria-busy', 'true');
    fetch(window.KC_CHECKOUT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.KC_CSRF },
      body: JSON.stringify(payload)
    })
    .then(function (r) {
      if (r.status >= 500) { throw new Error('server'); }
      return r.json().then(function (data) { return { status: r.status, data: data }; });
    })
    .then(function (result) {
      var data = result.data;
      if (data.ok) {
        localStorage.removeItem(DRAFT_KEY);
        window.location.href = data.redirect;
      } else {
        setError(data.error || 'Could not complete the sale. Review the highlighted fields and try again.');
        resetBtn();
      }
    })
    .catch(function () {
      if (needsPin()) {
        setError('This manager-approved discount cannot be stored offline. Reconnect or remove the discount, then try again.');
        resetBtn();
        return;
      }
      offlineCheckout();
    });
  });

  function offlineCheckout() {
    setConn(false);
    var ref = ensureCheckoutRef();
    var snap = buildReceiptSnapshot(ref);
    var summary = { total: snap.total, item_count: snap.items.reduce(function (sum, item) { return sum + item.qty; }, 0), payment: snap.payment, customer: snap.customer };
    KCPosOffline.queueSale(buildOfflinePayload(), ref, summary).then(function (item) {
      cart = [];
      checkoutRef = '';
      renderCart();
      resetBtn();
      showOfflineReceipt(snap);
      updatePending();
      setInfo('Sale saved on this device. It will sync automatically when the connection is restored.');
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
    document.getElementById('offline-close').focus();
  }

  document.getElementById('offline-close').addEventListener('click', closeOfflineModal);
  document.getElementById('offline-print').addEventListener('click', function () {
    document.body.classList.add('printing-offline');
    window.print();
    setTimeout(function () { document.body.classList.remove('printing-offline'); }, 500);
  });

  /* ---------------- offline status + sync ---------------- */
  function setConn(on) {
    $connBadge.textContent = on ? '● Online' : '● Offline';
    $connBadge.className = 'conn-badge ' + (on ? 'online' : 'offline');
    $connBadge.setAttribute('aria-label', on ? 'Register online' : 'Register offline; sales will be saved on this device');
  }
  function updatePending() {
    KCPosOffline.queueItems().then(function (items) {
      var n = items.length;
      $pendingBadge.style.display = n > 0 ? '' : 'none';
      var attention = items.filter(function (item) { return item.status === 'attention'; }).length;
      $pendingBadge.textContent = attention ? attention + ' need attention' : n + ' queued';
      $pendingBadge.classList.toggle('has-error', attention > 0);
      $syncNow.style.display = n > 0 ? '' : 'none';
      renderQueue(items);
    });
  }
  function renderQueue(items) {
    var list = document.getElementById('queue-list');
    document.getElementById('queue-retry').disabled = !items.length || !KCPosOffline.isOnline();
    if (!items.length) {
      list.innerHTML = '<div class="queue-empty"><strong>Queue is clear</strong><br>Every saved sale is confirmed by the main system.</div>';
      return;
    }
    list.innerHTML = items.map(function (item) {
      var summary = item.summary || {};
      var status = item.status === 'attention' ? 'Needs attention' : 'Saved on this device';
      return '<article class="queue-item"><div class="queue-item-head"><span class="queue-item-ref">' + esc(item.client_ref) + '</span>' +
        '<span class="badge ' + (item.status === 'attention' ? 'badge-red' : 'badge-orange') + '">' + status + '</span></div>' +
        '<div class="queue-item-meta">' + esc(item.created_at || '') + ' · ' + (summary.item_count || 0) + ' items' + (summary.total != null ? ' · ' + money(summary.total) : '') + '</div>' +
        (item.last_error ? '<div class="queue-item-error">' + esc(item.last_error) + '</div>' : '') + '</article>';
    }).join('');
  }
  function doSync() {
    if (!KCPosOffline.isOnline()) { setError('This register is offline. Reconnect before retrying the queue.'); return; }
    $syncNow.disabled = true;
    $syncNow.innerHTML = '<span class="button-spinner" aria-hidden="true"></span>Syncing…';
    $pendingBadge.textContent = 'Syncing…';
    KCPosOffline.syncQueue().then(function (r) {
      if (!r.auth && r.failed === 0) setConn(true);
      if (r.auth) { setError('Your session expired — sign in again to sync offline sales.'); }
      else if (r.synced > 0) {
        var nums = (r.results || []).filter(function (x) { return x.ok; }).map(function (x) { return x.sale_number; });
        setInfo(r.synced + ' offline sale(s) synced' + (nums.length ? ' (' + nums.join(', ') + ')' : '') + '.');
        window.KC.toast(r.synced + ' queued sale' + (r.synced === 1 ? '' : 's') + ' synced.', 'success');
      } else if (r.failed > 0) {
        setError('Some queued sales need attention. Open the queue for details.');
      }
      $syncNow.disabled = false;
      $syncNow.textContent = 'Sync now';
      updatePending();
    });
  }

  $syncNow.addEventListener('click', doSync);
  var queueBackdrop = document.getElementById('queue-backdrop');
  $pendingBadge.addEventListener('click', function () { queueBackdrop.style.display = 'block'; document.getElementById('queue-close').focus(); updatePending(); });
  document.getElementById('queue-close').addEventListener('click', function () { queueBackdrop.style.display = 'none'; $pendingBadge.focus(); });
  document.getElementById('queue-retry').addEventListener('click', doSync);
  queueBackdrop.addEventListener('mousedown', function (event) { if (event.target === queueBackdrop) { queueBackdrop.style.display = 'none'; $pendingBadge.focus(); } });
  window.addEventListener('online', function () { setConn(true); doSync(); });
  window.addEventListener('offline', function () { setConn(false); });
  setInterval(function () {
    if (KCPosOffline.isOnline()) {
      KCPosOffline.pendingCount().then(function (n) { if (n > 0) { doSync(); } });
    }
  }, 30000);

  /* ---------------- helpers ---------------- */
  function resetBtn() {
    checkoutBusy = false;
    $checkout.disabled = cart.length === 0;
    $checkout.removeAttribute('aria-busy');
    $checkout.textContent = 'Review and complete sale';
  }
  function setError(msg) { $error.textContent = msg; }
  function setInfo(msg) { $info.textContent = msg; }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>\"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------------- reset / modal helpers ---------------- */
  function resetSale() {
    document.getElementById('cust-name').value = '';
    document.getElementById('cust-phone').value = '';
    document.getElementById('pay-ref').value = '';
    if ($saleSource) $saleSource.value = 'walk-in';
    if ($waEnquiryId) $waEnquiryId.value = '';
    $discount.value = '0';
    $discountType.value = 'flat';
    if ($authPin) { $authPin.value = ''; $authPin.style.display = 'none'; }
    if ($pinHint) { $pinHint.style.display = 'none'; }
    $payMethod.value = 'cash';
    $cashReceived.value = '';
    $refField.style.display = 'none';
    $cashField.style.display = '';
    $changeRow.style.display = 'none';
    setError('');
    setInfo('');
    cart = [];
    checkoutRef = '';
    document.getElementById('customer-details').open = false;
    renderCart();
  }

  function closeSerialModal() {
    document.getElementById('serial-modal').style.display = 'none';
    pendingSerial = null;
    $search.focus();
  }

  function closeOfflineModal() {
    document.getElementById('offline-modal').style.display = 'none';
    resetSale();
    $search.focus();
  }

  var serialModalEl  = document.getElementById('serial-modal');
  var offlineModalEl = document.getElementById('offline-modal');

  serialModalEl.addEventListener('mousedown', function (e) {
    if (e.target === serialModalEl) { closeSerialModal(); }
  });
  offlineModalEl.addEventListener('mousedown', function (e) {
    if (e.target === offlineModalEl) { closeOfflineModal(); }
  });

  function trapFocus(container, e) {
    if (e.key === 'Tab') {
      var f = Array.prototype.filter.call(container.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href], [tabindex]:not([tabindex="-1"])'), function (el) {
        return !!(el.offsetWidth || el.offsetHeight);
      });
      if (!f.length) { return; }
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  }
  [serialModalEl, offlineModalEl, reviewModal, queueBackdrop].forEach(function (container) {
    container.addEventListener('keydown', function (e) { trapFocus(container, e); });
  });

  reviewModal.addEventListener('mousedown', function (e) { if (e.target === reviewModal && !checkoutBusy) closeReview(); });

  document.addEventListener('keydown', function (e) {
    var overlayOpen = serialModalEl.style.display !== 'none' || offlineModalEl.style.display !== 'none' ||
      reviewModal.style.display !== 'none' || queueBackdrop.style.display !== 'none';
    if (!overlayOpen && e.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) {
      e.preventDefault(); $search.focus(); $search.select(); return;
    }
    if (!overlayOpen && e.key === 'F2') { e.preventDefault(); openReview(); return; }
    if (e.key !== 'Escape') return;
    if (serialModalEl.style.display !== 'none') closeSerialModal();
    else if (offlineModalEl.style.display !== 'none') closeOfflineModal();
    else if (reviewModal.style.display !== 'none' && !checkoutBusy) closeReview();
    else if (queueBackdrop.style.display !== 'none') { queueBackdrop.style.display = 'none'; $pendingBadge.focus(); }
    else if ($search.value) { $search.value = ''; renderStart(); $search.focus(); }
  });

  /* ---------------- boot ---------------- */
  setConn(KCPosOffline.isOnline());
  renderSearchLoading();
  KCPosOffline.init().then(function (list) {
    catalog = Array.isArray(list) ? list : [];
    loadDraft();
    renderCart();
    renderStart();
    updatePending();
  });
})();
</script>
