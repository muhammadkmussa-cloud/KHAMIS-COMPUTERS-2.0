<?php
declare(strict_types=1);

class PosController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('pos/index', [
            'title' => 'POS Terminal',
            'wide'  => true,
        ]);
    }

    /**
     * AJAX product search for the POS.
     * An exact serial-number match returns that specific unit, so cashiers can
     * scan or type an IMEI/serial directly.
     */
    public function search(): void
    {
        Auth::requireLogin();
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '') {
            json_response([]);
        }

        // 1) Exact serial / IMEI match on an available unit.
        $unit = Database::fetch(
            "SELECT u.id AS unit_id, u.serial_number, u.warranty_expires,
                    p.id, p.name, p.sku, p.barcode, p.sell_price, p.is_serialized,
                    c.name AS category_name
               FROM inventory_units u
               JOIN products p ON p.id = u.product_id
               LEFT JOIN categories c ON c.id = p.category_id
              WHERE u.serial_number = ? AND u.status = 'in_stock' AND p.is_active = 1
              LIMIT 1",
            [$q]
        );
        if ($unit) {
            json_response([[
                'id'          => (int) $unit['id'],
                'name'        => $unit['name'],
                'sku'         => $unit['sku'],
                'barcode'     => $unit['barcode'] ?: '',
                'category'    => (string) ($unit['category_name'] ?? ''),
                'sell_price'  => (float) $unit['sell_price'],
                'serialized'  => true,
                'stock'       => 1,
                'exact_match' => true,
                'matched_by'  => 'serial',
                'units'       => [[
                    'id' => (int) $unit['unit_id'],
                    'serial' => $unit['serial_number'],
                    'warranty_expires' => $unit['warranty_expires'] ?? null,
                ]],
            ]]);
        }

        // 2) Fuzzy match on name / SKU / barcode.
        $like = '%' . $q . '%';
        $rows = Database::fetchAll(
            "SELECT p.id, p.name, p.sku, p.barcode, p.sell_price, p.is_serialized,
                    c.name AS category_name,
                    (SELECT COUNT(*) FROM inventory_units u
                      WHERE u.product_id = p.id AND u.status = 'in_stock') AS units_stock,
                    (SELECT COALESCE(SUM(m.quantity), 0) FROM stock_movements m
                      WHERE m.product_id = p.id) AS qty_stock
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.is_active = 1
                AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)
              ORDER BY p.name
              LIMIT 30",
            [$like, $like, $like]
        );

        $out = [];
        foreach ($rows as $r) {
            $serialized = (int) $r['is_serialized'] === 1;
            $stock      = $serialized ? (int) $r['units_stock'] : (int) $r['qty_stock'];
            $units      = [];
            if ($serialized) {
                $units = Database::fetchAll(
                    "SELECT id, serial_number, warranty_expires FROM inventory_units
                      WHERE product_id = ? AND status = 'in_stock' ORDER BY id LIMIT 200",
                    [$r['id']]
                );
                $units = array_map(fn ($u) => [
                    'id' => (int) $u['id'],
                    'serial' => $u['serial_number'],
                    'warranty_expires' => $u['warranty_expires'] ?? null,
                ], $units);
            }
            $out[] = [
                'id'          => (int) $r['id'],
                'name'        => $r['name'],
                'sku'         => $r['sku'],
                'barcode'     => $r['barcode'] ?: '',
                'category'    => (string) ($r['category_name'] ?? ''),
                'sell_price'  => (float) $r['sell_price'],
                'serialized'  => $serialized,
                'stock'       => $stock,
                'units'       => $units,
                'exact_match' => strcasecmp((string) $r['sku'], $q) === 0 || strcasecmp((string) ($r['barcode'] ?? ''), $q) === 0,
                'matched_by'  => strcasecmp((string) ($r['barcode'] ?? ''), $q) === 0 ? 'barcode' : (strcasecmp((string) $r['sku'], $q) === 0 ? 'sku' : ''),
            ];
        }
        json_response($out);
    }

    /** Complete a sale (JSON API). */
    public function checkout(): void
    {
        Auth::requireLogin();

        if (!Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''))) {
            json_response(['ok' => false, 'error' => 'Invalid or expired session token.'], 419);
        }

        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data)) {
            json_response(['ok' => false, 'error' => 'Invalid request.'], 400);
        }

        $lines = is_array($data['lines'] ?? null) ? $data['lines'] : [];
        try {
            $sale = SaleService::create($lines, [
                'channel'        => 'pos',
                'discount'       => (float) ($data['discount'] ?? 0),
                'discount_pin'   => (string) ($data['discount_pin'] ?? ''),
                'payment_method' => (string) ($data['payment_method'] ?? 'cash'),
                'payment_ref'    => (string) ($data['payment_ref'] ?? ''),
                'cash_received'  => (float) ($data['cash_received'] ?? 0),
                'customer_name'  => (string) ($data['customer_name'] ?? ''),
                'customer_phone' => (string) ($data['customer_phone'] ?? ''),
                'customer_email' => (string) ($data['customer_email'] ?? ''),
                'user_id'        => Auth::id(),
                'device_id'      => trim((string) ($data['device_id'] ?? '')),
                'client_ref'     => trim((string) ($data['client_ref'] ?? '')),
            ]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        json_response(['ok' => true, 'redirect' => url('pos/receipt/' . $sale['id'])]);
    }

    public function receipt(int $id): void
    {
        Auth::requireLogin();
        $sale = SaleService::find($id);
        if (!$sale) {
            flash('error', 'Sale not found.');
            redirect('pos');
        }
        View::render('pos/receipt', [
            'title' => $sale['sale_number'],
            'sale'  => $sale,
            'items' => SaleService::items($id),
        ]);
    }

    /** Full active catalogue for the offline IndexedDB cache. */
    public function catalog(): void
    {
        Auth::requireLogin();
        $rows = Database::fetchAll(
            "SELECT p.id, p.name, p.sku, p.sell_price, p.is_serialized, p.barcode, c.name AS category_name,
                    (SELECT COALESCE(SUM(si.quantity), 0) FROM sale_items si
                      JOIN sales s ON s.id = si.sale_id
                      WHERE si.product_id = p.id AND s.status = 'completed') AS sold_count,
                    (SELECT COUNT(*) FROM inventory_units u
                      WHERE u.product_id = p.id AND u.status = 'in_stock') AS units_stock,
                    (SELECT COALESCE(SUM(m.quantity), 0) FROM stock_movements m
                      WHERE m.product_id = p.id) AS qty_stock
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.is_active = 1
              ORDER BY sold_count DESC, p.name"
        );
        $out = [];
        foreach ($rows as $r) {
            $serialized = (int) $r['is_serialized'] === 1;
            $stock      = $serialized ? (int) $r['units_stock'] : (int) $r['qty_stock'];
            $units      = [];
            if ($serialized) {
                $units = Database::fetchAll(
                    "SELECT id, serial_number, warranty_expires FROM inventory_units
                      WHERE product_id = ? AND status = 'in_stock' ORDER BY id",
                    [$r['id']]
                );
                $units = array_map(fn ($u) => [
                    'id' => (int) $u['id'],
                    'serial' => $u['serial_number'],
                    'warranty_expires' => $u['warranty_expires'] ?? null,
                ], $units);
            }
            $out[] = [
                'id'          => (int) $r['id'],
                'name'        => $r['name'],
                'sku'         => $r['sku'],
                'barcode'     => $r['barcode'] ?: '',
                'category'    => (string) ($r['category_name'] ?? ''),
                'sell_price'  => (float) $r['sell_price'],
                'serialized'  => $serialized,
                'stock'       => $stock,
                'sold_count'  => (int) $r['sold_count'],
                'units'       => $units,
            ];
        }
        json_response($out);
    }

    /** Receive queued offline sales from a POS device (idempotent). */
    public function sync(): void
    {
        if (!Auth::check()) {
            json_response(['ok' => false, 'auth' => true], 401);
        }
        if (!Csrf::validate($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''))) {
            json_response(['ok' => false, 'auth' => true], 419);
        }

        $data = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($data) || !is_array($data['items'] ?? null)) {
            json_response(['ok' => false, 'error' => 'Invalid request.'], 400);
        }

        $results = [];
        foreach ($data['items'] as $it) {
            $clientRef = trim((string) ($it['client_ref'] ?? ''));
            $deviceId  = trim((string) ($it['device_id'] ?? ''));
            if ($clientRef === '' || $deviceId === '') {
                $results[] = ['client_ref' => $clientRef, 'ok' => false, 'error' => 'Missing client reference.'];
                continue;
            }

            $payload = is_array($it['payload'] ?? null) ? $it['payload'] : [];
            $lines   = [];
            foreach ((array) ($payload['lines'] ?? []) as $l) {
                $pid = (int) ($l['product_id'] ?? 0);
                $qty = max(1, (int) ($l['quantity'] ?? 1));
                if ($pid <= 0) {
                    continue;
                }
                $lines[] = [
                    'product_id'     => $pid,
                    'quantity'       => $qty,
                    'unit_ids'       => [],
                    'serial_numbers' => (array) ($l['serial_numbers'] ?? []),
                ];
            }
            if (!$lines) {
                $results[] = ['client_ref' => $clientRef, 'ok' => false, 'error' => 'Empty cart.'];
                continue;
            }

            try {
                $sale = SaleService::create($lines, [
                    'channel'         => 'pos',
                    'discount'        => (float) ($payload['discount'] ?? 0),
                    'discount_pin'    => (string) ($payload['discount_pin'] ?? ''),
                    'payment_method'  => (string) ($payload['payment_method'] ?? 'cash'),
                    'payment_ref'     => (string) ($payload['payment_ref'] ?? ''),
                    'cash_received'   => (float) ($payload['cash_received'] ?? 0),
                    'customer_name'   => (string) ($payload['customer_name'] ?? ''),
                    'customer_phone'  => (string) ($payload['customer_phone'] ?? ''),
                    'customer_email'  => (string) ($payload['customer_email'] ?? ''),
                    'user_id'         => Auth::id(),
                    'offline_created' => true,
                    'device_id'       => $deviceId,
                    'client_ref'      => $clientRef,
                    'created_at'      => (string) ($it['created_at'] ?? ''),
                ]);
                $results[] = [
                    'client_ref'  => $clientRef,
                    'ok'          => true,
                    'duplicate'   => !empty($sale['duplicate']),
                    'sale_number' => $sale['number'],
                    'sale_id'     => $sale['id'],
                ];
            } catch (Throwable $e) {
                $results[] = ['client_ref' => $clientRef, 'ok' => false, 'error' => $e->getMessage()];
            }
        }

        json_response(['ok' => true, 'results' => $results]);
    }
}
