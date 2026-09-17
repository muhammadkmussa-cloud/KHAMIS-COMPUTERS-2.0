<?php
$maxPoint = max(1.0, ...array_map(fn($p) => (float)$p['value'], $chart));
$channelTotal = array_sum(array_map(fn($c) => (float)$c['rev'], $channels)) ?: 1;
$saleSourceTotal = array_sum(array_map(fn($c) => (float)($c['rev'] ?? 0), $saleSources ?? [])) ?: 1;
$margin = $revenue > 0 ? round($grossProfit / $revenue * 100, 1) : 0;
$cmp = function ($value) {
    return $value === null ? 'No prior-period baseline' : (($value >= 0 ? '↑ ' : '↓ ') . abs($value) . '% vs prior period');
};
?>
<?php include APP_PATH.'/views/partials/reports-tabs.php'; ?>
<div class="page-head report-head">
    <div>
        <div class="section-kicker">Business intelligence</div>
        <h1>Performance overview</h1>
        <p class="lede"><?= e(date('d M Y', strtotime($from))) ?> – <?= e(date('d M Y', strtotime($to))) ?></p>
    </div>
    <a class="btn btn-outline" href="<?= e(url('reports/export') . '?from=' . e($from) . '&to=' . e($to)) ?>">Export CSV</a>
</div>

<section class="period-presets" aria-label="Report period">
    <a href="<?= e(url('reports') . '?from=' . date('Y-m-d') . '&to=' . date('Y-m-d')) ?>">Today</a>
    <a href="<?= e(url('reports') . '?from=' . date('Y-m-d', strtotime('-6 days')) . '&to=' . date('Y-m-d')) ?>">Last 7 days</a>
    <a href="<?= e(url('reports') . '?from=' . date('Y-m-01') . '&to=' . date('Y-m-t')) ?>">This month</a>
    <form method="get" action="<?= e(url('reports')) ?>">
        <label>From<input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>To<input type="date" name="to" value="<?= e($to) ?>"></label>
        <button class="btn btn-primary btn-sm">Apply</button>
    </form>
</section>

<section class="report-kpis">
    <article><small>Net sales (excl. VAT)</small><strong><?= money($revenue) ?></strong><span><?= e($cmp($comparison['revenue'])) ?></span></article>
    <article><small>Completed orders</small><strong><?= number_format($orders) ?></strong><span><?= e($cmp($comparison['orders'])) ?> · <?= money($avgOrder) ?> average</span></article>
    <article><small>Gross profit</small><strong><?= money($grossProfit) ?></strong><span><?= e($cmp($comparison['gross'])) ?> · <?= $margin ?>% margin</span></article>
    <article><small>Net result</small><strong class="<?= $net >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($net) ?></strong><span><?= money($refunds) ?> refunds · <?= money($expenses) ?> expenses</span></article>
</section>

<?php if (!empty($whatsappEnquiries)): ?>
<section class="report-kpis" style="margin-top:14px;">
    <article><small>WhatsApp enquiries</small><strong><?= number_format($whatsappEnquiries['total'] ?? 0) ?></strong><span>Catalogue → WhatsApp leads in period</span></article>
    <article><small>Converted to sales</small><strong><?= number_format($whatsappEnquiries['converted'] ?? 0) ?></strong><span><?= ($whatsappEnquiries['total'] ?? 0) > 0 ? round((($whatsappEnquiries['converted'] ?? 0) / max(1, $whatsappEnquiries['total'])) * 100, 1) . '% conversion' : 'No enquiries yet' ?></span></article>
    <article><small>Walk-in vs WhatsApp</small><strong><?php
        $walk = 0; $wa = 0;
        foreach ($saleSources as $ss) {
            if (($ss['sale_source'] ?? '') === 'walk-in') $walk = (int)$ss['n'];
            if (($ss['sale_source'] ?? '') === 'whatsapp') $wa = (int)$ss['n'];
        }
        echo $walk . ' / ' . $wa;
    ?></strong><span>Walk-in / WhatsApp orders</span></article>
    <article><small>Shop mode</small><strong><?= is_online_checkout_enabled() ? 'Online checkout' : 'Catalogue + WhatsApp' ?></strong><span><?= is_whatsapp_ordering_enabled() ? 'WhatsApp ordering ON' : 'WhatsApp ordering OFF' ?></span></article>
