<?php [$label, $cls] = status_badge($r['status']); ?>
<div class="page-head">
    <div>
        <div class="section-kicker">Return</div>
        <h1><?= e($r['return_number']) ?></h1>
        <p class="lede">
            <span class="badge badge-<?= $cls ?>"><?= e($label) ?></span>
            &nbsp; against <a href="<?= e(url('sales/' . $r['sale_id'])) ?>"><?= e($r['sale_number']) ?></a>
            &nbsp; · <?= e(date('d M Y · h:i A', strtotime($r['created_at']))) ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('returns')) ?>">← Returns</a>
        <?php if ($r['status'] === 'pending'): ?>
            <form method="post" action="<?= e(url('returns/' . $r['id'] . '/reject')) ?>" onsubmit="return confirm('Reject this return?');" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn-danger-ghost" type="submit">Reject</button>
            </form>
            <form method="post" action="<?= e(url('returns/' . $r['id'] . '/approve')) ?>" onsubmit="return confirm('Approve and restock the returned items?');" style="display:inline">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit">Approve &amp; restock</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2" style="margin-top:0;align-items:start">
    <div class="card">
        <h3>Items</h3>
        <table class="table">
            <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Refund</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr>
                    <td>
                        <div class="cell-main"><?= e($i['product_name']) ?></div>
                        <div class="cell-sub"><?= e($i['sku']) ?><?= $i['serial_number'] ? ' · SN ' . e($i['serial_number']) : '' ?></div>
                    </td>
                    <td class="num"><?= (int) $i['quantity'] ?></td>
                    <td class="num"><?= money($i['refund_amount']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="2" style="text-align:right;font-weight:700">Total refund</td>
                    <td class="num" style="font-weight:700"><?= money($r['refund_amount']) ?></td></tr>
            </tfoot>
        </table>
    </div>

    <div class="card">
        <h3>Details</h3>
        <dl class="kv">
            <dt>Customer</dt><dd><?= e($r['customer_name'] ?? 'Walk-in') ?></dd>
            <dt>Phone</dt><dd><?= e($r['customer_phone'] ?? '—') ?></dd>
            <dt>Channel</dt><dd><?= e($r['channel']) ?></dd>
            <dt>Reason</dt><dd><?= $r['reason'] !== '' ? e($r['reason']) : '—' ?></dd>
        </dl>
    </div>
</div>
