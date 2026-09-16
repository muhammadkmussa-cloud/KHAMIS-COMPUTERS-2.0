<?php
include APP_PATH . '/views/partials/inventory-tabs.php';
$hasFilters = $filters['q']!=='' || $filters['supplier_id']!=='' || $filters['date_from']!=='' || $filters['date_to']!=='';
$exportUrl = url('grn/export') . query_string($filters);
?>
<div class="page-head grn-page-head">
    <div><div class="section-kicker">Purchasing ledger</div><h1>Goods received</h1><p class="lede">Every posted delivery, its supplier reference, quantities, and purchase value.</p></div>
    <div class="page-actions"><a class="btn btn-primary" href="<?= e(url('grn/new')) ?>">Receive stock</a><details class="action-menu"><summary class="btn btn-ghost">More</summary><div><a href="<?= e($exportUrl) ?>">Export filtered CSV</a><a href="<?= e(url('suppliers')) ?>">Supplier directory</a></div></details></div>
</div>

<section class="inventory-kpis grn-kpis" aria-label="Receiving summary">
    <div><span>Receipts</span><b><?= number_format($summary['records']) ?></b><small><?= $hasFilters?'Matching filters':'All posted GRNs' ?></small></div>
    <div><span>Units received</span><b><?= number_format($summary['items']) ?></b><small>Across all lines</small></div>
    <div><span>Purchase value</span><b><?= money($summary['total']) ?></b><small>Recorded unit costs</small></div>
</section>

<details class="inventory-filter-panel grn-filter-panel" <?= $hasFilters?'open':'' ?>>
    <summary><span>Search and filters<?= $hasFilters?' · active':'' ?></span><span aria-hidden="true">⌄</span></summary>
    <form method="get" action="<?= e(url('grn')) ?>" class="grn-filter-form">
        <label><span>GRN or supplier reference</span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="GRN-… or delivery note"></label>
        <label><span>Supplier</span><select name="supplier_id"><option value="">All suppliers</option><?php foreach($suppliers as $supplier): ?><option value="<?= (int)$supplier['id'] ?>" <?= (string)$supplier['id']===$filters['supplier_id']?'selected':'' ?>><?= e($supplier['name']) ?></option><?php endforeach; ?></select></label>
        <label><span>From</span><input type="date" name="date_from" value="<?= e($filters['date_from']) ?>"></label>
        <label><span>To</span><input type="date" name="date_to" value="<?= e($filters['date_to']) ?>"></label>
        <div class="inventory-filter-actions"><button class="btn btn-primary" type="submit">Show receipts</button><?php if($hasFilters): ?><a class="btn btn-ghost" href="<?= e(url('grn')) ?>">Clear</a><?php endif; ?></div>
    </form>
</details>

<div class="inventory-results-head"><div><h2><?= $hasFilters?'Matching receipts':'Receiving history' ?></h2><p><?= count($grns) ?> record<?= count($grns)===1?'':'s' ?></p></div></div>
<?php if(!$grns): ?><div class="empty-state"><h2>No goods-received notes found</h2><p class="muted"><?= $hasFilters?'Change the supplier, reference, or date range.':'Post the first supplier delivery to begin the purchasing ledger.' ?></p><a class="btn btn-primary" href="<?= e(url('grn/new')) ?>">Receive stock</a></div><?php else: ?>
<div class="table-wrap grn-table-wrap"><table class="table grn-table"><thead><tr><th>GRN / reference</th><th>Supplier</th><th class="num">Lines</th><th class="num">Units</th><th class="num">Purchase value</th><th>Status</th><th>Received</th><th><span class="sr-only">Action</span></th></tr></thead><tbody>
<?php foreach($grns as $grn): ?><tr><td><a class="cell-main" href="<?= e(url('grn/'.$grn['id'])) ?>"><?= e($grn['grn_number']) ?></a><span class="cell-sub"><?= e($grn['supplier_reference']?:'No supplier reference') ?></span></td><td><span class="cell-main"><?= e($grn['supplier']) ?></span><span class="cell-sub">by <?= e($grn['user_name']??'System') ?></span></td><td class="num"><?= number_format($grn['item_count']) ?></td><td class="num"><?= number_format($grn['total_quantity']) ?></td><td class="num"><strong><?= money($grn['total_cost']) ?></strong></td><td><span class="badge badge-green">Posted</span></td><td><span class="cell-main"><?= e(date('d M Y',strtotime($grn['created_at']))) ?></span><span class="cell-sub"><?= e(date('h:i A',strtotime($grn['created_at']))) ?></span></td><td><a class="btn btn-outline btn-sm" href="<?= e(url('grn/'.$grn['id'])) ?>">Open</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
