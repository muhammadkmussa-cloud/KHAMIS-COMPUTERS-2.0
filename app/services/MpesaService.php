<?php
declare(strict_types=1);

/**
 * M-PESA STK Push integration (Safaricom Daraja API).
 *
 * Flow:
 *   1. Shop checkout (pay now) creates the sale as 'pending' and calls stkPush().
 *   2. Safaricom prompts the customer's phone for their M-PESA PIN.
 *   3. Safaricom POSTs the result to the public /mpesa/callback route.
 *   4. processCallback() marks the sale 'completed' and stores the receipt number.
 *
 * Config lives in shop_settings (Settings page): mpesa_enabled, mpesa_env
 * (sandbox|live), shortcode, passkey, consumer key/secret, shortcode type.
 */
class MpesaService
{
    public static function enabled(): bool
    {
        if (Setting::get('mpesa_enabled', '0') !== '1') {
            return false;
        }
        return trim(Setting::get('mpesa_shortcode', '')) !== ''
            && trim(Setting::get('mpesa_passkey', '')) !== ''
            && trim(Setting::get('mpesa_consumer_key', '')) !== ''
            && trim(Setting::get('mpesa_consumer_secret', '')) !== '';
    }

    public static function isLive(): bool
    {
        return Setting::get('mpesa_env', 'sandbox') === 'live';
    }

    public static function shortcode(): string
    {
        return trim(Setting::get('mpesa_shortcode', ''));
    }

    private static function baseUrl(): string
    {
        return self::isLive() ? 'https://api.safaricom.co.ke' : 'https://sandbox.safaricom.co.ke';
    }

