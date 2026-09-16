<?php $vat = vat_rate(); ?>
<div class="cart-layout">
    <div class="page-head cart-page-head">
        <div><div class="section-kicker">Secure checkout</div><h1>Your cart</h1><p class="lede">Review your items, then complete four short steps.</p></div>
        <a class="continue-link" href="<?= e(url('shop/products')) ?>">← Continue shopping</a>
    </div>

    <div class="cart-grid">
        <section class="cart-products" aria-labelledby="cart-items-title">
            <h2 class="sr-only" id="cart-items-title">Cart items</h2>
            <div class="card" id="cart-lines" aria-live="polite"></div>
            <section class="cart-recommendations" id="cart-recommendations">
                <div class="shop-section-head"><div><div class="section-kicker">Popular choices</div><h2>Keep browsing</h2></div></div>
                <div class="product-grid compact-grid">
                    <?php foreach ($recommendations as $p): include APP_PATH . '/views/shop/_product-card.php'; endforeach; ?>
                </div>
            </section>
        </section>

        <aside class="order-summary" id="cart-summary" hidden>
            <div class="checkout-progress" aria-label="Checkout progress">
                <button type="button" class="checkout-progress-step active" data-step-target="1"><span>1</span>Contact</button>
                <button type="button" class="checkout-progress-step" data-step-target="2"><span>2</span>Fulfilment</button>
                <button type="button" class="checkout-progress-step" data-step-target="3"><span>3</span>Payment</button>
                <button type="button" class="checkout-progress-step" data-step-target="4"><span>4</span>Review</button>
            </div>

            <div class="card checkout-card">
                <form id="checkout-form" novalidate>
                    <fieldset class="checkout-step active" data-step="1">
                        <legend>How can we reach you?</legend>
                        <p class="step-intro">We use these details for this order only.</p>
                        <label class="field" for="co-name"><span>Full name</span><input type="text" id="co-name" autocomplete="name" required placeholder="Amina Hassan"><small class="field-error" data-error-for="co-name"></small></label>
                        <label class="field" for="co-phone"><span>Kenyan mobile number</span><input type="tel" id="co-phone" autocomplete="tel" inputmode="tel" required placeholder="0712 345 678"><small>We will call or text about fulfilment.</small><small class="field-error" data-error-for="co-phone"></small></label>
                        <label class="field" for="co-email"><span>Email <em class="hint">optional</em></span><input type="email" id="co-email" autocomplete="email" placeholder="you@example.com"><small class="field-error" data-error-for="co-email"></small></label>
                    </fieldset>

                    <fieldset class="checkout-step" data-step="2" hidden>
                        <legend>How would you like it?</legend>
                        <p class="step-intro">Pickup is usually quickest. Delivery fees depend on your area.</p>
                        <div class="choice-cards">
                            <label class="choice-card"><input type="radio" name="fulfillment" value="pickup" checked><span><b>Pick up in store</b><small>No delivery fee · we will confirm when it is ready</small></span></label>
                            <label class="choice-card"><input type="radio" name="fulfillment" value="delivery"><span><b>Deliver to me</b><small>Choose a Nairobi delivery area</small></span></label>
                        </div>
                        <div id="delivery-fields" hidden>
                            <label class="field" for="co-zone"><span>Delivery area</span><select id="co-zone"><option value="">Choose your area</option><?php foreach ($zones as $z): ?><option value="<?= (int) $z['id'] ?>" data-fee="<?= e((string) $z['fee']) ?>"><?= e($z['name']) ?> · <?= money((float) $z['fee']) ?></option><?php endforeach; ?></select><small class="field-error" data-error-for="co-zone"></small></label>
                            <label class="field" for="co-address"><span>Delivery address</span><textarea id="co-address" rows="3" autocomplete="street-address" placeholder="Estate, street, building and a nearby landmark"></textarea><small class="field-error" data-error-for="co-address"></small></label>
                        </div>
                    </fieldset>

                    <fieldset class="checkout-step" data-step="3" hidden>
                        <legend>Choose payment</legend>
                        <p class="step-intro">You will never be asked for your M-PESA PIN outside the secure phone prompt.</p>
                        <div class="choice-cards">
                            <label class="choice-card"><input type="radio" name="payment" value="cash" checked><span><b>Pay on pickup or delivery</b><small>Cash or M-PESA when your order is handed over</small></span></label>
                            <?php if ($mpesaEnabled): ?><label class="choice-card"><input type="radio" name="payment" value="mpesa-now"><span><b>Pay now with M-PESA</b><small>We send an STK prompt after you place the order</small></span></label><?php endif; ?>
                        </div>
                    </fieldset>

                    <fieldset class="checkout-step" data-step="4" hidden>
                        <legend>Review your order</legend>
                        <p class="step-intro">Check the details below before placing the order.</p>
                        <div id="checkout-review" class="checkout-review"></div>
                        <div class="summary-block">
                            <div class="summary-row"><span>Items before VAT</span><b id="sum-subtotal">KSh 0.00</b></div>
                            <div class="summary-row"><span>VAT (<?= e((string) $vat) ?>%)</span><b id="sum-vat">KSh 0.00</b></div>
                            <div class="summary-row" id="sum-delivery-row" hidden><span>Delivery</span><b id="sum-delivery">KSh 0.00</b></div>
                            <div class="summary-row total"><span>Total</span><b id="sum-total">KSh 0.00</b></div>
                        </div>
                    </fieldset>

                    <div class="checkout-actions">
                        <button class="btn btn-ghost" type="button" id="checkout-back" hidden>Back</button>
                        <button class="btn btn-primary" type="button" id="checkout-next">Continue</button>
                        <button class="btn btn-primary" type="submit" id="place-order" hidden>Place order</button>
                    </div>
                    <div class="checkout-status" id="checkout-status" role="status" aria-live="polite" hidden><span class="spinner" aria-hidden="true"></span><span>Securing your order…</span></div>
                    <p id="checkout-error" class="checkout-error" role="alert"></p>
                </form>
            </div>
            <p class="checkout-assurance">Stock and totals are checked again when you place the order.</p>
        </aside>
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
