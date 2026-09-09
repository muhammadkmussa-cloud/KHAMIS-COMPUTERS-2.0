<?php $serialized = (int) $product['is_serialized'] === 1; ?>

<div class="page-head">
    <div>
        <div class="section-kicker"><?= e($product['category_name'] ?? 'Uncategorised') ?><?= $product['brand_name'] ? ' · ' . e($product['brand_name']) : '' ?></div>
        <h1><?= e($product['name']) ?></h1>
        <p class="lede">
            SKU <?= e($product['sku']) ?>
            <?= $product['barcode'] ? ' · Barcode ' . e($product['barcode']) : '' ?>
            &nbsp;·&nbsp; <?= $serialized ? '<span class="badge badge-blue">serial / IMEI tracked</span>' : '<span class="badge badge-gray">quantity tracked</span>' ?>
            &nbsp;·&nbsp; <?= $product['is_active'] ? '<span class="badge badge-green">active</span>' : '<span class="badge badge-gray">inactive</span>' ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('products')) ?>">← Inventory</a>
        <?php if (Auth::isAdmin()): ?>
            <a class="btn btn-outline" href="<?= e(url('products/' . $product['id'] . '/labels?autoprint=1')) ?>">Print labels</a>
            <?php if ($serialized): ?>
                <a class="btn btn-outline" href="<?= e(url('products/' . $product['id'] . '/labels?kind=serials&autoprint=1')) ?>">Serial labels</a>
            <?php endif; ?>
            <a class="btn btn-outline" href="<?= e(url('products/' . $product['id'] . '/edit')) ?>">Edit</a>
        <?php endif; ?>
    </div>
</div>

<div class="stat-strip">
    <div class="stat"><b><?= $stock ?></b><span>in stock</span></div>
    <div class="stat"><b><?= money($product['sell_price']) ?></b><span>sell (excl. VAT)</span></div>
    <div class="stat"><b><?= money(gross_of($product['sell_price'])) ?></b><span>sell (incl. VAT)</span></div>
    <div class="stat"><b><?= money($product['cost_price']) ?></b><span>cost price</span></div>
    <div class="stat"><b><?= money($stock * (float) $product['cost_price']) ?></b><span>stock value</span></div>
</div>

<?php if ($product['description']): ?>
<div class="card" style="margin-bottom:18px">
    <p class="muted" style="margin:0"><?= nl2br(e($product['description'])) ?></p>
</div>
<?php endif; ?>

<?php if (Auth::isAdmin()): ?>
<div class="card" style="margin-bottom:18px">
    <h3>Images <span class="badge badge-gray"><?= count($images) ?></span></h3>
    <p class="sub">Shown on the online shop. The first image is used on cards and lists.</p>

    <div style="display:flex;flex-wrap:wrap;gap:12px;margin:12px 0">
        <?php if (!$images): ?>
            <p class="muted" style="margin:0">No images yet — upload some below.</p>
        <?php endif; ?>
        <?php foreach ($images as $im): ?>
            <div style="border:1px solid var(--border);border-radius:14px;padding:8px;text-align:center">
                <img src="<?= e(url('uploads/p/' . rawurlencode($im['filename']))) ?>" alt=""
                     style="width:120px;height:120px;object-fit:cover;border-radius:10px;display:block">
                <div style="display:flex;gap:6px;justify-content:center;margin-top:8px">
                    <?php if ($product['image'] === $im['filename']): ?>
                        <span class="badge badge-blue">primary</span>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('products/' . $product['id'] . '/images/' . $im['id'] . '/primary')) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button class="btn btn-outline btn-sm" type="submit">Make primary</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('products/' . $product['id'] . '/images/' . $im['id'] . '/delete')) ?>"
                          onsubmit="return confirm('Remove this image?');" style="display:inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-danger-ghost btn-sm" type="submit">Remove</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <form method="post" action="<?= e(url('products/' . $product['id'] . '/images')) ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple
                   style="font-size:14px;padding:8px;border:1px solid var(--border);border-radius:10px">
            <button class="btn btn-primary btn-sm" type="submit">Upload images</button>
            <span class="hint">JPG, PNG or WebP · up to 3 MB each · multiple allowed</span>
        </div>
    </form>
</div>
<?php endif; ?>

