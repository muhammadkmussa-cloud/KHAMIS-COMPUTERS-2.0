<?php
$cardStock = Product::stockOf($p);
$cardCategory = (string) ($p['category_name'] ?? 'Technology');
$checkoutEnabled = $checkoutEnabled ?? is_online_checkout_enabled();
$hasVariants = false;
$fromPrice = null;
try {
    $hasVariants = !empty(Product::variants((int)$p['id'], true));
    if ($hasVariants) {
        $fromPrice = Product::fromPrice($p);
    }
} catch (Throwable $e) {
    $hasVariants = false;
}
$displayPrice = $hasVariants ? $fromPrice : (float)$p['sell_price'];
$condition = $p['condition_type'] ?? '';
?>
<article class="product-card">
    <a class="thumb" href="<?= e(url('shop/product/' . $p['id'])) ?>" aria-label="View <?= e($p['name']) ?>">
        <?= product_thumb($p, 360) ?>
    </a>
    <div class="product-card-body">
        <div class="pcat"><?= e(($p['brand_name'] ?? '') ?: $cardCategory) ?><?php if ($condition !== '' && strtolower($condition) !== 'new'): ?> · <?= e(ucfirst($condition)) ?><?php endif; ?></div>
        <a class="pname" href="<?= e(url('shop/product/' . $p['id'])) ?>"><?= e($p['name']) ?></a>
        <div class="stock-line <?= $cardStock > 0 ? 'is-available' : 'is-unavailable' ?>">
            <?= $cardStock > 0 ? ($cardStock <= 3 ? 'Only ' . $cardStock . ' left' : 'Ready for pickup') : 'Currently unavailable' ?>
        </div>
    </div>
    <div class="product-card-foot">
        <div class="price-row">
            <span class="price"><?php if ($hasVariants): ?><small style="font-weight:600;color:#64748b;font-size:11px">From </small><?php endif; ?><?= money(gross_of($displayPrice)) ?></span>
            <span class="price-ex">VAT included</span>
        </div>
        <?php if ($checkoutEnabled): ?>
            <?php if ($cardStock > 0): ?>
                <button class="btn btn-primary add-btn" data-add="<?= (int) $p['id'] ?>" data-stock="<?= $cardStock ?>" data-name="<?= e($p['name']) ?>">Add to cart</button>
            <?php else: ?>
                <button class="btn btn-ghost add-btn" disabled>Out of stock</button>
            <?php endif; ?>
        <?php else: ?>
            <a class="btn btn-outline add-btn" href="<?= e(url('shop/product/' . $p['id'])) ?>"><?= $cardStock > 0 ? 'View details' : 'Check availability' ?></a>
        <?php endif; ?>
    </div>
</article>
