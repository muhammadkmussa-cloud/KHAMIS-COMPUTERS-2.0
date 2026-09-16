<?php
declare(strict_types=1);

class ProductController
{
    public function index(): void
    {
        Auth::requireLogin();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'category' => (string) ($_GET['category'] ?? ''),
            'tracking' => (string) ($_GET['tracking'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'stock' => (string) ($_GET['stock'] ?? ''),
        ];
        $products = Product::search($filters);

        View::render('products/index', [
            'title'    => 'Inventory',
            'products' => $products,
            'filters'  => $filters,
            'summary'  => Product::inventorySummary(),
            'categories' => Category::all(),
        ]);
    }

    public function create(): void
    {
        Auth::requireAdmin();
        View::render('products/form', [
            'title'      => 'Add product',
            'product'    => null,
            'categories' => Category::all(),
            'brands'     => Brand::all(),
            'hasHistory' => false,
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $errors = array_merge(Product::validate($_POST), Product::uniquenessErrors($_POST));
        $sku = trim((string) ($_POST['sku'] ?? ''));

        if ($errors) {
            flash('error', implode(' ', $errors));
            set_old($_POST);
            redirect('products/new');
        }

        $now = Database::now();
        try {
            $id = Database::insert('products', [
            'name'          => trim($_POST['name']),
            'sku'           => $sku,
            'category_id'   => (int) ($_POST['category_id'] ?? 0) ?: null,
            'brand_id'      => (int) ($_POST['brand_id'] ?? 0) ?: null,
            'cost_price'    => round((float) $_POST['cost_price'], 2),
            'sell_price'    => round((float) $_POST['sell_price'], 2),
            'barcode'       => trim((string) ($_POST['barcode'] ?? '')) ?: null,
            'description'   => trim((string) ($_POST['description'] ?? '')),
            'is_active'     => ($_POST['is_active'] ?? '') === '1' ? 1 : 0,
            'is_serialized' => ($_POST['is_serialized'] ?? '') === '1' ? 1 : 0,
            'warranty_months' => ($_POST['warranty_months'] ?? '') !== '' ? max(0, (int) $_POST['warranty_months']) : null,
            'reorder_level' => max(0, (int) ($_POST['reorder_level'] ?? 0)),
            'created_at'    => $now,
            'updated_at'    => $now,
            ]);
        } catch (Throwable $e) {
            if (!Database::isDuplicateKey($e)) throw $e;
            set_old($_POST);
            flash('error', 'That SKU or barcode was just used by another product. Choose a unique value and try again.');
            redirect('products/new');
        }

        flash('success', 'Product created.');
        redirect(!empty($_POST['print_labels']) ? 'products/' . $id . '/labels?autoprint=1' : 'products/' . $id);
    }

    /** JSON: generate a unique internal product barcode (Code 128-safe). */
    public function generateBarcode(): void
    {
        Auth::requireAdmin();
        for ($i = 0; $i < 12; $i++) {
            $code = 'KC' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
            if ((int) Database::fetchValue('SELECT COUNT(*) FROM products WHERE barcode = ?', [$code]) === 0) {
                json_response(['ok' => true, 'barcode' => $code]);
            }
        }
        json_response(['ok' => false, 'error' => 'Could not generate a unique barcode. Try again.'], 500);
    }

    /** JSON uniqueness check used by the product identity form. */
    public function checkUnique(): void
    {
        Auth::requireAdmin();
        $field = (string) ($_GET['field'] ?? '');
        if (!in_array($field, ['sku', 'barcode'], true)) {
            json_response(['ok' => false, 'error' => 'Unsupported field.'], 422);
        }
        $value = trim((string) ($_GET['value'] ?? ''));
        $exclude = max(0, (int) ($_GET['exclude'] ?? 0));
        $errors = Product::uniquenessErrors([$field => $value], $exclude > 0 ? $exclude : null);
        json_response(['ok' => true, 'available' => $errors === [], 'message' => $errors[0] ?? 'Available']);
    }

    /** Export the catalogue (with live stock) as CSV. */
    public function export(): void
    {
        Auth::requireLogin();
        Auth::requireAdmin();

        $rows = Product::all();
        $csv  = [['SKU', 'Barcode', 'Name', 'Category', 'Brand', 'Cost (KSh)', 'Sell (KSh)', 'In stock', 'Tracked by', 'Warranty (months)', 'Active']];
        foreach ($rows as $p) {
            $csv[] = [
                $p['sku'],
                (string) ($p['barcode'] ?? ''),
                $p['name'],
                $p['category_name'] ?? '',
                $p['brand_name'] ?? '',
                number_format((float) $p['cost_price'], 2, '.', ''),
                number_format((float) $p['sell_price'], 2, '.', ''),
                (int) Product::stockOf($p),
                (int) $p['is_serialized'] === 1 ? 'serial/IMEI' : 'quantity',
                $p['warranty_months'] ?? '',
                (int) $p['is_active'] === 1 ? 'yes' : 'no',
            ];
        }

        csv_response($csv, 'products-' . date('Ymd-His') . '.csv');
    }

    public function show(int $id): void
    {
        Auth::requireLogin();
        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Product not found.');
            redirect('products');
        }

        $unitQ = trim((string) ($_GET['unit_q'] ?? ''));
        $unitStatus = (string) ($_GET['unit_status'] ?? '');
        View::render('products/show', [
            'title'     => $product['name'],
            'product'   => $product,
            'units'     => Product::units($id, $unitQ, $unitStatus),
            'unitSummary' => Product::unitSummary($id),
            'unitQ' => $unitQ,
            'unitStatus' => $unitStatus,
            'movements' => Product::movements($id),
            'images'    => Product::images($id),
            'stock'     => Product::stock($id),
        ]);
    }

    public function edit(int $id): void
    {
        Auth::requireAdmin();
        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Product not found.');
            redirect('products');
        }

        View::render('products/form', [
            'title'      => 'Edit product',
            'product'    => $product,
            'categories' => Category::all(),
            'brands'     => Brand::all(),
            'hasHistory' => self::hasStockHistory($id),
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Product not found.');
            redirect('products');
        }

        $errors = array_merge(Product::validate($_POST), Product::uniquenessErrors($_POST, $id));
        $sku = trim((string) ($_POST['sku'] ?? ''));

        // Changing the tracking mode on a product with history would desync
        // stock (units vs movements), so it is only allowed on untouched products.
        $newSerialized = ($_POST['is_serialized'] ?? '') === '1' ? 1 : 0;
        if ((int) $product['is_serialized'] !== $newSerialized) {
            if (self::hasStockHistory($id)) {
                $errors[] = 'You cannot change serial/IMEI tracking on a product that already has stock history. Create a new product instead.';
            }
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('products/' . $id . '/edit');
        }

        try {
            Database::update('products', [
            'name'          => trim($_POST['name']),
            'sku'           => $sku,
            'category_id'   => (int) ($_POST['category_id'] ?? 0) ?: null,
            'brand_id'      => (int) ($_POST['brand_id'] ?? 0) ?: null,
            'cost_price'    => round((float) $_POST['cost_price'], 2),
            'sell_price'    => round((float) $_POST['sell_price'], 2),
            'barcode'       => trim((string) ($_POST['barcode'] ?? '')) ?: null,
            'description'   => trim((string) ($_POST['description'] ?? '')),
            'is_active'     => ($_POST['is_active'] ?? '') === '1' ? 1 : 0,
            'is_serialized' => ($_POST['is_serialized'] ?? '') === '1' ? 1 : 0,
            'warranty_months' => ($_POST['warranty_months'] ?? '') !== '' ? max(0, (int) $_POST['warranty_months']) : null,
            'reorder_level' => max(0, (int) ($_POST['reorder_level'] ?? 0)),
            'updated_at'    => Database::now(),
            ], 'id = :id', ['id' => $id]);
        } catch (Throwable $e) {
            if (!Database::isDuplicateKey($e)) throw $e;
            flash('error', 'That SKU or barcode was just used by another product. Choose a unique value and try again.');
            redirect('products/' . $id . '/edit');
        }

        flash('success', 'Product updated.');
        redirect(!empty($_POST['print_labels']) ? 'products/' . $id . '/labels?autoprint=1' : 'products/' . $id);
    }

    private static function hasStockHistory(int $id): bool
    {
        return (int) Database::fetchValue(
            'SELECT (SELECT COUNT(*) FROM inventory_units WHERE product_id = ?)
                  + (SELECT COUNT(*) FROM stock_movements WHERE product_id = ?)',
            [$id, $id]
        ) > 0;
    }

    public function delete(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Product not found.');
            redirect('products');
        }
        if (Product::hasSales($id)) {
            flash('error', 'This product has sales history — deactivate it instead of deleting.');
            redirect('products/' . $id);
        }
        $grnHistory = (int) Database::fetchValue('SELECT COUNT(*) FROM goods_received_items WHERE product_id = ?', [$id]);
        if ($grnHistory > 0) {
            flash('error', 'This product appears on goods-received notes — deactivate it instead of deleting.');
            redirect('products/' . $id);
        }
        // MySQL ignores the inline REFERENCES in schema.sql, so child rows would
        // be orphaned there — refuse to delete any product with stock history
        // (and clean up the rest explicitly below).
        $stockHistory = (int) Database::fetchValue(
            'SELECT (SELECT COUNT(*) FROM inventory_units WHERE product_id = ?)
                  + (SELECT COUNT(*) FROM stock_movements WHERE product_id = ?)',
            [$id, $id]
        );
        if ($stockHistory > 0) {
            flash('error', 'This product has stock history — deactivate it instead of deleting.');
            redirect('products/' . $id);
        }

        // Remove uploaded image files before deleting the product.
        $dir  = BASE_PATH . '/storage/uploads/products';
        $real = realpath($dir);
        foreach (Database::fetchAll('SELECT filename FROM product_images WHERE product_id = ?', [$id]) as $im) {
            if ($real !== false) {
                $p = realpath($dir . '/' . $im['filename']);
                if ($p !== false && strpos($p, $real . DIRECTORY_SEPARATOR) === 0 && is_file($p)) {
                    @unlink($p);
                }
            }
        }
        Database::delete('product_images', 'product_id = ?', [$id]);
        Database::delete('low_stock_alerts', 'product_id = ?', [$id]);

        Database::delete('products', 'id = ?', [$id]);
        flash('success', 'Product deleted.');
        redirect('products');
    }

    /** Add serial numbers to a serialized product. */
    public function addUnits(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Product not found.');
            redirect('products');
        }
        if ((int) $product['is_serialized'] !== 1) {
            flash('error', 'Serial numbers only apply to serialized products.');
            redirect('products/' . $id);
        }

        $print  = !empty($_POST['print_labels']);
        $serials = [];
        if (($_POST['action'] ?? 'add') === 'generate') {
            $count   = max(1, min(200, (int) ($_POST['auto_count'] ?? 1)));
            $serials = self::generateSerials($count);
        } else {
            $lines   = preg_split('/\r\n|\r|\n/', trim((string) ($_POST['serials'] ?? '')));
            $serials = array_values(array_unique(array_filter(array_map('trim', $lines))));
        }
        if (!$serials) {
            flash('error', 'Enter at least one serial number (one per line) or generate some.');
            redirect('products/' . $id);
        }

        $now = Database::now();
        $wm  = max(0, (int) ($product['warranty_months'] ?? 0));
        $expires = $wm > 0 ? date('Y-m-d', strtotime($now . ' +' . $wm . ' months')) : null;
        $inserted = 0;
        $skipped = 0;
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($serials as $sn) {
                $sn = mb_substr($sn, 0, 120);
                if (Database::fetchValue('SELECT COUNT(*) FROM inventory_units WHERE serial_number = ?', [$sn]) > 0) {
                    $skipped++;
                    continue;
                }
                try {
                    Database::insert('inventory_units', [
                        'product_id' => $id, 'serial_number' => $sn,
                        'status' => 'in_stock', 'received_at' => $now,
                        'warranty_expires' => $expires,
                    ]);
                    $inserted++;
                } catch (Throwable $e) {
                    if (!Database::isDuplicateKey($e)) throw $e;
                    $skipped++;
                }
            }
            if ($inserted > 0) {
                Database::insert('stock_movements', [
                    'product_id' => $id, 'type' => 'received', 'quantity' => $inserted,
                    'reference' => 'MANUAL', 'user_id' => Auth::id(), 'created_at' => $now,
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        if ($inserted === 0) {
            flash('error', 'Those serial numbers already exist — nothing was added.');
            redirect('products/' . $id);
        }

        flash('success', $inserted . ' serial number(s) added' . ($skipped > 0 ? '; ' . $skipped . ' duplicate(s) skipped.' : '.'));
        redirect($print ? 'products/' . $id . '/labels?kind=serials&autoprint=1' : 'products/' . $id);
    }

    /** Unique auto-generated serial numbers (for units without a real IMEI). */
    private static function generateSerials(int $count): array
    {
        $out   = [];
        $tries = 0;
        while (count($out) < $count && $tries < ($count * 10 + 20)) {
            $tries++;
            $sn = 'KC' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
            if (in_array($sn, $out, true)) {
                continue;
            }
            if ((int) Database::fetchValue('SELECT COUNT(*) FROM inventory_units WHERE serial_number = ?', [$sn]) > 0) {
                continue;
            }
            $out[] = $sn;
        }
        return $out;
    }

    /** Change a unit's status (sold / damaged / returned / missing / in_stock). */
    public function setUnitStatus(int $id, int $unitId): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $unit = Database::fetch('SELECT * FROM inventory_units WHERE id = ? AND product_id = ?', [$unitId, $id]);
        if (!$unit) {
            flash('error', 'Unit not found.');
            redirect('products/' . $id);
        }

        $status = (string) ($_POST['status'] ?? 'in_stock');
        $allowed = ['in_stock', 'reserved', 'sold', 'damaged', 'returned', 'missing'];
        if (!in_array($status, $allowed, true)) {
            flash('error', 'Choose a valid unit status.');
            redirect('products/' . $id . '#stock');
        }

        // A unit sold on a live order is managed by the sale/return/void flows.
        // Hand-editing its status here would let it be sold twice (or remove
        // the sales record's meaning) — block it.
        $liveSale = Database::fetch(
            "SELECT s.sale_number FROM sale_items si JOIN sales s ON s.id = si.sale_id
              WHERE si.unit_id = ? AND s.status IN ('completed','pending') LIMIT 1",
            [$unitId]
        );
        if ($liveSale && $unit['status'] === 'sold' && $status !== 'sold') {
            flash('error', 'This unit is sold on order ' . $liveSale['sale_number'] . ' — process a return or void instead of changing its status.');
            redirect('products/' . $id);
        }
        // A unit can only become "sold" by going through a real sale.
        if ($status === 'sold' && $unit['status'] !== 'sold') {
            flash('error', 'Marking a unit as sold by hand is not allowed — record the sale at the POS or online shop.');
            redirect('products/' . $id);
        }

        $warranty = trim((string) ($_POST['warranty_expires'] ?? ''));
        if ($warranty !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $warranty)) {
            $warranty = '';
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::update('inventory_units',
                ['status' => $status, 'note' => trim((string) ($_POST['note'] ?? '')) ?: null,
                 'warranty_expires' => $warranty !== '' ? $warranty : null],
                'id = :id AND product_id = :pid',
                ['id' => $unitId, 'pid' => $id]);
            self::recordUnitStatusMovement($id, $unitId, (string) $unit['status'], $status, 'Unit status update');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        flash('success', 'Unit updated.');
        redirect('products/' . $id);
    }

    /** Apply one explicit status to selected serialized units. */
    public function bulkUnits(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        $product = Product::find($id);
        if (!$product || (int) $product['is_serialized'] !== 1) {
            flash('error', 'Bulk unit updates apply to serialized products.');
            redirect('products/' . $id);
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['unit_ids'] ?? [])))));
        $ids = array_slice($ids, 0, 200);
        $status = (string) ($_POST['bulk_status'] ?? '');
        $allowed = ['in_stock', 'reserved', 'returned', 'damaged', 'missing'];
        if (!$ids || !in_array($status, $allowed, true)) {
            flash('error', 'Select at least one unit and a permitted destination status.');
            redirect('products/' . $id . '#stock');
        }

        $slots = implode(',', array_fill(0, count($ids), '?'));
        $units = Database::fetchAll(
            "SELECT * FROM inventory_units WHERE product_id = ? AND id IN ({$slots})",
            array_merge([$id], $ids)
        );
        if (count($units) !== count($ids)) {
            flash('error', 'One or more selected units no longer exist. Refresh and try again.');
            redirect('products/' . $id . '#stock');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($units as $unit) {
                $liveSale = Database::fetchValue(
                    "SELECT COUNT(*) FROM sale_items si JOIN sales s ON s.id = si.sale_id
                      WHERE si.unit_id = ? AND s.status IN ('completed','pending')",
                    [(int) $unit['id']]
                );
                if ((int) $liveSale > 0 && $unit['status'] === 'sold') {
                    throw new RuntimeException('A selected sold unit belongs to a live order. Process a return or void instead.');
                }
                Database::update('inventory_units', [
                    'status' => $status,
                    'note' => trim((string) ($_POST['bulk_note'] ?? '')) ?: ($unit['note'] ?? null),
                ], 'id = :id AND product_id = :pid', ['id' => (int) $unit['id'], 'pid' => $id]);
                self::recordUnitStatusMovement($id, (int) $unit['id'], (string) $unit['status'], $status, 'Bulk unit update');
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $e->getMessage());
            redirect('products/' . $id . '#stock');
        }
        Activity::log('inventory.units_bulk_updated', json_encode(['product_id' => $id, 'unit_ids' => $ids, 'status' => $status], JSON_UNESCAPED_SLASHES));
        flash('success', count($ids) . ' selected unit(s) updated to ' . str_replace('_', ' ', $status) . '.');
        redirect('products/' . $id . '?unit_status=' . rawurlencode($status) . '#stock');
    }

    private static function recordUnitStatusMovement(int $productId, int $unitId, string $from, string $to, string $reference): void
    {
        if ($from === $to || ($from === 'in_stock') === ($to === 'in_stock')) return;
        Database::insert('stock_movements', [
            'product_id' => $productId,
            'unit_id' => $unitId,
            'type' => $to === 'in_stock' ? 'adjustment_in' : 'adjustment_out',
            'quantity' => $to === 'in_stock' ? 1 : -1,
            'reference' => $reference,
            'user_id' => Auth::id(),
            'created_at' => Database::now(),
        ]);
    }

    public function deleteUnit(int $id, int $unitId): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        // Never delete a unit that appears in sales or returns history —
        // it would orphan receipts and the return trail.
        $refs = (int) Database::fetchValue(
            "SELECT (SELECT COUNT(*) FROM sale_items WHERE unit_id = ?)
                  + (SELECT COUNT(*) FROM return_items WHERE unit_id = ?)",
            [$unitId, $unitId]
        );
        if ($refs > 0) {
            flash('error', 'This unit has sales or returns history — change its status instead of deleting it.');
            redirect('products/' . $id);
        }

        Database::delete('inventory_units', 'id = ? AND product_id = ?', [$unitId, $id]);
        flash('success', 'Unit removed.');
        redirect('products/' . $id);
    }

    /** Manual stock adjustment for non-serialized products. */
    public function adjust(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $product = Product::find($id);
        if (!$product || (int) $product['is_serialized'] === 1) {
            flash('error', 'Adjustments apply to quantity-based products.');
            redirect('products/' . $id);
        }

        $delta = (int) ($_POST['delta'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        if ($delta === 0) {
            flash('error', 'Enter a non-zero adjustment.');
            redirect('products/' . $id);
        }
        $newStock = Product::stockAfterAdjustment($id, $delta);
        if ($newStock < 0) {
            flash('error', 'Stock cannot go below zero.');
            redirect('products/' . $id);
        }
        if (mb_strlen($reason) < 3) {
            flash('error', 'Enter a clear reason for the stock adjustment.');
            redirect('products/' . $id . '#stock');
        }

        Database::insert('stock_movements', [
            'product_id' => $id,
            'type'       => $delta > 0 ? 'adjustment_in' : 'adjustment_out',
            'quantity'   => $delta,
            'reference'  => mb_substr($reason, 0, 255),
            'user_id'    => Auth::id(),
            'created_at' => Database::now(),
        ]);

        if ($delta < 0) {
            LowStock::maybeAlert($id);
        }

        flash('success', 'Stock adjusted.');
        redirect('products/' . $id);
    }

    /** Stream a product image. Public — the online shop needs it without auth. */
    public function image(string $filename): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+\.(jpg|jpeg|png|webp)$/', $filename)) {
            http_response_code(404);
            exit;
        }
        $dir  = BASE_PATH . '/storage/uploads/products';
        $real = realpath($dir);
        $path = realpath($dir . '/' . $filename);
        if ($real === false || $path === false || strpos($path, $real . DIRECTORY_SEPARATOR) !== 0 || !is_file($path)) {
            http_response_code(404);
            exit;
        }
        $mime = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        ][strtolower((string) pathinfo($filename, PATHINFO_EXTENSION))] ?? 'application/octet-stream';

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }

    /** Upload gallery images for a product. */
    public function uploadImages(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Product not found.');
            redirect('products');
        }

        $dir = BASE_PATH . '/storage/uploads/products';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        $files   = $_FILES['images'] ?? null;
        $count   = 0;
        $errors  = [];

        if ($files && is_array($files['name'] ?? null)) {
            foreach ($files['name'] as $i => $orig) {
                if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    continue;
                }
                if ((int) $files['size'][$i] > 3 * 1024 * 1024) {
                    $errors[] = basename((string) $orig) . ' is over 3 MB.';
                    continue;
                }
                $ext = strtolower((string) pathinfo((string) $orig, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed, true)) {
                    $errors[] = basename((string) $orig) . ' is not a JPG/PNG/WebP image.';
                    continue;
                }
                $imageInfo = @getimagesize((string) $files['tmp_name'][$i]);
                $mimeExt = is_array($imageInfo) ? [
                    'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp',
                ][$imageInfo['mime'] ?? ''] ?? null : null;
                if ($mimeExt === null) {
                    $errors[] = basename((string) $orig) . ' is not a valid image file.';
                    continue;
                }
                $ext = $mimeExt;
                $name = $id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (!move_uploaded_file((string) $files['tmp_name'][$i], $dir . '/' . $name)) {
                    $errors[] = 'Could not save ' . basename((string) $orig) . '.';
                    continue;
                }
                $max = (int) Database::fetchValue('SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?', [$id]);
                Database::insert('product_images', [
                    'product_id' => $id, 'filename' => $name, 'alt_text' => $product['name'],
                    'sort_order' => $max + 1, 'created_at' => Database::now(),
                ]);
                if (empty($product['image'])) {
                    Database::update('products', ['image' => $name, 'updated_at' => Database::now()], 'id = :id', ['id' => $id]);
                    $product['image'] = $name;
                }
                $count++;
            }
        }

        if ($count > 0) {
            flash('success', $count . ' image(s) uploaded.');
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
        }
        if ($count === 0 && !$errors) {
            flash('error', 'No images received.');
        }
        redirect('products/' . $id);
    }

    /** Update image description and move it one position in the gallery. */
    public function updateImage(int $id, int $imageId): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        $img = Database::fetch('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $id]);
        if (!$img) {
            flash('error', 'Image not found.');
            redirect('products/' . $id . '#images');
        }
        $alt = mb_substr(trim((string) ($_POST['alt_text'] ?? '')), 0, 255);
        $direction = (string) ($_POST['direction'] ?? '');
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::update('product_images', ['alt_text' => $alt ?: null], 'id = :id', ['id' => $imageId]);
            if (in_array($direction, ['up', 'down'], true)) {
                $operator = $direction === 'up' ? '<' : '>';
                $order = $direction === 'up' ? 'DESC' : 'ASC';
                $other = Database::fetch(
                    "SELECT * FROM product_images WHERE product_id = ? AND sort_order {$operator} ? ORDER BY sort_order {$order}, id {$order} LIMIT 1",
                    [$id, (int) $img['sort_order']]
                );
                if ($other) {
                    Database::update('product_images', ['sort_order' => (int) $other['sort_order']], 'id = :id', ['id' => $imageId]);
                    Database::update('product_images', ['sort_order' => (int) $img['sort_order']], 'id = :id', ['id' => (int) $other['id']]);
                }
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', 'Could not update the image.');
            redirect('products/' . $id . '#images');
        }
        flash('success', 'Image details updated.');
        redirect('products/' . $id . '#images');
    }

    /** Set a gallery image as the product's primary image. */
    public function setPrimaryImage(int $id, int $imageId): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $img = Database::fetch('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $id]);
        if ($img) {
            Database::update('products', ['image' => $img['filename'], 'updated_at' => Database::now()], 'id = :id', ['id' => $id]);
            flash('success', 'Primary image updated.');
        } else {
            flash('error', 'Image not found.');
        }
        redirect('products/' . $id);
    }

    /** Remove a gallery image (promotes the next one if it was primary). */
    public function deleteImage(int $id, int $imageId): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $img = Database::fetch('SELECT * FROM product_images WHERE id = ? AND product_id = ?', [$imageId, $id]);
        if (!$img) {
            flash('error', 'Image not found.');
            redirect('products/' . $id);
        }

        $dir  = BASE_PATH . '/storage/uploads/products';
        $real = realpath($dir);
        $path = realpath($dir . '/' . $img['filename']);
        if ($real !== false && $path !== false && strpos($path, $real . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
            @unlink($path);
        }
        Database::delete('product_images', 'id = ?', [$imageId]);

        $product = Product::find($id);
        if ($product && $product['image'] === $img['filename']) {
            $next = Database::fetch('SELECT filename FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1', [$id]);
            Database::update('products', ['image' => $next ? $next['filename'] : null, 'updated_at' => Database::now()], 'id = :id', ['id' => $id]);
        }
        flash('success', 'Image removed.');
        redirect('products/' . $id);
    }

    /**
     * Printable barcode labels. kind=shelf (default, qty copies) or kind=serials
     * (one label per in-stock unit). &thermal=1 switches to 60×40mm roll labels.
     */
    public function labels(int $id): void
    {
        Auth::requireAdmin();

        $product = Product::find($id);
        if (!$product) {
            flash('error', 'Product not found.');
            redirect('products');
        }

        $kind    = ($_GET['kind'] ?? 'shelf') === 'serials' ? 'serials' : 'shelf';
        $paper   = (($_GET['paper'] ?? '') === 'thermal' || !empty($_GET['thermal'])) ? 'thermal' : 'a4';
        $thermal = $paper === 'thermal';
        $qty     = max(1, min(500, (int) ($_GET['qty'] ?? 24)));

        $units = [];
        if ($kind === 'serials') {
            if ((int) $product['is_serialized'] !== 1) {
                $kind = 'shelf'; // non-serialized products have no unit stickers
            } else {
                $units = Database::fetchAll(
                    "SELECT serial_number, warranty_expires FROM inventory_units
                      WHERE product_id = ? AND status = 'in_stock' ORDER BY id",
                    [$id]
                );
            }
        }

        $barcodeValue = trim((string) ($product['barcode'] ?? '')) !== ''
            ? (string) $product['barcode'] : (string) $product['sku'];

        View::render('products/labels', [
            'title'        => 'Labels — ' . $product['name'],
            'product'      => $product,
            'kind'         => $kind,
            'qty'          => $qty,
            'thermal'      => $thermal,
            'paper'        => $paper,
            'units'        => $units,
            'barcodeValue' => $barcodeValue,
        ], 'print');
    }
}
