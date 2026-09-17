<?php
$f = $filters;
$filterUrl = function (array $changes = []) use ($f): string {
    $next = array_merge($f, $changes);
    unset($next['page']);
    return url('sales') . query_string($next);
};
$hasFilters = $f['q'] !== '' || $f['channel'] !== '' || $f['status'] !== '' || $f['payment'] !== '' || $f['from'] !== '' || $f['to'] !== '' || ($f['source'] ?? '') !== '';
?>
<div class="page-head sales-page-head">
    <div><div class="section-kicker">Revenue workspace</div><h1>Sales</h1><p class="lede">Find orders, resolve payment exceptions, and open receipts.</p></div>
    <?php if (Auth::isAdmin()): ?><a class="btn btn-outline" href="<?= e(url('sales/export') . query_string($f)) ?>">Export current view</a><?php endif; ?>
</div>

<div class="sales-kpis">
    <div><span>Today’s completed sales</span><b><?= money($summary['today_total']) ?></b><small><?= number_format($summary['today_count']) ?> orders created today</small></div>
    <div><span>Needs attention</span><b><?= number_format($summary['pending']) ?></b><small>Pending payment orders</small></div>
    <div><span>Online today</span><b><?= number_format($summary['online']) ?></b><small>Orders from the storefront</small></div>
    <div><span>Current results</span><b><?= number_format($total) ?></b><small><?= $hasFilters ? 'Matching active filters' : 'All recorded orders' ?></small></div>
</div>