<?php if ($serialized): ?>
<div class="card" style="margin-bottom:18px">
    <h3>Serial numbers <span class="badge badge-gray"><?= count($units) ?></span></h3>
    <p class="sub">Each unit is tracked individually for warranty and returns.</p>

    <table class="table" style="margin-top:10px">
        <thead><tr><th>Serial / IMEI</th><th>Status</th><th>Received</th><th>Warranty until</th><th>Note</th><th></th></tr></thead>
        <tbody>
        <?php if (!$units): ?>
            <tr><td colspan="6" class="muted" style="padding:22px;text-align:center">No units recorded yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($units as $u): ?>
            <?php
                $wexp = $u['warranty_expires'] ?? null;
                $expired = $wexp && $wexp < date('Y-m-d');
            ?>
            <tr>
                <td><span class="unit-chip"><?= e($u['serial_number']) ?></span></td>
                <td><span class="unit-status st-<?= e($u['status']) ?>"></span> <?= e(str_replace('_', ' ', $u['status'])) ?></td>
                <td class="cell-sub"><?= e(substr($u['received_at'], 0, 10)) ?></td>
                <td class="cell-sub" style="<?= $expired ? 'color:var(--red);font-weight:600' : '' ?>">
                    <?= $wexp ? e($wexp) . ($expired ? ' (expired)' : '') : '—' ?>
                </td>
                <td class="cell-sub"><?= e($u['note'] ?? '') ?></td>
                <td>
                    <?php if (Auth::isAdmin()): ?>
                    <form method="post" action="<?= e(url('products/' . $product['id'] . '/units/' . $u['id'] . '/status')) ?>" style="display:flex;gap:6px;align-items:center;justify-content:flex-end">
                        <?= csrf_field() ?>
                        <select name="status" style="font-size:12px;padding:5px 8px;border-radius:8px;border:1px solid var(--border)">
                            <?php foreach (['in_stock','reserved','sold','returned','damaged','missing'] as $s): ?>
                                <option value="<?= $s ?>" <?= $u['status'] === $s ? 'selected' : '' ?>><?= str_replace('_', ' ', $s) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="warranty_expires" value="<?= e($wexp ?? '') ?>" title="Warranty expiry" style="font-size:12px;padding:5px 8px;border-radius:8px;border:1px solid var(--border)">
                        <input type="text" name="note" placeholder="note" value="<?= e($u['note'] ?? '') ?>" style="font-size:12px;padding:5px 8px;border-radius:8px;border:1px solid var(--border);width:120px">
                        <button class="btn btn-outline btn-sm" type="submit">Save</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (Auth::isAdmin()): ?>
    <hr>
    <div class="form-row">
        <form method="post" action="<?= e(url('products/' . $product['id'] . '/units')) ?>" class="field" style="flex:1">
            <?= csrf_field() ?>
            <span>Add serial numbers (one per line — duplicates are skipped)</span>
            <textarea name="serials" rows="3" placeholder="SN1234567890&#10;SN1234567891"></textarea>
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                <button class="btn btn-primary btn-sm" type="submit" name="action" value="add">Add units</button>
                <span class="muted" style="font-size:12px">or auto-generate</span>
                <input type="number" name="auto_count" min="1" max="200" value="10" style="width:72px" title="How many serials to generate">
                <button class="btn btn-outline btn-sm" type="submit" name="action" value="generate">Generate serials</button>
                <label class="field field-check" style="margin:0 0 0 auto">
                    <input type="checkbox" name="print_labels" value="1">
                    <span>Print serial labels after adding</span>
                </label>
            </div>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="grid-2" style="margin-top:0">
    <?php if (Auth::isAdmin()): ?>
    <div class="card">
        <h3>Adjust stock</h3>
        <p class="sub">Positive adds stock, negative removes it.</p>
        <form method="post" action="<?= e(url('products/' . $product['id'] . '/adjust')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="form-row">
                <div class="field">
                    <span>Adjustment (+/−)</span>
                    <input type="number" name="delta" step="1" required placeholder="e.g. +5 or -2">
                </div>
                <div class="field">
                    <span>Reason</span>
                    <input type="text" name="reason" placeholder="e.g. stock count">
                </div>
            </div>
            <div><button class="btn btn-primary btn-sm" type="submit">Apply adjustment</button></div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h3>Stock movements</h3>
        <table class="table">
            <thead><tr><th>Type</th><th class="num">Qty</th><th>When</th><th>Ref</th></tr></thead>
            <tbody>
            <?php if (!$movements): ?>
                <tr><td colspan="4" class="muted" style="text-align:center;padding:18px">No movements yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($movements as $m): ?>
                <tr>
                    <td><?= e(str_replace('_', ' ', $m['type'])) ?></td>
                    <td class="num" style="<?= $m['quantity'] < 0 ? 'color:var(--red)' : 'color:var(--green)' ?>">
                        <?= $m['quantity'] > 0 ? '+' : '' ?><?= (int) $m['quantity'] ?>
                    </td>
                    <td class="cell-sub"><?= e(substr($m['created_at'], 0, 16)) ?></td>
                    <td class="cell-sub"><?= e($m['reference'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!$serialized): ?>
<div class="card" style="margin-top:18px">
    <h3>Stock movements</h3>
    <table class="table">
        <thead><tr><th>Type</th><th class="num">Qty</th><th>When</th><th>Ref</th><th>By</th></tr></thead>
        <tbody>
        <?php if (!$movements): ?>
            <tr><td colspan="5" class="muted" style="text-align:center;padding:18px">No movements yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($movements as $m): ?>
            <tr>
                <td><?= e(str_replace('_', ' ', $m['type'])) ?></td>
                <td class="num" style="<?= $m['quantity'] < 0 ? 'color:var(--red)' : 'color:var(--green)' ?>">
                    <?= $m['quantity'] > 0 ? '+' : '' ?><?= (int) $m['quantity'] ?>
                </td>
                <td class="cell-sub"><?= e(substr($m['created_at'], 0, 16)) ?></td>
                <td class="cell-sub"><?= e($m['reference'] ?? '') ?></td>
                <td class="cell-sub"><?= e($m['user_name'] ?? '—') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