</section>
<?php endif; ?>

<div class="report-grid">
    <section class="card report-chart-card">
        <div class="dashboard-section-head"><div><div class="section-kicker"><?= e($chartMode) ?> aggregation</div><h2>Revenue trend</h2></div><small>Prior: <?= e(date('d M', strtotime($previousFrom))) ?> – <?= e(date('d M', strtotime($previousTo))) ?></small></div>
        <div class="report-chart">
            <?php foreach ($chart as $point): $height = max(3, round($point['value'] / $maxPoint * 100)); ?>
                <div title="<?= e($point['label']) ?> · <?= money($point['value']) ?>"><span><?= $point['value'] ? e(number_format($point['value'] / 1000, 0)) . 'k' : '' ?></span><i style="height:<?= $height ?>%"></i><small><?= e($point['label']) ?></small></div>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="card">
        <div class="section-kicker">Sales mix</div>
        <h2>Channels</h2>
        <?php if (!$channels): ?><p class="muted">No sales in this period.</p>
        <?php else: ?><?php foreach ($channels as $channel): $pct = round((float)$channel['rev'] / $channelTotal * 100); ?>
            <div class="hbar">
                <div class="hbar-label"><span><?= e(strtoupper($channel['channel'])) ?> · <?= (int)$channel['n'] ?> orders</span><b><?= $pct ?>%</b></div>
                <div class="hbar-track"><div class="hbar-fill" style="width:<?= max(2, $pct) ?>%"></div></div>
                <small><?= money($channel['rev']) ?></small>
            </div>
        <?php endforeach; ?><?php endif; ?>

        <?php if (!empty($saleSources)): ?>
            <div style="margin-top:22px;"><div class="section-kicker">By source</div><h3 style="margin:6px 0 12px;">Sale source (POS)</h3></div>
            <?php foreach ($saleSources as $src): $pct = round((float)($src['rev'] ?? 0) / $saleSourceTotal * 100); ?>
                <div class="hbar">
                    <div class="hbar-label"><span><?= e(ucfirst($src['sale_source'] ?? 'walk-in')) ?> · <?= (int)$src['n'] ?> orders</span><b><?= $pct ?>%</b></div>
                    <div class="hbar-track"><div class="hbar-fill" style="width:<?= max(2, $pct) ?>%; background:<?= ($src['sale_source'] ?? '') === 'whatsapp' ? '#25D366' : '' ?>"></div></div>
                    <small><?= money($src['rev']) ?></small>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<div class="report-grid">
    <section class="card">
        <div class="dashboard-section-head"><div><div class="section-kicker">Product performance</div><h2>Top products</h2></div></div>
        <?php if (!$topProducts): ?><p class="muted">No sales in this period.</p>
        <?php else: ?><div class="ranked-list"><?php foreach ($topProducts as $i => $product): ?>
            <div><span><?= $i + 1 ?></span><p><b><?= e($product['name']) ?></b><small><?= e($product['sku']) ?> · <?= (int)$product['qty'] ?> sold</small></p><strong><?= money($product['rev']) ?></strong></div>
        <?php endforeach; ?></div><?php endif; ?>
    </section>
    <section class="card">
        <div class="dashboard-section-head"><div><div class="section-kicker">Action required</div><h2>Low-stock exceptions</h2></div><a href="<?= e(url('products?stock=low')) ?>">Open inventory →</a></div>
        <?php if (!$lowStock): ?><p class="muted">No products at or below their reorder point.</p>
        <?php else: ?><div class="dashboard-exceptions"><?php foreach (array_slice($lowStock, 0, 8) as $product): ?>
            <a href="<?= e(url('products/' . $product['id'])) ?>"><span><b><?= e($product['name']) ?></b><small><?= e($product['sku']) ?></small></span><strong><?= Product::stockOf($product) ?> / <?= (int)$product['reorder_level'] ?></strong></a>
        <?php endforeach; ?></div><?php endif; ?>
    </section>
</div>
