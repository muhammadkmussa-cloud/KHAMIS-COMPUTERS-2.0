<?php $vat = vat_rate(); ?>
<div class="cart-layout">
    <div class="page-head">
        <div>
            <h1 style="font-size:34px">Your cart</h1>
            <p class="lede">Review your items, then choose pickup or delivery.</p>
        </div>
    </div>

    <div class="cart-grid">
        <div class="card">
            <div id="cart-lines"></div>
        </div>

        <div class="order-summary">
            <div class="card" id="cart-summary">
                <h3>Order summary</h3>
                <div class="summary-row"><span>Subtotal</span><b id="sum-subtotal">KSh 0.00</b></div>
                <div class="summary-row"><span>VAT (<?= e((string) $vat) ?>%)</span><b id="sum-vat">KSh 0.00</b></div>
                <div class="summary-row" id="sum-delivery-row" style="display:none"><span>Delivery</span><b id="sum-delivery">KSh 0.00</b></div>
                <div class="summary-row total"><span>Total</span><b id="sum-total">KSh 0.00</b></div>
                <hr>
                <form id="checkout-form" class="form">
                    <div class="field">
                        <span>Full name</span>
                        <input type="text" id="co-name" required placeholder="e.g. Amina Hassan">
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <span>Phone</span>
                            <input type="text" id="co-phone" required placeholder="07…">
                        </div>
                        <div class="field">
                            <span>Email <em class="hint">(optional)</em></span>
                            <input type="email" id="co-email" placeholder="you@email.com">
                        </div>
                    </div>
                    <div class="field">
                        <span>Fulfilment</span>
                        <select id="co-fulfillment">
                            <option value="pickup">Pick up in store</option>
                            <option value="delivery">Deliver to me</option>
                        </select>
                    </div>
                    <div class="field" id="zone-field" style="display:none">
                        <span>Delivery area</span>
                        <select id="co-zone">
                            <option value="">— choose your area —</option>
                            <?php foreach ($zones as $z): ?>
                                <option value="<?= (int) $z['id'] ?>" data-fee="<?= e((string) $z['fee']) ?>">
                                    <?= e($z['name']) ?> · <?= money((float) $z['fee']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field" id="address-field" style="display:none">
                        <span>Delivery address</span>
                        <textarea id="co-address" rows="2" placeholder="Estate, street, building…"></textarea>
                    </div>
                    <div class="field">
                        <span>Payment</span>
                        <select id="co-payment">
                            <option value="cash">Cash on pickup / delivery</option>
                            <option value="mpesa">M-PESA on pickup / delivery</option>
                            <?php if ($mpesaEnabled): ?><option value="mpesa" data-paynow="1">Pay now with M-PESA (STK push)</option><?php endif; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary btn-block" type="submit" id="place-order">Place order</button>
                    <p class="muted" id="checkout-error" style="color:var(--red);margin:0;min-height:18px"></p>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
window.KC_CART_CONFIG = {
    api: '<?= e(url('shop/api/cart')) ?>',
    checkout: '<?= e(url('shop/checkout')) ?>',
    browse: '<?= e(url('shop/products')) ?>',
    csrf: '<?= e(Csrf::token()) ?>',
    vat: <?= $vat ?>,
    currency: '<?= e(config('app.currency', 'KSh')) ?>'
};
</script>
