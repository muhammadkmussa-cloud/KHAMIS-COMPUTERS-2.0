<?php
declare(strict_types=1);

/**
 * Mailer — transactional email for Khamis Computers.
 *
 * Sends HTML email through either:
 *   - 'mail' : PHP's mail() (works out of the box on cPanel), or
 *   - 'smtp' : a small dependency-free SMTP client (Gmail/Office365/etc.).
 *
 * All configuration lives in the shop_settings table (Settings page), so the
 * shop owner can switch transport without touching code. Sending is best-
 * effort: callers wrap it in try/catch so a mail failure never breaks a sale.
 */
class Mailer
{
    public static function enabled(): bool
    {
        return Setting::get('mail_enabled', '0') === '1';
    }

    public static function fromEmail(): string
    {
        $email = trim(Setting::get('mail_from', ''));
        if ($email === '') {
            $email = trim(Setting::get('shop_email', 'no-reply@khamiscomputers.com'));
        }
        return $email !== '' ? $email : 'no-reply@khamiscomputers.com';
    }

    public static function fromName(): string
    {
        $name = trim(Setting::get('mail_from_name', ''));
        return $name !== '' ? $name : trim(Setting::get('shop_name', 'Khamis Computers'));
    }

    public static function transport(): string
    {
        return Setting::get('mail_transport', 'mail') === 'smtp' ? 'smtp' : 'mail';
    }

