<?php
include APP_PATH . '/views/partials/inventory-tabs.php';
$f = $filters;
$hasFilters = $f['q'] !== '' || $f['category'] !== '' || $f['tracking'] !== '' || $f['status'] !== '' || $f['stock'] !== '';
$filterUrl = function (array $changes) use ($f): string { return url('products') . query_string(array_merge($f, $changes)); };
?>
<div class="page-head inventory-page-head">
    <div><div class="section-kicker">Stock control</div><h1>Inventory</h1><p class="lede">Search the catalogue, monitor stock risk, and manage individually tracked units.</p></div>
    <?php if (Auth::isAdmin()): ?><div class="page-actions"><a class="btn btn-outline" href="<?= e(url('grn/new')) ?>">Receive stock</a><a class="btn btn-primary" href="<?= e(url('products/new')) ?>">Add product</a><details class="action-menu"><summary class="btn btn-ghost">More</summary><div><a href="<?= e(url('products/export')) ?>">Export CSV</a><a href="<?= e(url('categories')) ?>">Manage categories</a><a href="<?= e(url('brands')) ?>">Manage brands</a></div></details></div><?php endif; ?>
</div>

<section class="inventory-kpis" aria-label="Inventory summary">
    <a href="<?= e(url('products')) ?>"><span>Catalogue</span><b><?= number_format($summary['total']) ?></b><small><?= number_format($summary['active']) ?> active products</small></a>
    <a href="<?= e($filterUrl(['stock'=>'low'])) ?>"><span>Low stock</span><b><?= number_format($summary['low']) ?></b><small>At or below reorder level</small></a>
    <a href="<?= e($filterUrl(['stock'=>'out'])) ?>"><span>Out of stock</span><b><?= number_format($summary['out']) ?></b><small>Unavailable for sale</small></a>
    <?php if (Auth::isAdmin()): ?><div><span>Stock value</span><b><?= money($summary['value']) ?></b><small>At recorded cost</small></div><?php endif; ?>
</section>

