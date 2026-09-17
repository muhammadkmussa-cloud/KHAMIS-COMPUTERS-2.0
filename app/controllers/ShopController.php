<?php
declare(strict_types=1);

/**
 * Online storefront — public pages.
 * Now supports Catalogue + WhatsApp mode via feature flags.
 * Shares the same inventory and the same SaleService as the POS.
 * Checkout is disabled by default but code remains for future activation.
 */
class ShopController
{
    public function home(): void
    {
        $checkoutEnabled = is_online_checkout_enabled();
        $whatsappEnabled = is_whatsapp_ordering_enabled();
        View::render('shop/home', [
            'title'      => config('app.name') . ' — ' . Setting::get('shop_tagline', 'Shop online or in store'),
            'shopName'   => Setting::get('shop_name', config('app.name')),
            'featured'   => Product::featured(8),
            'categories' => Category::all(true),
            'slides'     => HeroSlide::active(),
            'checkoutEnabled' => $checkoutEnabled,
            'whatsappEnabled' => $whatsappEnabled,
            'whatsappNumber' => whatsapp_number(),
        ], 'shop');
    }

    public function browse(): void
    {
        $q      = trim((string) ($_GET['q'] ?? ''));
        $cat    = trim((string) ($_GET['category'] ?? ''));
        $brand  = trim((string) ($_GET['brand'] ?? ''));
        $sort   = (string) ($_GET['sort'] ?? 'name');

        View::render('shop/browse', [
            'title'      => 'All products — ' . config('app.name'),
            'products'   => Product::shopBrowse($q, $cat, $brand, $sort),
            'categories' => Category::all(true),
            'brands'     => Brand::all(true),
            'q'          => $q,
            'cat'        => $cat,
            'brand'      => $brand,
            'sort'       => $sort,
            'checkoutEnabled' => is_online_checkout_enabled(),
            'whatsappEnabled' => is_whatsapp_ordering_enabled(),
        ], 'shop');
    }

    public function product(int $id): void
    {
        $product = Product::find($id);
        if (!$product || (int) $product['is_active'] !== 1) {
            http_response_code(404);
            View::render('shop/404', ['title' => 'Product not found'], 'shop');
            return;
        }

        $images = Product::images($id);
        $stock = Product::stock($id);
        $variants = Product::variants($id, true);
        $checkoutEnabled = is_online_checkout_enabled();
        $whatsappEnabled = is_whatsapp_ordering_enabled();
        $whatsappNumber = whatsapp_number();
        $shopName = Setting::get('shop_name', config('app.name'));

        // Open Graph metadata
        $primaryImage = null;
        if (!empty($images)) {
            foreach ($images as $img) {
                if (($img['filename'] ?? '') === ($product['image'] ?? '')) {
                    $primaryImage = $img;
                    break;
                }
            }
            if (!$primaryImage) $primaryImage = $images[0];
        }
        $ogImage = $primaryImage ? url('uploads/p/' . rawurlencode($primaryImage['filename'])) : url('assets/favicon.svg');
        // Ensure absolute HTTPS URL for OG in production - base_url already absolute
        $ogUrl = url('shop/product/' . $id);
        $variantSummary = '';
        if (!empty($variants)) {
            $sample = $variants[0];
            $parts = [];
            if (!empty($sample['ram'])) $parts[] = $sample['ram'] . ' RAM';
            if (!empty($sample['storage'])) $parts[] = $sample['storage'];
            if (!empty($sample['colour'])) $parts[] = $sample['colour'];
            $variantSummary = $parts ? implode(', ', $parts) . ' - ' : '';
        }
        $ogDescription = $variantSummary . ($product['description'] ? substr(trim($product['description']), 0, 160) : 'Available at ' . $shopName);
        $ogTitle = $product['name'] . ' | ' . $shopName;

        View::render('shop/product', [
            'title'   => $product['name'] . ' — ' . config('app.name'),
            'product' => $product,
            'images'  => $images,
            'stock'   => $stock,
            'related' => Product::related($id, $product['category_id'] ? (int) $product['category_id'] : null, 4),
            'variants' => $variants,
            'checkoutEnabled' => $checkoutEnabled,
            'whatsappEnabled' => $whatsappEnabled,
            'whatsappNumber' => $whatsappNumber,
            'shopName' => $shopName,
            'ogTitle' => $ogTitle,
            'ogDescription' => $ogDescription,
            'ogImage' => $ogImage,
            'ogUrl' => $ogUrl,
        ], 'shop');
    }

