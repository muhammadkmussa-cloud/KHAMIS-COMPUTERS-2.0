<?php $found = isset($sale) && $sale; ?>
<div class="content-narrow">
    <div class="page-head">
        <div>
            <h1>Track your order</h1>
            <p class="lede">Enter your order number and the phone number you used at checkout.</p>
        </div>
    </div>

    <div class="card">
        <form method="post" action="<?= e(url('shop/track')) ?>" class="form-row" style="align-items:flex-end">
            <?= csrf_field() ?>
            <div class="field">
                <span>Order number</span>
                <input type="text" name="order_number" required placeholder="e.g. S-20260908-0001" value="<?= e($_POST['order_number'] ?? '') ?>">
            </div>
            <div class="field">
                <span>Phone number</span>
                <input type="text" name="phone" required placeholder="e.g. 07XX XXX XXX" value="<?= e($_POST['phone'] ?? '') ?>">
            </div>
            <button class="btn btn-primary" type="submit">Find order</button>
        </form>
    </div>

    <?php if ($found): ?>
    <div class="card" style="margin-top:18px">
        <div class="receipt-head">
            <div class="receipt-brand">
                <?= brand_mark(26) ?>
                <div><strong><?= e(Setting::get('shop_name', config('app.name'))) ?></strong></div>
            </div>
            <div class="receipt-title">ORDER</div>
        </div>
        <div class="receipt-meta">
            <div><span>Order</span><b><?= e($sale['sale_number']) ?></b></div>
            <div><span>Date</span><b><?= e(date('d M Y · h:i A', strtotime($sale['created_at']))) ?></b></div>
            <div><span>Status</span><b><?= $sale['status'] === 'completed' ? 'Paid / confirmed' : ucfirst($sale['status']) ?></b></div>
            <div><span>Fulfilment</span><b><?= e(ucfirst($sale['fulfillment'])) ?></b></div>
            <?php if ($sale['payment_ref']): ?>
                <div><span>Payment ref</span><b><?= e($sale['payment_ref']) ?></b></div>
            <?php endif; ?>
            <?php if ($sale['delivery_address']): ?>
                <div><span>Deliver to</span><b><?= e($sale['delivery_address']) ?></b></div>
            <?php endif; ?>
        </div>

        <table class="receipt-items">
            <thead><tr><th>Item</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach ($items as $i): ?>
                <tr>
                    <td>
                        <div class="cell-main"><?= e($i['product_name']) ?></div>
                        <?php if ($i['serial_number']): ?><div class="cell-sub">Serial: <?= e($i['serial_number']) ?></div><?php endif; ?>
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
            <div><span>VAT (<?= e((string) vat_rate()) ?>%)</span><b><?= money($sale['tax_amount']) ?></b></div>
            <?php if ((float) $sale['delivery_fee'] > 0): ?>
                <div><span>Delivery (<?= e($sale['delivery_zone'] ?: 'delivery') ?>)</span><b><?= money($sale['delivery_fee']) ?></b></div>
            <?php endif; ?>
            <div class="grand"><span>Total</span><b><?= money($sale['total']) ?></b></div>
        </div>

        <?php if ($sale['status'] === 'pending' && $sale['payment_method'] === 'mpesa'): ?>
            <div class="alert alert-info" style="margin-top:12px">
                This order is awaiting M-PESA payment. If you already paid, the status updates automatically — refresh this page in a moment.
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="receipt-actions" style="margin-top:18px">
        <a class="btn btn-ghost" href="<?= e(url('shop')) ?>">← Back to shop</a>
    </div>
</div>
