<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#0b5fcc">
<title><?= e($title ?? config('app.name')) ?></title>
<link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/shop.css')) ?>">
</head>
<body class="layout-shop">
<a class="skip-link" href="#main-content">Skip to main content</a>
<?php
$shopName = Setting::get('shop_name', config('app.name'));
$navCats = array_values(array_filter(Category::all(), fn ($c) => (int) $c['is_active'] === 1));
$navCats = array_slice($navCats, 0, 5);
$current = Router::currentPath();
?>
<header class="global-nav shop-nav">
    <div class="nav-inner">
        <a class="nav-brand" href="<?= e(url('shop')) ?>" aria-label="<?= e($shopName) ?> home">
            <?= brand_mark(24) ?>
            <span><?= e($shopName) ?></span>
        </a>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="shop-navigation" aria-label="Open navigation">
            <span></span><span></span><span></span>
        </button>
        <nav id="shop-navigation" class="nav-links" aria-label="Shop navigation">
            <a class="<?= $current === 'shop' ? 'active' : '' ?>" href="<?= e(url('shop')) ?>">Home</a>
            <a class="<?= $current === 'shop/products' ? 'active' : '' ?>" href="<?= e(url('shop/products')) ?>">All products</a>
            <?php foreach ($navCats as $c): ?>
                <a href="<?= e(url('shop/products?category=' . urlencode($c['slug']))) ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="nav-actions">
            <a class="btn btn-primary btn-sm cart-link" href="<?= e(url('shop/cart')) ?>" aria-label="Open shopping cart">Cart <span id="cart-count" class="cart-count" aria-live="polite">0</span></a>
        </div>
    </div>
</header>
<button class="nav-backdrop" type="button" aria-label="Close navigation" tabindex="-1"></button>

<main id="main-content" class="shop-main" tabindex="-1">
    <div class="flash-region" aria-live="polite" aria-atomic="true">
        <?php include APP_PATH . '/views/partials/flash.php'; ?>
    </div>
    <?= $content ?>
</main>

<footer class="shop-footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <div class="nav-brand"><?= brand_mark(24) ?><strong><?= e($shopName) ?></strong></div>
            <p><?= e(Setting::get('shop_tagline', 'Technology with local service and support.')) ?></p>
        </div>
        <div class="footer-column"><strong>Shop</strong><a href="<?= e(url('shop/products')) ?>">All products</a><a href="<?= e(url('shop/cart')) ?>">Your cart</a><a href="<?= e(url('shop/track')) ?>">Track an order</a></div>
        <div class="footer-column"><strong>Visit or contact</strong><span><?= e(Setting::get('shop_address', 'Nairobi, Kenya')) ?></span><?php if (Setting::get('shop_phone', '') !== ''): ?><a href="tel:<?= e(preg_replace('/\s+/', '', Setting::get('shop_phone', ''))) ?>"><?= e(Setting::get('shop_phone', '')) ?></a><?php endif; ?><?php if (Setting::get('shop_email', '') !== ''): ?><a href="mailto:<?= e(Setting::get('shop_email', '')) ?>"><?= e(Setting::get('shop_email', '')) ?></a><?php endif; ?><span>Mon–Sat · 8:30 AM–6:00 PM</span></div>
        <div class="footer-column"><strong>Customer care</strong><span>VAT-inclusive online prices</span><span>Warranty recorded on receipts</span><span>Returns handled with your order number</span></div>
        <div class="footer-bottom">
            <span>© <?= date('Y') ?> <?= e($shopName) ?></span>
            <span>Secure local checkout · Kenya</span>
        </div>
    </div>
</footer>

<script src="<?= e(url('assets/js/app.js')) ?>"></script>
<script src="<?= e(url('assets/js/shop.js')) ?>"></script>
</body>
</html>