    public function cart(): void
    {
        // If checkout disabled, redirect to shop with message or show unavailable page
        if (!is_online_checkout_enabled()) {
            View::render('shop/cart', [
                'title' => 'Online checkout unavailable — ' . config('app.name'),
                'zones' => [],
                'mpesaEnabled' => false,
                'recommendations' => Product::featured(4),
                'checkoutEnabled' => false,
                'whatsappEnabled' => is_whatsapp_ordering_enabled(),
                'checkoutDisabledMessage' => true,
            ], 'shop');
            return;
        }

        View::render('shop/cart', [
            'title' => 'Your cart — ' . config('app.name'),
            'zones' => DeliveryZone::active(),
            'mpesaEnabled' => is_mpesa_online_enabled(),
            'recommendations' => Product::featured(4),
            'checkoutEnabled' => true,
            'whatsappEnabled' => is_whatsapp_ordering_enabled(),
        ], 'shop');
    }

    /** JSON: live product info + stock for the ids currently in the cart. */
    public function apiCart(): void
    {
        // If checkout disabled, return empty or 403 but keep endpoint alive for future
        if (!is_online_checkout_enabled()) {
            json_response(['ok' => false, 'error' => 'Online checkout is currently unavailable.'], 403);
        }

        $ids = array_filter(array_map('intval', explode(',', (string) ($_GET['ids'] ?? ''))));
        $out = [];
        foreach ($ids as $id) {
            $p = Product::find($id);
            if (!$p || (int) $p['is_active'] !== 1) {
                continue;
            }
            $out[] = [
                'id'         => (int) $p['id'],
                'name'       => $p['name'],
                'sku'        => $p['sku'],
                'price'      => (float) $p['sell_price'],
                'stock'      => Product::stock((int) $p['id']),
                'serialized' => (int) $p['is_serialized'] === 1,
                'category'   => (string) ($p['category_name'] ?? ''),
            ];
        }
        json_response($out);
    }

