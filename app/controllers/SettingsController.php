<?php
declare(strict_types=1);

class SettingsController
{
    public function index(): void
    {
        Auth::requireLogin();
        $migrated = Schema::tableExists('hero_slides');
        View::render('settings/index', [
            'title' => 'Settings',
            'settings' => Setting::all(),
            'zones' => DeliveryZone::all(),
            'vat'      => vat_rate(),
            'slides'   => HeroSlide::all(),
            'migrated' => $migrated,
        ]);
    }

    public function update(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $errors = [];

        $shopEmail = strtolower(trim((string) ($_POST['shop_email'] ?? '')));
        $mailFrom = strtolower(trim((string) ($_POST['mail_from'] ?? '')));
        $alertRecipient = strtolower(trim((string) ($_POST['low_stock_recipient'] ?? '')));
        foreach ([['Shop email', $shopEmail], ['Sender email', $mailFrom], ['Low-stock recipient', $alertRecipient]] as [$label, $value]) {
            if ($value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[] = $label . ' must be a valid email address.';
            }
        }

        $rawVat = trim((string) ($_POST['vat_rate'] ?? ''));
        $pin = (string) ($_POST['discount_pin'] ?? '');
        if ($pin !== '' && strlen($pin) < 4) {
            $errors[] = 'Discount PIN must be at least 4 characters.';
        }
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $rawVat) || (float) $rawVat > 100) {
            $errors[] = 'VAT rate must be a number from 0 to 100 with at most two decimal places.';
        }

        $phone = trim((string) ($_POST['shop_phone'] ?? ''));
        if ($phone !== '' && !preg_match('/^\+?[0-9\s\-\(\)]{7,20}$/', $phone)) {
            $errors[] = 'Phone number format is invalid.';
        }

        $mpesaShortcode = trim((string) ($_POST['mpesa_shortcode'] ?? ''));
        if ($mpesaShortcode !== '' && !preg_match('/^\d{1,10}$/', $mpesaShortcode)) {
            $errors[] = 'M-PESA shortcode must be a number (e.g. 174379).';
        }
        $mpesaPasskey = trim((string) ($_POST['mpesa_passkey'] ?? ''));
        if ($mpesaPasskey !== '' && strlen($mpesaPasskey) < 8) {
            $errors[] = 'M-PESA passkey must be at least 8 characters.';
        }

        // WhatsApp number validation
        $waRaw = trim((string) ($_POST['whatsapp_sales_number'] ?? ''));
        if ($waRaw !== '') {
            $waNorm = normalize_whatsapp_number($waRaw);
            if ($waNorm === '') {
                $errors[] = 'WhatsApp sales number is invalid. Use formats like 07XXXXXXXX, 2547XXXXXXXX, or +2547XXXXXXXX.';
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('settings');
            return;
        }

        // Business profile.
        foreach (['shop_name', 'shop_tagline', 'shop_phone', 'shop_email', 'shop_address', 'receipt_footer'] as $f) {
            Setting::set($f, trim((string) ($_POST[$f] ?? '')));
        }

        // Email notifications.
        Setting::set('mail_enabled', isset($_POST['mail_enabled']) ? '1' : '0');
        Setting::set('mail_transport', ($_POST['mail_transport'] ?? 'mail') === 'smtp' ? 'smtp' : 'mail');
        Setting::set('smtp_encryption', in_array($_POST['smtp_encryption'] ?? 'tls', ['tls', 'ssl', 'none'], true) ? (string) $_POST['smtp_encryption'] : 'tls');
        foreach (['mail_from_name', 'mail_from', 'low_stock_recipient'] as $f) {
            Setting::set($f, trim((string) ($_POST[$f] ?? '')));
        }
        Setting::set('smtp_host', trim((string) ($_POST['smtp_host'] ?? '')));
        Setting::set('smtp_port', (string) max(1, min(65535, (int) ($_POST['smtp_port'] ?? 587))));
        Setting::set('smtp_username', trim((string) ($_POST['smtp_username'] ?? '')));
        if (($_POST['smtp_password'] ?? '') !== '') {
            Setting::set('smtp_password', (string) $_POST['smtp_password']);
        }
        Setting::set('low_stock_threshold', (string) max(0, (int) ($_POST['low_stock_threshold'] ?? 5)));

        // M-PESA STK push (POS + online)
        Setting::set('mpesa_enabled', isset($_POST['mpesa_enabled']) ? '1' : '0');
        Setting::set('mpesa_env', ($_POST['mpesa_env'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox');
        Setting::set('mpesa_shortcode_type', ($_POST['mpesa_shortcode_type'] ?? 'paybill') === 'till' ? 'till' : 'paybill');
        foreach (['mpesa_shortcode', 'mpesa_passkey', 'mpesa_consumer_key'] as $f) {
            Setting::set($f, trim((string) ($_POST[$f] ?? '')));
        }
        if (($_POST['mpesa_consumer_secret'] ?? '') !== '') {
            Setting::set('mpesa_consumer_secret', (string) $_POST['mpesa_consumer_secret']);
        }

        // Catalogue + WhatsApp mode flags
        Setting::set('online_checkout_enabled', isset($_POST['online_checkout_enabled']) ? '1' : '0');
        Setting::set('mpesa_online_enabled', isset($_POST['mpesa_online_enabled']) ? '1' : '0');
        Setting::set('whatsapp_ordering_enabled', isset($_POST['whatsapp_ordering_enabled']) ? '1' : '0');
        if ($waRaw !== '') {
            Setting::set('whatsapp_sales_number', $waRaw);
        } elseif (isset($_POST['whatsapp_sales_number'])) {
            // Allow clearing
            Setting::set('whatsapp_sales_number', '');
        }
        if (isset($_POST['whatsapp_message_template'])) {
            Setting::set('whatsapp_message_template', trim((string) $_POST['whatsapp_message_template']));
        }

        // VAT rate.
        $vat = (float) $rawVat;
        $vat = max(0.0, min(100.0, $vat));
        Setting::set('vat_rate', (string) round($vat, 2));

        // Discount authorisation (manager PIN gate).
        $threshold = max(0.0, (float) ($_POST['discount_pin_threshold'] ?? 0));
        Setting::set('discount_pin_threshold', (string) round($threshold, 2));
        if (!empty($_POST['discount_pin_clear'])) {
            Setting::set('discount_pin_hash', '');
        } else {
            if ($pin !== '') {
                Setting::set('discount_pin_hash', password_hash($pin, PASSWORD_BCRYPT, ['cost' => (int) config('security.bcrypt_rounds', 12)]));
            }
        }

        Activity::log('settings.updated', 'vat=' . round($vat, 2) . ' checkout=' . (isset($_POST['online_checkout_enabled']) ? '1' : '0') . ' whatsapp=' . (isset($_POST['whatsapp_ordering_enabled']) ? '1' : '0'));
        flash('success', 'Settings saved. They apply to new sales and receipts immediately.');
        // Redirect back to the tab that was submitted if present
        $tab = $_POST['active_tab'] ?? '';
        if ($tab !== '') {
            redirect('settings?tab=' . urlencode($tab));
        } else {
            redirect('settings');
        }
    }

    public function testEmail(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $settings = Setting::all();
        $mailFrom = $settings['mail_from'] ?? '';
        $mailFromName = $settings['mail_from_name'] ?? config('app.name');

        $body = '<h2>Test Email</h2><p>This is a test email from ' . e($settings['shop_name'] ?? config('app.name')) . '.</p><p>If you are receiving this, your email settings are working correctly.</p>';

        $sent = mail($mailFrom, 'Email configuration test — ' . e($settings['shop_name'] ?? config('app.name')), $body, [
            'From' => $mailFromName . ' <' . $mailFrom . '>',
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);

        if ($sent) {
            flash('success', 'Test email sent successfully. Check your inbox at ' . e($mailFrom));
        } else {
            flash('error', 'Failed to send test email. Check your SMTP or PHP mail configuration.');
        }
        redirect('settings?tab=notifications');
    }

    public function testMpesa(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $settings = Setting::all();
        if (!isset($settings['mpesa_enabled']) || $settings['mpesa_enabled'] !== '1') {
            flash('error', 'M-PESA is not enabled. Enable it first in the M-PESA tab.');
            redirect('settings?tab=mpesa');
            return;
        }
        if (empty($settings['mpesa_shortcode']) || empty($settings['mpesa_passkey']) || empty($settings['mpesa_consumer_key']) || empty($settings['mpesa_consumer_secret'])) {
            flash('error', 'Complete all M-PESA fields (shortcode, passkey, consumer key, consumer secret) before testing.');
            redirect('settings?tab=mpesa');
            return;
        }

        try {
            $result = MpesaService::stkPush('254700000000', 1, 'TEST-' . date('YmdHis'), 0);
            // stkPush now returns ['ok'=>bool,...] not ['success']
            if (!empty($result['ok'])) {
                flash('success', 'M-PESA connection test successful. STK push initiated (test transaction will not complete).');
            } else {
                flash('error', 'M-PESA test failed: ' . ($result['error'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            flash('error', 'M-PESA test failed: ' . $e->getMessage());
        }
        redirect('settings?tab=mpesa');
    }
}
