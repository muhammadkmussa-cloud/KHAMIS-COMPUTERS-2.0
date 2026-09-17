<?php
declare(strict_types=1);

/**
 * SaleService — the single checkout engine shared by the POS terminal (piece 3),
 * the online shop (piece 4) and the offline sync queue (piece 5).
 *
 * It re-validates stock server-side inside a transaction and never trusts the
 * client for prices. For serialized products the client must supply the exact
 * in_stock units being sold.
 */
class SaleService
{
    public static function nextNumber(): string
    {
        $seq = (int) Database::fetchValue('SELECT COALESCE(MAX(id), 0) FROM sales') + 1;
        return 'S-' . date('Ymd') . '-' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param array $lines [ ['product_id'=>int, 'quantity'=>int, 'unit_ids'=>int[]], ... ]
     * @param array $opts  channel, discount, payment_method, payment_ref, cash_received,
     *                     customer_name/email/phone, user_id, offline_created,
     *                     device_id, client_ref, status
     * @return array ['id'=>int, 'number'=>string]
     */
    public static function create(array $lines, array $opts = []): array
    {
        $o = self::normalizeOpts($opts);

        if (!$lines) {
            throw new Exception('The cart is empty.');
        }

        // Idempotent sync: the same offline sale must never be inserted twice.
        if ($o['device_id'] !== null && $o['client_ref'] !== null && $o['device_id'] !== '' && $o['client_ref'] !== '') {
            $existing = Database::fetch(
                'SELECT id, sale_number FROM sales WHERE device_id = ? AND client_ref = ? LIMIT 1',
                [$o['device_id'], $o['client_ref']]
            );
            if ($existing) {
                return ['id' => (int) $existing['id'], 'number' => $existing['sale_number'], 'duplicate' => true];
            }
        }

        // Retry on reference-number collisions (two cashiers finishing at the
        // same instant compute the same number); the UNIQUE index catches it.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return self::commit($lines, $o);
            } catch (Throwable $e) {
                if (!Database::isDuplicateKey($e)) {
                    throw $e;
                }
                // A concurrent retry may have committed this same register
                // reference after the preflight check above. Return that sale
                // instead of retrying the unique key and surfacing an error.
                if ($o['device_id'] && $o['client_ref']) {
                    $existing = Database::fetch(
                        'SELECT id, sale_number FROM sales WHERE device_id = ? AND client_ref = ? LIMIT 1',
                        [$o['device_id'], $o['client_ref']]
                    );
                    if ($existing) {
                        return ['id' => (int) $existing['id'], 'number' => $existing['sale_number'], 'duplicate' => true];
                    }
                }
                if ($attempt === 4) {
                    throw $e;
                }
            }
        }
        throw new Exception('Could not allocate a sale number. Please try again.');
    }

    /** Normalize and validate the options array. */
    private static function normalizeOpts(array $opts): array
    {
        $paymentMethod = $opts['payment_method'] ?? 'cash';
        if (!in_array($paymentMethod, ['cash', 'mpesa', 'card', 'bank'], true)) {
            $paymentMethod = 'cash';
        }
        $channel = $opts['channel'] ?? 'pos';
        if (!in_array($channel, ['pos', 'online'], true)) {
            $channel = 'pos';
        }
        $status = $opts['status'] ?? 'completed';
        if (!in_array($status, ['completed', 'cancelled', 'pending'], true)) {
            $status = 'completed';
        }
        $deviceId  = $opts['device_id'] ?? null;
        $clientRef = $opts['client_ref'] ?? null;
        $saleSource = strtolower(trim((string)($opts['sale_source'] ?? 'walk-in')));
        $allowedSources = ['walk-in','whatsapp','phone','other','online'];
        if (!in_array($saleSource, $allowedSources, true)) {
            $saleSource = 'walk-in';
        }
        return [
            'channel'             => $channel,
            'discount'            => max(0.0, round((float) ($opts['discount'] ?? 0), 2)),
            'payment_method'      => $paymentMethod,
            'cash_received'       => max(0.0, round((float) ($opts['cash_received'] ?? 0), 2)),
            'customer_name'       => trim((string) ($opts['customer_name'] ?? '')),
            'customer_email'      => trim((string) ($opts['customer_email'] ?? '')),
            'customer_phone'      => trim((string) ($opts['customer_phone'] ?? '')),
            'payment_ref'         => trim((string) ($opts['payment_ref'] ?? '')),
            'fulfillment'         => ($opts['fulfillment'] ?? 'pickup') === 'delivery' ? 'delivery' : 'pickup',
            'delivery_address'    => trim((string) ($opts['delivery_address'] ?? '')),
            'delivery_fee'        => max(0.0, round((float) ($opts['delivery_fee'] ?? 0), 2)),
            'delivery_zone'       => trim((string) ($opts['delivery_zone'] ?? '')),
            'user_id'             => $opts['user_id'] ?? null,
            'offline_created'     => !empty($opts['offline_created']) ? 1 : 0,
            'device_id'           => $deviceId !== null && $deviceId !== '' ? $deviceId : null,
            'client_ref'          => $clientRef !== null && $clientRef !== '' ? $clientRef : null,
            'status'              => $status,
            'auto_assign_serials' => !empty($opts['auto_assign_serials']),
            'created_at'          => $opts['created_at'] ?? null,
            'discount_pin'        => (string) ($opts['discount_pin'] ?? ''),
            'sale_source'         => $saleSource,
            'whatsapp_enquiry_id' => isset($opts['whatsapp_enquiry_id']) ? (int)$opts['whatsapp_enquiry_id'] : null,
        ];
    }

    private static function commit(array $lines, array $o): array
    {
        $channel       = $o['channel'];
        $discount      = $o['discount'];
        $paymentMethod = $o['payment_method'];
        $cashReceived  = $o['cash_received'];
        $customerName  = $o['customer_name'];
        $customerEmail = $o['customer_email'];
        $customerPhone = $o['customer_phone'];
        $paymentRef    = $o['payment_ref'];
        $fulfillment   = $o['fulfillment'];
        $deliveryAddr  = $o['delivery_address'];
        $deliveryFee   = $o['delivery_fee'];
        $deliveryZone  = $o['delivery_zone'];
        $userId        = $o['user_id'];
        $offline       = $o['offline_created'];
        $deviceId      = $o['device_id'];
        $clientRef     = $o['client_ref'];
        $status        = $o['status'];
        $discountPin   = $o['discount_pin'];
        $saleSource    = $o['sale_source'];
        $waEnquiryId   = $o['whatsapp_enquiry_id'];

        $number = self::nextNumber();
        $now    = $o['created_at'] ?? null;
        if (!is_string($now) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $now)) {
            $now = Database::now();
        }
        $pdo    = Database::pdo();
        $pdo->beginTransaction();

        try {
            $subtotal = 0.0;
            $items    = [];
            $soldProductIds = [];

            foreach ($lines as $line) {
                $pid     = (int) ($line['product_id'] ?? 0);
                $qty     = max(1, (int) ($line['quantity'] ?? 1));
                $unitIds = array_values(array_unique(array_filter(array_map('intval', (array) ($line['unit_ids'] ?? [])))));

                $product = Product::find($pid);
                if (!$product) {
                    throw new Exception('A product in the cart no longer exists. Remove it and try again.');
                }
                if ((int) $product['is_active'] !== 1) {
                    throw new Exception('"' . $product['name'] . '" is not available for sale.');
                }
                $serialized = (int) $product['is_serialized'] === 1;
                $price      = (float) $product['sell_price'];
                $cost       = (float) $product['cost_price'];

                if ($serialized) {
                    // Offline syncs send serial *numbers* (not unit ids) — ids can go stale.
                    if (!$unitIds && !empty($line['serial_numbers'])) {
                        $serials = array_values(array_filter(array_map('trim', (array) $line['serial_numbers'])));
                        if (count($serials) === $qty) {
                            $ph   = rtrim(str_repeat('?,', count($serials)), ',');
                            $rows = Database::fetchAll(
                                "SELECT id FROM inventory_units WHERE product_id = ? AND status = 'in_stock' AND serial_number IN ({$ph})",
                                array_merge([$pid], $serials)
                            );
                            if (count($rows) === $qty) {
                                $unitIds = array_map('intval', array_column($rows, 'id'));
                            } else {
                                throw new Exception('A serial number for "' . $product['name'] . '" is no longer available (already sold or missing). Please remove it from the sale.');
                            }
                        }
                    }
                    // Online/guest sales don't pre-pick units — assign available ones.
                    if (!$unitIds && $o['auto_assign_serials']) {
                        $avail = Database::fetchAll(
                            "SELECT id FROM inventory_units WHERE product_id = ? AND status = 'in_stock' ORDER BY id LIMIT ?",
                            [$pid, $qty]
                        );
                        if (count($avail) < $qty) {
                            throw new Exception('Only ' . count($avail) . ' left in stock for "' . $product['name'] . '".');
                        }
                        $unitIds = array_map('intval', array_column($avail, 'id'));
                    }
                    if (count($unitIds) !== $qty) {
                        throw new Exception('Select exactly ' . $qty . ' serial number(s) for "' . $product['name'] . '".');
                    }
                    foreach ($unitIds as $uid) {
                        $unit = Database::fetch(
                            "SELECT * FROM inventory_units WHERE id = ? AND product_id = ? AND status = 'in_stock'",
                            [$uid, $pid]
                        );
                        if (!$unit) {
                            throw new Exception('A selected serial number for "' . $product['name'] . '" was just sold by another register. Please reselect.');
                        }
                        $updated = Database::update('inventory_units',
                            ['status' => 'sold'],
                            "id = :id AND status = 'in_stock'",
                            ['id' => $uid]);
                        if ($updated === 0) {
                            throw new Exception('A selected serial number for "' . $product['name'] . '" was just sold by another register. Please reselect.');
                        }
                        Database::insert('stock_movements', [
                            'product_id' => $pid, 'unit_id' => $uid, 'type' => 'sold', 'quantity' => -1,
                            'reference' => $number, 'user_id' => $userId, 'created_at' => $now,
                        ]);
                        $items[] = ['product_id' => $pid, 'unit_id' => $uid, 'quantity' => 1, 'unit_cost' => $cost, 'unit_price' => $price, 'line_total' => $price];
                        $soldProductIds[] = $pid;
                    }
                } else {
                    $stock = (int) Database::fetchValue(
                        'SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE product_id = ?',
                        [$pid]
                    );
                    if ($stock < $qty) {
                        throw new Exception('Only ' . $stock . ' in stock for "' . $product['name'] . '".');
                    }
                    Database::insert('stock_movements', [
                        'product_id' => $pid, 'unit_id' => null, 'type' => 'sold', 'quantity' => -$qty,
                        'reference' => $number, 'user_id' => $userId, 'created_at' => $now,
                    ]);
                    $items[] = ['product_id' => $pid, 'unit_id' => null, 'quantity' => $qty, 'unit_cost' => $cost, 'unit_price' => $price, 'line_total' => round($price * $qty, 2)];
                    $soldProductIds[] = $pid;
                }

                $subtotal += round($price * $qty, 2);
            }

            $subtotal = round($subtotal, 2);
            if ($discount > $subtotal) {
                $discount = $subtotal;
            }

            // Discount authorisation gate: once a manager PIN is configured,
            // discounts at or above the threshold need it (checked server-side,
            // so neither the POS UI nor the offline sync can bypass it).
            $pinHash = Setting::get('discount_pin_hash', '');
            if ($pinHash !== '' && $discount > 0) {
                $threshold = max(0.0, (float) Setting::get('discount_pin_threshold', '1000'));
                if ($discount >= $threshold) {
                    $pin = trim($discountPin);
                    if ($pin === '') {
                        throw new Exception('Manager PIN required for discounts of ' . money($threshold) . ' or more.');
                    }
                    if (!password_verify($pin, $pinHash)) {
                        throw new Exception('Incorrect manager PIN.');
                    }
                }
            }

            $tax   = round(($subtotal - $discount) * vat_rate() / 100, 2);
            // Delivery is a flat charge on top of the VAT-inclusive goods total.
            $total = $paymentMethod === 'mpesa'
                ? round($subtotal - $discount + $tax + $deliveryFee, 0)
                : round($subtotal - $discount + $tax + $deliveryFee, 2);

            // POS payment evidence is validated at the transaction boundary,
            // including queued offline sales. Online shop M-PESA orders use a
            // separate pending-payment flow and are intentionally unaffected.
            if ($channel === 'pos') {
                if ($paymentMethod === 'cash' && $cashReceived < $total) {
                    throw new Exception('Cash received must cover the sale total.');
                }
                if ($paymentMethod !== 'cash' && strlen($paymentRef) < 3) {
                    throw new Exception('Enter a valid payment reference.');
                }
            }

            $insertData = [
                'sale_number'     => $number,
                'channel'         => $channel,
                'customer_name'   => $customerName !== '' ? $customerName : null,
                'customer_email'  => $customerEmail !== '' ? $customerEmail : null,
                'customer_phone'  => $customerPhone !== '' ? $customerPhone : null,
                'subtotal'        => $subtotal,
                'discount'        => $discount,
                'tax_amount'      => $tax,
                'total'           => $total,
                'status'          => $status,
                'payment_method'  => $paymentMethod,
                'payment_ref'     => $paymentRef !== '' ? $paymentRef : null,
                'fulfillment'     => $fulfillment,
                'delivery_address'=> $deliveryAddr !== '' ? $deliveryAddr : null,
                'delivery_fee'    => $deliveryFee,
                'delivery_zone'   => $deliveryZone !== '' ? $deliveryZone : null,
                'offline_created' => $offline,
                'device_id'       => $deviceId,
                'client_ref'      => $clientRef,
                'user_id'         => $userId,
                'created_at'      => $now,
            ];
            // Add sale_source if column exists (forward-compatible)
            if (Schema::columnExists('sales', 'sale_source')) {
                $insertData['sale_source'] = $saleSource;
            }
            if (Schema::columnExists('sales', 'whatsapp_enquiry_id') && $waEnquiryId) {
                $insertData['whatsapp_enquiry_id'] = $waEnquiryId;
            }
            $saleId = Database::insert('sales', $insertData);

            foreach ($items as $item) {
                $item['sale_id'] = $saleId;
                Database::insert('sale_items', $item);
            }

            $pdo->commit();

            // Low-stock check (after commit; best-effort, never breaks the sale).
            foreach (array_unique($soldProductIds) as $soldPid) {
                LowStock::maybeAlert((int) $soldPid);
            }

            return ['id' => $saleId, 'number' => $number];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT s.*, u.name AS user_name FROM sales s LEFT JOIN users u ON u.id = s.user_id WHERE s.id = ?",
            [$id]
        );
    }

    public static function items(int $saleId): array
    {
        return Database::fetchAll(
            "SELECT si.*, p.name AS product_name, p.sku, u.serial_number, u.warranty_expires
               FROM sale_items si
               JOIN products p ON p.id = si.product_id
               LEFT JOIN inventory_units u ON u.id = si.unit_id
              WHERE si.sale_id = ?
              ORDER BY si.id",
            [$saleId]
        );
    }

    /**
     * Void (cancel) a completed sale and restore its stock.
     * Serialized units return to in_stock; bulk items get an offsetting
     * 'voided' movement. Blocked when the sale has returns — a return is the
     * correct tool once any item has been returned/refunded.
     */
    public static function void(int $id, ?int $userId, array $allowedStatuses = ['completed', 'pending']): void
    {
        $sale = self::find($id);
        if (!$sale) {
            throw new Exception('Sale not found.');
        }
        $allowedStatuses = array_values(array_intersect(['completed', 'pending'], array_unique($allowedStatuses)));
        if ($allowedStatuses === [] || !in_array($sale['status'], $allowedStatuses, true)) {
            throw new Exception('Only completed or pending sales can be voided.');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            // Claim the sale before restoring anything. The conditional update
            // makes duplicate requests and callback/manual-payment races safe.
            $statusParams = [];
            $statusSlots = [];
            foreach ($allowedStatuses as $index => $status) {
                $key = 'status_' . $index;
                $statusSlots[] = ':' . $key;
                $statusParams[$key] = $status;
            }
            $claimed = Database::update(
                'sales',
                ['status' => 'cancelled'],
                'id = :id AND status IN (' . implode(', ', $statusSlots) . ')',
                array_merge(['id' => $id], $statusParams)
            );
            if ($claimed !== 1) {
                throw new Exception('This sale is no longer eligible to be voided. Refresh and check its status.');
            }

            $hasReturn = (int) Database::fetchValue(
                "SELECT COUNT(*) FROM returns WHERE sale_id = ? AND status != 'rejected'",
                [$id]
            );
            if ($hasReturn > 0) {
                throw new Exception('This sale already has returns — process a return instead of voiding.');
            }

            foreach (self::items($id) as $it) {
                if ($it['unit_id']) {
                    $updated = Database::update('inventory_units',
                        ['status' => 'in_stock'],
                        "id = :id AND status = 'sold'",
                        ['id' => (int) $it['unit_id']]);
                    if ($updated === 0) {
                        throw new Exception('A serial unit on this sale is no longer marked as sold — it may already have been restocked.');
                    }
                }
                Database::insert('stock_movements', [
                    'product_id' => (int) $it['product_id'],
                    'unit_id'    => $it['unit_id'] ? (int) $it['unit_id'] : null,
                    'type'       => 'voided',
                    'quantity'   => (int) $it['quantity'],
                    'reference'  => $sale['sale_number'],
                    'user_id'    => $userId,
                    'created_at' => Database::now(),
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
