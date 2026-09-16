<?php
[$statusLabel, $statusClass] = status_badge($sale['status']);
$isPendingMpesa = $sale['status'] === 'pending' && $sale['payment_method'] === 'mpesa';
$itemQuantity = array_sum(array_map(fn ($item) => (int) $item['quantity'], $items));
$paymentLabel = strtoupper((string) $sale['payment_method']);
if ($sale['payment_ref']) $paymentLabel .= ' · ' . $sale['payment_ref'];
$mpesaStatus = $mpesa['status'] ?? null;
?>

<div class="sale-identity-bar">
    <div class="sale-identity-main">
        <a class="sale-back" href="<?= e(url('sales')) ?>">← Sales</a>
        <div><div class="section-kicker">Order record</div><h1><?= e($sale['sale_number']) ?></h1></div>
    </div>
    <div class="sale-identity-status"><span class="badge badge-<?= $statusClass ?>"><?= e($statusLabel) ?></span><span class="badge badge-<?= $sale['channel'] === 'online' ? 'blue' : 'gray' ?>"><?= $sale['channel'] === 'online' ? 'Online' : 'In store' ?></span><?php if ((int) $sale['offline_created'] === 1): ?><span class="badge badge-blue">Saved offline</span><?php endif; ?><time><?= e(date('d M Y · h:i A', strtotime($sale['created_at']))) ?></time></div>
</div>

<?php if ($isPendingMpesa): ?>
<section class="payment-recovery-card <?= $mpesaStatus === 'failed' ? 'has-failed' : '' ?>">
    <div class="payment-recovery-head"><div><div class="section-kicker">Payment exception</div><h2><?= $mpesaStatus === 'failed' ? 'The last M-PESA attempt failed' : 'Waiting for M-PESA confirmation' ?></h2><p>Stock remains reserved while this order is pending. Confirm the customer and amount before taking action.</p></div><span class="payment-state-dot" aria-hidden="true"></span></div>
    <ol class="payment-ops-timeline">
        <li class="complete"><span>1</span><div><b>Order created</b><small><?= e(date('d M · h:i A', strtotime($sale['created_at']))) ?></small></div></li>
        <li class="<?= $mpesaStatus === 'failed' ? 'failed' : 'current' ?>"><span>2</span><div><b><?= $mpesaStatus === 'failed' ? 'Prompt failed' : 'Prompt sent' ?></b><small><?= $mpesa ? e(date('d M · h:i A', strtotime($mpesa['created_at']))) : 'No attempt record found' ?></small></div></li>
        <li><span>3</span><div><b>Payment confirmation</b><small>Receipt code required</small></div></li>
    </ol>
    <?php if ($mpesa && $mpesaStatus === 'failed'): ?><div class="payment-failure-reason"><b>Safaricom response</b><span><?= e($mpesa['result_desc'] ?: 'The request did not complete.') ?></span></div><?php endif; ?>
    <div class="payment-recovery-facts"><span>Customer <b><?= e($sale['customer_phone'] ?: 'No phone recorded') ?></b></span><span>Amount <b><?= money($sale['total']) ?></b></span><span>Stock <b>Reserved</b></span></div>
    <?php if (Auth::isAdmin()): ?><div class="payment-recovery-actions"><button class="btn btn-primary" type="button" data-open-dialog="mpesa-retry-dialog">Send another prompt</button><button class="btn btn-outline" type="button" data-open-dialog="mpesa-paid-dialog">Verify receipt and mark paid</button></div><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($sale['status'] === 'cancelled'): ?>
<div class="sale-cancelled-banner"><div><b>This sale is cancelled</b><span>Stock was returned to inventory. Any customer payment must be reconciled separately.</span></div><?php if ($voidAudit): ?><div><small>Voided by</small><b><?= e($voidAudit['user_name'] ?? 'System') ?> · <?= e(date('d M Y, h:i A', strtotime($voidAudit['created_at']))) ?></b><span><?= e($voidAudit['reason'] ?: 'No reason recorded') ?></span></div><?php endif; ?></div>
<?php endif; ?>

