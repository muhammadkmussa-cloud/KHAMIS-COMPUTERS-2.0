<?php
$found = isset($sale) && $sale;
$enteredNumber = $orderNumber ?? ($_POST['order_number'] ?? '');
$enteredPhone = $phone ?? ($_POST['phone'] ?? '');
?>
<div class="track-layout">
    <section class="track-intro">
        <div class="section-kicker">Order support</div>
        <h1>Track your order</h1>
        <p>Use the order number from your confirmation and the same mobile number you entered at checkout.</p>
        <div class="track-help"><b>Where is my order number?</b><span>It looks like S-20260916-0001 and appears on the confirmation screen and email.</span></div>
    </section>

    <section class="card track-search-card" aria-labelledby="track-form-title">
        <h2 id="track-form-title">Find an order</h2>
        <form method="post" action="<?= e(url('shop/track')) ?>" class="track-form">
            <?= csrf_field() ?>
            <label class="field" for="track-order"><span>Order number</span><input id="track-order" type="text" name="order_number" required autocomplete="off" autocapitalize="characters" placeholder="S-20260916-0001" value="<?= e($enteredNumber) ?>"></label>
            <label class="field" for="track-phone"><span>Kenyan mobile number</span><input id="track-phone" type="tel" name="phone" required autocomplete="tel" inputmode="tel" placeholder="0712 345 678" value="<?= e($enteredPhone) ?>"><small>Formatting does not matter.</small></label>
            <?php if (!empty($lookupError)): ?><div class="lookup-error" role="alert"><b>Order not found</b><span><?= e($lookupError) ?></span></div><?php endif; ?>
            <button class="btn btn-primary btn-block" type="submit">Check order status</button>
        </form>
    </section>

    <?php if ($found): ?>
    <?php $paid = !empty($sale['payment_ref']); $cancelled = in_array($sale['status'], ['cancelled', 'voided'], true); ?>
    <section class="track-result">
        <div class="track-result-head"><div><div class="section-kicker">Order found</div><h2><?= e($sale['sale_number']) ?></h2><p>Placed <?= e(date('d M Y · h:i A', strtotime($sale['created_at']))) ?></p></div><span class="order-status-pill <?= e($sale['status']) ?>"><?= $sale['status'] === 'pending' ? 'Awaiting payment' : ($cancelled ? 'Not completed' : 'Confirmed') ?></span></div>

        <ol class="order-timeline" aria-label="Order progress">
            <li class="complete"><span>1</span><div><b>Order received</b><small>Your order details are saved</small></div></li>
            <li class="<?= $sale['status'] === 'pending' ? 'current' : ($cancelled ? 'stopped' : 'complete') ?>"><span>2</span><div><b><?= $sale['status'] === 'pending' ? 'Payment pending' : ($cancelled ? 'Order stopped' : ($paid ? 'Payment confirmed' : 'Payment on handover')) ?></b><small><?= $sale['status'] === 'pending' ? 'Complete the M-PESA prompt or contact the store' : ($paid ? e($sale['payment_ref']) : 'No advance payment required') ?></small></div></li>
            <li class="<?= $sale['status'] === 'completed' ? 'current' : '' ?>"><span>3</span><div><b><?= $sale['fulfillment'] === 'delivery' ? 'Delivery' : 'Store pickup' ?></b><small>We will contact you to confirm timing</small></div></li>
        </ol>

        <div class="card order-card">
            <dl class="order-facts">
                <div><dt>Fulfilment</dt><dd><?= e(ucfirst($sale['fulfillment'])) ?><?= $sale['delivery_zone'] ? ' · ' . e($sale['delivery_zone']) : '' ?></dd></div>
                <div><dt>Payment</dt><dd><?= $sale['status'] === 'pending' ? 'M-PESA awaiting confirmation' : ($paid ? 'Confirmed · ' . e($sale['payment_ref']) : 'Due on handover') ?></dd></div>
                <?php if ($sale['delivery_address']): ?><div><dt>Address</dt><dd><?= e($sale['delivery_address']) ?></dd></div><?php endif; ?>
            </dl>
            <div class="order-items-list"><?php foreach ($items as $i): ?><div class="order-item"><div><b><?= e($i['product_name']) ?></b><small>Quantity <?= (int) $i['quantity'] ?></small></div><strong><?= money(gross_of($i['line_total'])) ?></strong></div><?php endforeach; ?></div>
            <div class="receipt-totals"><div><span>Items before VAT</span><b><?= money($sale['subtotal']) ?></b></div><div><span>VAT</span><b><?= money($sale['tax_amount']) ?></b></div><?php if ((float) $sale['delivery_fee'] > 0): ?><div><span>Delivery</span><b><?= money($sale['delivery_fee']) ?></b></div><?php endif; ?><div class="grand"><span>Total</span><b><?= money($sale['total']) ?></b></div></div>
        </div>
    </section>
    <?php endif; ?>

    <div class="track-back"><a href="<?= e(url('shop')) ?>">← Back to shop</a></div>
</div>
