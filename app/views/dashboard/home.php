<?php
$kpis = [
    ['label' => 'Revenue today', 'value' => money($stats['revenue_today']), 'hint' => 'completed sales · ' . $today],
    ['label' => 'Sales today',    'value' => number_format($stats['sales_today']), 'hint' => 'transactions'],
    ['label' => 'Active products','value' => number_format($stats['products']), 'hint' => 'in catalogue'],
    ['label' => 'Stock value',    'value' => money($stats['stock_value']), 'hint' => 'at cost price'],
];
?>

<div class="page-head">
    <div>
        <h1><?= e(config('app.name')) ?></h1>
        <p class="lede">Point of Sale &amp; Online Shop. Everything connected.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-outline" href="<?= e(url('shop')) ?>" target="_blank" rel="noopener">Open shop ↗</a>
        <a class="btn btn-primary" href="<?= e(url('pos')) ?>">Open POS terminal</a>
    </div>
</div>

<div class="kpi-grid">
    <?php foreach ($kpis as $kpi): ?>
        <div class="kpi">
            <div class="kpi-label"><?= e($kpi['label']) ?></div>
            <div class="kpi-value"><?= e($kpi['value']) ?></div>
            <div class="kpi-hint"><?= e($kpi['hint']) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Build roadmap</h3>
        <p class="sub">Delivered piece by piece — each part turns on as it's built.</p>
        <ul class="checklist">
            <li class="done"><span>1. Foundation</span><em>login, shell, database</em></li>
            <li class="done"><span>2. Inventory</span><em>products, serial/IMEI, GRN</em></li>
            <li class="done"><span>3. POS terminal</span><em>cart, checkout, receipt</em></li>
            <li class="done"><span>4. Online shop</span><em>storefront, cart, checkout</em></li>
            <li class="done"><span>5. Offline POS</span><em>service worker + sync</em></li>
            <li class="done"><span>6. Orders &amp; reports</span><em>history, returns, analytics</em></li>
            <li class="done"><span>7. Polish</span><em>roles, settings, PDFs</em></li>
        </ul>
        <p class="sub" style="margin-top:10px">All seven pieces are complete ✅</p>
    </div>

    <div class="card">
        <h3>System</h3>
        <dl class="kv">
            <dt>Environment</dt><dd><span class="badge badge-<?= config('app.env') === 'production' ? 'green' : 'orange' ?>"><?= e(config('app.env')) ?></span></dd>
            <dt>Database driver</dt><dd><?= e($driver) ?></dd>
            <dt>Currency / VAT</dt><dd><?= e(config('app.currency')) ?> · <?= e((string) vat_rate()) ?>%</dd>
            <dt>Units in stock</dt><dd><?= number_format($stats['units_stock']) ?></dd>
            <dt>Pending returns</dt><dd><?= number_format($stats['pending_returns']) ?></dd>
        </dl>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Recent orders</h3>
        <?php if (!$recent): ?>
            <p class="muted">No orders yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Sale</th><th>Customer</th><th>Channel</th><th class="num">Total</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($recent as $s): ?>
                    <tr>
                        <td class="cell-main"><?= e($s['sale_number']) ?></td>
                        <td><?= e($s['customer_name'] ?? 'Walk-in') ?></td>
                        <td><span class="badge badge-<?= $s['channel'] === 'online' ? 'blue' : 'gray' ?>"><?= e($s['channel']) ?></span></td>
                        <td class="num" style="font-weight:700"><?= money($s['total']) ?></td>
                        <td><div class="row-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('sales/' . $s['id'])) ?>">View</a></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top:12px"><a href="<?= e(url('sales')) ?>">See all orders →</a></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Low stock</h3>
        <?php if (!$lowStock): ?>
            <p class="muted">All good — nothing at its reorder level.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Product</th><th class="num">In stock</th><th class="num">Reorder at</th></tr></thead>
                <tbody>
                <?php foreach ($lowStock as $p): ?>
                    <tr>
                        <td class="cell-main"><a href="<?= e(url('products/' . $p['id'])) ?>"><?= e($p['name']) ?></a></td>
                        <td class="num" style="color:var(--orange);font-weight:700"><?= (int) Product::stockOf($p) ?></td>
                        <td class="num"><?= (int) $p['reorder_level'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