    /** Guest checkout (JSON API). Disabled in catalogue mode. */
    public function checkout(): void
    {
        // Server-side enforcement of feature flag
        if (!is_online_checkout_enabled()) {
            json_response(['ok' => false, 'error' => 'Online checkout is currently unavailable. Please contact us via WhatsApp to place your order.'], 403);
        }

        if (!Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''))) {
            json_response(['ok' => false, 'error' => 'Your session expired. Please refresh and try again.'], 419);
        }

        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            json_response(['ok' => false, 'error' => 'Invalid request.'], 400);
        }

        $name        = trim((string) ($data['name'] ?? ''));
        $phone       = self::normalizeKenyanPhone((string) ($data['phone'] ?? ''));
        $email       = trim((string) ($data['email'] ?? ''));
        $fulfillment = (($data['fulfillment'] ?? 'pickup') === 'delivery') ? 'delivery' : 'pickup';
        $address     = trim((string) ($data['address'] ?? ''));
        $mpesaOnlineEnabled = is_mpesa_online_enabled();
        try {
            [$payment, $payNow] = self::checkoutPaymentIntent($data, $mpesaOnlineEnabled);
        } catch (InvalidArgumentException $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $clientRef = trim((string) ($data['client_ref'] ?? ''));
        $deviceId  = trim((string) ($data['device_id'] ?? ''));

        if ($name === '') {
            json_response(['ok' => false, 'error' => 'Please enter your full name.'], 422);
        }
        if ($phone === null) {
            json_response(['ok' => false, 'error' => 'Enter a valid Kenyan mobile number, for example 0712 345 678.'], 422);
        }
        if ($clientRef === '' || $deviceId === '' || strlen($clientRef) > 100 || strlen($deviceId) > 100) {
            json_response(['ok' => false, 'error' => 'Could not secure this checkout. Refresh the page and try again.'], 422);
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            json_response(['ok' => false, 'error' => 'Please enter a valid email address.'], 422);
        }
        if ($fulfillment === 'delivery' && $address === '') {
            json_response(['ok' => false, 'error' => 'Please enter your delivery address.'], 422);
        }

        // Delivery fee: resolved server-side
        $deliveryFee  = 0.0;
        $deliveryZone = '';
        if ($fulfillment === 'delivery') {
            $zoneId = (int) ($data['delivery_zone_id'] ?? 0);
            $zone   = $zoneId > 0 ? DeliveryZone::find($zoneId) : null;
            if (!$zone || (int) $zone['is_active'] !== 1) {
                json_response(['ok' => false, 'error' => 'Please choose a delivery area.'], 422);
            }
            $deliveryFee  = round((float) $zone['fee'], 2);
            $deliveryZone = (string) $zone['name'];
        }

        $lines = [];
        foreach ((array) ($data['lines'] ?? []) as $l) {
            $pid = (int) ($l['product_id'] ?? 0);
            $qty = max(1, (int) ($l['quantity'] ?? 1));
            if ($pid <= 0) {
                continue;
            }
            $lines[] = ['product_id' => $pid, 'quantity' => $qty, 'unit_ids' => []];
        }
        if (!$lines) {
            json_response(['ok' => false, 'error' => 'Your cart is empty.'], 422);
        }

        try {
            $sale = SaleService::create($lines, [
                'channel'             => 'online',
                'auto_assign_serials' => true,
                'discount'            => 0,
                'payment_method'      => $payment,
                'customer_name'       => $name,
                'customer_email'      => $email,
                'customer_phone'      => $phone,
                'fulfillment'         => $fulfillment,
                'delivery_address'    => $address,
                'delivery_fee'        => $deliveryFee,
                'delivery_zone'       => $deliveryZone,
                'status'              => $payNow ? 'pending' : 'completed',
                'user_id'             => null,
                'client_ref'          => $clientRef,
                'device_id'           => $deviceId,
                'sale_source'         => 'online',
            ]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        if (!empty($sale['duplicate'])) {
            $existing = SaleService::find((int) $sale['id']);
            if ($existing && in_array($existing['status'], ['cancelled', 'voided'], true)) {
                json_response(['ok' => false, 'reset_ref' => true, 'error' => 'That payment attempt ended. Please place the order again.'], 409);
            }
        }

        // M-PESA STK push: only if online M-Pesa enabled
        if ($payNow && empty($sale['duplicate'])) {
            if (!is_mpesa_online_enabled()) {
                try {
                    SaleService::void((int) $sale['id'], null);
                } catch (Throwable $e) {
                    error_log('[mpesa] could not void disabled online order: ' . $e->getMessage());
                }
                json_response(['ok' => false, 'reset_ref' => true, 'error' => 'M-PESA online payments are currently unavailable.'], 403);
            }
            $full = SaleService::find((int) $sale['id']);
            $push = MpesaService::stkPush($phone, (float) $full['total'], (string) $full['sale_number'], (int) $sale['id']);
            if (!$push['ok']) {
                try {
                    SaleService::void((int) $sale['id'], null);
                } catch (Throwable $e) {
                    error_log('[mpesa] could not void failed order: ' . $e->getMessage());
                }
                json_response(['ok' => false, 'reset_ref' => true, 'error' => $push['error'] ?? 'M-PESA is unavailable. Please try again.'], 422);
            }
        }

        $_SESSION['last_order_id'] = $sale['id'];

        if ($email !== '' && Mailer::enabled()) {
            try {
                $full  = SaleService::find((int) $sale['id']);
                $items = SaleService::items((int) $sale['id']);
                if ($full) {
                    Mailer::send(
                        $email,
                        'Order ' . $full['sale_number'] . ' confirmed — ' . Setting::get('shop_name', config('app.name')),
                        Mailer::orderConfirmationHtml($full, $items)
                    );
                }
            } catch (Throwable $e) {
                error_log('[mailer] order confirmation failed: ' . $e->getMessage());
            }
        }

        json_response(['ok' => true, 'redirect' => url('shop/order/' . $sale['id'])]);
    }

    /** Lightweight WhatsApp enquiry tracking (optional, no personal data). */
    public function whatsappEnquiry(): void
    {
        if (!Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''))) {
            json_response(['ok' => false, 'error' => 'Invalid CSRF'], 419);
        }
        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            json_response(['ok' => false, 'error' => 'Invalid request'], 400);
        }
        $productId = (int)($data['product_id'] ?? 0);
        $variantId = isset($data['variant_id']) ? (int)$data['variant_id'] : null;
        $productName = trim((string)($data['product_name'] ?? ''));
        $variantLabel = trim((string)($data['variant_label'] ?? ''));
        $priceShown = isset($data['price_shown']) ? (float)$data['price_shown'] : null;
        $sourcePage = trim((string)($data['source_page'] ?? ''));
        $productUrl = trim((string)($data['product_url'] ?? $sourcePage));
        $conditionType = trim((string)($data['condition_type'] ?? ''));

        if (!Schema::tableExists('whatsapp_enquiries')) {
            json_response(['ok' => true]);
        }

        try {
            $row = [
                'product_id' => $productId > 0 ? $productId : null,
                'variant_id' => $variantId,
                'product_name' => $productName !== '' ? substr($productName, 0, 190) : null,
                'variant_label' => $variantLabel !== '' ? substr($variantLabel, 0, 190) : null,
                'price_shown' => $priceShown,
                'source_page' => $sourcePage !== '' ? substr($sourcePage, 0, 255) : null,
                'created_at' => Database::now(),
            ];
            if (Schema::columnExists('whatsapp_enquiries','product_url')) {
                $row['product_url'] = $productUrl !== '' ? substr($productUrl,0,255) : null;
            }
            if (Schema::columnExists('whatsapp_enquiries','condition_type')) {
                $row['condition_type'] = $conditionType !== '' ? substr($conditionType,0,20) : null;
            }
            Database::insert('whatsapp_enquiries', $row);
        } catch (Throwable $e) {
            error_log('[whatsapp] enquiry tracking failed: ' . $e->getMessage());
        }
        json_response(['ok' => true]);
    }

    /** JSON status for the order-confirmation page's payment poll. */
    public function orderStatus(int $id): void
    {
        if ((int) ($_SESSION['last_order_id'] ?? 0) !== $id) {
            json_response(['ok' => false, 'error' => 'Not found.'], 404);
        }
        $sale = SaleService::find($id);
        if (!$sale) {
            json_response(['ok' => false, 'error' => 'Not found.'], 404);
        }
        json_response([
            'ok'      => true,
            'status'  => $sale['status'],
            'receipt' => $sale['payment_ref'],
        ]);
    }

    /** Safaricom Daraja STK callback (server-to-server, no session). */
    public function mpesaCallback(): void
    {
        if (!MpesaService::validCallbackToken((string) ($_GET['token'] ?? ''))) {
            http_response_code(404);
            echo '{\"ResultCode\":1,\"ResultDesc\":\"Not found\"}';
            return;
        }
        $raw  = (string) file_get_contents('php://input');
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            http_response_code(400);
            echo '{\"ResultCode\":1,\"ResultDesc\":\"Bad payload\"}';
            return;
        }

        $ok = MpesaService::processCallback($body);
        header('Content-Type: application/json');
        echo $ok
            ? '{\"ResultCode\":0,\"ResultDesc\":\"Accepted\"}'
            : '{\"ResultCode\":1,\"ResultDesc\":\"Unknown transaction\"}';
    }

    /** Customer order lookup — GET form. */
    public function track(): void
    {
        // Hide tracking when checkout disabled? Keep for historical orders but show message.
        View::render('shop/track', [
            'title' => 'Track your order — ' . config('app.name'),
            'checkoutEnabled' => is_online_checkout_enabled(),
        ], 'shop');
    }

    /** Customer order lookup — POST (session rate-limited). */
    public function trackLookup(): void
    {
        if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
            flash('error', 'Your session expired. Please try again.');
            redirect('shop/track');
        }

        $key   = 'order_lookup';
        $now   = time();
        $state = $_SESSION[$key] ?? ['count' => 0, 'window' => $now];
        if ($state['window'] < $now - 600) {
            $state = ['count' => 0, 'window' => $now];
        }
        if ($state['count'] >= 10) {
            flash('error', 'Too many attempts — please wait a few minutes and try again.');
            redirect('shop/track');
        }
        $state['count']++;
        $_SESSION[$key] = $state;

        $number = trim((string) ($_POST['order_number'] ?? ''));
        $phone  = trim((string) ($_POST['phone'] ?? ''));

        $sale = $number !== '' && $phone !== '' ? Sale::findByNumberAndPhone($number, $phone) : null;

        if (!$sale) {
            http_response_code(404);
            View::render('shop/track', [
                'title'       => 'Track your order — ' . config('app.name'),
                'lookupError' => 'We could not find an order with those details. Check both entries and try again.',
                'orderNumber' => $number,
                'phone'       => $phone,
                'checkoutEnabled' => is_online_checkout_enabled(),
            ], 'shop');
            return;
        }

        $_SESSION['last_order_id'] = (int) $sale['id'];
        View::render('shop/track', [
            'title' => 'Order ' . e($sale['sale_number']) . ' — ' . config('app.name'),
            'sale'  => $sale,
            'items' => SaleService::items((int) $sale['id']),
            'checkoutEnabled' => is_online_checkout_enabled(),
        ], 'shop');
    }

    /** Return a consistent local Kenyan mobile number, or null when invalid. */
    public static function normalizeKenyanPhone(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '254') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 3);
        } elseif (strlen($digits) === 9 && ($digits[0] === '7' || $digits[0] === '1')) {
            $digits = '0' . $digits;
        }
        return preg_match('/^0(?:1|7)\d{8}$/', $digits) ? $digits : null;
    }

    /** Validate the client payment choice before any stock can be reserved. */
    public static function checkoutPaymentIntent(array $data, bool $mpesaEnabled): array
    {
        $payment = (string) ($data['payment_method'] ?? 'cash');
        if (!in_array($payment, ['cash', 'mpesa'], true)) {
            throw new InvalidArgumentException('Please choose a valid payment method.');
        }
        if ($payment === 'mpesa') {
            if (empty($data['pay_now']) || !$mpesaEnabled) {
                throw new InvalidArgumentException('M-PESA pay now is unavailable. Choose pay on handover or refresh the page.');
            }
            return ['mpesa', true];
        }
        return ['cash', false];
    }

    public function order(int $id): void
    {
        if ((int) ($_SESSION['last_order_id'] ?? 0) !== $id) {
            flash('error', 'We could not find that order in this session.');
            redirect('shop');
        }
        $sale = SaleService::find($id);
        if (!$sale) {
            flash('error', 'Order not found.');
            redirect('shop');
        }
        View::render('shop/order', [
            'title' => ($sale['status'] === 'pending' ? 'Complete M-PESA payment' : (in_array($sale['status'], ['cancelled', 'voided'], true) ? 'Order not completed' : 'Order confirmed')) . ' — ' . config('app.name'),
            'sale'  => $sale,
            'items' => SaleService::items($id),
            'checkoutEnabled' => is_online_checkout_enabled(),
        ], 'shop');
    }
}
