<section class="shop-hero">
    <div class="hero-copy">
        <div class="section-kicker">Nairobi technology store</div>
        <h1>Technology you can buy with confidence.</h1>
        <p class="sub"><?= e(Setting::get('shop_tagline', 'Laptops, phones and accessories with verified stock, clear pricing and local support.')) ?></p>
        <div class="ctas">
            <a class="btn btn-primary btn-lg" href="<?= e(url('shop/products')) ?>">Browse products</a>
            <a class="btn btn-outline btn-lg" href="<?= e(url('shop/track')) ?>">Track an order</a>
        </div>
        <div class="hero-trust" aria-label="Store benefits">
            <span><b>Live stock</b> from our store</span>
            <span><b>VAT included</b> in every price</span>
            <span><b>Local support</b> after purchase</span>
        </div>
    </div>
    <div class="hero-showcase" aria-label="Featured technology categories">
        <?php foreach (array_slice($featured, 0, 3) as $index => $p): ?>
            <a class="hero-product hero-product-<?= $index + 1 ?>" href="<?= e(url('shop/product/' . $p['id'])) ?>">
                <?= product_thumb($p, 420) ?>
                <span><?= e($p['name']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<div class="service-strip">
    <div><span class="service-icon">⌁</span><b>Pickup in Nairobi</b><small>Reserve online, collect in store</small></div>
    <div><span class="service-icon">↗</span><b>Delivery available</b><small>Choose your area at checkout</small></div>
    <div><span class="service-icon">✓</span><b>Warranty recorded</b><small>Serials kept with your receipt</small></div>
</div>

<?php if ($featured): ?>
<div class="shop-section">
    <div class="shop-section-head">
        <h2>New arrivals</h2>
        <a href="<?= e(url('shop/products')) ?>">See all →</a>
    </div>
    <div class="product-grid">
        <?php foreach ($featured as $p): $stock = Product::stockOf($p); ?>
            <?php include APP_PATH . '/views/shop/_product-card.php'; ?>
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
                <span class="category-arrow" aria-hidden="true">→</span>
                <h3><?= e($c['name']) ?></h3>
                <span class="count"><?= (int) $c['product_count'] ?> product<?= (int) $c['product_count'] === 1 ? '' : 's' ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (empty($featured) && empty($categories)): ?>
<div class="empty-state">
    <h2>No products or categories yet</h2>
    <p class="muted">Our catalogue is being set up. Check back soon or browse the full shop.</p>
    <a class="btn btn-primary" href="<?= e(url('shop/products')) ?>">Browse products</a>
</div>
<?php endif; ?>

<div class="promo">
    <div class="promo-inner">
        <div class="section-kicker light">Simple fulfilment</div>
        <h3>Order online. Collect or get it delivered.</h3>
        <p>Our online catalogue uses the same stock as our Nairobi store. Choose pickup for the quickest handover or select a delivery area during checkout.</p>
        <a class="btn" href="<?= e(url('shop/products')) ?>">Start shopping</a>
    </div>
</div>
