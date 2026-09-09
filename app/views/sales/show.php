<?php [$statusLabel, $statusClass] = status_badge($sale['status']); ?>
<div class="page-head">
    <div>
        <div class="section-kicker">Order</div>
        <h1><?= e($sale['sale_number']) ?></h1>
        <p class="lede">
            <span class="badge badge-<?= $statusClass ?>"><?= e($statusLabel) ?></span>
            <span class="badge badge-<?= $sale['channel'] === 'online' ? 'blue' : 'gray' ?>"><?= e($sale['channel']) ?></span>
            <?php if ((int) $sale['offline_created'] === 1): ?><span class="badge badge-blue">offline</span><?php endif; ?>
            &nbsp; <?= e(date('d M Y · h:i A', strtotime($sale['created_at']))) ?>
        </p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('sales')) ?>">← Sales</a>
        <a class="btn btn-outline" href="<?= e(url('sales/' . $sale['id'] . '/pdf')) ?>">Download PDF</a>
        <button class="btn btn-ghost" type="button"
                onclick="window.open('<?= e(url('sales/' . $sale['id'] . '/print')) ?>', 'receipt', 'width=420,height=720')">Print receipt</button>
        <?php if ($returnable && $sale['status'] === 'completed'): ?>
            <a class="btn btn-primary" href="<?= e(url('returns/new?sale=' . $sale['id'])) ?>">Create return</a>
        <?php endif; ?>
        <?php if (Auth::isAdmin() && $sale['status'] === 'pending' && $sale['payment_method'] === 'mpesa'): ?>
            <form method="post" action="<?= e(url('sales/' . $sale['id'] . '/mpesa-mark-paid')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline">Mark paid</button>
            </form>
            <form method="post" action="<?= e(url('sales/' . $sale['id'] . '/mpesa-retry')) ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline">Re-send M-PESA prompt</button>
            </form>
        <?php endif; ?>
        <?php if (Auth::isAdmin() && in_array($sale['status'], ['completed', 'pending'], true)): ?>
            <form method="post" action="<?= e(url('sales/' . $sale['id'] . '/void')) ?>" onsubmit="return confirm('Void this sale? Stock will be restored and the sale marked cancelled.');" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-danger-ghost">Void sale</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2" style="margin-top:0;align-items:start">
    <div class="card">
        <div class="receipt-meta" style="grid-template-columns:1fr 1fr">
            <div><span>Payment</span><b><?= e(strtoupper($sale['payment_method'])) ?><?= $sale['payment_ref'] ? ' · ' . e($sale['payment_ref']) : '' ?></b></div>
            <div><span>Fulfilment</span><b><?= e(ucfirst($sale['fulfillment'])) ?></b></div>
            <div><span>Customer</span><b><?= e($sale['customer_name'] ?? 'Walk-in') ?></b></div>
            <div><span>Served by</span><b><?= e($sale['user_name'] ?? '—') ?></b></div>
            <?php if ($sale['customer_phone']): ?><div><span>Phone</span><b><?= e($sale['customer_phone']) ?></b></div><?php endif; ?>
            <?php if ($sale['customer_email']): ?><div><span>Email</span><b><?= e($sale['customer_email']) ?></b></div><?php endif; ?>
        </div>
        <?php if ($sale['delivery_address']): ?>
            <p class="muted" style="margin:8px 0 0"><b>Deliver to:</b> <?= e($sale['delivery_address']) ?><?= $sale['delivery_zone'] ? ' <span class="badge badge-gray">' . e($sale['delivery_zone']) . '</span>' : '' ?></p>
        <?php endif; ?>
        <?php if ($sale['status'] === 'pending' && $sale['payment_method'] === 'mpesa'): ?>
            <div class="alert alert-info" style="margin:10px 0 0">
                Awaiting M-PESA payment from <b><?= e($sale['customer_phone'] ?? 'customer') ?></b>.
                <?= $sale['payment_ref'] ? 'Latest receipt: ' . e($sale['payment_ref']) : 'Use "Mark paid" once the customer confirms the M-PESA SMS, or "Re-send M-PESA prompt" to prompt them again.' ?>
            </div>
        <?php endif; ?>

        <hr>
        <table class="table">
            <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr>
                    <td>
                        <div class="cell-main"><?= e($i['product_name']) ?></div>
                        <div class="cell-sub"><?= e($i['sku']) ?><?= $i['serial_number'] ? ' · SN ' . e($i['serial_number']) : '' ?></div>
                        <?php if (!empty($i['warranty_expires'])): ?>
                            <div class="cell-sub" style="<?= $i['warranty_expires'] < date('Y-m-d') ? 'color:var(--red)' : '' ?>">
                                Warranty until <?= e($i['warranty_expires']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="num"><?= (int) $i['quantity'] ?></td>
                    <td class="num"><?= money($i['unit_price']) ?></td>
                    <td class="num"><?= money($i['line_total']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div class="receipt-totals">
            <div><span>Subtotal</span><b><?= money($sale['subtotal']) ?></b></div>
            <?php if ((float) $sale['discount'] > 0): ?><div class="discount"><span>Discount</span><b>− <?= money($sale['discount']) ?></b></div><?php endif; ?>
            <div><span>VAT (<?= e((string) vat_rate()) ?>%)</span><b><?= money($sale['tax_amount']) ?></b></div>
            <?php if ((float) $sale['delivery_fee'] > 0): ?><div><span>Delivery (<?= e($sale['delivery_zone'] ?: 'delivery') ?>)</span><b><?= money($sale['delivery_fee']) ?></b></div><?php endif; ?>
            <div class="grand"><span>Total</span><b><?= money($sale['total']) ?></b></div>
        </div>
    </div>

    <div class="card">
        <h3>Returns</h3>
        <?php if (!$returns): ?>
            <p class="muted">No returns on this order yet.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Return</th><th class="num">Refund</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($returns as $r): [$rl, $rc] = status_badge($r['status']); ?>
                    <tr>
                        <td class="cell-main"><?= e($r['return_number']) ?></td>
                        <td class="num"><?= money($r['refund_amount']) ?></td>
                        <td><span class="badge badge-<?= $rc ?>"><?= e($rl) ?></span></td>
                        <td><div class="row-actions"><a class="btn btn-primary btn-sm" href="<?= e(url('returns/' . $r['id'])) ?>">View</a></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