<details class="sales-filter-panel" open>
    <summary><span>Search and filters<?= $hasFilters ? ' · active' : '' ?></span><span aria-hidden="true">⌄</span></summary>
    <form method="get" action="<?= e(url('sales')) ?>" class="sales-filter-form">
        <label class="sales-filter-search" for="sales-q"><span>Search orders</span><input id="sales-q" type="search" name="q" aria-label="Search orders" value="<?= e($f['q']) ?>" placeholder="Order number, customer, phone or payment reference"></label>
        <label for="sales-channel"><span>Channel</span><select id="sales-channel" name="channel" aria-label="Channel"><option value="">All channels</option><option value="pos" <?= $f['channel'] === 'pos' ? 'selected' : '' ?>>In store</option><option value="online" <?= $f['channel'] === 'online' ? 'selected' : '' ?>>Online</option></select></label>
        <label for="sales-status"><span>Status</span><select id="sales-status" name="status" aria-label="Status"><option value="">All statuses</option><option value="completed" <?= $f['status'] === 'completed' ? 'selected' : '' ?>>Completed</option><option value="pending" <?= $f['status'] === 'pending' ? 'selected' : '' ?>>Pending payment</option><option value="cancelled" <?= $f['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option><option value="offline" <?= $f['status'] === 'offline' ? 'selected' : '' ?>>Created offline</option></select></label>
        <label for="sales-payment"><span>Payment</span><select id="sales-payment" name="payment" aria-label="Payment"><option value="">All payments</option><?php foreach (['cash'=>'Cash','mpesa'=>'M-PESA','card'=>'Card','bank'=>'Bank'] as $value=>$label): ?><option value="<?= $value ?>" <?= $f['payment'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label for="sales-source"><span>Source</span><select id="sales-source" name="source" aria-label="Source"><option value="">All sources</option><option value="walk-in" <?= ($f['source'] ?? '') === 'walk-in' ? 'selected' : '' ?>>Walk-in</option><option value="whatsapp" <?= ($f['source'] ?? '') === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option><option value="phone" <?= ($f['source'] ?? '') === 'phone' ? 'selected' : '' ?>>Phone</option><option value="other" <?= ($f['source'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option></select></label>
        <label for="sales-from"><span>From</span><input id="sales-from" type="date" name="from" aria-label="From date" value="<?= e($f['from']) ?>"></label>
        <label for="sales-to"><span>To</span><input id="sales-to" type="date" name="to" aria-label="To date" value="<?= e($f['to']) ?>"></label>
        <div class="sales-filter-actions"><button class="btn btn-primary" type="submit">Show results</button><?php if ($hasFilters): ?><a class="btn btn-ghost" href="<?= e(url('sales')) ?>">Clear all</a><?php endif; ?></div>
    </form>
</details>

<?php if ($hasFilters): ?>
<div class="sales-active-filters" aria-label="Active filters"><span>Active filters</span>
    <?php if ($f['q'] !== ''): ?><a href="<?= e($filterUrl(['q'=>''])) ?>">Search: “<?= e($f['q']) ?>” <b>×</b></a><?php endif; ?>
    <?php if ($f['channel'] !== ''): ?><a href="<?= e($filterUrl(['channel'=>''])) ?>"><?= e($f['channel'] === 'pos' ? 'In store' : 'Online') ?> <b>×</b></a><?php endif; ?>
    <?php if ($f['status'] !== ''): ?><a href="<?= e($filterUrl(['status'=>''])) ?>"><?= e(ucfirst($f['status'])) ?> <b>×</b></a><?php endif; ?>
    <?php if ($f['payment'] !== ''): ?><a href="<?= e($filterUrl(['payment'=>''])) ?>"><?= e(strtoupper($f['payment'])) ?> <b>×</b></a><?php endif; ?>
    <?php if (($f['source'] ?? '') !== ''): ?><a href="<?= e($filterUrl(['source'=>''])) ?>"><?= e(ucfirst($f['source'])) ?> <b>×</b></a><?php endif; ?>
    <?php if ($f['from'] !== ''): ?><a href="<?= e($filterUrl(['from'=>''])) ?>">From <?= e($f['from']) ?> <b>×</b></a><?php endif; ?>
    <?php if ($f['to'] !== ''): ?><a href="<?= e($filterUrl(['to'=>''])) ?>">To <?= e($f['to']) ?> <b>×</b></a><?php endif; ?>
</div>
<?php endif; ?>

<div class="sales-results-head"><div><h2><?= $hasFilters ? 'Matching orders' : 'Recent orders' ?></h2><p><?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?></p></div></div>

<?php if (!$rows): ?>
    <div class="empty-state sales-empty"><h2>No sales match these filters</h2><p class="muted">Check the order number or remove a filter to widen the results.</p><a class="btn btn-primary" href="<?= e(url('sales')) ?>">Show all sales</a></div>
<?php else: ?>
<div class="table-wrap sales-table-wrap">
    <table class="table sales-table mobile-cards">
        <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Channel</th><th>Source</th><th>Payment</th><th class="num">Items</th><th class="num">Total</th><th>Status</th><th><span class="sr-only">Action</span></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $s): [$statusLabel, $statusClass] = status_badge($s['status']); $src = $s['sale_source'] ?? 'walk-in'; ?>
            <tr>
                <td data-label="Order"><div class="cell-main"><a href="<?= e(url('sales/' . $s['id'])) ?>"><?= e($s['sale_number']) ?></a></div><div class="cell-sub"><?php if ((int) $s['offline_created'] === 1): ?><span class="badge badge-blue">Saved offline</span><?php elseif ((int) $s['return_count'] > 0): ?><?= (int) $s['return_count'] ?> return record<?= (int) $s['return_count'] === 1 ? '' : 's' ?><?php endif; ?><?php if (!empty($s['whatsapp_enquiry_id'])): ?> <span class="badge badge-green">WA #<?= (int)$s['whatsapp_enquiry_id'] ?></span><?php endif; ?></div></td>
                <td data-label="Date"><div class="cell-main"><?= e(date('d M Y', strtotime($s['created_at']))) ?></div><div class="cell-sub"><?= e(date('h:i A', strtotime($s['created_at']))) ?></div></td>
                <td data-label="Customer"><div class="cell-main"><?= $s['customer_name'] ? e($s['customer_name']) : 'Walk-in' ?></div><?php if ($s['customer_phone']): ?><div class="cell-sub"><?= e($s['customer_phone']) ?></div><?php endif; ?></td>
                <td data-label="Channel"><span class="badge badge-<?= $s['channel'] === 'online' ? 'blue' : 'gray' ?>"><?= $s['channel'] === 'online' ? 'Online' : 'In store' ?></span></td>
                <td data-label="Source"><span class="badge badge-<?= $src === 'whatsapp' ? 'green' : ($src === 'phone' ? 'blue' : 'gray') ?>"><?= e(ucfirst($src)) ?></span></td>
                <td data-label="Payment"><div class="cell-main"><?= e(strtoupper($s['payment_method'])) ?></div><?php if ($s['payment_ref']): ?><div class="cell-sub"><?= e($s['payment_ref']) ?></div><?php endif; ?></td>
                <td data-label="Items" class="num"><?= (int) $s['item_count'] ?></td>
                <td data-label="Total" class="num"><strong><?= money($s['total']) ?></strong></td>
                <td data-label="Status"><span class="badge badge-<?= $statusClass ?>"><?= e($statusLabel) ?></span></td>
                <td data-label="Action"><a class="btn btn-outline btn-sm" href="<?= e(url('sales/' . $s['id'])) ?>">Open</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if ($pages > 1): ?>
<nav class="pager" aria-label="Sales pages">
    <?php $pageFilters = $f; ?>
    <?php if ($page > 1): $pageFilters['page']=$page-1; ?><a class="btn btn-ghost btn-sm" href="<?= e(url('sales') . query_string($pageFilters)) ?>">← Previous</a><?php endif; ?>
    <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
    <?php if ($page < $pages): $pageFilters['page']=$page+1; ?><a class="btn btn-ghost btn-sm" href="<?= e(url('sales') . query_string($pageFilters)) ?>">Next →</a><?php endif; ?>
</nav>
<?php endif; ?>

<script>if (window.matchMedia('(max-width:700px)').matches) { var filters = document.querySelector('.sales-filter-panel'); if (filters) filters.removeAttribute('open'); }</script>
