<?php $d = $vat; ?>
<?php include APP_PATH . '/views/partials/reports-tabs.php'; ?>

<div class="page-head">
    <div>
        <h1>VAT report (KRA)</h1>
        <p class="lede">Output VAT collected vs input VAT on purchases — ready for your monthly KRA return.</p>
    </div>
    <form method="get" action="<?= e(url('reports/vat')) ?>" class="toolbar" style="margin:0">
        <input type="date" name="from" value="<?= e($from) ?>">
        <input type="date" name="to" value="<?= e($to) ?>">
        <button class="btn btn-primary btn-sm" type="submit">Update</button>
        <a class="btn btn-outline btn-sm" href="<?= e(url('reports/vat/export') . '?from=' . e($from) . '&to=' . e($to)) ?>">Export CSV</a>
    </form>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><div class="kpi-label">Output VAT (sales)</div><div class="kpi-value blue"><?= money($d['output']) ?></div><div class="kpi-hint"><?= number_format($d['sales_count']) ?> completed sales</div></div>
    <div class="kpi"><div class="kpi-label">Input VAT (purchases)</div><div class="kpi-value" style="color:var(--green)"><?= money($d['input']) ?></div><div class="kpi-hint">est. <?= e((string) $rate) ?>% of purchases (<?= money($d['purchase_net']) ?>)</div></div>
    <div class="kpi"><div class="kpi-label">VAT on returns</div><div class="kpi-value" style="color:var(--orange)"><?= money($d['return_vat']) ?></div><div class="kpi-hint">credited back to customers</div></div>
    <div class="kpi"><div class="kpi-label">Net VAT payable</div><div class="kpi-value" style="color:<?= $d['net'] >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= money($d['net']) ?></div><div class="kpi-hint">output − returns − input</div></div>
</div>

<div class="grid-2" style="margin-top:16px;align-items:start">
    <div class="card">
        <h3>Daily breakdown</h3>
        <table class="table">
            <thead><tr><th>Date</th><th class="num">Output VAT</th><th class="num">Input VAT</th><th class="num">Net</th></tr></thead>
            <tbody>
            <?php foreach ($d['days'] as $day => $v): $dayNet = round($v['out'] - $v['in'], 2); ?>
                <tr>
                    <td class="cell-main"><?= e(date('D d M Y', strtotime($day))) ?></td>
                    <td class="num"><?= money($v['out']) ?></td>
                    <td class="num"><?= money($v['in']) ?></td>
                    <td class="num" style="font-weight:700;color:<?= $dayNet >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= money($dayNet) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Sales (output VAT)</h3>
        <?php if (!$d['sales']): ?>
            <p class="muted">No completed sales in this period.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Sale</th><th class="num">Net excl. VAT</th><th class="num">Output VAT</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($d['sales'], 0, 300) as $s): ?>
                    <tr>
                        <td><div class="cell-main"><?= e($s['sale_number']) ?></div><div class="cell-sub"><?= e(substr((string) $s['created_at'], 0, 16)) ?> · <?= e($s['customer_name'] ?? 'Walk-in') ?></div></td>
                        <td class="num"><?= money((float) $s['subtotal'] - (float) $s['discount']) ?></td>
                        <td class="num" style="font-weight:700"><?= money($s['tax_amount']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($d['sales']) > 300): ?><p class="muted">Showing the most recent 300 of <?= number_format(count($d['sales'])) ?> sales — the CSV export includes all.</p><?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2" style="margin-top:16px;align-items:start">
    <div class="card">
        <h3>Purchases (input VAT)</h3>
        <?php if (!$d['purchases']): ?>
            <p class="muted">No goods received in this period.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>GRN</th><th class="num">Net cost</th><th class="num">Est. input VAT</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($d['purchases'], 0, 300) as $p): ?>
                    <tr>
                        <td><div class="cell-main"><?= e($p['grn_number']) ?></div><div class="cell-sub"><?= e($p['supplier']) ?> · <?= e(substr((string) $p['created_at'], 0, 16)) ?></div></td>
                        <td class="num"><?= money($p['net']) ?></td>
                        <td class="num" style="font-weight:700"><?= money(round((float) $p['net'] * $rate / 100, 2)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($d['purchases']) > 300): ?><p class="muted">Showing the most recent 300 of <?= number_format(count($d['purchases'])) ?> GRNs — the CSV export includes all.</p><?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Returns (VAT reversed)</h3>
        <?php if (!$d['returns']): ?>
            <p class="muted">No completed returns in this period.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Return</th><th class="num">Refund (net)</th><th class="num">VAT reversed</th></tr></thead>
                <tbody>
                <?php foreach ($d['returns'] as $r): ?>
                    <tr>
                        <td><div class="cell-main"><?= e($r['return_number']) ?></div><div class="cell-sub"><?= e($r['sale_number']) ?> · <?= e(substr((string) $r['created_at'], 0, 16)) ?></div></td>
                        <td class="num"><?= money($r['refund_amount']) ?></td>
                        <td class="num" style="font-weight:700"><?= money($r['vat']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<div class="card" style="margin-top:16px">
    <h3>How this is calculated</h3>
    <ul class="muted" style="font-size:13px;line-height:1.6;margin:0;padding-left:18px">
        <li><b>Output VAT</b> — the VAT actually charged on completed sales (stored per sale). Voided/pending sales are excluded.</li>
        <li><b>VAT on returns</b> — the VAT reversed when a return is approved, re-taxed at that sale's effective VAT rate.</li>
        <li><b>Input VAT</b> — estimated at the current <?= e((string) $rate) ?>% rate on goods-received costs, which are recorded VAT-exclusive. If a supplier invoice included VAT or was VAT-exempt, adjust those lines manually.</li>
        <li><b>Not included</b> — VAT on expenses (expenses don't capture VAT separately yet) and any VAT on delivery fees.</li>
    </ul>
</div>