    /** Send an email. Returns true when handed off successfully. */
    public static function send(string $to, string $subject, string $html, string $text = ''): bool
    {
        if (!self::enabled() || trim($to) === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if (trim($text) === '') {
            $text = trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace(['</p>', '</tr>', '</h1>', '</h2>', '</h3>', '<br>', '<br/>'], "\n", $html))));
        }
        try {
            return self::transport() === 'smtp'
                ? self::sendSmtp($to, $subject, $html, $text)
                : self::sendPhpMail($to, $subject, $html, $text);
        } catch (Throwable $e) {
            error_log('[mailer] ' . $e->getMessage());
            return false;
        }
    }

    private static function sendPhpMail(string $to, string $subject, string $html, string $text): bool
    {
        $from    = self::fromEmail();
        $fromName = self::fromName();
        $boundary = 'kc_' . md5((string) microtime(true) . $to);

        $headers = [
            'MIME-Version: 1.0',
            'From: ' . self::headerLine($fromName, $from),
            'Reply-To: ' . self::headerLine($fromName, $from),
            'X-Mailer: Khamis Computers',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $body  = '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
        $body .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
        $body .= $text . "\r\n\r\n";
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Type: text/html; charset=UTF-8' . "\r\n";
        $body .= 'Content-Transfer-Encoding: 8bit' . "\r\n\r\n";
        $body .= $html . "\r\n\r\n";
        $body .= '--' . $boundary . '--';

        $subject = self::encodeHeader($subject);
        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    /** Minimal dependency-free SMTP client (STARTTLS + AUTH LOGIN). */
    private static function sendSmtp(string $to, string $subject, string $html, string $text): bool
    {
        $host = trim(Setting::get('smtp_host', ''));
        if ($host === '') {
            return false;
        }
        $port    = (int) Setting::get('smtp_port', '587');
        $enc     = Setting::get('smtp_encryption', 'tls'); // tls = STARTTLS, ssl = SMTPS
        $user    = trim(Setting::get('smtp_username', ''));
        $pass    = (string) Setting::get('smtp_password', '');

        $remote = ($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host;
        $fp     = @stream_socket_client($remote . ':' . $port, $errno, $errstr, 15);
        if (!$fp) {
            error_log('[mailer] SMTP connect failed: ' . $errstr . ' (' . $errno . ')');
            return false;
        }
        stream_set_timeout($fp, 15);

        $say = function (string $line, string $expect) use ($fp): bool {
            if ($line !== '') {
                fwrite($fp, $line . "\r\n");
            }
            $resp = '';
            while ($chunk = fgets($fp, 515)) {
                $resp .= $chunk;
                if (isset($chunk[3]) && $chunk[3] === ' ') {
                    break;
                }
            }
            $code = (int) substr($resp, 0, 3);
            if ($code >= 400) {
                error_log('[mailer] SMTP error: ' . trim($resp));
            }
            return strpos((string) $code, $expect) === 0 || ($expect === '' && $code === 0);
        };

        $ok = $say('', '2') !== false; // banner
        if (!$ok) { fclose($fp); return false; }

        $say('EHLO ' . (gethostname() ?: 'localhost'), '2');

        if ($enc === 'tls') {
            $say('STARTTLS', '2');
            if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                error_log('[mailer] SMTP STARTTLS failed');
                fclose($fp);
                return false;
            }
            $say('EHLO ' . (gethostname() ?: 'localhost'), '2');
        }

        if ($user !== '') {
            $say('AUTH LOGIN', '3');
            $say(base64_encode($user), '3');
            $say(base64_encode($pass), '2');
        }

        $say('MAIL FROM:<' . self::fromEmail() . '>', '2');
        $say('RCPT TO:<' . $to . '>', '2');
        $say('DATA', '3');

        $boundary = 'kc_' . md5((string) microtime(true) . $to);
        $headers  = "From: " . self::headerLine(self::fromName(), self::fromEmail()) . "\r\n"
                  . "To: <" . $to . ">\r\n"
                  . "Subject: " . self::encodeHeader($subject) . "\r\n"
                  . "MIME-Version: 1.0\r\n"
                  . "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n"
                  . "X-Mailer: Khamis Computers\r\n";
        $payload  = $headers . "\r\n"
                  . '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n" . $text . "\r\n\r\n"
                  . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n" . $html . "\r\n\r\n"
                  . '--' . $boundary . "--\r\n";

        fwrite($fp, $payload . ".\r\n");
        $say('', '2');
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }

    private static function headerLine(string $name, string $email): string
    {
        return self::encodeHeader($name) . ' <' . $email . '>';
    }

    /** RFC 2047 encode a header value when it contains non-ASCII characters. */
    private static function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    /* ---------------------------------------------------------------------
     * Templates
     * ------------------------------------------------------------------- */

    /** HTML order-confirmation email for the online shop. */
    public static function orderConfirmationHtml(array $sale, array $items): string
    {
        $shop = e(Setting::get('shop_name', 'Khamis Computers'));
        $rows = '';
        foreach ($items as $i) {
            $serial = !empty($i['serial_number']) ? '<br><span style="color:#86868b;font-size:12px">Serial: ' . e($i['serial_number']) . '</span>' : '';
            $rows .= '<tr>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #eee">' . e($i['product_name']) . $serial . '</td>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #eee;text-align:right">' . (int) $i['quantity'] . '</td>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #eee;text-align:right">' . money($i['unit_price']) . '</td>'
                . '<td style="padding:10px 8px;border-bottom:1px solid #eee;text-align:right">' . money($i['line_total']) . '</td>'
                . '</tr>';
        }
        $vatLine = '<tr><td colspan="3" style="padding:6px 8px;text-align:right;color:#6e6e73">VAT (' . e((string) vat_rate()) . '%)</td>'
            . '<td style="padding:6px 8px;text-align:right">' . money($sale['tax_amount']) . '</td></tr>';
        if ((float) $sale['discount'] > 0) {
            $vatLine = '<tr><td colspan="3" style="padding:6px 8px;text-align:right;color:#6e6e73">Discount</td>'
                . '<td style="padding:6px 8px;text-align:right">− ' . money($sale['discount']) . '</td></tr>' . $vatLine;
        }
        $deliveryLine = '';
        if ((float) $sale['delivery_fee'] > 0) {
            $deliveryLine = '<tr><td colspan="3" style="padding:6px 8px;text-align:right;color:#6e6e73">Delivery ('
                . e($sale['delivery_zone'] ?: 'delivery') . ')</td>'
                . '<td style="padding:6px 8px;text-align:right">' . money($sale['delivery_fee']) . '</td></tr>';
        }
        $fulfil = $sale['fulfillment'] === 'delivery' ? 'Delivery' : 'Store pickup';
        if ($sale['delivery_address']) {
            $fulfil .= '<br><span style="color:#86868b;font-size:12px">' . e($sale['delivery_address']) . '</span>';
        }

        return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">'
            . '<div style="max-width:600px;margin:0 auto;padding:32px 16px">'
            . '<h1 style="font-size:22px;color:#1d1d1f;margin:0 0 6px">Thank you for your order, ' . e((string) ($sale['customer_name'] ?? '')) . '</h1>'
            . '<p style="color:#6e6e73;font-size:14px;margin:0 0 24px">Order <b style="color:#1d1d1f">' . e($sale['sale_number']) . '</b> · ' . e(date('d M Y · h:i A', strtotime($sale['created_at']))) . '</p>'
            . '<table style="width:100%;border-collapse:collapse;background:#fff;border-radius:14px;overflow:hidden;font-size:14px;color:#1d1d1f">'
            . '<thead><tr style="background:#fafafa">'
            . '<th style="padding:10px 8px;text-align:left">Item</th><th style="padding:10px 8px;text-align:right">Qty</th>'
            . '<th style="padding:10px 8px;text-align:right">Price</th><th style="padding:10px 8px;text-align:right">Total</th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<table style="width:100%;margin-top:12px;font-size:14px">'
            . '<tr><td style="padding:4px 0;color:#6e6e73">Subtotal</td><td style="text-align:right">' . money($sale['subtotal']) . '</td></tr>'
            . $vatLine . $deliveryLine
            . '<tr><td style="padding:10px 0 4px;font-weight:700;font-size:16px">Total</td><td style="text-align:right;font-weight:700;font-size:16px">' . money($sale['total']) . '</td></tr></table>'
            . '<p style="color:#6e6e73;font-size:14px;margin:24px 0 8px">Fulfilment: <b style="color:#1d1d1f">' . e($fulfil) . '</b><br>Payment: <b style="color:#1d1d1f">' . e(strtoupper($sale['payment_method'])) . ' on ' . ($sale['fulfillment'] === 'delivery' ? 'delivery' : 'pickup') . '</b></p>'
            . '<p style="color:#6e6e73;font-size:14px;margin:0 0 24px">We will call you on <b style="color:#1d1d1f">' . e((string) ($sale['customer_phone'] ?? '')) . '</b> to confirm your order.</p>'
            . '<p style="color:#86868b;font-size:12px;margin:0">' . e($shop) . '</p>'
            . '</div></body></html>';
    }

    /** HTML low-stock alert for the shop owner. */
    public static function lowStockHtml(array $product, int $stock, float $threshold): string
    {
        $shop = e(Setting::get('shop_name', 'Khamis Computers'));
        return '<!DOCTYPE html><html><body style="margin:0;padding:0;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">'
            . '<div style="max-width:600px;margin:0 auto;padding:32px 16px">'
            . '<div style="display:inline-block;background:#ff3b30;color:#fff;font-size:12px;font-weight:600;border-radius:999px;padding:4px 12px;margin-bottom:14px">LOW STOCK</div>'
            . '<h1 style="font-size:22px;color:#1d1d1f;margin:0 0 6px">' . e($product['name']) . '</h1>'
            . '<p style="color:#6e6e73;font-size:14px;margin:0 0 24px">Only <b style="color:#1d1d1f">' . $stock . '</b> left in stock, which is at or below your alert threshold of ' . (int) $threshold . '. Please restock or hide the product from the online shop.</p>'
            . '<p style="color:#86868b;font-size:12px;margin:0">SKU ' . e($product['sku']) . ' · ' . e($shop) . '</p>'
            . '</div></body></html>';
    }
}
