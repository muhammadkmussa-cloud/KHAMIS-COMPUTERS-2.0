<?php
declare(strict_types=1);

class SettingsController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('settings/index', [
            'title' => 'Settings',
            'settings' => Setting::all(),
            'zones' => DeliveryZone::all(),
            'vat'      => vat_rate(),
        ]);
    }

    public function update(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

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
        Setting::set('mail_from', strtolower(trim((string) ($_POST['mail_from'] ?? ''))));
        Setting::set('smtp_host', trim((string) ($_POST['smtp_host'] ?? '')));
        Setting::set('smtp_port', (string) max(1, (int) ($_POST['smtp_port'] ?? 587)));
        Setting::set('smtp_username', trim((string) ($_POST['smtp_username'] ?? '')));
        if (($_POST['smtp_password'] ?? '') !== '') {
            Setting::set('smtp_password', (string) $_POST['smtp_password']);
        }
        Setting::set('low_stock_threshold', (string) max(0, (int) ($_POST['low_stock_threshold'] ?? 5)));

        // M-PESA STK push.
        Setting::set('mpesa_enabled', isset($_POST['mpesa_enabled']) ? '1' : '0');
        Setting::set('mpesa_env', ($_POST['mpesa_env'] ?? 'sandbox') === 'live' ? 'live' : 'sandbox');
        Setting::set('mpesa_shortcode_type', ($_POST['mpesa_shortcode_type'] ?? 'paybill') === 'till' ? 'till' : 'paybill');
        foreach (['mpesa_shortcode', 'mpesa_passkey', 'mpesa_consumer_key'] as $f) {
            Setting::set($f, trim((string) ($_POST[$f] ?? '')));
        }
        if (($_POST['mpesa_consumer_secret'] ?? '') !== '') {
            Setting::set('mpesa_consumer_secret', (string) $_POST['mpesa_consumer_secret']);
        }

        $vat = (float) ($_POST['vat_rate'] ?? vat_rate());
        $vat = max(0.0, min(100.0, $vat));
        Setting::set('vat_rate', (string) round($vat, 2));

        // Discount authorisation (manager PIN gate).
        $threshold = max(0.0, (float) ($_POST['discount_pin_threshold'] ?? 0));
        Setting::set('discount_pin_threshold', (string) round($threshold, 2));
        if (!empty($_POST['discount_pin_clear'])) {
            Setting::set('discount_pin_hash', '');
        } else {
            $pin = (string) ($_POST['discount_pin'] ?? '');
            if ($pin !== '') {
                if (strlen($pin) < 4) {
                    flash('error', 'Discount PIN must be at least 4 characters.');
                    redirect('settings');
                }
                Setting::set('discount_pin_hash', password_hash($pin, PASSWORD_BCRYPT, ['cost' => (int) config('security.bcrypt_rounds', 12)]));
            }
        }

        Activity::log('settings.updated', 'vat=' . round($vat, 2));
        flash('success', 'Settings saved. They apply to new sales and receipts immediately.');
        redirect('settings');
    }
}
