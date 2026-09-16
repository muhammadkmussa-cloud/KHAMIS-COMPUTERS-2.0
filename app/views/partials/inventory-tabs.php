<?php
$current = Router::currentPath();
$tabs = [
    ['products',   'Products'],
];
if (Auth::isAdmin()) {
    $tabs[] = ['categories', 'Categories'];
    $tabs[] = ['brands', 'Brands'];
    $tabs[] = ['suppliers', 'Suppliers'];
    $tabs[] = ['grn', 'Goods received'];
}
?>
<nav class="tabs" aria-label="Inventory sections">
    <?php foreach ($tabs as [$route, $label]): $active = str_starts_with($current, $route); ?>
        <a class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(url($route)) ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
