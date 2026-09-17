<?php
$checkoutEnabled = $checkoutEnabled ?? is_online_checkout_enabled();
$whatsappEnabled = $whatsappEnabled ?? is_whatsapp_ordering_enabled();
$waNumber = $whatsappNumber ?? whatsapp_number();
if (!empty($slides)): $slideCount = count($slides); ?>
<section class="hero-carousel" id="hero-carousel" aria-roledescription="carousel" aria-label="Featured promotions" data-autoplay="6500">
    <h1 class="sr-only"><?= e($shopName ?? Setting::get('shop_name', config('app.name'))) ?> online shop</h1>
    <div class="hero-slides">
        <?php foreach ($slides as $i => $slide):
            $cta       = HeroSlide::resolveCta($slide);
            $secondary = HeroSlide::resolveSecondaryCta($slide);
            $price     = HeroSlide::fromPrice($slide);
            $desktop   = (string) $slide['desktop_image'];
            $mobile    = !empty($slide['mobile_image']) ? (string) $slide['mobile_image'] : $desktop;
            $overlay   = max(0, min(90, (int) ($slide['overlay_strength'] ?? 45))) / 100;
            $textPos   = in_array($slide['text_position'] ?? 'left', ['left', 'center', 'right'], true) ? $slide['text_position'] : 'left';
            $imgPos    = in_array($slide['image_position'] ?? 'center', ['left', 'center', 'right'], true) ? $slide['image_position'] : 'center';
            $first     = $i === 0;
        ?>
        <div class="hero-slide text-<?= e($textPos) ?><?= $first ? ' is-active' : '' ?>" data-index="<?= $i ?>" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= $slideCount ?>"<?= $first ? '' : ' aria-hidden="true"' ?>>
            <picture>
                <?php if (!empty($slide['mobile_image'])): ?>
                <source media="(max-width: 700px)" srcset="<?= e(url('uploads/h/' . rawurlencode($mobile))) ?>">
                <?php endif; ?>
                <img class="hero-slide-img" src="<?= e(url('uploads/h/' . rawurlencode($desktop))) ?>" alt=""
                     style="object-position: <?= e($imgPos) ?>"
                     <?= $first ? 'loading="eager" fetchpriority="high"' : 'loading="lazy" decoding="async"' ?>>
            </picture>
            <div class="hero-slide-overlay" style="--overlay: <?= $overlay ?>"></div>
            <div class="hero-slide-content">
                <div class="hero-inner">
                    <?php if (!empty($slide['eyebrow'])): ?><p class="hero-eyebrow"><?= e($slide['eyebrow']) ?></p><?php endif; ?>
                    <h2 class="hero-headline"><?= e($slide['headline']) ?></h2>
                    <?php if (!empty($slide['description'])): ?><p class="hero-description"><?= e($slide['description']) ?></p><?php endif; ?>
                    <?php if ($price !== null): ?><p class="hero-price"><span>From</span> <strong><?= e($price) ?></strong></p><?php endif; ?>
                    <div class="hero-ctas">
                        <a class="hero-btn hero-btn-primary" href="<?= e($cta['url']) ?>"><?= e($cta['label']) ?><span aria-hidden="true">→</span></a>
                        <?php if ($secondary): ?><a class="hero-btn hero-btn-secondary" href="<?= e($secondary['url']) ?>"><?= e($secondary['label']) ?></a><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($slideCount > 1): ?>
    <button class="hero-arrow hero-prev" type="button" aria-label="Previous slide"><span aria-hidden="true">‹</span></button>
    <button class="hero-arrow hero-next" type="button" aria-label="Next slide"><span aria-hidden="true">›</span></button>
    <div class="hero-controls">
        <div class="hero-progress" role="group" aria-label="Choose slide">
            <?php foreach ($slides as $i => $s): ?>
            <button class="hero-dot<?= $i === 0 ? ' is-active' : '' ?>" type="button" aria-current="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="Slide <?= $i + 1 ?>">
                <span class="hero-dot-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <span class="hero-dot-bar" aria-hidden="true"></span>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="hero-meta">
            <button class="hero-pause" type="button" data-hero-pause aria-label="Pause slideshow">Pause</button>
            <div class="hero-counter" aria-hidden="true"><span class="hero-counter-current">01</span> / <?= str_pad((string) $slideCount, 2, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>
    <?php endif; ?>
</section>
<?php else: ?>
<section class="shop-hero">
    <div class="hero-copy">
        <div class="section-kicker">Nairobi technology store</div>
        <h1>Technology you can buy with confidence.</h1>
        <p class="sub"><?= e(Setting::get('shop_tagline', 'Laptops, phones and accessories with verified stock, clear pricing and local support.')) ?></p>
        <div class="ctas">
            <a class="btn btn-primary btn-lg" href="<?= e(url('shop/products')) ?>">Browse products</a>
            <?php if ($checkoutEnabled): ?>
                <a class="btn btn-outline btn-lg" href="<?= e(url('shop/track')) ?>">Track an order</a>
            <?php elseif ($whatsappEnabled && $waNumber !== ''): ?>
                <a class="btn btn-whatsapp btn-lg" href="<?= e('https://wa.me/' . $waNumber . '?text=' . rawurlencode('Hello ' . ($shopName ?? 'Khamis Computers') . ', I would like to browse your catalogue.')) ?>" target="_blank" rel="noopener">💬 Order on WhatsApp</a>
            <?php else: ?>
                <a class="btn btn-outline btn-lg" href="<?= e(url('shop/products')) ?>">View catalogue</a>
            <?php endif; ?>
        </div>
        <div class="hero-trust" aria-label="Store benefits">
            <span><b>Live stock</b> from our store</span>
            <span><b>VAT included</b> in every price</span>
            <span><b>WhatsApp ordering</b> available</span>
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
<?php endif; ?>

<div class="service-strip">
    <div><span class="service-icon">⌁</span><b>Pickup in Nairobi</b><small>Reserve via WhatsApp, collect in store</small></div>
    <div><span class="service-icon">↗</span><b>Delivery available</b><small>Confirmed on WhatsApp</small></div>
    <div><span class="service-icon">✓</span><b>Warranty recorded</b><small>Serials kept with your receipt</small></div>
</div>

<?php if ($featured): ?>
<div class="shop-section">
    <div class="shop-section-head">
        <h2><?= $checkoutEnabled ? 'New arrivals' : 'Featured products' ?></h2>
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
        <div class="section-kicker light"><?= $checkoutEnabled ? 'Simple fulfilment' : 'Catalogue + WhatsApp' ?></div>
        <h3><?= $checkoutEnabled ? 'Order online. Collect or get it delivered.' : 'Browse our catalogue. Order on WhatsApp.' ?></h3>
        <p><?= $checkoutEnabled ? 'Our online catalogue uses the same stock as our Nairobi store. Choose pickup for the quickest handover or select a delivery area during checkout.' : 'Our online shop is now a professional product catalogue. View product details, select your desired variant, and contact us on WhatsApp. We will confirm availability, price, and delivery, then complete your sale through our trusted POS.' ?></p>
        <a class="btn" href="<?= e(url('shop/products')) ?>"><?= $checkoutEnabled ? 'Start shopping' : 'Browse catalogue' ?></a>
    </div>
</div>
