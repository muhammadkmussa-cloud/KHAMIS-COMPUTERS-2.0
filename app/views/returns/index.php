<div class="page-head">
    <div>
        <h1>Returns</h1>
        <p class="lede">Refunds and reversals across all orders.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('returns/new')) ?>">+ New return</a>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Return</th>
                <th>Sale</th>
                <th>Customer</th>
                <th class="num">Items</th>
                <th class="num">Refund</th>
                <th>Status</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$returns): ?>
            <tr><td colspan="8" class="muted" style="text-align:center;padding:26px">No returns yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($returns as $r): [$label, $cls] = status_badge($r['status']); ?>
            <tr>
                <td class="cell-main"><a href="<?= e(url('returns/' . $r['id'])) ?>"><?= e($r['return_number']) ?></a></td>
                <td class="cell-sub"><a href="<?= e(url('sales/' . $r['sale_id'])) ?>"><?= e($r['sale_number']) ?></a></td>
                <td><?= e($r['customer_name'] ?? 'Walk-in') ?></td>
                <td class="num"><?= (int) $r['item_count'] ?></td>
                <td class="num" style="font-weight:700"><?= money($r['refund_amount']) ?></td>
                <td><span class="badge badge-<?= $cls ?>"><?= e($label) ?></span></td>
                <td class="cell-sub"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                <td><div class="row-actions"><a class="btn btn-primary btn-sm" href="<?= e(url('returns/' . $r['id'])) ?>">View</a></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
