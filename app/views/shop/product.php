<?php $serialized = (int) $product['is_serialized'] === 1; ?>
<div class="product-detail">
    <div class="detail-thumb">
        <?php if ($images): ?>
            <img id="main-image" src="<?= e(url('uploads/p/' . rawurlencode($product['image'] ?: $images[0]['filename']))) ?>"
                 alt="<?= e($product['name']) ?>" style="width:520px;height:520px;object-fit:cover;border-radius:18px;display:block;max-width:100%">
        <?php else: ?>
            <?= product_thumb($product, 520) ?>
        <?php endif; ?>
    </div>
    <?php if (count($images) > 1): ?>
        <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
            <?php foreach ($images as $im): ?>
                <img src="<?= e(url('uploads/p/' . rawurlencode($im['filename']))) ?>" alt=""
                     onclick="var m=document.getElementById('main-image'); if(m){m.src=this.src;}"
                     style="width:64px;height:64px;object-fit:cover;border-radius:10px;border:2px solid var(--border);cursor:pointer">
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="detail-info">
        <div class="section-kicker"><?= e($product['brand_name'] ? $product['brand_name'] . ' · ' : '') ?><?= e($product['category_name'] ?? '') ?></div>
        <h1><?= e($product['name']) ?></h1>

        <div class="detail-price"><?= money(gross_of($product['sell_price'])) ?></div>
        <div class="detail-price-ex">includes <?= e((string) vat_rate()) ?>% VAT · <?= money($product['sell_price']) ?> excluding VAT</div>

        <div class="detail-stock">
            <?php if ($stock > 0): ?>
                <span class="badge badge-green">In stock<?= $stock <= 3 ? ' · only ' . $stock . ' left' : '' ?></span>
            <?php else: ?>
                <span class="badge badge-red">Out of stock</span>
            <?php endif; ?>
            <?php if ($serialized): ?>
                <span class="badge badge-blue">Serial / IMEI tracked</span>
            <?php endif; ?>
        </div>

        <?php if ($product['description']): ?>
            <p class="muted"><?= nl2br(e($product['description'])) ?></p>
        <?php endif; ?>

        <?php if ($serialized): ?>
            <p class="muted" style="font-size:13px">This is a serial-tracked item. A specific unit is assigned to you automatically at checkout — perfect for warranty registration.</p>
        <?php endif; ?>

        <?php if ($stock > 0): ?>
            <div class="qty-picker">
                <?php if (!$serialized): ?>
                    <span class="muted">Quantity</span>
                    <input type="number" id="qty-input" value="1" min="1" max="<?= $stock ?>">
                <?php endif; ?>
                <button class="btn btn-primary" id="add-to-cart"
                        data-add="<?= (int) $product['id'] ?>"
                        data-qty-input="<?= $serialized ? '0' : '1' ?>"
                        data-stock="<?= $stock ?>"
                        data-serialized="<?= $serialized ? '1' : '0' ?>">Add to cart</button>
            </div>
        <?php else: ?>
            <button class="btn btn-ghost" disabled>Out of stock</button>
        <?php endif; ?>

        <div class="detail-meta">
            <span>SKU: <b><?= e($product['sku']) ?></b></span>
            <?php if ($product['barcode']): ?><span>Barcode: <b><?= e($product['barcode']) ?></b></span><?php endif; ?>
            <span>Delivery: pickup in Nairobi or home delivery at checkout.</span>
        </div>
    </div>
</div>

<?php if ($related): ?>
<div class="shop-section">
    <div class="shop-section-head"><h2>You might also like</h2></div>
    <div class="product-grid">
        <?php foreach ($related as $p): $rstock = Product::stockOf($p); ?>
            <div class="product-card">
                <a class="thumb" href="<?= e(url('shop/product/' . $p['id'])) ?>"><?= product_thumb($p, 320) ?></a>
                <div>
                    <div class="pcat"><?= e($p['category_name'] ?? '') ?></div>
                    <a class="pname" href="<?= e(url('shop/product/' . $p['id'])) ?>"><?= e($p['name']) ?></a>
                </div>
                <div class="price-row">
                    <span class="price"><?= money(gross_of($p['sell_price'])) ?></span>
                </div>
                <?php if ($rstock > 0): ?>
                    <button class="btn btn-primary add-btn" data-add="<?= (int) $p['id'] ?>" data-stock="<?= $rstock ?>">Add to cart</button>
                <?php else: ?>
                    <button class="btn btn-ghost add-btn" disabled>Out of stock</button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