    /** Normalize a Kenyan phone number to Safaricom's 2547XXXXXXXX format. */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            $digits = '254' . $digits;
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = '254' . substr($digits, 1);
        }
        return $digits;
    }

    /** Account reference for STK push — alphanumeric, max 12 characters. */
    public static function accountReference(string $saleNumber): string
    {
        $ref = preg_replace('/[^A-Za-z0-9]/', '', $saleNumber) ?? '';
        return substr($ref, 0, 12) ?: ('KC' . date('ymd'));
    }

    /** Fetch (or reuse) a Daraja OAuth access token, cached with its expiry. */
    public static function accessToken(): ?string
    {
        $expiry = (int) Setting::get('mpesa_token_expiry', '0');
        $cached = trim(Setting::get('mpesa_access_token', ''));
        if ($cached !== '' && $expiry > time() + 30) {
            return $cached;
        }

        $url  = self::baseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $auth = base64_encode(trim(Setting::get('mpesa_consumer_key', '')) . ':' . trim(Setting::get('mpesa_consumer_secret', '')));

        $json = self::postJson($url, ['Authorization: Basic ' . $auth]);
        if ($json === null) {
            return null;
        }
        $token = (string) ($json['access_token'] ?? '');
        if ($token === '') {
            error_log('[mpesa] token response missing access_token');
            return null;
        }
        $expiresIn = max(60, (int) ($json['expires_in'] ?? 3599));
        Setting::set('mpesa_access_token', $token);
        Setting::set('mpesa_token_expiry', (string) (time() + $expiresIn));
        return $token;
    }

    /**
     * Fire an STK push to the customer's phone.
     *
     * @return array{ok: bool, checkout_request_id: ?string, error: ?string}
     */
    public static function stkPush(string $phone, float $amount, string $saleNumber, int $saleId): array
    {
        $token = self::accessToken();
        if ($token === null) {
            return ['ok' => false, 'checkout_request_id' => null, 'error' => 'Could not connect to M-PESA (authentication failed).'];
        }

        $normalized = self::normalizePhone($phone);
        if (strlen($normalized) !== 12 || !str_starts_with($normalized, '254')) {
            return ['ok' => false, 'checkout_request_id' => null, 'error' => 'Enter a valid Safaricom phone number (e.g. 07XX XXX XXX).'];
        }
        if ($amount < 1) {
            return ['ok' => false, 'checkout_request_id' => null, 'error' => 'Order total is too small for M-PESA.'];
        }

        $shortcode = self::shortcode();
        $timestamp = date('YmdHis');
        $password  = base64_encode($shortcode . trim(Setting::get('mpesa_passkey', '')) . $timestamp);
        $txType    = Setting::get('mpesa_shortcode_type', 'paybill') === 'till'
            ? 'CustomerBuyGoodsOnline'
            : 'CustomerPayBillOnline';

        $payload = [
            'BusinessShortCode' => $shortcode,
            'Password'          => $password,
            'Timestamp'         => $timestamp,
            'TransactionType'   => $txType,
            'Amount'            => (string) round($amount, 0),
            'PartyA'            => $normalized,
            'PartyB'            => $shortcode,
            'PhoneNumber'       => $normalized,
            'CallBackURL'       => url('mpesa/callback'),
            'AccountReference'  => self::accountReference($saleNumber),
            'TransactionDesc'   => 'Khamis Computers order ' . $saleNumber,
        ];

        $json = self::postJson(
            self::baseUrl() . '/mpesa/stkpush/v1/processrequest',
            ['Authorization: Bearer ' . $token, 'Content-Type: application/json'],
            $payload
        );

        $checkoutRequestId = null;
        if (is_array($json)) {
            $responseCode        = (string) ($json['ResponseCode'] ?? '');
            $checkoutRequestId   = (string) ($json['CheckoutRequestID'] ?? '');
            $responseDescription = (string) ($json['ResponseDescription'] ?? '');
            $merchantRequestId   = (string) ($json['MerchantRequestID'] ?? '');

            Database::insert('mpesa_transactions', [
                'sale_id'             => $saleId,
                'checkout_request_id' => $checkoutRequestId !== '' ? $checkoutRequestId : null,
                'merchant_request_id' => $merchantRequestId !== '' ? $merchantRequestId : null,
                'phone'               => $normalized,
                'amount'              => round($amount, 2),
                'status'              => $responseCode === '0' ? 'requested' : 'failed',
                'result_code'         => $responseCode !== '' ? (int) $responseCode : null,
                'result_desc'         => $responseDescription !== '' ? $responseDescription : null,
                'created_at'          => Database::now(),
                'updated_at'          => Database::now(),
            ]);

            if ($responseCode === '0') {
                return ['ok' => true, 'checkout_request_id' => $checkoutRequestId, 'error' => null];
            }
            $err = $responseDescription !== ''
                ? $responseDescription
                : ($json['errorMessage'] ?? 'M-PESA rejected the request.');
            return ['ok' => false, 'checkout_request_id' => null, 'error' => $err];
        }

        return ['ok' => false, 'checkout_request_id' => null, 'error' => 'M-PESA did not respond. Please try again.'];
    }

    /**
     * Handle a Daraja STK callback. Public — invoked by Safaricom's servers.
     * Returns true when the callback was recognised and applied.
     */
    public static function processCallback(array $body): bool
    {
        $cb = $body['Body']['stkCallback'] ?? null;
        if (!is_array($cb)) {
            return false;
        }

        $checkoutRequestId = trim((string) ($cb['CheckoutRequestID'] ?? ''));
        $resultCode        = (int) ($cb['ResultCode'] ?? 1);
        $resultDesc        = trim((string) ($cb['ResultDesc'] ?? ''));

        $txn = $checkoutRequestId !== ''
            ? Database::fetch('SELECT * FROM mpesa_transactions WHERE checkout_request_id = ? LIMIT 1', [$checkoutRequestId])
            : null;
        if (!$txn) {
            error_log('[mpesa] callback for unknown CheckoutRequestID ' . $checkoutRequestId);
            return false;
        }

        $receipt = '';
        if ($resultCode === 0) {
            foreach ((array) ($cb['CallbackMetadata']['Item'] ?? []) as $item) {
                if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
                    $receipt = trim((string) ($item['Value'] ?? ''));
                }
            }
        }

        $status = $resultCode === 0 ? 'success' : 'failed';
        Database::update('mpesa_transactions', [
            'status'        => $status,
            'result_code'   => $resultCode,
            'result_desc'   => mb_substr($resultDesc, 0, 255),
            'receipt_number' => $receipt !== '' ? $receipt : null,
            'updated_at'    => Database::now(),
        ], 'id = :id', ['id' => (int) $txn['id']]);

        if ($resultCode === 0) {
            // Only flip a still-pending sale; never resurrect a voided one.
            Database::update(
                'sales',
                ['status' => 'completed', 'payment_ref' => $receipt !== '' ? $receipt : null],
                "id = :id AND status = 'pending'",
                ['id' => (int) $txn['sale_id']]
            );
            Activity::log('mpesa.paid', 'sale #' . $txn['sale_id'] . ' ' . ($receipt ?: ''));
        }
        return true;
    }

    /** Latest transaction for a sale (for admin display + retry). */
    public static function latestForSale(int $saleId): ?array
    {
        return Database::fetch(
            'SELECT * FROM mpesa_transactions WHERE sale_id = ? ORDER BY id DESC LIMIT 1',
            [$saleId]
        );
    }

    /** POST JSON with cURL (falls back to streams when cURL is unavailable). */
    private static function postJson(string $url, array $headers, ?array $body = null): ?array
    {
        $jsonBody = $body === null ? null : json_encode($body);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);
            if ($jsonBody !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            }
            $raw = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($raw === false) {
                error_log('[mpesa] cURL error: ' . $err);
                return null;
            }
        } else {
            $ctx = stream_context_create([
                'http' => [
                    'method'        => 'POST',
                    'header'        => implode("\r\n", $headers) . "\r\n",
                    'content'       => $jsonBody ?? '',
                    'timeout'       => 20,
                    'ignore_errors' => true,
                ],
                'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $raw = @file_get_contents($url, false, $ctx);
            if ($raw === false) {
                error_log('[mpesa] stream request failed');
                return null;
            }
        }

        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
