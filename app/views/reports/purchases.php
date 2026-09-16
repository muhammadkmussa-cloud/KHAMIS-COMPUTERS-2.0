<?php $d = $purch; ?>
<?php include APP_PATH . '/views/partials/reports-tabs.php'; ?>

<div class="page-head">
    <div>
        <h1>Purchases by supplier</h1>
        <p class="lede">What you've bought, from whom, and for how much — across all goods-received notes.</p>
    </div>
    <form method="get" action="<?= e(url('reports/purchases')) ?>" class="toolbar" style="margin:0">
        <input type="date" name="from" value="<?= e($from) ?>">
        <input type="date" name="to" value="<?= e($to) ?>">
        <select name="supplier">
            <option value="">All suppliers</option>
            <?php foreach ($suppliers as $s): ?>
                <option value="<?= (int) $s['id'] ?>" <?= $filter === (string) (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?><?= $s['is_active'] ? '' : ' (inactive)' ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-primary btn-sm" type="submit">Update</button>
        <a class="btn btn-outline btn-sm" href="<?= e(url('reports/purchases/export') . '?supplier=' . rawurlencode($filter) . '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to)) ?>">Export CSV</a>
    </form>
</div>

<div class="report-period-links"><span>Quick period</span><a href="<?= e(url('reports/purchases').'?from='.date('Y-m-01').'&to='.date('Y-m-t')) ?>">This month</a><a href="<?= e(url('reports/purchases').'?from='.date('Y-m-01',strtotime('first day of last month')).'&to='.date('Y-m-t',strtotime('last day of last month'))) ?>">Last month</a><a href="<?= e(url('reports/purchases').'?from='.date('Y-01-01').'&to='.date('Y-m-d')) ?>">Year to date</a></div>

<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><div class="kpi-label">Total purchases (net)</div><div class="kpi-value blue"><?= money($d['grand_net']) ?></div><div class="kpi-hint">VAT-exclusive, <?= e($from) ?> → <?= e($to) ?></div></div>
    <div class="kpi"><div class="kpi-label">Est. input VAT</div><div class="kpi-value" style="color:var(--green)"><?= money($d['grand_vat']) ?></div><div class="kpi-hint"><?= e((string) $rate) ?>% of purchases</div></div>
    <div class="kpi"><div class="kpi-label">Deliveries</div><div class="kpi-value"><?= number_format($d['grn_count']) ?></div><div class="kpi-hint">goods-received notes</div></div>
    <div class="kpi"><div class="kpi-label">Suppliers</div><div class="kpi-value"><?= number_format(count($d['summary'])) ?></div><div class="kpi-hint">with purchases this period</div></div>
</div>

<?php if($d['trend']): $maxPurchase=max(1.0,...array_map(fn($point)=>(float)$point['net'],$d['trend'])); ?>
<section class="card purchase-trend"><div class="section-kicker">Spend trend</div><h2>Purchases over time</h2><div><?php foreach($d['trend'] as $point): ?><span title="<?= e(date('d M Y',strtotime($point['date']))) ?> · <?= money($point['net']) ?> · <?= number_format($point['deliveries']) ?> deliveries"><i style="height:<?= max(5,round($point['net']/$maxPurchase*100)) ?>%"></i><small><?= e(date('d M',strtotime($point['date']))) ?></small><b><?= money($point['net']) ?></b></span><?php endforeach; ?></div></section>
<?php endif; ?>

<?php if ($filter !== ''): ?>
    <?php $f = $d['summary'][0] ?? null; ?>
    <div class="card" style="margin-top:16px">
        <?php if (!$f): ?>
            <p class="muted">No purchases for this supplier in the selected period.</p>
        <?php else: ?>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <h3 style="margin:0"><?= e($f['name']) ?><?= $f['free'] ? ' <span class="badge badge-gray">free-text</span>' : '' ?></h3>
                <a class="btn btn-outline btn-sm" href="<?= e(url('reports/purchases') . '?from=' . rawurlencode($from) . '&to=' . rawurlencode($to)) ?>">Clear filter</a>
            </div>
            <p class="muted" style="margin:6px 0 14px"><?= number_format($f['deliveries']) ?> deliveries · <?= money($f['net']) ?> net · <?= money($f['vat']) ?> est. input VAT</p>
            <?php foreach ($f['grns'] as $g): ?>
                <div class="card" style="background:var(--bg-soft,#f6f9fd);margin:10px 0;box-shadow:none">
                    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:6px">
                        <div class="cell-main"><?= e($g['grn_number']) ?></div>
                        <div class="cell-sub"><?= e(substr((string) $g['created_at'], 0, 16)) ?> · <b><?= money($g['net']) ?></b></div>
                    </div>
                    <?php if ($g['items'] ?? []): ?>
                        <table class="table" style="margin-top:8px">
                            <thead><tr><th>Product</th><th>SKU</th><th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Line net</th></tr></thead>
                            <tbody>
                            <?php foreach ($g['items'] as $it): ?>
                                <tr>
                                    <td class="cell-main"><?= e($it['product_name']) ?></td>
                                    <td class="cell-sub"><?= e($it['sku']) ?></td>
                                    <td class="num"><?= (int) $it['quantity'] ?></td>
                                    <td class="num"><?= money($it['unit_cost']) ?></td>
                                    <td class="num" style="font-weight:700"><?= money($it['line_net']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card" style="margin-top:16px">
    <h3>By supplier</h3>
    <?php if (!$d['summary']): ?>
        <p class="muted">No goods received in this period.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Supplier</th><th class="num">Deliveries</th><th class="num">Net purchases</th><th class="num">Est. input VAT</th><th>Share</th><th>Last delivery</th></tr></thead>
            <tbody>
            <?php foreach ($d['summary'] as $s): ?>
                <?php
                if ($s['free']) {
                    $href = url('reports/purchases') . '?supplier=name:' . rawurlencode($s['name']) . '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to);
                } else {
                    $href = url('reports/purchases') . '?supplier=' . (int) $s['id'] . '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to);
                }
                $pct = $d['grand_net'] > 0 ? round($s['net'] / $d['grand_net'] * 100) : 0;
                ?>
                <tr>
                    <td>
                        <div class="cell-main"><a href="<?= e($href) ?>"><?= e($s['name']) ?></a><?= $s['free'] ? ' <span class="badge badge-gray">free-text</span>' : '' ?></div>
                        <div class="cell-sub"><?= $s['free'] ? 'typed on GRN (no saved record)' : 'saved supplier' ?></div>
                    </td>
                    <td class="num"><?= number_format($s['deliveries']) ?></td>
                    <td class="num" style="font-weight:700"><?= money($s['net']) ?></td>
                    <td class="num"><?= money($s['vat']) ?></td>
                    <td>
                        <div class="hbar" style="min-width:90px">
                            <div class="hbar-track"><div class="hbar-fill" style="width:<?= max(2, $pct) ?>%"></div></div>
                        </div>
                        <div class="cell-sub"><?= $pct ?>%</div>
                    </td>
                    <td class="cell-sub"><?= $s['last'] ? e(date('d M Y', strtotime($s['last']))) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total</th>
                    <th class="num"><?= number_format($d['grn_count']) ?></th>
                    <th class="num"><?= money($d['grand_net']) ?></th>
                    <th class="num"><?= money($d['grand_vat']) ?></th>
                    <th colspan="2"></th>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</div>

<div class="card" style="margin-top:16px">
    <h3>How this is calculated</h3>
    <ul class="muted" style="font-size:13px;line-height:1.6;margin:0;padding-left:18px">
        <li><b>Net purchases</b> — the sum of <code>unit cost × quantity</code> across each delivery's items, VAT-exclusive (costs are recorded exclusive of VAT).</li>
        <li><b>Est. input VAT</b> — <?= e((string) $rate) ?>% of net purchases, the same figure the VAT (KRA) report uses.</li>
        <li><b>Saved supplier</b> — GRNs linked to a supplier record show the supplier's current name. <b>Free-text</b> — GRNs where a name was typed instead of choosing a saved supplier are grouped by that typed name, so one-off vendors still appear.</li>
        <li><b>Share</b> — each supplier's percentage of total net purchases in the period.</li>
        <li>Click a supplier name to drill into their deliveries and items.</li>
    </ul>
</div>
