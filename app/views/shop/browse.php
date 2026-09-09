<div class="shop-section">
    <div class="shop-section-head">
        <div>
            <h1 style="font-size:34px"><?= $cat !== '' ? e(ucwords(str_replace('-', ' ', $cat))) : 'All products' ?></h1>
            <p class="muted"><?= count($products) ?> product(s) · prices include VAT</p>
        </div>
    </div>

    <div class="chips">
        <a class="chip <?= $cat === '' ? 'active' : '' ?>" href="<?= e(url('shop/products')) ?>">All</a>
        <?php foreach ($categories as $c): ?>
            <a class="chip <?= $cat === $c['slug'] ? 'active' : '' ?>"
               href="<?= e(url('shop/products?category=' . urlencode($c['slug']))) ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
    </div>

    <div class="shop-filters">
        <form method="get" action="<?= e(url('shop/products')) ?>" style="display:flex;gap:10px;flex-wrap:wrap;flex:1">
            <?php if ($cat !== ''): ?><input type="hidden" name="category" value="<?= e($cat) ?>"><?php endif; ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products…" style="min-width:200px">
            <select name="brand">
                <option value="">All brands</option>
                <?php foreach ($brands as $b): ?>
                    <option value="<?= e($b['name']) ?>" <?= $brand === $b['name'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="sort">
                <option value="name"       <?= $sort === 'name' ? 'selected' : '' ?>>Name A–Z</option>
                <option value="price_asc"  <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
                <option value="newest"     <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
            </select>
            <button class="btn btn-primary btn-sm" type="submit">Apply</button>
            <?php if ($q !== '' || $brand !== '' || $sort !== 'name'): ?>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('shop/products' . ($cat !== '' ? '?category=' . urlencode($cat) : ''))) ?>">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (!$products): ?>
        <div class="empty-state">
            <h2>No products found</h2>
            <p class="muted">Try a different search or category.</p>
            <a class="btn btn-primary" href="<?= e(url('shop/products')) ?>">Browse everything</a>
        </div>
    <?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $p): $stock = Product::stockOf($p); ?>
            <div class="product-card">
                <a class="thumb" href="<?= e(url('shop/product/' . $p['id'])) ?>"><?= product_thumb($p, 320) ?></a>
                <div>
                    <div class="pcat"><?= e($p['brand_name'] ?? $p['category_name'] ?? '') ?></div>
                    <a class="pname" href="<?= e(url('shop/product/' . $p['id'])) ?>"><?= e($p['name']) ?></a>
                </div>
                <div class="price-row">
                    <span class="price"><?= money(gross_of($p['sell_price'])) ?></span>
                    <span class="price-ex">excl. VAT <?= money($p['sell_price']) ?></span>
                </div>
                <div>
                    <?php if ($stock > 0): ?>
                        <span class="stock-badge badge badge-green">In stock<?= $stock <= 3 ? ' · only ' . $stock . ' left' : '' ?></span>
                    <?php else: ?>
                        <span class="stock-badge badge badge-red">Out of stock</span>
                    <?php endif; ?>
                </div>
                <?php if ($stock > 0): ?>
                    <button class="btn btn-primary add-btn" data-add="<?= (int) $p['id'] ?>" data-stock="<?= $stock ?>">Add to cart</button>
                <?php else: ?>
                    <button class="btn btn-ghost add-btn" disabled>Out of stock</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
