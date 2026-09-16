<?php
$serialized = (int) $product['is_serialized'] === 1;
$warrantyMonths = (int) ($product['warranty_months'] ?? 0);
$mainImage = $images[0] ?? null;
foreach ($images as $candidate) {
    if ($candidate['filename'] === ($product['image'] ?? null)) { $mainImage = $candidate; break; }
}
?>
<div class="product-breadcrumb"><a href="<?= e(url('shop/products')) ?>">Products</a><span>/</span><span><?= e($product['category_name'] ?? 'Product') ?></span></div>
<section class="product-detail">
    <div class="product-gallery">
        <div class="detail-thumb">
            <?php if ($images): ?>
                <img id="main-image" src="<?= e(url('uploads/p/' . rawurlencode($mainImage['filename']))) ?>" alt="<?= e($mainImage['alt_text'] ?: $product['name']) ?>">
            <?php else: ?>
                <?= product_thumb($product, 620) ?>
            <?php endif; ?>
        </div>
        <?php if (count($images) > 1): ?>
            <div class="gallery-thumbs" aria-label="Product images">
                <?php foreach ($images as $index => $im): ?>
                    <button type="button" class="gallery-thumb <?= $im['filename'] === $mainImage['filename'] ? 'active' : '' ?>" data-gallery-src="<?= e(url('uploads/p/' . rawurlencode($im['filename']))) ?>" data-gallery-alt="<?= e($im['alt_text'] ?: $product['name']) ?>" aria-label="Show image <?= $index + 1 ?>: <?= e($im['alt_text'] ?: $product['name']) ?>">
                        <img src="<?= e(url('uploads/p/' . rawurlencode($im['filename']))) ?>" alt="">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="detail-info">
        <div class="section-kicker"><?= e($product['brand_name'] ? $product['brand_name'] . ' · ' : '') ?><?= e($product['category_name'] ?? '') ?></div>
        <h1><?= e($product['name']) ?></h1>
        <div class="detail-rating"><span aria-hidden="true">★★★★★</span> <span>Local stock and support</span></div>
        <div class="detail-price"><?= money(gross_of($product['sell_price'])) ?></div>
        <div class="detail-price-ex">VAT included · <?= money($product['sell_price']) ?> before VAT</div>

        <div class="availability-card">
            <div><span class="availability-dot <?= $stock > 0 ? 'available' : '' ?>"></span><b><?= $stock > 0 ? ($stock <= 3 ? 'Only ' . $stock . ' available' : 'In stock now') : 'Currently out of stock' ?></b><small><?= $stock > 0 ? 'Stock is reserved after checkout' : 'Contact us for restock timing' ?></small></div>
            <div><span aria-hidden="true">⌂</span><b>Store pickup</b><small><?= $stock > 0 ? 'Usually ready the same business day' : 'Unavailable' ?></small></div>
            <div><span aria-hidden="true">↗</span><b>Nairobi delivery</b><small>Fee and area confirmed at checkout</small></div>
        </div>

        <?php if ($product['description']): ?><div class="product-description"><?= nl2br(e($product['description'])) ?></div><?php endif; ?>

        <?php if ($stock > 0): ?>
            <div class="purchase-panel">
                <?php if (!$serialized): ?><label class="quantity-field" for="qty-input"><span>Quantity</span><input type="number" id="qty-input" value="1" min="1" max="<?= $stock ?>" inputmode="numeric"></label><?php endif; ?>
                <button class="btn btn-primary btn-lg" id="add-to-cart" data-add="<?= (int) $product['id'] ?>" data-stock="<?= $stock ?>" data-serialized="<?= $serialized ? '1' : '0' ?>" data-name="<?= e($product['name']) ?>">Add to cart</button>
            </div>
        <?php else: ?>
            <a class="btn btn-outline btn-lg" href="<?= e(url('shop/products')) ?>">Find an alternative</a>
        <?php endif; ?>

        <dl class="product-specs">
            <div><dt>SKU</dt><dd><?= e($product['sku']) ?></dd></div>
            <div><dt>Warranty</dt><dd><?= $warrantyMonths > 0 ? $warrantyMonths . ' month' . ($warrantyMonths === 1 ? '' : 's') : 'Receipt-backed store support' ?></dd></div>
            <div><dt>Tracking</dt><dd><?= $serialized ? 'Serial / IMEI recorded on receipt' : 'Quantity tracked' ?></dd></div>
            <div><dt>Returns</dt><dd>Contact the store with your order number</dd></div>
        </dl>
    </div>
</section>

<?php if ($stock > 0): ?>
<div class="mobile-buy-bar"><div><small>VAT included</small><b><?= money(gross_of($product['sell_price'])) ?></b></div><button class="btn btn-primary" type="button" data-mobile-add>Add to cart</button></div>
<?php endif; ?>

<?php if ($related): ?>
<section class="shop-section related-section">
    <div class="shop-section-head"><div><div class="section-kicker">More to explore</div><h2>You might also like</h2></div></div>
    <div class="product-grid"><?php foreach ($related as $p): include APP_PATH . '/views/shop/_product-card.php'; endforeach; ?></div>
</section>
<?php endif; ?>
