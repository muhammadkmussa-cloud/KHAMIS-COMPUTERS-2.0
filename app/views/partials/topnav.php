<?php
$current = Router::currentPath();
$nav = [
    ['dashboard',  'Dashboard'],
    ['pos',        'POS',  true],
    ['sales',      'Sales'],
    ['returns',    'Returns'],
    ['expenses',   'Expenses'],
];
if (Auth::isAdmin()) {
    $nav[] = ['products', 'Inventory'];
    $nav[] = ['reports',  'Reports'];
    $nav[] = ['staff',    'Staff'];
    $nav[] = ['settings', 'Settings'];
} else {
    $nav[] = ['products',  'Inventory'];
    $nav[] = ['reports/z', 'Z-report'];
}
?>
<nav id="primary-navigation" class="nav-links" aria-label="Primary navigation">
    <?php foreach ($nav as $item): $route = $item[0]; $label = $item[1]; $hasDot = !empty($item[2]); ?>
        <?php
        $active = $current === $route || str_starts_with($current, $route . '/');
        if ($route === 'reports' && str_starts_with($current, 'reports/')) {
            $active = true;
        }
        ?>
        <a class="<?= $active ? 'active' : '' ?>" href="<?= e(url($route)) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <?php if ($hasDot): ?><span class="dot"></span><?php endif; ?><?= e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>