<div class="sale-detail-grid">
    <section class="sale-document card">
        <div class="sale-document-head"><div><span>Order total</span><strong><?= money($sale['total']) ?></strong></div><div><span><?= $itemQuantity ?> item<?= $itemQuantity === 1 ? '' : 's' ?></span><span>Prices below exclude VAT</span></div></div>

        <section class="sale-items-section" aria-labelledby="sale-items-title">
            <div class="sale-section-head"><h2 id="sale-items-title">Items</h2><span><?= count($items) ?> line<?= count($items) === 1 ? '' : 's' ?></span></div>
            <div class="sale-item-list">
                <?php foreach ($items as $i): ?>
                <article class="sale-item-row">
                    <div class="sale-item-main"><b><?= e($i['product_name']) ?></b><span><?= e($i['sku']) ?><?php if ($i['serial_number']): ?> · Serial <?= e($i['serial_number']) ?><?php endif; ?></span><?php if (!empty($i['warranty_expires'])): ?><small class="<?= $i['warranty_expires'] < date('Y-m-d') ? 'expired' : '' ?>">Warranty <?= $i['warranty_expires'] < date('Y-m-d') ? 'expired' : 'until' ?> <?= e(date('d M Y', strtotime($i['warranty_expires']))) ?></small><?php endif; ?></div>
                    <div class="sale-item-qty"><span>Qty</span><b><?= (int) $i['quantity'] ?></b></div>
                    <div class="sale-item-price"><span><?= money($i['unit_price']) ?> each</span><b><?= money($i['line_total']) ?></b></div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="sale-totals">
            <div><span>Subtotal before VAT</span><b><?= money($sale['subtotal']) ?></b></div>
            <?php if ((float) $sale['discount'] > 0): ?><div class="discount"><span>Discount</span><b>− <?= money($sale['discount']) ?></b></div><?php endif; ?>
            <div><span>VAT (<?= e((string) vat_rate()) ?>%)</span><b><?= money($sale['tax_amount']) ?></b></div>
            <?php if ((float) $sale['delivery_fee'] > 0): ?><div><span>Delivery · <?= e($sale['delivery_zone'] ?: 'delivery') ?></span><b><?= money($sale['delivery_fee']) ?></b></div><?php endif; ?>
            <div class="grand"><span>Total</span><b><?= money($sale['total']) ?></b></div>
        </div>
    </section>

    <aside class="sale-sidebar">
        <section class="card sale-info-card"><div class="sale-section-head"><h2>Order information</h2></div><dl>
            <div><dt>Payment</dt><dd><?= e($paymentLabel) ?></dd></div>
            <div><dt>Fulfilment</dt><dd><?= e(ucfirst($sale['fulfillment'])) ?><?= $sale['delivery_zone'] ? ' · ' . e($sale['delivery_zone']) : '' ?></dd></div>
            <div><dt>Customer</dt><dd><?= e($sale['customer_name'] ?: 'Walk-in customer') ?></dd></div>
            <?php if ($sale['customer_phone']): ?><div><dt>Phone</dt><dd><a href="tel:<?= e(preg_replace('/\D+/', '', $sale['customer_phone'])) ?>"><?= e($sale['customer_phone']) ?></a></dd></div><?php endif; ?>
            <?php if ($sale['customer_email']): ?><div><dt>Email</dt><dd><a href="mailto:<?= e($sale['customer_email']) ?>"><?= e($sale['customer_email']) ?></a></dd></div><?php endif; ?>
            <?php if ($sale['delivery_address']): ?><div><dt>Delivery address</dt><dd><?= e($sale['delivery_address']) ?></dd></div><?php endif; ?>
            <div><dt>Recorded by</dt><dd><?= e($sale['user_name'] ?: ($sale['channel'] === 'online' ? 'Online checkout' : '—')) ?></dd></div>
        </dl></section>

        <section class="card sale-actions-card"><div class="sale-section-head"><h2>Receipt and actions</h2></div>
            <div class="receipt-action-grid"><a class="btn btn-primary" target="_blank" rel="noopener" href="<?= e(url('sales/' . $sale['id'] . '/pdf')) ?>">Open PDF receipt</a><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= e(url('sales/' . $sale['id'] . '/print?size=80')) ?>">Print 80mm</a><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= e(url('sales/' . $sale['id'] . '/print?size=58')) ?>">Print 58mm</a></div>
            <?php if ($returnable && $sale['status'] === 'completed'): ?><a class="btn btn-outline btn-block return-action" href="<?= e(url('returns/new?sale=' . $sale['id'])) ?>">Start a return</a><?php endif; ?>
            <?php if (Auth::isAdmin() && in_array($sale['status'], ['completed', 'pending'], true)): ?><button class="btn btn-danger-ghost btn-block" type="button" data-open-dialog="void-sale-dialog">Void this sale</button><?php endif; ?>
            <p>PDF is best for email or A4 printing. Choose the thermal width that matches the installed roll.</p>
        </section>

        <section class="card sale-returns-card"><div class="sale-section-head"><h2>Returns</h2><span><?= count($returns) ?></span></div>
            <?php if (!$returns): ?><p class="muted">No returns have been recorded for this order.</p><?php else: ?><div class="sale-return-list"><?php foreach ($returns as $r): [$rl,$rc]=status_badge($r['status']); ?><a href="<?= e(url('returns/' . $r['id'])) ?>"><span><b><?= e($r['return_number']) ?></b><small><?= money($r['refund_amount']) ?></small></span><span class="badge badge-<?= $rc ?>"><?= e($rl) ?></span></a><?php endforeach; ?></div><?php endif; ?>
        </section>
    </aside>