<details class="inventory-filter-panel" open>
    <summary><span>Search and filters<?= $hasFilters ? ' · active' : '' ?></span><span aria-hidden="true">⌄</span></summary>
    <form method="get" action="<?= e(url('products')) ?>" class="inventory-filter-form">
        <label class="inventory-filter-search" for="inventory-q"><span>Search products</span><input id="inventory-q" type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Name, SKU or barcode"></label>
        <label for="inventory-category"><span>Category</span><select id="inventory-category" name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (string) $category['id'] === $f['category'] ? 'selected' : '' ?>><?= e($category['name']) ?></option><?php endforeach; ?></select></label>
        <label for="inventory-stock"><span>Stock</span><select id="inventory-stock" name="stock"><option value="">Any stock level</option><option value="available" <?= $f['stock']==='available'?'selected':'' ?>>Available</option><option value="low" <?= $f['stock']==='low'?'selected':'' ?>>Low stock</option><option value="out" <?= $f['stock']==='out'?'selected':'' ?>>Out of stock</option></select></label>
        <label for="inventory-tracking"><span>Tracking</span><select id="inventory-tracking" name="tracking"><option value="">Any tracking</option><option value="serialized" <?= $f['tracking']==='serialized'?'selected':'' ?>>Serial / IMEI</option><option value="quantity" <?= $f['tracking']==='quantity'?'selected':'' ?>>Quantity</option></select></label>
        <label for="inventory-status"><span>Catalogue status</span><select id="inventory-status" name="status"><option value="">Active and inactive</option><option value="active" <?= $f['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $f['status']==='inactive'?'selected':'' ?>>Inactive</option></select></label>
        <div class="inventory-filter-actions"><button class="btn btn-primary" type="submit">Show products</button><?php if ($hasFilters): ?><a class="btn btn-ghost" href="<?= e(url('products')) ?>">Clear all</a><?php endif; ?></div>
    </form>
</details>

<?php if ($hasFilters): ?><div class="sales-active-filters inventory-active-filters" aria-label="Active inventory filters"><span>Active filters</span>
    <?php if ($f['q'] !== ''): ?><a href="<?= e($filterUrl(['q'=>''])) ?>">Search: “<?= e($f['q']) ?>” <b>×</b></a><?php endif; ?>
    <?php if ($f['category'] !== ''): $catName='Category'; foreach($categories as $c){if((string)$c['id']===$f['category']){$catName=$c['name'];break;}} ?><a href="<?= e($filterUrl(['category'=>''])) ?>"><?= e($catName) ?> <b>×</b></a><?php endif; ?>
    <?php if ($f['stock'] !== ''): ?><a href="<?= e($filterUrl(['stock'=>''])) ?>"><?= e(ucwords(str_replace('_',' ',$f['stock']))) ?> <b>×</b></a><?php endif; ?>
    <?php if ($f['tracking'] !== ''): ?><a href="<?= e($filterUrl(['tracking'=>''])) ?>"><?= e($f['tracking']==='serialized'?'Serial / IMEI':'Quantity') ?> <b>×</b></a><?php endif; ?>
    <?php if ($f['status'] !== ''): ?><a href="<?= e($filterUrl(['status'=>''])) ?>"><?= e(ucfirst($f['status'])) ?> <b>×</b></a><?php endif; ?>
</div><?php endif; ?>

<div class="inventory-results-head"><div><h2><?= $hasFilters ? 'Matching products' : 'Product catalogue' ?></h2><p><?= count($products) ?> result<?= count($products)===1?'':'s' ?></p></div></div>
<?php if (!$products): ?>
    <div class="empty-state inventory-empty"><h2>No products match these filters</h2><p class="muted">Try a different SKU, barcode, category, or stock level.</p><a class="btn btn-primary" href="<?= e(url('products')) ?>">Show all products</a></div>
<?php else: ?>
<div class="table-wrap inventory-table-wrap"><table class="table inventory-table">
    <thead><tr><th>Product</th><th>Category / brand</th><th>Tracking</th><th class="num">In stock</th><th class="num">Sell excl. VAT</th><th>Status</th><th><span class="sr-only">Action</span></th></tr></thead>
    <tbody><?php foreach ($products as $p): $stock=(int)($p['live_stock']??Product::stockOf($p)); $risk=(int)$p['reorder_level']>0&&$stock<=(int)$p['reorder_level']; ?>
        <tr>
            <td><div class="inventory-product-cell"><?= product_thumb($p, 44) ?><div><a class="cell-main" href="<?= e(url('products/'.$p['id'])) ?>"><?= e($p['name']) ?></a><span class="cell-sub"><?= e($p['sku']) ?><?= $p['barcode']?' · '.e($p['barcode']):'' ?></span></div></div></td>
            <td><div class="cell-main"><?= e($p['category_name']??'Uncategorised') ?></div><div class="cell-sub"><?= e($p['brand_name']??'No brand') ?></div></td>
            <td><span class="badge badge-<?= $p['is_serialized']?'blue':'gray' ?>"><?= $p['is_serialized']?'Serial / IMEI':'Quantity' ?></span></td>
            <td class="num"><strong class="<?= $risk?'stock-risk':'' ?>"><?= $stock ?></strong><?php if($risk): ?><span class="cell-sub">Reorder at <?= (int)$p['reorder_level'] ?></span><?php endif; ?></td>
            <td class="num"><strong><?= money($p['sell_price']) ?></strong><span class="cell-sub"><?= money(gross_of($p['sell_price'])) ?> incl.</span></td>
            <td><?= $p['is_active']?'<span class="badge badge-green">Active</span>':'<span class="badge badge-gray">Inactive</span>' ?></td>
            <td><a class="btn btn-outline btn-sm" href="<?= e(url('products/'.$p['id'])) ?>">Open</a></td>
        </tr>
    <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
<script>if(matchMedia('(max-width:700px)').matches){var p=document.querySelector('.inventory-filter-panel');if(p)p.removeAttribute('open');}</script>
