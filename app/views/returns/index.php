<?php $hasFilters = implode('', $filters) !== ''; ?>
<div class="page-head returns-head">
    <div><div class="section-kicker">After-sales desk</div><h1>Returns</h1><p class="lede">Review customer requests, refund exposure, and stock outcomes in one place.</p></div>
    <a class="btn btn-primary" href="<?= e(url('returns/new')) ?>">Start a return</a>
</div>

<section class="returns-kpis" aria-label="Returns summary">
    <article class="kpi"><div class="kpi-label">Needs approval</div><div class="kpi-value text-warning"><?= number_format($summary['pending']) ?></div><div class="kpi-hint">Decision required</div></article>
    <article class="kpi"><div class="kpi-label">Completed</div><div class="kpi-value"><?= number_format($summary['completed']) ?></div><div class="kpi-hint">Approved returns</div></article>
    <article class="kpi"><div class="kpi-label">Refunded</div><div class="kpi-value"><?= money($summary['refunded']) ?></div><div class="kpi-hint">Completed value</div></article>
    <article class="kpi"><div class="kpi-label">Rejected</div><div class="kpi-value"><?= number_format($summary['rejected']) ?></div><div class="kpi-hint">Closed without refund</div></article>
</section>

<details class="filter-panel" <?= $hasFilters ? 'open' : '' ?>>
    <summary><span>Search and filters</span><?php if($hasFilters): ?><span class="badge badge-blue">Active</span><?php endif; ?></summary>
    <form method="get" action="<?= e(url('returns')) ?>" class="returns-filter-grid">
        <label class="field"><span>Return, sale, customer, or phone</span><input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="R-…, S-…, name, phone"></label>
        <label class="field"><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach(['pending'=>'Pending approval','completed'=>'Completed','rejected'=>'Rejected'] as $value=>$label): ?><option value="<?= $value ?>" <?= $filters['status']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label class="field"><span>From</span><input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
        <label class="field"><span>To</span><input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
        <div class="filter-actions"><button class="btn btn-primary" type="submit">Apply filters</button><?php if($hasFilters): ?><a class="btn btn-ghost" href="<?= e(url('returns')) ?>">Clear</a><?php endif; ?></div>
    </form>
</details>

<?php if (!$returns): ?>
<section class="inline-empty returns-empty"><b><?= $hasFilters ? 'No returns match these filters' : 'No returns recorded yet' ?></b><span><?= $hasFilters ? 'Adjust the status, date, or search terms.' : 'Start with a sale or customer receipt.' ?></span><a class="btn btn-primary" href="<?= e(url('returns/new')) ?>">Start a return</a></section>
<?php else: ?>
<section class="return-directory" aria-label="Return records">
    <?php foreach($returns as $r): [$label,$cls]=status_badge($r['status']); ?>
    <a class="return-record <?= $r['status']==='pending'?'is-pending':'' ?>" href="<?= e(url('returns/'.$r['id'])) ?>">
        <span class="return-record-main"><span class="return-record-title"><b><?= e($r['return_number']) ?></b><span class="badge badge-<?= $cls ?>"><?= e($r['status']==='pending'?'Pending approval':$label) ?></span></span><small><?= e($r['customer_name'] ?: 'Walk-in customer') ?> · <?= e($r['customer_phone'] ?: 'No phone') ?></small></span>
        <span><small>Original sale</small><b><?= e($r['sale_number']) ?></b></span>
        <span><small>Returned</small><b><?= number_format((int)$r['unit_count']) ?> unit<?= (int)$r['unit_count']===1?'':'s' ?></b></span>
        <span class="return-refund"><small>Refund</small><b><?= money($r['refund_amount']) ?></b></span>
        <span><small>Requested</small><b><?= e(date('d M Y',strtotime($r['created_at']))) ?></b></span>
        <span class="return-record-arrow" aria-hidden="true">→</span>
    </a>
    <?php endforeach; ?>
</section>
<?php endif; ?>
