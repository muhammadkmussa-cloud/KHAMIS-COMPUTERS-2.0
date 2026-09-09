<?php
$f = $filters;
$sel = function (string $name, array $options, string $label) use ($f) {
    $html = '<select name="' . $name . '"><option value="">' . e($label) . '</option>';
    foreach ($options as $val => $txt) {
        $html .= '<option value="' . e($val) . '"' . ($f[$name] === $val ? ' selected' : '') . '>' . e($txt) . '</option>';
    }
    return $html . '</select>';
};
?>
<div class="page-head">
    <div>
        <h1>Sales</h1>
        <p class="lede">Every order across the shop and online — <?= number_format($total) ?> total.</p>
    </div>
</div>

<form method="get" action="<?= e(url('sales')) ?>" class="toolbar">
    <input type="search" name="q" value="<?= e($f['q']) ?>" placeholder="Search #, customer, phone, ref…" style="max-width:300px">
    <?= $sel('channel', ['pos' => 'In store', 'online' => 'Online'], 'All channels') ?>
    <?= $sel('status', ['completed' => 'Completed', 'offline' => 'Offline', 'cancelled' => 'Cancelled'], 'All statuses') ?>
    <?= $sel('payment', ['cash' => 'Cash', 'mpesa' => 'M-PESA', 'card' => 'Card', 'bank' => 'Bank'], 'All payments') ?>
    <input type="date" name="from" value="<?= e($f['from']) ?>">
    <input type="date" name="to" value="<?= e($f['to']) ?>">
    <button class="btn btn-primary btn-sm" type="submit">Filter</button>
    <?php if ($f['q'] || $f['channel'] || $f['status'] || $f['payment'] || $f['from'] || $f['to']): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('sales')) ?>">Reset</a>
    <?php endif; ?>
    <a class="btn btn-outline btn-sm" href="<?= e(url('sales/export') . query_string($f)) ?>">Export CSV</a>
</form>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Sale</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Channel</th>
                <th>Payment</th>
                <th class="num">Items</th>
                <th class="num">Total</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="9" class="muted" style="text-align:center;padding:26px">No sales found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $s): [$statusLabel, $statusClass] = status_badge($s['status']); ?>
            <tr>
                <td>
                    <div class="cell-main"><a href="<?= e(url('sales/' . $s['id'])) ?>"><?= e($s['sale_number']) ?></a></div>
                    <?php if ((int) $s['offline_created'] === 1): ?><div class="cell-sub"><span class="badge badge-blue">offline</span></div><?php endif; ?>
                </td>
                <td class="cell-sub"><?= e(date('d M Y H:i', strtotime($s['created_at']))) ?></td>
                <td>
                    <?= $s['customer_name'] ? e($s['customer_name']) : '<span class="cell-sub">Walk-in</span>' ?>
                    <?php if ($s['customer_phone']): ?><div class="cell-sub"><?= e($s['customer_phone']) ?></div><?php endif; ?>
                </td>
                <td><span class="badge badge-<?= $s['channel'] === 'online' ? 'blue' : 'gray' ?>"><?= e($s['channel']) ?></span></td>
                <td class="cell-sub"><?= e(strtoupper($s['payment_method'])) ?></td>
                <td class="num"><?= (int) $s['item_count'] ?></td>
                <td class="num" style="font-weight:700"><?= money($s['total']) ?></td>
                <td><span class="badge badge-<?= $statusClass ?>"><?= e($statusLabel) ?></span></td>
                <td><div class="row-actions">
                    <a class="btn btn-primary btn-sm" href="<?= e(url('sales/' . $s['id'])) ?>">View</a>
                    <?php if (Auth::isAdmin() && $s['status'] === 'pending' && $s['payment_method'] === 'mpesa'): ?>
                        <form method="post" action="<?= e(url('sales/' . $s['id'] . '/mpesa-mark-paid')) ?>" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-outline btn-sm">Mark paid</button>
                        </form>
                    <?php endif; ?>
                    <?php if (Auth::isAdmin() && in_array($s['status'], ['completed', 'pending'], true)): ?>
                        <form method="post" action="<?= e(url('sales/' . $s['id'] . '/void')) ?>" onsubmit="return confirm('Void <?= e($s['sale_number']) ?> and restore its stock?');" style="display:inline">
                            <?= csrf_field() ?>
                            <button type="submit" class="btn btn-danger-ghost btn-sm">Void</button>
                        </form>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
<div class="pager">
    <?php if ($page > 1): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('sales') . query_string(['page' => $page - 1])) ?>">← Prev</a>
    <?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('sales') . query_string(['page' => $page + 1])) ?>">Next →</a>
    <?php endif; ?>
</div>
<?php endif; ?>
