<div class="page-head">
    <div>
        <h1>Inventory</h1>
        <p class="lede">Products, stock levels and serial-number tracking.</p>
    </div>
    <div class="page-actions">
        <?php if (Auth::isAdmin()): ?>
            <a class="btn btn-ghost" href="<?= e(url('categories')) ?>">Categories</a>
            <a class="btn btn-ghost" href="<?= e(url('brands')) ?>">Brands</a>
            <a class="btn btn-ghost" href="<?= e(url('suppliers')) ?>">Suppliers</a>
            <a class="btn btn-outline" href="<?= e(url('grn/new')) ?>">Receive stock</a>
            <a class="btn btn-primary" href="<?= e(url('products/new')) ?>">+ Add product</a>
        <?php endif; ?>
        <a class="btn btn-outline" href="<?= e(url('products/export')) ?>">Export CSV</a>
    </div>
</div>

<?php if ($lowStock): ?>
<div class="card" style="margin-bottom:18px;border-left:4px solid var(--orange)">
    <div class="section-kicker" style="color:var(--orange)">Attention</div>
    <h3>Low stock</h3>
    <p class="sub">These products are at or below their reorder level.</p>
    <table class="table" style="margin-top:8px">
        <thead><tr><th>Product</th><th>SKU</th><th class="num">In stock</th><th class="num">Reorder at</th></tr></thead>
        <tbody>
        <?php foreach ($lowStock as $p): ?>
            <tr>
                <td class="cell-main"><a href="<?= e(url('products/' . $p['id'])) ?>"><?= e($p['name']) ?></a></td>
                <td class="cell-sub"><?= e($p['sku']) ?></td>
                <td class="num" style="color:var(--orange);font-weight:700"><?= (int) Product::stockOf($p) ?></td>
                <td class="num"><?= (int) $p['reorder_level'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="toolbar">
    <form method="get" action="<?= e(url('products')) ?>" class="search" style="display:flex;gap:8px;max-width:none;flex:1">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name, SKU or barcode…" style="max-width:420px">
        <button class="btn btn-primary btn-sm" type="submit">Search</button>
        <?php if ($q !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('products')) ?>">Clear</a><?php endif; ?>
    </form>
    <span class="muted" style="font-size:13px"><?= count($products) ?> product(s)</span>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Category / Brand</th>
                <th class="num">Stock</th>
                <th class="num">Sell (excl. VAT)</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$products): ?>
            <tr><td colspan="6" class="muted" style="padding:26px;text-align:center">No products found.</td></tr>
        <?php endif; ?>
        <?php foreach ($products as $p):
            $stock = Product::stockOf($p); ?>
            <tr>
                <td>
                    <div class="cell-main"><a href="<?= e(url('products/' . $p['id'])) ?>"><?= e($p['name']) ?></a></div>
                    <div class="cell-sub"><?= e($p['sku']) ?><?= $p['is_serialized'] ? ' · <span class="badge badge-blue">serialized</span>' : '' ?></div>
                </td>
                <td class="cell-sub"><?= e($p['category_name'] ?? '—') ?> · <?= e($p['brand_name'] ?? '—') ?></td>
                <td class="num" style="font-weight:700;<?= $p['reorder_level'] > 0 && $stock <= (int) $p['reorder_level'] ? 'color:var(--orange)' : '' ?>">
                    <?= $stock ?>
                </td>
                <td class="num"><?= money($p['sell_price']) ?></td>
                <td><?= $p['is_active'] ? '<span class="badge badge-green">Active</span>' : '<span class="badge badge-gray">Inactive</span>' ?></td>
                <td>
                    <div class="row-actions">
                        <?php if (Auth::isAdmin()): ?>
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('products/' . $p['id'] . '/edit')) ?>">Edit</a>
                        <?php endif; ?>
                        <a class="btn btn-primary btn-sm" href="<?= e(url('products/' . $p['id'])) ?>">Open</a>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
