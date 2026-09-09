<?php $current = Router::currentPath(); ?>
<div class="tabs" style="display:flex;gap:8px;margin:2px 0 20px;flex-wrap:wrap">
    <a class="btn btn-sm <?= $current === 'reports' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(url('reports')) ?>">Reports</a>
    <a class="btn btn-sm <?= $current === 'reports/z' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(url('reports/z')) ?>">Z-report</a>
    <a class="btn btn-sm <?= $current === 'reports/vat' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(url('reports/vat')) ?>">VAT (KRA)</a>
    <a class="btn btn-sm <?= $current === 'reports/purchases' ? 'btn-primary' : 'btn-outline' ?>" href="<?= e(url('reports/purchases')) ?>">Purchases</a>
</div>
