<?php
$cardStock = Product::stockOf($p);
$cardCategory = (string) ($p['category_name'] ?? 'Technology');
?>
<article class="product-card">
    <a class="thumb" href="<?= e(url('shop/product/' . $p['id'])) ?>" aria-label="View <?= e($p['name']) ?>">
        <?= product_thumb($p, 360) ?>
    </a>
    <div class="product-card-body">
        <div class="pcat"><?= e(($p['brand_name'] ?? '') ?: $cardCategory) ?></div>
        <a class="pname" href="<?= e(url('shop/product/' . $p['id'])) ?>"><?= e($p['name']) ?></a>
        <div class="stock-line <?= $cardStock > 0 ? 'is-available' : 'is-unavailable' ?>">
            <?= $cardStock > 0 ? ($cardStock <= 3 ? 'Only ' . $cardStock . ' left' : 'Ready for pickup') : 'Currently unavailable' ?>
        </div>
    </div>
    <div class="product-card-foot">
        <div class="price-row">
            <span class="price"><?= money(gross_of($p['sell_price'])) ?></span>
            <span class="price-ex">VAT included</span>
        </div>
        <?php if ($cardStock > 0): ?>
            <button class="btn btn-primary add-btn" data-add="<?= (int) $p['id'] ?>" data-stock="<?= $cardStock ?>" data-name="<?= e($p['name']) ?>">Add to cart</button>
        <?php else: ?>
            <button class="btn btn-ghost add-btn" disabled>Out of stock</button>
        <?php endif; ?>
    </div>
</article>
