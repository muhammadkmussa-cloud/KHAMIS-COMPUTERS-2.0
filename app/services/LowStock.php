<?php
declare(strict_types=1);

/**
 * Low-stock alerting.
 *
 * After stock decreases (a sale, or a downward manual adjustment), the app
 * checks whether the product has fallen to or below the configured threshold
 * and emails the shop owner. To avoid flooding the inbox on busy days, each
 * product is alerted at most once per 24 hours. Sending is best-effort and
 * never throws back into the sale path.
 */
class LowStock
{
    public static function maybeAlert(int $productId): void
    {
        try {
            if (!Mailer::enabled()) {
                return;
            }
            $threshold = (float) Setting::get('low_stock_threshold', '5');
            $recipient = trim(Setting::get('low_stock_recipient', ''));
            if ($recipient === '') {
                $recipient = trim(Setting::get('shop_email', ''));
            }
            if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                return;
            }

            $product = Product::find($productId);
            if (!$product) {
                return;
            }
            $stock = Product::stock($productId);
            if ($stock > $threshold) {
                return;
            }

            // At most one alert per product per 24h.
            $last = Database::fetchValue('SELECT MAX(created_at) FROM low_stock_alerts WHERE product_id = ?', [$productId]);
            if ($last !== null && strtotime((string) $last) > time() - 86400) {
                return;
            }

            $html = Mailer::lowStockHtml($product, $stock, $threshold);
            $sent = Mailer::send($recipient, 'Low stock: ' . $product['name'] . ' (' . $stock . ' left)', $html);

            // Only record a *delivered* alert. A failed send must not suppress
            // the next attempt for 24 hours.
            if ($sent) {
                Database::insert('low_stock_alerts', [
                    'product_id'     => $productId,
                    'stock_quantity' => $stock,
                    'sent'           => 1,
                    'created_at'     => Database::now(),
                ]);
            } else {
                error_log('[low-stock] alert for product #' . $productId . ' could not be delivered.');
            }
        } catch (Throwable $e) {
            error_log('[low-stock] ' . $e->getMessage());
        }
    }
}
