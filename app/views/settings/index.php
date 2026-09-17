<?php
$s = function (string $key, string $default = '') use ($settings) {
    return $settings[$key] ?? $default;
};
$isProd = config('app.env') === 'production';
$pinSet = $s('discount_pin_hash', '') !== '';
$mailOn = $s('mail_enabled', '0') === '1';
$mpesaOn = $s('mpesa_enabled', '0') === '1';
$activeTab = isset($_GET['tab']) ? $_GET['tab'] : 'business';
?>
<div class="content-narrow settings-page">
    <div class="page-head">
        <div>
            <h1>Settings</h1>
            <p class="lede">Shop details, receipts, tax, and integrations.</p>
        </div>
    </div>

    <div class="settings-tabs" role="tablist" aria-label="Settings sections">
        <a class="settings-tab <?= $activeTab === 'business' ? 'active' : '' ?>" href="?tab=business" role="tab" aria-selected="<?= $activeTab === 'business' ?>" data-tab="business">Business</a>
        <a class="settings-tab <?= $activeTab === 'tax' ? 'active' : '' ?>" href="?tab=tax" role="tab" aria-selected="<?= $activeTab === 'tax' ?>" data-tab="tax">Tax &amp; Receipts</a>
        <a class="settings-tab <?= $activeTab === 'discounts' ? 'active' : '' ?>" href="?tab=discounts" role="tab" aria-selected="<?= $activeTab === 'discounts' ?>" data-tab="discounts">Discounts</a>
        <a class="settings-tab <?= $activeTab === 'notifications' ? 'active' : '' ?>" href="?tab=notifications" role="tab" aria-selected="<?= $activeTab === 'notifications' ?>" data-tab="notifications">Notifications</a>
        <a class="settings-tab <?= $activeTab === 'mpesa' ? 'active' : '' ?>" href="?tab=mpesa" role="tab" aria-selected="<?= $activeTab === 'mpesa' ?>" data-tab="mpesa">M-PESA</a>
        <a class="settings-tab <?= $activeTab === 'delivery' ? 'active' : '' ?>" href="?tab=delivery" role="tab" aria-selected="<?= $activeTab === 'delivery' ?>" data-tab="delivery">Delivery</a>
        <a class="settings-tab <?= $activeTab === 'online-shop' ? 'active' : '' ?>" href="?tab=online-shop" role="tab" aria-selected="<?= $activeTab === 'online-shop' ?>" data-tab="online-shop">Online Shop</a>
        <?php if (!$isProd): ?><a class="settings-tab <?= $activeTab === 'deploy' ? 'active' : '' ?>" href="?tab=deploy" role="tab" aria-selected="<?= $activeTab === 'deploy' ?>" data-tab="deploy">Deploy</a><?php endif; ?>
    </div>

    <div class="card">
        <form method="post" action="<?= e(url('settings')) ?>" class="form settings-form" id="settings-form">
            <?= csrf_field() ?>

            <div class="settings-tab-content" id="panel-business" data-panel="business" style="<?= $activeTab !== 'business' ? 'display:none' : '' ?>">
                <div class="section-kicker">Shop identity</div>
                <div class="form-row">
                    <div class="field">
                        <label for="shop_name">Shop name</label>
                        <input type="text" id="shop_name" name="shop_name" value="<?= e($s('shop_name', config('app.name'))) ?>" required>
                    </div>
                    <div class="field">
                        <label for="shop_tagline">Tagline</label>
                        <input type="text" id="shop_tagline" name="shop_tagline" value="<?= e($s('shop_tagline', '')) ?>" placeholder="Shown on the online shop">
                    </div>
                </div>

                <div class="section-kicker">Contact &amp; address</div>
                <div class="form-row">
                    <div class="field">
                        <label for="shop_phone">Phone</label>
                        <input type="tel" id="shop_phone" name="shop_phone" value="<?= e($s('shop_phone', '')) ?>" placeholder="+254 7…" inputmode="tel">
                    </div>
                    <div class="field">
                        <label for="shop_email">Email</label>
                        <input type="email" id="shop_email" name="shop_email" value="<?= e($s('shop_email', '')) ?>" placeholder="sales@…">
                    </div>
                </div>
                <div class="field">
                    <label for="shop_address">Address</label>
                    <input type="text" id="shop_address" name="shop_address" value="<?= e($s('shop_address', '')) ?>" placeholder="e.g. Moi Avenue, Nairobi">
                </div>
            </div>

            <div class="settings-tab-content" id="panel-tax" data-panel="tax" style="<?= $activeTab !== 'tax' ? 'display:none' : '' ?>">
                <div class="section-kicker">Pricing &amp; receipts</div>
                <div class="form-row">
                    <div class="field">
                        <label for="vat_rate">VAT rate (%)</label>
                        <input type="number" id="vat_rate" name="vat_rate" step="0.01" min="0" max="100" value="<?= e((string) $vat) ?>">
                        <span class="hint">Applied to new sales and receipts. Existing receipts keep their stored tax.</span>
                    </div>
                    <div class="field">
                        <label for="currency">Currency</label>
                        <input type="text" id="currency" value="<?= e(config('app.currency', 'KSh')) ?>" disabled>
                        <span class="hint">Set in app/config/config.php or .env</span>
                    </div>
                </div>
                <div class="field">
                    <label for="receipt_footer">Receipt footer</label>
                    <textarea id="receipt_footer" name="receipt_footer" rows="2"><?= e($s('receipt_footer', '')) ?></textarea>
                </div>
            </div>

            <div class="settings-tab-content" id="panel-discounts" data-panel="discounts" style="<?= $activeTab !== 'discounts' ? 'display:none' : '' ?>">
                <div class="section-kicker">Discount authorisation</div>
                <div class="form-row">
                    <div class="field">
                        <label for="discount_pin_threshold">Manager PIN threshold (KSh)</label>
                        <input type="number" id="discount_pin_threshold" name="discount_pin_threshold" step="0.01" min="0" value="<?= e($s('discount_pin_threshold', '1000')) ?>">
                        <span class="hint">Discounts of this amount or more need a manager PIN at checkout.</span>
                    </div>
                    <div class="field">
                        <label for="discount_pin">Manager discount PIN <?= $pinSet ? '<span class="badge badge-green">enabled</span>' : '' ?></label>
                        <input type="password" id="discount_pin" name="discount_pin" minlength="4" placeholder="<?= $pinSet ? '•••••• (leave blank to keep)' : 'Set a PIN to turn on the gate' ?>" autocomplete="new-password">
                        <?php if ($pinSet): ?>
                            <label class="hint" style="display:flex;gap:6px;align-items:center;margin-top:6px">
                                <input type="checkbox" name="discount_pin_clear" value="1"> Remove PIN (disables the gate)
                            </label>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="settings-tab-content" id="panel-notifications" data-panel="notifications" style="<?= $activeTab !== 'notifications' ? 'display:none' : '' ?>">
                <div class="section-kicker">Email notifications</div>
                <div class="field">
                    <label class="field-check" style="gap:8px">
                        <input type="checkbox" name="mail_enabled" value="1" <?= $mailOn ? 'checked' : '' ?>>
                        Enable email notifications (order confirmations &amp; low-stock alerts)
                    </label>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="mail_from_name">Sender name</label>
                        <input type="text" id="mail_from_name" name="mail_from_name" value="<?= e($s('mail_from_name', '')) ?>" placeholder="Khamis Computers">
                    </div>
                    <div class="field">
                        <label for="mail_from">Sender email</label>
                        <input type="email" id="mail_from" name="mail_from" value="<?= e($s('mail_from', '')) ?>" placeholder="no-reply@yourdomain.com">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="mail_transport">Transport</label>
                        <select id="mail_transport" name="mail_transport">
                            <option value="mail" <?= $s('mail_transport', 'mail') === 'mail' ? 'selected' : '' ?>>PHP mail() — works on cPanel</option>
                            <option value="smtp" <?= $s('mail_transport', 'mail') === 'smtp' ? 'selected' : '' ?>>SMTP (Gmail / Office 365 / custom)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="low_stock_threshold">Low-stock alert threshold</label>
                        <input type="number" id="low_stock_threshold" name="low_stock_threshold" step="1" min="0" value="<?= e($s('low_stock_threshold', '5')) ?>">
                        <span class="hint">Alert when a product's stock falls to or below this number.</span>
                    </div>
                </div>
                <div class="field">
                    <label for="low_stock_recipient">Low-stock alert recipient</label>
                    <input type="email" id="low_stock_recipient" name="low_stock_recipient" value="<?= e($s('low_stock_recipient', '')) ?>" placeholder="owner@yourdomain.com">
                    <span class="hint">Defaults to the shop email above if left blank.</span>
                </div>
                <div class="section-kicker" style="margin-top:18px">SMTP settings <?= $s('mail_transport', 'mail') !== 'smtp' ? '<span class="hint">(only when Transport is SMTP)</span>' : '' ?></div>
                <div class="form-row">
                    <div class="field">
                        <label for="smtp_host">Host</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?= e($s('smtp_host', '')) ?>" placeholder="smtp.gmail.com">
                    </div>
                    <div class="field">
                        <label for="smtp_port">Port</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?= e($s('smtp_port', '587')) ?>" placeholder="587">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="smtp_encryption">Encryption</label>
                        <select id="smtp_encryption" name="smtp_encryption">
                            <option value="tls" <?= $s('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                            <option value="ssl" <?= $s('smtp_encryption', 'tls') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                            <option value="none" <?= $s('smtp_encryption', 'tls') === 'none' ? 'selected' : '' ?>>None</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="smtp_username">Username</label>
                        <input type="text" id="smtp_username" name="smtp_username" value="<?= e($s('smtp_username', '')) ?>" autocomplete="off">
                    </div>
                </div>
                <div class="field">
                    <label for="smtp_password">Password</label>
                    <input type="password" id="smtp_password" name="smtp_password" value="" placeholder="<?= $s('smtp_password', '') !== '' ? '•••••• (leave blank to keep current)' : '' ?>" autocomplete="new-password">
                </div>
                <div class="form-actions" style="margin-top:12px">
                    <button class="btn btn-outline" type="button" id="test-email-btn">Test email</button>
                </div>
            </div>

            <div class="settings-tab-content" id="panel-mpesa" data-panel="mpesa" style="<?= $activeTab !== 'mpesa' ? 'display:none' : '' ?>">
                <div class="section-kicker">M-PESA (STK push)</div>
                <div class="field">
                    <label class="field-check" style="gap:8px">
                        <input type="checkbox" name="mpesa_enabled" value="1" <?= $mpesaOn ? 'checked' : '' ?>>
                        Accept "Pay now" M-PESA payments at online checkout (Safaricom Daraja STK push)
                    </label>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="mpesa_env">Environment</label>
                        <select id="mpesa_env" name="mpesa_env">
                            <option value="sandbox" <?= $s('mpesa_env', 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (test)</option>
                            <option value="live" <?= $s('mpesa_env', 'sandbox') === 'live' ? 'selected' : '' ?>>Live (production)</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="mpesa_shortcode_type">Shortcode type</label>
                        <select id="mpesa_shortcode_type" name="mpesa_shortcode_type">
                            <option value="paybill" <?= $s('mpesa_shortcode_type', 'paybill') === 'paybill' ? 'selected' : '' ?>>Paybill</option>
                            <option value="till" <?= $s('mpesa_shortcode_type', 'paybill') === 'till' ? 'selected' : '' ?>>Till number</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="mpesa_shortcode">Shortcode / Party B</label>
                        <input type="text" id="mpesa_shortcode" name="mpesa_shortcode" value="<?= e($s('mpesa_shortcode', '')) ?>" placeholder="e.g. 174379">
                    </div>
                    <div class="field">
                        <label for="mpesa_passkey">Passkey</label>
                        <input type="text" id="mpesa_passkey" name="mpesa_passkey" value="<?= e($s('mpesa_passkey', '')) ?>" placeholder="From the Daraja developer portal">
                    </div>
                </div>
                <div class="form-row">
                    <div class="field">
                        <label for="mpesa_consumer_key">Consumer key</label>
                        <input type="text" id="mpesa_consumer_key" name="mpesa_consumer_key" value="<?= e($s('mpesa_consumer_key', '')) ?>" autocomplete="off">
                    </div>
                    <div class="field">
                        <label for="mpesa_consumer_secret">Consumer secret</label>
                        <input type="password" id="mpesa_consumer_secret" name="mpesa_consumer_secret" value="" placeholder="<?= $s('mpesa_consumer_secret', '') !== '' ? '•••••• (leave blank to keep current)' : '' ?>" autocomplete="new-password">
                    </div>
                </div>
                <p class="hint">Callback URL (copy this exact tokenized URL into the Daraja portal): <code class="callback-url"><?= e(url('mpesa/callback?token=' . rawurlencode(MpesaService::callbackToken()))) ?></code></p>
                <div class="form-actions" style="margin-top:12px">
                    <button class="btn btn-outline" type="button" id="test-mpesa-btn">Test M-PESA connection</button>
                </div>
            </div>

            <div class="settings-tab-content" id="panel-delivery" data-panel="delivery" style="<?= $activeTab !== 'delivery' ? 'display:none' : '' ?>">
                <div class="section-kicker">Delivery zones</div>
                <p class="sub">Flat delivery fees per area, charged on online delivery orders. Existing orders keep the fee they were placed with.</p>

                <div class="zone-list">
                <?php foreach ($zones as $z): ?>
                    <div class="zone-card">
                        <div class="zone-card-head">
                            <div>
                                <b class="zone-name"><?= e($z['name']) ?></b>
                                <span class="zone-fee">KSh <?= e((string) $z['fee']) ?></span>
                                <span class="badge <?= (int) $z['is_active'] === 1 ? 'badge-green' : 'badge-gray' ?>" style="margin-left:8px"><?= (int) $z['is_active'] === 1 ? 'Active' : 'Hidden' ?></span>
                            </div>
                            <button class="btn btn-outline btn-sm zone-edit-toggle" type="button">Edit</button>
                        </div>
                        <form method="post" action="<?= e(url('delivery-zones/' . $z['id'])) ?>" class="zone-edit-form" style="display:none">
                            <?= csrf_field() ?>
                            <div class="form-row">
                                <div class="field">
                                    <label for="zone_name_<?= (int) $z['id'] ?>">Zone name</label>
                                    <input type="text" id="zone_name_<?= (int) $z['id'] ?>" name="name" value="<?= e($z['name']) ?>" required>
                                </div>
                                <div class="field">
                                    <label for="zone_fee_<?= (int) $z['id'] ?>">Fee (KSh)</label>
                                    <input type="number" id="zone_fee_<?= (int) $z['id'] ?>" name="fee" step="0.01" min="0" value="<?= e((string) $z['fee']) ?>" required style="width:120px">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="field">
                                    <label for="zone_active_<?= (int) $z['id'] ?>">Status</label>
                                    <label class="switch" style="display:inline-flex;align-items:center;gap:6px">
                                        <input type="hidden" name="is_active" value="0">
                                        <input type="checkbox" name="is_active" value="1" <?= (int) $z['is_active'] === 1 ? 'checked' : '' ?>>
                                        <span><?= (int) $z['is_active'] === 1 ? 'Active' : 'Hidden' ?></span>
                                    </label>
                                </div>
                                <div class="field">
                                    <label for="zone_order_<?= (int) $z['id'] ?>">Order</label>
                                    <input type="number" name="sort_order" value="<?= (int) $z['sort_order'] ?>" style="width:70px">
                                </div>
                            </div>
                            <div class="form-actions" style="margin-top:8px">
                                <button class="btn btn-outline btn-sm" type="submit">Save</button>
                                <button class="btn btn-danger-ghost btn-sm" type="submit" formaction="<?= e(url('delivery-zones/' . $z['id'] . '/delete')) ?>" data-confirm="Delete this delivery zone?">Delete</button>
                                <button class="btn btn-ghost btn-sm zone-cancel-edit" type="button">Cancel</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
                <?php if (!$zones): ?>
                    <p class="muted" style="text-align:center;padding:20px">No delivery zones yet — add one below.</p>
                <?php endif; ?>
                </div>

                <form method="post" action="<?= e(url('delivery-zones')) ?>" style="margin-top:18px" class="add-zone-form">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="field" style="flex:1">
                            <label for="new_zone_name">New zone name</label>
                            <input type="text" id="new_zone_name" name="name" placeholder="e.g. Nairobi CBD" required>
                        </div>
                        <div class="field" style="width:150px">
                            <label for="new_zone_fee">Fee (KSh)</label>
                            <input type="number" id="new_zone_fee" name="fee" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                        <div class="field" style="width:90px">
                            <label for="new_zone_order">Order</label>
                            <input type="number" name="sort_order" value="0">
                        </div>
                        <div class="field" style="align-self:end">
                            <button class="btn btn-primary" type="submit">Add zone</button>
                        </div>
                    </div>
                </form>
            </div>

            <?php if (!$isProd): ?>
            <div class="settings-tab-content" id="panel-deploy" data-panel="deploy" style="<?= $activeTab !== 'deploy' ? 'display:none' : '' ?>">
                <div class="card" style="border-left:4px solid var(--orange)">
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
            <?php endif; ?>

            <div class="form-actions settings-form-actions">
                <button class="btn btn-primary" type="submit">Save all settings</button>
            </div>
        </form>
        <div class="settings-tab-content" id="panel-online-shop" data-panel="online-shop" style="<?= $activeTab !== 'online-shop' ? 'display:none' : '' ?>">
            <?php include APP_PATH . '/views/settings/hero.php'; ?>
        </div>
    </div>
</div>

<div class="toast-region" id="settings-toast-region" aria-live="polite" aria-atomic="true"></div>

<script>
(function() {
    var tabs = document.querySelectorAll('[data-tab]');
    var panels = document.querySelectorAll('[data-panel]');
    var form = document.getElementById('settings-form');
    var saveStatus = document.getElementById('settings-save-status');
    var dirtySections = new Set();

    function switchTab(tabName) {
        tabs.forEach(function(t) { t.classList.toggle('active', t.dataset.tab === tabName); t.setAttribute('aria-selected', t.dataset.tab === tabName); });
        panels.forEach(function(p) { p.style.display = p.dataset.panel === tabName ? '' : 'none'; });
        window.location.hash = 'tab=' + tabName;
    }

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            switchTab(tab.dataset.tab);
        });
    });

    form.addEventListener('change', function() { dirtySections.add('form'); });
    form.addEventListener('input', function() { dirtySections.add('form'); });

    setInterval(function() {
        if (dirtySections.size > 0) {
            saveStatus.textContent = '● You have unsaved changes';
            saveStatus.style.color = 'var(--orange)';
        } else {
            saveStatus.textContent = '';
        }
    }, 1500);

    var testEmailBtn = document.getElementById('test-email-btn');
    if (testEmailBtn) {
        testEmailBtn.addEventListener('click', function() {
            KC.confirm({message: 'Send a test email to the configured sender address?', title: 'Test email', action: 'Send', tone: 'primary'}).then(function(ok) {
                if (!ok) return;
                var formData = new FormData();
                formData.append('_csrf', document.querySelector('input[name="_csrf"]').value);
                fetch('<?= e(url('settings/test-email')) ?>', {method:'POST', body: formData, headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function(r) { return r.text(); })
                    .then(function(html) {
                        var temp = document.createElement('div');
                        temp.innerHTML = html;
                        var flash = temp.querySelector('.alert');
                        if (flash) {
                            var region = document.getElementById('settings-toast-region');
                            region.innerHTML = '<div class="kc-toast show ' + (flash.classList.contains('alert-success') ? 'toast-success' : 'toast-error') + '" role="alert">' + flash.textContent + '</div>';
                            setTimeout(function() { region.innerHTML = ''; }, 4000);
                        }
                        window.location.href = '<?= e(url('settings')) ?>#tab-notifications';
                    })
                    .catch(function() { KC.toast('Test email request failed.', 'error'); });
            });
        });
    }

    var testMpesaBtn = document.getElementById('test-mpesa-btn');
    if (testMpesaBtn) {
        testMpesaBtn.addEventListener('click', function() {
            KC.confirm({message: 'Initiate a test M-PESA STK push? This will send a test request to the sandbox.', title: 'Test M-PESA', action: 'Send', tone: 'primary'}).then(function(ok) {
                if (!ok) return;
                var formData = new FormData();
                formData.append('_csrf', document.querySelector('input[name="_csrf"]').value);
                fetch('<?= e(url('settings/test-mpesa')) ?>', {method:'POST', body: formData, headers: {'X-Requested-With': 'XMLHttpRequest'}})
                    .then(function(r) { return r.text(); })
                    .then(function(html) {
                        var temp = document.createElement('div');
                        temp.innerHTML = html;
                        var flash = temp.querySelector('.alert');
                        if (flash) {
                            var region = document.getElementById('settings-toast-region');
                            region.innerHTML = '<div class="kc-toast show ' + (flash.classList.contains('alert-success') ? 'toast-success' : 'toast-error') + '" role="alert">' + flash.textContent + '</div>';
                            setTimeout(function() { region.innerHTML = ''; }, 4000);
                        }
                        window.location.href = '<?= e(url('settings')) ?>#tab-mpesa';
                    })
                    .catch(function() { KC.toast('M-PESA test request failed.', 'error'); });
            });
        });
    }
})();
</script>