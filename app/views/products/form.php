<?php $editing = $product !== null; ?>
<div class="content-narrow">
    <div class="page-head">
        <div>
            <h2><?= $editing ? 'Edit product' : 'Add product' ?></h2>
            <p class="lede"><?= $editing ? e($product['name']) : 'Add a new item to your catalogue.' ?></p>
        </div>
        <a class="btn btn-ghost" href="<?= e(url($editing ? 'products/' . $product['id'] : 'products')) ?>">← Back</a>
    </div>

    <div class="card">
        <form method="post"
              action="<?= e(url($editing ? 'products/' . $product['id'] : 'products')) ?>"
              class="form">
            <?= csrf_field() ?>

            <div class="field">
                <span>Product name</span>
                <input type="text" name="name" required
                       value="<?= e($editing ? $product['name'] : old('name')) ?>"
                       placeholder="e.g. HP ProBook 450 G10">
            </div>

            <div class="form-row">
                <div class="field">
                    <span>SKU</span>
                    <input type="text" name="sku" required
                           value="<?= e($editing ? $product['sku'] : old('sku')) ?>"
                           placeholder="KC-LAP-001">
                </div>
                <div class="field">
                    <span>Barcode</span>
                    <div style="display:flex;gap:6px">
                        <input type="text" name="barcode" id="fld-barcode" style="flex:1"
                               value="<?= e($editing ? (string) $product['barcode'] : old('barcode')) ?>"
                               placeholder="optional — or click Generate">
                        <button type="button" id="gen-barcode" class="btn btn-outline btn-sm" style="white-space:nowrap">Generate</button>
                    </div>
                    <span class="hint">Printed on the shelf label as a Code 128 barcode.</span>
                </div>
            </div>

            <div class="form-row">
                <div class="field">
                    <span>Category</span>
                    <select name="category_id">
                        <option value="">— none —</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"
                                <?= ($editing && (int) $product['category_id'] === (int) $c['id']) ? 'selected' : '' ?>>
                                <?= e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <span>Brand</span>
                    <select name="brand_id">
                        <option value="">— none —</option>
                        <?php foreach ($brands as $b): ?>
                            <option value="<?= (int) $b['id'] ?>"
                                <?= ($editing && (int) $product['brand_id'] === (int) $b['id']) ? 'selected' : '' ?>>
                                <?= e($b['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row-3">
                <div class="field">
                    <span>Cost price (KSh)</span>
                    <input type="number" step="0.01" min="0" name="cost_price" required
                           value="<?= e($editing ? $product['cost_price'] : old('cost_price')) ?>">
                </div>
                <div class="field">
                    <span>Selling price (KSh, excl. VAT)</span>
                    <input type="number" step="0.01" min="0" name="sell_price" required
                           value="<?= e($editing ? $product['sell_price'] : old('sell_price')) ?>">
                    <span class="hint">Customer pays <?= money(gross_of($editing ? $product['sell_price'] : 0)) ?> incl. VAT</span>
                </div>
                <div class="field">
                    <span>Reorder level</span>
                    <input type="number" step="1" min="0" name="reorder_level"
                           value="<?= e($editing ? $product['reorder_level'] : old('reorder_level', '0')) ?>">
                </div>
            </div>

            <div class="field">
                <span>Description</span>
                <textarea name="description" rows="3"
                          placeholder="Specs, notes…"><?= e($editing ? (string) $product['description'] : old('description')) ?></textarea>
            </div>

            <div class="form-row">
                <div class="field">
                    <span>Warranty (months)</span>
                    <input type="number" step="1" min="0" name="warranty_months"
                           value="<?= e($editing ? (string) ($product['warranty_months'] ?? '') : old('warranty_months')) ?>"
                           placeholder="e.g. 12">
                    <span class="hint">Optional. New serial/IMEI units get an expiry date (received date + this many months).</span>
                </div>
                <div class="field">
                    <span>&nbsp;</span>
                    <span class="hint">Warranty applies per unit for serialized products. Leave blank for no warranty.</span>
                </div>
            </div>

            <div class="form-row">
                <label class="field field-check">
                    <input type="checkbox" name="is_serialized" value="1"
                        <?= ($editing && (int) $product['is_serialized'] === 1) || (!$editing && old('is_serialized')) ? 'checked' : '' ?>>
                    <span>Track by serial / IMEI (each unit is individually tracked)</span>
                </label>
                <label class="field field-check">
                    <input type="checkbox" name="is_active" value="1"
                        <?= !$editing || (int) $product['is_active'] === 1 ? 'checked' : '' ?>>
                    <span>Active (visible in shop)</span>
                </label>
            </div>

            <div class="form-actions" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
                <label class="field field-check" style="margin:0">
                    <input type="checkbox" name="print_labels" value="1">
                    <span>Print labels right after saving</span>
                </label>
                <div style="display:flex;gap:8px">
                    <button class="btn btn-primary" type="submit"><?= $editing ? 'Save changes' : 'Create product' ?></button>
                    <a class="btn btn-ghost" href="<?= e(url($editing ? 'products/' . $product['id'] : 'products')) ?>">Cancel</a>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
  var btn = document.getElementById('gen-barcode');
  var fld = document.getElementById('fld-barcode');
  if (!btn || !fld) { return; }
  btn.addEventListener('click', function () {
    btn.disabled = true;
    btn.textContent = '…';
    fetch(<?= json_encode(url('products/barcode/generate')) ?>)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok) { fld.value = d.barcode; }
        else { alert(d.error || 'Could not generate a barcode.'); }
      })
      .catch(function () { alert('Could not generate a barcode.'); })
      .finally(function () { btn.disabled = false; btn.textContent = 'Generate'; });
  });
})();
</script>
