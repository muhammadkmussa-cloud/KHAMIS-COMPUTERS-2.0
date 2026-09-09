<?php
$maxDay = max(1.0, (float) max($days ?: [0]));
$channelTotal = array_sum(array_map(fn ($c) => (float) $c['rev'], $channels)) ?: 1;
?>
<?php include APP_PATH . '/views/partials/reports-tabs.php'; ?>
<div class="page-head">
    <div>
        <h1>Reports</h1>
        <p class="lede">Performance across the shop and online.</p>
    </div>
    <form method="get" action="<?= e(url('reports')) ?>" class="toolbar" style="margin:0">
        <input type="date" name="from" value="<?= e($from) ?>">
        <input type="date" name="to" value="<?= e($to) ?>">
        <button class="btn btn-primary btn-sm" type="submit">Update</button>
        <a class="btn btn-outline btn-sm" href="<?= e(url('reports/export') . '?from=' . e($from) . '&to=' . e($to)) ?>">Export CSV</a>
    </form>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr)">
    <div class="kpi"><div class="kpi-label">Revenue (<?= e($from) ?> → <?= e($to) ?>)</div><div class="kpi-value blue"><?= money($revenue) ?></div><div class="kpi-hint"><?= number_format($orders) ?> completed orders</div></div>
    <div class="kpi"><div class="kpi-label">Average order value</div><div class="kpi-value"><?= money($avgOrder) ?></div><div class="kpi-hint">revenue ÷ orders</div></div>
    <div class="kpi"><div class="kpi-label">Gross profit</div><div class="kpi-value" style="color:var(--green)"><?= money($grossProfit) ?></div><div class="kpi-hint">revenue − cost of goods (<?= money($cogs) ?>)</div></div>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-top:16px">
    <div class="kpi"><div class="kpi-label">Refunds</div><div class="kpi-value" style="color:var(--orange)"><?= money($refunds) ?></div><div class="kpi-hint">approved returns</div></div>
    <div class="kpi"><div class="kpi-label">Expenses</div><div class="kpi-value" style="color:var(--red)"><?= money($expenses) ?></div><div class="kpi-hint">same period</div></div>
    <div class="kpi"><div class="kpi-label">Net result</div><div class="kpi-value" style="color:<?= $net >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= money($net) ?></div><div class="kpi-hint">revenue − refunds − expenses</div></div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Sales by channel</h3>
        <?php if (!$channels): ?>
            <p class="muted">No sales in this period.</p>
        <?php else: ?>
            <?php foreach ($channels as $c): $pct = round((float) $c['rev'] / $channelTotal * 100); ?>
                <div class="hbar">
                    <div class="hbar-label"><span><?= e(strtoupper($c['channel'])) ?> · <?= (int) $c['n'] ?> orders</span><b><?= money($c['rev']) ?> (<?= $pct ?>%)</b></div>
                    <div class="hbar-track"><div class="hbar-fill" style="width:<?= max(2, $pct) ?>%"></div></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Revenue by day</h3>
        <div class="vchart">
            <?php foreach ($days as $d => $rev): $h = max(3, round($rev / $maxDay * 100)); ?>
                <div class="vbar" title="<?= e($d) ?> · <?= money($rev) ?>">
                    <div class="vbar-fill" style="height:<?= $h ?>%"></div>
                    <span class="vbar-val"><?= $rev > 0 ? e(number_format($rev / 1000, 0)) . 'k' : '' ?></span>
                    <span class="vbar-day"><?= e(date('d M', strtotime($d))) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="grid-2">
    <div class="card">
        <h3>Top products</h3>
        <table class="table">
            <thead><tr><th>Product</th><th class="num">Qty sold</th><th class="num">Revenue</th></tr></thead>
            <tbody>
            <?php if (!$topProducts): ?>
                <tr><td colspan="3" class="muted" style="text-align:center;padding:20px">No sales in this period.</td></tr>
            <?php endif; ?>
            <?php foreach ($topProducts as $t): ?>
                <tr>
                    <td><div class="cell-main"><?= e($t['name']) ?></div><div class="cell-sub"><?= e($t['sku']) ?></div></td>
                    <td class="num"><?= (int) $t['qty'] ?></td>
                    <td class="num" style="font-weight:700"><?= money($t['rev']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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
