<?php
// Products for the dynamic line picker.
$productJson = [];
foreach ($products as $p) {
    $productJson[] = [
        'id' => (int) $p['id'],
        'name' => $p['name'],
        'sku' => $p['sku'],
        'cost' => (float) $p['cost_price'],
        'serialized' => (int) $p['is_serialized'] === 1,
    ];
}
?>
<div class="content-narrow">
    <div class="page-head">
        <div>
            <h2>Receive stock</h2>
            <p class="lede">Record a delivery — it updates stock (and serial numbers) immediately.</p>
        </div>
        <a class="btn btn-ghost" href="<?= e(url('grn')) ?>">← Goods received</a>
    </div>

    <div class="card">
        <form method="post" action="<?= e(url('grn')) ?>" id="grn-form" class="form">
            <?= csrf_field() ?>

            <div class="form-row">
                <div class="field">
                    <span>Supplier <a href="<?= e(url('suppliers')) ?>" class="hint" style="font-weight:600">(manage)</a></span>
                    <select name="supplier_id">
                        <option value="">— choose saved supplier or type below —</option>
                        <?php foreach ($suppliers as $sp): ?>
                            <option value="<?= (int) $sp['id'] ?>"><?= e($sp['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="supplier" placeholder="…or type a supplier name" style="margin-top:6px">
                </div>
                <div class="field">
                    <span>Note (optional)</span>
                    <input type="text" name="note" placeholder="Delivery reference…">
                </div>
            </div>

            <div>
                <div class="section-kicker">Items</div>
                <div id="grn-rows"></div>
                <button type="button" class="btn btn-outline btn-sm" id="add-row">+ Add product</button>
            </div>

            <div class="grn-total">Total cost: <span id="grn-total">KSh 0.00</span></div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Receive &amp; update stock</button>
                <a class="btn btn-ghost" href="<?= e(url('grn')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
window.KC_PRODUCTS = <?= json_encode($productJson) ?>;
</script>
<script>
(function () {
  var products = window.KC_PRODUCTS || [];
  var rows = document.getElementById('grn-rows');
  var counter = 0;

  function money(n) {
    return 'KSh ' + Number(n || 0).toLocaleString('en-KE', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  }

  function productSelect() {
    var sel = document.createElement('select');
    sel.name = 'product_id[]';
    sel.innerHTML = '<option value="">— choose product —</option>' + products.map(function (p) {
      return '<option value="' + p.id + '" data-serialized="' + (p.serialized ? 1 : 0) + '" data-cost="' + p.cost + '">' +
             escapeHtml(p.name) + ' (' + escapeHtml(p.sku) + ')' + (p.serialized ? ' · serial' : '') + '</option>';
    }).join('');
    return sel;
  }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }

  function addRow() {
    counter++;
    var row = document.createElement('div');
    row.className = 'grn-row';
    row.style.cssText = 'padding:12px;border:1px solid var(--border);border-radius:12px;margin-bottom:10px;background:var(--surface-2)';

    var sel = productSelect();
    var qty = document.createElement('input');
    qty.type = 'number'; qty.min = 1; qty.value = 1; qty.name = 'qty[]';
    var cost = document.createElement('input');
    cost.type = 'number'; cost.step = '0.01'; cost.min = 0; cost.value = ''; cost.name = 'cost[]'; cost.placeholder = 'Unit cost';

    var serials = document.createElement('textarea');
    serials.name = 'serials[]'; serials.rows = 2; serials.placeholder = 'Serial numbers (one per line) — blank = auto-generate';
    serials.style.display = 'none';

    var rm = document.createElement('button');
    rm.type = 'button'; rm.className = 'btn btn-danger-ghost btn-sm'; rm.textContent = '✕';
    rm.onclick = function () { row.remove(); updateTotal(); };

    var p1 = document.createElement('div'); p1.className = 'product'; p1.appendChild(sel);
    var p2 = document.createElement('div'); p2.appendChild(qty);
    var p3 = document.createElement('div'); p3.appendChild(cost);
    var p4 = document.createElement('div'); p4.appendChild(rm);
    var sWrap = document.createElement('div'); sWrap.className = 'serials'; sWrap.appendChild(serials);

    row.appendChild(p1); row.appendChild(p2); row.appendChild(p3); row.appendChild(p4); row.appendChild(sWrap);
    rows.appendChild(row);

    sel.addEventListener('change', function () {
      var opt = sel.selectedOptions[0];
      var serialized = opt && opt.getAttribute('data-serialized') === '1';
      serials.style.display = serialized ? 'block' : 'none';
      if (opt && !cost.value) { cost.value = opt.getAttribute('data-cost'); }
      syncQtyFromSerials();
    });

    serials.addEventListener('input', function () {
      if (serials.style.display !== 'none') { syncQtyFromSerials(); }
    });
    [qty, cost].forEach(function (el) { el.addEventListener('input', updateTotal); });
  }

  function syncQtyFromSerials() {
    var rows = document.querySelectorAll('#grn-rows .grn-row');
    rows.forEach(function (row) {
      var sel = row.querySelector('select');
      var ta = row.querySelector('textarea');
      var qty = row.querySelector('input[name="qty[]"]');
      var opt = sel.selectedOptions[0];
      if (opt && opt.getAttribute('data-serialized') === '1') {
        var n = ta.value.split(/\r?\n/).map(function (s) { return s.trim(); }).filter(Boolean).length;
        if (n > 0) { qty.value = n; }
      }
    });
    updateTotal();
  }

  function updateTotal() {
    var total = 0;
    document.querySelectorAll('#grn-rows .grn-row').forEach(function (row) {
      var q = parseFloat(row.querySelector('input[name="qty[]"]').value) || 0;
      var c = parseFloat(row.querySelector('input[name="cost[]"]').value) || 0;
      total += q * c;
    });
    document.getElementById('grn-total').textContent = money(total);
  }

  document.getElementById('add-row').addEventListener('click', addRow);
  addRow();
})();
</script>
