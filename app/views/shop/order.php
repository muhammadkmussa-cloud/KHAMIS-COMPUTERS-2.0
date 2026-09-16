<?php
$pending = $sale['status'] === 'pending';
$cancelled = in_array($sale['status'], ['cancelled', 'voided'], true);
$nameParts = preg_split('/\s+/', trim((string) ($sale['customer_name'] ?? ''))) ?: [];
$firstName = $nameParts[0] ?? '';
$paid = !$pending && !$cancelled && !empty($sale['payment_ref']);
$supportPhone = Setting::get('shop_phone', '');
?>
<div class="order-confirm">
    <div class="confirmation-hero <?= $pending ? 'is-pending' : ($cancelled ? 'is-cancelled' : 'is-confirmed') ?>">
        <div class="big-check" aria-hidden="true"><?= $pending ? '⌛' : ($cancelled ? '!' : '✓') ?></div>
        <div class="section-kicker"><?= $pending ? 'Payment in progress' : ($cancelled ? 'Payment not completed' : 'Order received') ?></div>
        <h1><?= $pending ? 'Check your phone' : ($cancelled ? 'Let’s get you back on track' : 'Thank you' . ($firstName !== '' ? ', ' . e($firstName) : '')) ?></h1>
        <?php if ($pending): ?>
            <p>Approve the M-PESA prompt sent to <b><?= e($sale['customer_phone'] ?? '') ?></b>. Keep this page open while we confirm the payment.</p>
        <?php elseif ($cancelled): ?>
            <p>The payment did not complete, so this order cannot proceed. You can restore the items and try checkout again.</p>
        <?php else: ?>
            <p>Order <b><?= e($sale['sale_number']) ?></b> is confirmed. We will contact you about <?= $sale['fulfillment'] === 'delivery' ? 'delivery' : 'store pickup' ?>.</p>
        <?php endif; ?>
    </div>

    <?php if ($pending): ?>
        <section class="payment-status-panel" id="payment-status-panel" data-status-url="<?= e(url('shop/api/order-status/' . $sale['id'])) ?>">
            <div class="payment-pulse" aria-hidden="true"></div>
            <div><b id="payment-status-title">Waiting for M-PESA confirmation</b><p id="payment-status-copy">This usually takes less than a minute. Next check in <span id="payment-countdown">3</span>s.</p></div>
        </section>
    <?php endif; ?>

    <ol class="order-timeline" aria-label="Order progress">
        <li class="complete"><span>1</span><div><b>Order placed</b><small><?= e(date('d M Y · h:i A', strtotime($sale['created_at']))) ?></small></div></li>
        <li class="<?= $pending ? 'current' : ($cancelled ? 'stopped' : 'complete') ?>"><span>2</span><div><b><?= $paid ? 'Payment confirmed' : ($pending ? 'Confirm M-PESA payment' : ($cancelled ? 'Payment incomplete' : 'Pay on handover')) ?></b><small><?= $paid ? e($sale['payment_ref']) : ($pending ? 'Approve the secure prompt on your phone' : ($cancelled ? 'No payment was recorded' : 'Cash or M-PESA accepted')) ?></small></div></li>
        <li class="<?= !$pending && !$cancelled ? 'current' : '' ?>"><span>3</span><div><b><?= $sale['fulfillment'] === 'delivery' ? 'Delivery confirmation' : 'Pickup confirmation' ?></b><small>Our team will contact you before handover</small></div></li>
    </ol>

    <section class="card order-card">
        <div class="order-card-head"><div><span>Order number</span><strong><?= e($sale['sale_number']) ?></strong></div><span class="order-status-pill <?= e($sale['status']) ?>"><?= $pending ? 'Awaiting payment' : ($cancelled ? 'Not completed' : 'Confirmed') ?></span></div>
        <dl class="order-facts">
            <div><dt>Fulfilment</dt><dd><?= e(ucfirst($sale['fulfillment'])) ?><?= $sale['delivery_zone'] ? ' · ' . e($sale['delivery_zone']) : '' ?></dd></div>
            <div><dt>Payment</dt><dd><?= $pending ? 'M-PESA pending' : ($paid ? 'M-PESA paid' : 'Pay on ' . e($sale['fulfillment'])) ?></dd></div>
            <?php if ($sale['delivery_address']): ?><div><dt>Deliver to</dt><dd><?= e($sale['delivery_address']) ?></dd></div><?php endif; ?>
            <div><dt>Contact</dt><dd><?= e($sale['customer_phone'] ?? '') ?></dd></div>
        </dl>
        <div class="order-items-list">
            <?php foreach ($items as $i): ?>
                <div class="order-item"><div><b><?= e($i['product_name']) ?></b><small><?= (int) $i['quantity'] ?> × <?= money(gross_of($i['unit_price'])) ?><?php if ($i['serial_number']): ?> · Serial <?= e($i['serial_number']) ?><?php endif; ?></small></div><strong><?= money(gross_of($i['line_total'])) ?></strong></div>
            <?php endforeach; ?>
        </div>
        <div class="receipt-totals">
            <div><span>Items before VAT</span><b><?= money($sale['subtotal']) ?></b></div>
            <div><span>VAT (<?= e((string) vat_rate()) ?>%)</span><b><?= money($sale['tax_amount']) ?></b></div>
            <?php if ((float) $sale['delivery_fee'] > 0): ?><div><span>Delivery</span><b><?= money($sale['delivery_fee']) ?></b></div><?php endif; ?>
            <div class="grand"><span>Total</span><b><?= money($sale['total']) ?></b></div>
        </div>
    </section>

    <div class="next-instructions"><b>What happens next?</b><p><?= $sale['fulfillment'] === 'delivery' ? 'We will call to confirm the delivery address and timing before dispatch.' : 'We will call when your items are ready to collect. Bring your order number.' ?></p><?php if ($supportPhone !== ''): ?><p>Need help? Call <a href="tel:<?= e(preg_replace('/\s+/', '', $supportPhone)) ?>"><?= e($supportPhone) ?></a>.</p><?php endif; ?></div>

    <div class="receipt-actions">
        <?php if ($cancelled): ?><button class="btn btn-primary" type="button" data-restore-cart data-cart-url="<?= e(url('shop/cart')) ?>">Return to cart</button><?php else: ?><a class="btn btn-primary" href="<?= e(url('shop/products')) ?>">Continue shopping</a><?php endif; ?>
        <button class="btn btn-ghost" type="button" data-print-order>Print order</button>
        <button class="btn btn-ghost" type="button" data-share-order data-order="<?= e($sale['sale_number']) ?>">Share</button>
        <a class="btn btn-ghost" href="<?= e(url('shop/track')) ?>">Track later</a>
    </div>
</div>
