<?php $m = $summary; ?>
<?php include APP_PATH . '/views/partials/reports-tabs.php'; ?>
<div class="page-head">
    <div>
        <h1>Z-report</h1>
        <p class="lede">Close-of-day till reconciliation per cashier.</p>
    </div>
    <form method="get" action="<?= e(url('reports/z')) ?>" class="toolbar" style="margin:0">
        <input type="date" name="date" value="<?= e($date) ?>" max="<?= e(date('Y-m-d')) ?>">
        <?php if (Auth::isAdmin()): ?>
            <select name="user">
                <option value="0" <?= $userId === null ? 'selected' : '' ?>>All staff (incl. online)</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int) $u['id'] ?>" <?= $userId === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <button class="btn btn-primary btn-sm" type="submit">Show</button>
    </form>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(4,1fr)">
    <div class="kpi"><div class="kpi-label">Total sales</div><div class="kpi-value blue"><?= money($m['total_sales']) ?></div><div class="kpi-hint"><?= $m['sales_count'] ?> completed sales</div></div>
    <div class="kpi"><div class="kpi-label">Cash</div><div class="kpi-value" style="color:var(--green)"><?= money($m['cash_sales']) ?></div><div class="kpi-hint">expected in till: <?= money($m['expected_cash']) ?></div></div>
    <div class="kpi"><div class="kpi-label">M-PESA / Card / Bank</div><div class="kpi-value"><?= money($m['mpesa_sales']) ?> · <?= money($m['card_sales']) ?> · <?= money($m['bank_sales']) ?></div><div class="kpi-hint">non-cash payments</div></div>
    <div class="kpi"><div class="kpi-label">Discounts</div><div class="kpi-value" style="color:var(--orange)"><?= money($m['discounts']) ?></div><div class="kpi-hint">given today</div></div>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-top:16px">
    <div class="kpi"><div class="kpi-label">Voids</div><div class="kpi-value" style="color:var(--red)"><?= money($m['voids_total']) ?></div><div class="kpi-hint"><?= $m['voids_count'] ?> voided sale(s)</div></div>
    <div class="kpi"><div class="kpi-label">Refunds</div><div class="kpi-value" style="color:var(--red)"><?= money($m['refunds_total']) ?></div><div class="kpi-hint"><?= $m['refunds_count'] ?> completed return(s)</div></div>
    <div class="kpi"><div class="kpi-label">Expected cash</div><div class="kpi-value" style="color:<?= $m['expected_cash'] >= 0 ? 'var(--green)' : 'var(--red)' ?>"><?= money($m['expected_cash']) ?></div><div class="kpi-hint">cash sales − refunds</div></div>
</div>

<div class="grid-2" style="margin-top:16px;align-items:start">
    <div class="card">
        <h3>Close the day</h3>
        <?php if ($userId === null): ?>
            <p class="muted">Select a specific staff member above to close their day. "All staff" is a live view only.</p>
        <?php elseif ($closed): ?>
            <div class="alert alert-info">This day is already closed for <b><?= e($closed['user_name'] ?? $userId) ?></b> — snapshot #<?= (int) $closed['id'] ?> at <?= e(date('h:i A', strtotime($closed['created_at']))) ?>.</div>
            <p class="muted">Closing again overwrites the snapshot with the latest figures.</p>
            <button class="btn btn-ghost" type="button" onclick="window.print()">Print</button>
        <?php else: ?>
            <p class="sub">Save a snapshot of today's figures. The report stays available even if a sale is later edited or voided.</p>
            <form method="post" action="<?= e(url('reports/z/close')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="user_id" value="<?= (int) $userId ?>">
                <div class="field">
                    <span>Notes <em class="hint">(optional)</em></span>
                    <textarea name="notes" rows="2" placeholder="e.g. float counted at KSh 5,000"></textarea>
                </div>
                <button class="btn btn-primary" type="submit">Close day</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Recent Z-reports</h3>
        <?php if (!$history): ?>
            <p class="muted">No days closed yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Date</th><th>Cashier</th><th class="num">Sales</th><th class="num">Expected cash</th></tr></thead>
                <tbody>
                <?php foreach ($history as $z): ?>
                    <tr>
                        <td><?= e($z['report_date']) ?></td>
                        <td><?= e($z['user_name'] ?? '—') ?></td>
                        <td class="num"><?= money($z['total_sales']) ?></td>
                        <td class="num"><?= money($z['expected_cash']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
