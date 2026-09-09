<?php
$current = Router::currentPath();
$nav = [
    ['dashboard',  'Dashboard',  '⌂'],
    ['pos',        'POS Terminal', '▤'],
    ['products',   'Inventory',  '▦'],
    ['sales',      'Sales',      '✓'],
    ['returns',    'Returns',    '↩'],
    ['expenses',   'Expenses',   'KSh'],
    ['reports',    'Reports',    '▥'],
    ['settings',   'Settings',   '⚙'],
];
?>
<nav class="side-nav">
    <p class="nav-label">Workspace</p>
    <?php foreach ($nav as [$route, $label, $icon]): ?>
        <a class="nav-link <?= $current === $route ? 'active' : '' ?>"
           href="<?= e(url($route)) ?>">
            <span class="nav-icon"><?= $icon ?></span>
            <span><?= e($label) ?></span>
            <?php if ($route === 'pos'): ?><span class="pill">offline-ready</span><?php endif; ?>
        </a>
    <?php endforeach; ?>
    <p class="nav-label nav-label-bottom">Storefront</p>
    <a class="nav-link" href="<?= e(url('shop')) ?>" target="_blank" rel="noopener">
        <span class="nav-icon">◎</span><span>Online Shop ↗</span>
    </a>
</nav>
