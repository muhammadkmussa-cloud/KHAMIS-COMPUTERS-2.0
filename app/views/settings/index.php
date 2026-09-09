<?php
$s = function (string $key, string $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};
?>
<div class="content-narrow">
    <div class="page-head">
        <div>
            <h1>Settings</h1>
            <p class="lede">Shop details, receipts and tax.</p>
        </div>
    </div>

    <div class="card">
        <form method="post" action="<?= e(url('settings')) ?>" class="form">
            <?= csrf_field() ?>

            <div class="section-kicker">Shop identity</div>
            <div class="form-row">
                <div class="field">
                    <span>Shop name</span>
                    <input type="text" name="shop_name" value="<?= e($s('shop_name', config('app.name'))) ?>" required>
                </div>
                <div class="field">
                    <span>Tagline</span>
                    <input type="text" name="shop_tagline" value="<?= e($s('shop_tagline', '')) ?>" placeholder="Shown on the online shop">
                </div>
            </div>

            <div class="section-kicker">Contact &amp; address</div>
            <div class="form-row">
                <div class="field">
                    <span>Phone</span>
                    <input type="text" name="shop_phone" value="<?= e($s('shop_phone', '')) ?>" placeholder="+254 7…">
                </div>
                <div class="field">
                    <span>Email</span>
                    <input type="email" name="shop_email" value="<?= e($s('shop_email', '')) ?>" placeholder="sales@…">
                </div>
            </div>
            <div class="field">
                <span>Address</span>
                <input type="text" name="shop_address" value="<?= e($s('shop_address', '')) ?>" placeholder="e.g. Moi Avenue, Nairobi">
            </div>

            <div class="section-kicker">Pricing &amp; receipts</div>
            <div class="form-row">
                <div class="field">
                    <span>VAT rate (%)</span>
                    <input type="number" name="vat_rate" step="0.01" min="0" max="100" value="<?= e((string) $vat) ?>">
                    <span class="hint">Applied to new sales and receipts. Existing receipts keep their stored tax.</span>
                </div>
                <div class="field">
                    <span>Currency</span>
                    <input type="text" value="<?= e(config('app.currency', 'KSh')) ?>" disabled>
                    <span class="hint">Set in app/config/config.php or .env</span>
                </div>
            </div>
            <div class="field">
                <span>Receipt footer</span>
                <textarea name="receipt_footer" rows="2"><?= e($s('receipt_footer', '')) ?></textarea>
            </div>

            <div class="section-kicker">Discount authorisation</div>
            <?php $pinSet = $s('discount_pin_hash', '') !== ''; ?>
            <div class="form-row">
                <div class="field">
                    <span>Manager PIN threshold (KSh)</span>
                    <input type="number" name="discount_pin_threshold" step="0.01" min="0" value="<?= e($s('discount_pin_threshold', '1000')) ?>">
                    <span class="hint">Discounts of this amount or more need a manager PIN at checkout.</span>
                </div>
                <div class="field">
                    <span>Manager discount PIN <?= $pinSet ? '<span class="badge badge-green" style="margin-left:4px">enabled</span>' : '' ?></span>
                    <input type="password" name="discount_pin" minlength="4" placeholder="<?= $pinSet ? '•••••• (leave blank to keep)' : 'Set a PIN to turn on the gate' ?>" autocomplete="new-password">
                    <?php if ($pinSet): ?>
                        <label class="hint" style="display:flex;gap:6px;align-items:center;margin-top:6px">
                            <input type="checkbox" name="discount_pin_clear" value="1"> Remove PIN (disables the gate)
                        </label>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-kicker">Email notifications</div>
            <?php $mailOn = $s('mail_enabled', '0') === '1'; ?>
            <div class="field">
                <label class="hint" style="display:flex;gap:8px;align-items:center">
                    <input type="checkbox" name="mail_enabled" value="1" <?= $mailOn ? 'checked' : '' ?>>
                    Enable email notifications (order confirmations &amp; low-stock alerts)
                </label>
            </div>
            <div class="form-row">
                <div class="field">
                    <span>Sender name</span>
                    <input type="text" name="mail_from_name" value="<?= e($s('mail_from_name', '')) ?>" placeholder="Khamis Computers">
                </div>
                <div class="field">
                    <span>Sender email</span>
                    <input type="email" name="mail_from" value="<?= e($s('mail_from', '')) ?>" placeholder="no-reply@yourdomain.com">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <span>Transport</span>
                    <select name="mail_transport">
                        <option value="mail" <?= $s('mail_transport', 'mail') === 'mail' ? 'selected' : '' ?>>PHP mail() — works on cPanel</option>
                        <option value="smtp" <?= $s('mail_transport', 'mail') === 'smtp' ? 'selected' : '' ?>>SMTP (Gmail / Office 365 / custom)</option>
                    </select>
                </div>
                <div class="field">
                    <span>Low-stock alert threshold</span>
                    <input type="number" name="low_stock_threshold" step="1" min="0" value="<?= e($s('low_stock_threshold', '5')) ?>">
                    <span class="hint">Alert when a product's stock falls to or below this number.</span>
                </div>
            </div>
            <div class="field">
                <span>Low-stock alert recipient</span>
                <input type="email" name="low_stock_recipient" value="<?= e($s('low_stock_recipient', '')) ?>" placeholder="owner@yourdomain.com">
                <span class="hint">Defaults to the shop email above if left blank.</span>
            </div>
            <div class="section-kicker">SMTP settings <span class="hint">(only when Transport is SMTP)</span></div>
            <div class="form-row">
                <div class="field">
                    <span>Host</span>
                    <input type="text" name="smtp_host" value="<?= e($s('smtp_host', '')) ?>" placeholder="smtp.gmail.com">
                </div>
                <div class="field">
                    <span>Port</span>
                    <input type="number" name="smtp_port" value="<?= e($s('smtp_port', '587')) ?>" placeholder="587">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <span>Encryption</span>
                    <select name="smtp_encryption">
                        <option value="tls" <?= $s('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                        <option value="ssl" <?= $s('smtp_encryption', 'tls') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                        <option value="none" <?= $s('smtp_encryption', 'tls') === 'none' ? 'selected' : '' ?>>None</option>
                    </select>
                </div>
                <div class="field">
                    <span>Username</span>
                    <input type="text" name="smtp_username" value="<?= e($s('smtp_username', '')) ?>" autocomplete="off">
                </div>
            </div>
            <div class="field">
                <span>Password</span>
                <input type="password" name="smtp_password" value="" placeholder="<?= $s('smtp_password', '') !== '' ? '•••••• (leave blank to keep)' : '' ?>" autocomplete="new-password">
            </div>

            <div class="section-kicker">M-PESA (STK push)</div>
            <?php $mpesaOn = $s('mpesa_enabled', '0') === '1'; ?>
            <div class="field">
                <label class="hint" style="display:flex;gap:8px;align-items:center">
                    <input type="checkbox" name="mpesa_enabled" value="1" <?= $mpesaOn ? 'checked' : '' ?>>
                    Accept "Pay now" M-PESA payments at online checkout (Safaricom Daraja STK push)
                </label>
            </div>
            <div class="form-row">
                <div class="field">
                    <span>Environment</span>
                    <select name="mpesa_env">
                        <option value="sandbox" <?= $s('mpesa_env', 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (test)</option>
                        <option value="live" <?= $s('mpesa_env', 'sandbox') === 'live' ? 'selected' : '' ?>>Live (production)</option>
                    </select>
                </div>
                <div class="field">
                    <span>Shortcode type</span>
                    <select name="mpesa_shortcode_type">
                        <option value="paybill" <?= $s('mpesa_shortcode_type', 'paybill') === 'paybill' ? 'selected' : '' ?>>Paybill</option>
                        <option value="till" <?= $s('mpesa_shortcode_type', 'paybill') === 'till' ? 'selected' : '' ?>>Till number</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <span>Shortcode / Party B</span>
                    <input type="text" name="mpesa_shortcode" value="<?= e($s('mpesa_shortcode', '')) ?>" placeholder="e.g. 174379">
                </div>
                <div class="field">
                    <span>Passkey</span>
                    <input type="text" name="mpesa_passkey" value="<?= e($s('mpesa_passkey', '')) ?>" placeholder="From the Daraja developer portal">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <span>Consumer key</span>
                    <input type="text" name="mpesa_consumer_key" value="<?= e($s('mpesa_consumer_key', '')) ?>" autocomplete="off">
                </div>
                <div class="field">
                    <span>Consumer secret</span>
                    <input type="password" name="mpesa_consumer_secret" value="" placeholder="<?= $s('mpesa_consumer_secret', '') !== '' ? '•••••• (leave blank to keep)' : '' ?>" autocomplete="new-password">
                </div>
            </div>
            <p class="hint" style="margin-top:-6px">
                Callback URL (set this as the Confirmation/Callback URL on the Daraja portal): <code><?= e(url('mpesa/callback')) ?></code>
            </p>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save settings</button>
            </div>
        </form>
    </div>

    <div class="card" id="delivery-zones" style="margin-top:18px">
        <h3>Delivery zones <span class="badge badge-gray"><?= count($zones) ?></span></h3>
        <p class="sub">Flat delivery fees per area, charged on online delivery orders. Existing orders keep the fee they were placed with.</p>

        <table class="table">
            <thead><tr><th>Zone</th><th class="num">Fee</th><th>Status</th><th>Order</th><th style="width:180px"></th></tr></thead>
            <tbody>
            <?php foreach ($zones as $z): ?>
                <tr>
                    <form method="post" action="<?= e(url('delivery-zones/' . $z['id'])) ?>">
                        <?= csrf_field() ?>
                        <td><input type="text" name="name" value="<?= e($z['name']) ?>" required></td>
                        <td class="num"><input type="number" name="fee" step="0.01" min="0" value="<?= e((string) $z['fee']) ?>" style="width:110px;text-align:right" required></td>
                        <td>
                            <label class="switch" style="display:inline-flex;align-items:center;gap:6px">
                                <input type="checkbox" name="is_active" value="1" <?= (int) $z['is_active'] === 1 ? 'checked' : '' ?>>
                                <span><?= (int) $z['is_active'] === 1 ? 'Active' : 'Hidden' ?></span>
                            </label>
                        </td>
                        <td><input type="number" name="sort_order" value="<?= (int) $z['sort_order'] ?>" style="width:70px"></td>
                        <td style="white-space:nowrap">
                            <button class="btn btn-outline btn-sm" type="submit">Save</button>
                            <button class="btn btn-danger-ghost btn-sm" type="submit" formaction="<?= e(url('delivery-zones/' . $z['id'] . '/delete')) ?>" onclick="return confirm('Delete this delivery zone?');">Delete</button>
                        </td>
                    </form>
                </tr>
            <?php endforeach; ?>
            <?php if (!$zones): ?>
                <tr><td colspan="5" class="muted">No delivery zones yet — add one below.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <form method="post" action="<?= e(url('delivery-zones')) ?>" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
            <?= csrf_field() ?>
            <div class="field" style="flex:1">
                <span>New zone name</span>
                <input type="text" name="name" placeholder="e.g. Nairobi CBD" required>
            </div>
            <div class="field" style="width:150px">
                <span>Fee (KSh)</span>
                <input type="number" name="fee" step="0.01" min="0" placeholder="0.00" required>
            </div>
            <div class="field" style="width:90px">
                <span>Order</span>
                <input type="number" name="sort_order" value="0">
            </div>
            <button class="btn btn-primary" type="submit">Add zone</button>
        </form>
    </div>

    <div class="card" style="margin-top:18px;border-left:4px solid var(--orange)">
        <h3>Production checklist</h3>
        <ul class="checklist">
            <li class="todo"><span>Remove the installer</span><em>delete the /install route + SetupController</em></li>
            <li class="todo"><span>Remove dev files</span><em>tools/, router-dev.php</em></li>
            <li class="todo"><span>Change default passwords</span><em>admin + cashier demo accounts</em></li>
            <li class="todo"><span>Enable HTTPS</span><em>via cPanel AutoSSL / Let's Encrypt</em></li>
        </ul>
        <p class="sub" style="margin-top:10px">Full guide: <a href="<?= e(url('docs/cpanel-deployment.md')) ?>">docs/cpanel-deployment.md</a></p>
    </div>
</div>
