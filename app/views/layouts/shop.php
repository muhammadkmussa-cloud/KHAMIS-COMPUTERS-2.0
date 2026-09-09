<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? config('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/shop.css')) ?>">
</head>
<body class="layout-shop">
<?php
$shopName = Setting::get('shop_name', config('app.name'));
$navCats = array_values(array_filter(Category::all(), fn ($c) => (int) $c['is_active'] === 1));
$navCats = array_slice($navCats, 0, 5);
$current = Router::currentPath();
?>
<header class="global-nav shop-nav">
    <div class="nav-inner">
        <a class="nav-brand" href="<?= e(url('shop')) ?>">
            <?= brand_mark(24) ?>
            <?= e($shopName) ?>
        </a>
        <nav class="nav-links">
            <a class="<?= $current === 'shop' ? 'active' : '' ?>" href="<?= e(url('shop')) ?>">Home</a>
            <a class="<?= $current === 'shop/products' ? 'active' : '' ?>" href="<?= e(url('shop/products')) ?>">All products</a>
            <?php foreach ($navCats as $c): ?>
                <a href="<?= e(url('shop/products?category=' . urlencode($c['slug']))) ?>"><?= e($c['name']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="nav-actions">
            <a class="btn btn-primary btn-sm" href="<?= e(url('shop/cart')) ?>">Cart <span id="cart-count" class="cart-count">0</span></a>
            <a class="nav-signout" href="<?= e(url('login')) ?>">Staff sign in</a>
        </div>
    </div>
</header>

<main class="shop-main">
    <?php include APP_PATH . '/views/partials/flash.php'; ?>
    <?= $content ?>
</main>

<footer class="shop-footer">
    <div class="footer-inner">
        <div>
            <strong><?= e($shopName) ?></strong>
            <p class="muted"><?= e(Setting::get('shop_address', 'Nairobi, Kenya')) ?></p>
        </div>
        <div>
            <a href="<?= e(url('shop/products')) ?>">All products</a> ·
            <a href="<?= e(url('shop/cart')) ?>">Cart</a> ·
            <a href="<?= e(url('shop/track')) ?>">Track your order</a> ·
            <a href="<?= e(url('login')) ?>">Staff sign in</a>
        </div>
        <div class="muted">© <?= date('Y') ?> <?= e($shopName) ?> · <?= e(Setting::get('shop_phone', '')) ?></div>
    </div>
</footer>

<script src="<?= e(url('assets/js/shop.js')) ?>"></script>
</body>
</html>
