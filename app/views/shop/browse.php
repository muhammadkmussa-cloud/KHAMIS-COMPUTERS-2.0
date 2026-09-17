<?php
$checkoutEnabled = $checkoutEnabled ?? is_online_checkout_enabled();
$whatsappEnabled = $whatsappEnabled ?? is_whatsapp_ordering_enabled();
?>
<div class="shop-section">
    <div class="shop-section-head browse-head">
        <div>
            <div class="section-kicker">Shop catalogue</div>
            <h1><?= $cat !== '' ? e(ucwords(str_replace('-', ' ', $cat))) : 'All products' ?></h1>
            <p class="muted"><b><?= count($products) ?></b> <?= count($products) === 1 ? 'product' : 'products' ?> available · prices include VAT<?= !$checkoutEnabled ? ' · WhatsApp ordering' : '' ?></p>
        </div>
    </div>

    <div class="chips">
        <a class="chip <?= $cat === '' ? 'active' : '' ?>" href="<?= e(url('shop/products')) ?>">All</a>
        <?php foreach ($categories as $c): ?>
            <a class="chip <?= $cat === $c['slug'] ? 'active' : '' ?>"
               href="<?= e(url('shop/products?category=' . urlencode($c['slug']))) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <details class="shop-filters" open>
        <summary>Search and filter <span aria-hidden="true">⌄</span></summary>
        <form method="get" action="<?= e(url('shop/products')) ?>" class="filter-form">
            <?php if ($cat !== ''): ?><input type="hidden" name="category" value="<?= e($cat) ?>"><?php endif; ?>
            <label class="filter-search"><span>Search products</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Name or SKU"></label>
            <label><span>Brand</span><select name="brand">
                <option value="">All brands</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?= e($b['name']) ?>" <?= $brand === $b['name'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label><span>Sort by</span><select name="sort">
                <option value="name"       <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
                <option value="price_asc"  <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
                <option value="newest"     <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
            </select></label>
            <button class="btn btn-primary" type="submit">Show results</button>
            <?php if ($q !== '' || $brand !== '' || $sort !== 'name'): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('shop/products' . ($cat !== '' ? '?category=' . urlencode($cat) : ''))) ?>">Reset</a>
            <?php endif; ?>
        </form>
    </details>

    <?php if ($q !== '' || $brand !== '' || $sort !== 'name' || $cat !== ''): ?>
        <div class="active-filters" aria-label="Active filters">
            <span>Active:</span>
            <?php if ($q !== ''): ?><span class="active-filter">Search “<?= e($q) ?>”</span><?php endif; ?>
            <?php if ($cat !== ''): ?><span class="active-filter"><?= e(ucwords(str_replace('-', ' ', $cat))) ?></span><?php endif; ?>
            <?php if ($brand !== ''): ?><span class="active-filter"><?= e($brand) ?></span><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (!$products): ?>
        <div class="empty-state">
            <h2>No products found</h2>
            <p class="muted">Check the spelling, clear a filter, or browse the full catalogue.</p>
            <a class="btn btn-primary" href="<?= e(url('shop/products')) ?>">Browse everything</a>
        </div>
    <?php else: ?>
    <div class="product-grid" data-product-grid data-page-size="12">
        <?php foreach ($products as $p): $stock = Product::stockOf($p); ?>
            <?php include APP_PATH . '/views/shop/_product-card.php'; ?>
        <?php endforeach; ?>
    </div>
    <?php if (count($products) > 12): ?>
        <div class="load-more-wrap"><button class="btn btn-outline" type="button" data-load-more>Show more products</button></div>
    <?php endif; ?>
    <?php endif; ?>
</div>
