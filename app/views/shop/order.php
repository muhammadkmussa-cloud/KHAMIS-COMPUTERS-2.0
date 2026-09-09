<div class="order-confirm">
    <?php
    $pending = $sale['status'] === 'pending';
    $nameParts = preg_split('/\s+/', trim((string) ($sale['customer_name'] ?? ''))) ?: [];
    $firstName = $nameParts[0] ?? '';
    ?>
    <?php if ($pending): ?>
        <div class="big-check" style="background:#f5f5f7;color:var(--blue)">⌛</div>
        <h1>Check your phone<?= $firstName !== '' ? ', ' . e($firstName) : '' ?></h1>
        <p class="muted">We've sent an M-PESA prompt to <b><?= e($sale['customer_phone'] ?? '') ?></b>.
           Enter your M-PESA PIN to pay <b><?= money($sale['total']) ?></b>. This page updates automatically once your payment goes through.</p>
    <?php else: ?>
        <div class="big-check">✓</div>
        <h1>Thank you<?= $firstName !== '' ? ', ' . e($firstName) : '' ?>!</h1>
        <p class="muted">Your order has been placed and our stock is already updated.
           We'll call you on <b><?= e($sale['customer_phone'] ?? '') ?></b> to confirm
           <?= $sale['fulfillment'] === 'delivery' ? 'delivery' : 'pickup' ?>.</p>
    <?php endif; ?>

    <div class="card" style="text-align:left;margin-top:22px">
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
            <div><span>Fulfilment</span><b><?= e(ucfirst($sale['fulfillment'])) ?></b></div>
            <div><span>Payment</span><b><?= e(strtoupper($sale['payment_method'])) ?> on <?= e($sale['fulfillment']) ?></b></div>
            <?php if ($sale['delivery_address']): ?>
                <div><span>Deliver to</span><b><?= e($sale['delivery_address']) ?></b></div>
            <?php endif; ?>
            <?php if ($sale['delivery_zone']): ?>
                <div><span>Area</span><b><?= e($sale['delivery_zone']) ?></b></div>
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
    </div>

    <div class="receipt-actions">
        <a class="btn btn-primary" href="<?= e(url('shop/products')) ?>">Continue shopping</a>
        <a class="btn btn-ghost" href="<?= e(url('shop')) ?>">Home</a>
    </div>
</div>

<?php if ($pending): ?>
<script>
(function () {
  var tries = 0;
  function poll() {
    fetch('<?= e(url('shop/api/order-status/' . $sale['id'])) ?>')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.ok && d.status === 'completed') {
          window.location.reload();
        } else if (tries < 40) {
          tries++;
          setTimeout(poll, 3000);
        }
      })
      .catch(function () { if (tries++ < 40) setTimeout(poll, 4000); });
  }
  setTimeout(poll, 3000);
})();
</script>
<?php endif; ?>
