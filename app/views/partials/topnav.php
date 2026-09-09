<?php
$current = Router::currentPath();
$nav = [
    ['dashboard',  'Dashboard'],
    ['pos',        'POS',  true],
    ['sales',      'Sales'],
    ['returns',    'Returns'],
    ['expenses',   'Expenses'],
    ['reports/z',  'Z-report'],
];
if (Auth::isAdmin()) {
    $nav[] = ['products', 'Inventory'];
    $nav[] = ['reports',  'Reports'];
    $nav[] = ['staff',    'Staff'];
    $nav[] = ['settings', 'Settings'];
}
?>
<nav class="nav-links">
    <?php foreach ($nav as $item): $route = $item[0]; $label = $item[1]; $hasDot = !empty($item[2]); ?>
        <a class="<?= $current === $route ? 'active' : '' ?>" href="<?= e(url($route)) ?>">
            <?php if ($hasDot): ?><span class="dot"></span><?php endif; ?><?= e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>