</div>

<?php if (Auth::isAdmin() && in_array($sale['status'], ['completed', 'pending'], true)): ?>
<dialog class="workflow-dialog" id="void-sale-dialog">
    <form method="post" action="<?= e(url('sales/' . $sale['id'] . '/void')) ?>">
        <?= csrf_field() ?>
        <div class="workflow-dialog-head danger"><span aria-hidden="true">!</span><div><div class="section-kicker">Destructive action</div><h2>Void <?= e($sale['sale_number']) ?>?</h2></div></div>
        <div class="impact-list"><div><span>Sale total</span><b><?= money($sale['total']) ?></b></div><div><span>Inventory</span><b><?= $itemQuantity ?> item<?= $itemQuantity === 1 ? '' : 's' ?> restored</b></div><div><span>Payment</span><b><?= $sale['payment_ref'] ? 'Not automatically refunded' : 'No automatic refund' ?></b></div></div>
        <label class="field" for="void-reason"><span>Reason for voiding</span><textarea id="void-reason" name="reason" rows="3" minlength="5" maxlength="500" required placeholder="Explain the error or cancellation"></textarea><small>This appears in the order audit history.</small></label>
        <label class="dialog-confirm-check"><input type="checkbox" name="confirm_void" value="1" required><span>I understand that payment reconciliation or refunding is a separate action.</span></label>
        <div class="workflow-dialog-actions"><button class="btn btn-ghost" type="button" data-close-dialog>Keep sale</button><button class="btn btn-danger" type="submit">Void sale and restore stock</button></div>
    </form>
</dialog>
<?php endif; ?>

<?php if (Auth::isAdmin() && $isPendingMpesa): ?>
<dialog class="workflow-dialog" id="mpesa-retry-dialog"><form method="post" action="<?= e(url('sales/' . $sale['id'] . '/mpesa-retry')) ?>"><?= csrf_field() ?><div class="workflow-dialog-head"><span aria-hidden="true">↻</span><div><div class="section-kicker">Payment recovery</div><h2>Send another M-PESA prompt?</h2></div></div><div class="impact-list"><div><span>Phone</span><b><?= e($sale['customer_phone'] ?: 'Missing') ?></b></div><div><span>Amount</span><b><?= money($sale['total']) ?></b></div></div><p class="dialog-copy">Ask the customer to ignore any older prompt. The system prevents another request within two minutes.</p><div class="workflow-dialog-actions"><button class="btn btn-ghost" type="button" data-close-dialog>Cancel</button><button class="btn btn-primary" type="submit">Send secure prompt</button></div></form></dialog>

<dialog class="workflow-dialog" id="mpesa-paid-dialog"><form method="post" action="<?= e(url('sales/' . $sale['id'] . '/mpesa-mark-paid')) ?>"><?= csrf_field() ?><div class="workflow-dialog-head"><span aria-hidden="true">✓</span><div><div class="section-kicker">Manual verification</div><h2>Confirm payment evidence</h2></div></div><div class="alert alert-info">Use this only after checking the payment in the business M-PESA records. A customer SMS alone is not sufficient.</div><label class="field" for="manual-receipt"><span>M-PESA receipt code</span><input id="manual-receipt" name="receipt" required minlength="6" maxlength="20" pattern="[A-Za-z0-9]{6,20}" autocomplete="off" placeholder="e.g. TQH7ABC123"></label><label class="dialog-confirm-check"><input type="checkbox" name="confirm_received" value="1" required><span>I verified the receipt, amount, and business account.</span></label><div class="workflow-dialog-actions"><button class="btn btn-ghost" type="button" data-close-dialog>Cancel</button><button class="btn btn-primary" type="submit">Mark payment confirmed</button></div></form></dialog>
<?php endif; ?>

<script>
document.querySelectorAll('[data-open-dialog]').forEach(function (button) { button.addEventListener('click', function () { var dialog=document.getElementById(button.dataset.openDialog); if(dialog) dialog.showModal(); }); });
document.querySelectorAll('[data-close-dialog]').forEach(function (button) { button.addEventListener('click', function () { var dialog=button.closest('dialog'); if(dialog) dialog.close(); }); });
document.querySelectorAll('.workflow-dialog').forEach(function (dialog) { dialog.addEventListener('click', function (event) { if(event.target===dialog) dialog.close(); }); });
</script>
