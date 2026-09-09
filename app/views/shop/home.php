<div class="shop-hero">
    <h1><?= e($shopName) ?></h1>
    <p class="sub"><?= e(Setting::get('shop_tagline', 'The latest laptops, phones and accessories — order online, pick up in store, or get it delivered in Nairobi.')) ?></p>
    <div class="ctas">
        <a class="btn btn-primary btn-lg" href="<?= e(url('shop/products')) ?>">Shop all products</a>
        <a class="btn btn-outline btn-lg" href="<?= e(url('shop/products?category=laptops')) ?>">Shop laptops</a>
    </div>
</div>

<?php if ($featured): ?>
<div class="shop-section">
    <div class="shop-section-head">
        <h2>New arrivals</h2>
        <a href="<?= e(url('shop/products')) ?>">See all →</a>
    </div>
    <div class="product-grid">
        <?php foreach ($featured as $p): $stock = Product::stockOf($p); ?>
            <div class="product-card">
                <a class="thumb" href="<?= e(url('shop/product/' . $p['id'])) ?>"><?= product_thumb($p, 320) ?></a>
                <div>
                    <div class="pcat"><?= e($p['category_name'] ?? '') ?></div>
                    <a class="pname" href="<?= e(url('shop/product/' . $p['id'])) ?>"><?= e($p['name']) ?></a>
                </div>
                <div class="price-row">
                    <span class="price"><?= money(gross_of($p['sell_price'])) ?></span>
                    <span class="price-ex">excl. VAT <?= money($p['sell_price']) ?></span>
                </div>
                <?php if ($stock > 0): ?>
                    <button class="btn btn-primary add-btn" data-add="<?= (int) $p['id'] ?>" data-stock="<?= $stock ?>">Add to cart</button>
                <?php else: ?>
                    <button class="btn btn-ghost add-btn" disabled>Out of stock</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($categories): ?>
<div class="shop-section">
    <div class="shop-section-head"><h2>Browse by category</h2></div>
    <div class="category-grid">
        <?php foreach ($categories as $c): if ((int) $c['product_count'] === 0) continue; ?>
            <a class="category-card" href="<?= e(url('shop/products?category=' . urlencode($c['slug']))) ?>">
                <h3><?= e($c['name']) ?></h3>
                <span class="count"><?= (int) $c['product_count'] ?> product<?= (int) $c['product_count'] === 1 ? '' : 's' ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<div class="promo">
    <div class="promo-inner">
        <h3>Order online, pick up in store.</h3>
        <p>Buy on the web and collect from our Nairobi shop — or choose delivery at checkout. Every online order is connected to our in-store stock, so what you see is what we have.</p>
        <a class="btn" href="<?= e(url('shop/products')) ?>">Start shopping</a>
    </div>
</div>
