<?php
declare(strict_types=1);

/**
 * Product model + stock helpers.
 *
 * Stock rules:
 *  - Serialized products  -> stock = COUNT of inventory_units with status 'in_stock'
 *  - Non-serialized       -> stock = SUM of signed stock_movements.quantity
 */
class Product
{
    public static function all(string $search = ''): array
    {
        $params = [];
        $where  = '';
        if ($search !== '') {
            $where  = ' WHERE (p.name LIKE :q OR p.sku LIKE :q OR p.barcode LIKE :q)';
            $params = ['q' => '%' . $search . '%'];
        }

        return Database::fetchAll(
            "SELECT p.*,
                    c.name AS category_name,
                    b.name AS brand_name,
                    (SELECT COUNT(*) FROM inventory_units u
                      WHERE u.product_id = p.id AND u.status = 'in_stock') AS units_stock,
                    (SELECT COALESCE(SUM(m.quantity), 0) FROM stock_movements m
                      WHERE m.product_id = p.id) AS qty_stock
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN brands b     ON b.id = p.brand_id
               $where
              ORDER BY p.name ASC",
            $params
        );
    }

    /** Filtered inventory workspace query with live stock from the ledger. */
    public static function search(array $filters = []): array
    {
        $q = trim((string) ($filters['q'] ?? ''));
        $category = (int) ($filters['category'] ?? 0);
        $tracking = (string) ($filters['tracking'] ?? '');
        $status = (string) ($filters['status'] ?? '');
        $stock = (string) ($filters['stock'] ?? '');
        $params = [];
        $where = [];
        $stockExpr = "CASE WHEN p.is_serialized = 1
            THEN (SELECT COUNT(*) FROM inventory_units u WHERE u.product_id = p.id AND u.status = 'in_stock')
            ELSE (SELECT COALESCE(SUM(m.quantity), 0) FROM stock_movements m WHERE m.product_id = p.id) END";

        if ($q !== '') {
            $where[] = '(p.name LIKE :q OR p.sku LIKE :q OR p.barcode LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($category > 0) {
            $where[] = 'p.category_id = :category';
            $params['category'] = $category;
        }
        if (in_array($tracking, ['serialized', 'quantity'], true)) {
            $where[] = 'p.is_serialized = :serialized';
            $params['serialized'] = $tracking === 'serialized' ? 1 : 0;
        }
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'p.is_active = :active';
            $params['active'] = $status === 'active' ? 1 : 0;
        }
        if ($stock === 'out') $where[] = "({$stockExpr}) <= 0";
        if ($stock === 'available') $where[] = "({$stockExpr}) > 0";
        if ($stock === 'low') $where[] = "p.reorder_level > 0 AND ({$stockExpr}) <= p.reorder_level";

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return Database::fetchAll(
            "SELECT p.*, c.name AS category_name, b.name AS brand_name,
                    (SELECT COUNT(*) FROM inventory_units u WHERE u.product_id = p.id AND u.status = 'in_stock') AS units_stock,
                    (SELECT COALESCE(SUM(m.quantity), 0) FROM stock_movements m WHERE m.product_id = p.id) AS qty_stock,
                    {$stockExpr} AS live_stock
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN brands b ON b.id = p.brand_id
               {$whereSql}
              ORDER BY p.name ASC",
            $params
        );
    }

    public static function inventorySummary(): array
    {
        $rows = self::search();
        $summary = ['total' => count($rows), 'active' => 0, 'low' => 0, 'out' => 0, 'value' => 0.0];
        foreach ($rows as $row) {
            $live = (int) ($row['live_stock'] ?? self::stockOf($row));
            if ((int) $row['is_active'] === 1) $summary['active']++;
            if ($live <= 0) $summary['out']++;
            if ((int) $row['reorder_level'] > 0 && $live <= (int) $row['reorder_level']) $summary['low']++;
            $summary['value'] += $live * (float) $row['cost_price'];
        }
        return $summary;
    }

    public static function find(int $id): ?array
    {
        $p = Database::fetch(
            "SELECT p.*, c.name AS category_name, b.name AS brand_name
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN brands b     ON b.id = p.brand_id
              WHERE p.id = ?",
            [$id]
        );
        return $p ?: null;
    }

    public static function stockOf(array $product): int
    {
        return (int) $product['is_serialized'] === 1
            ? (int) ($product['units_stock'] ?? 0)
            : (int) ($product['qty_stock'] ?? 0);
    }

    /** Live stock for a single product id (independent of which columns were selected). */
    public static function stock(int $id): int
    {
        $isSerialized = Database::fetchValue('SELECT is_serialized FROM products WHERE id = ?', [$id]);
        if ($isSerialized === null) {
            return 0;
        }
        if ((int) $isSerialized === 1) {
            return (int) Database::fetchValue(
                "SELECT COUNT(*) FROM inventory_units WHERE product_id = ? AND status = 'in_stock'",
                [$id]
            );
        }
        return (int) Database::fetchValue(
            'SELECT COALESCE(SUM(quantity), 0) FROM stock_movements WHERE product_id = ?',
            [$id]
        );
    }

    /** Preview a quantity adjustment against the live ledger balance. */
    public static function stockAfterAdjustment(int $id, int $delta): int
    {
        return self::stock($id) + $delta;
    }

    public static function units(int $productId, string $search = '', string $status = ''): array
    {
        $where = ['product_id = :product_id'];
        $params = ['product_id' => $productId];
        if ($search !== '') {
            $where[] = '(serial_number LIKE :search OR note LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if (in_array($status, ['in_stock', 'reserved', 'sold', 'returned', 'damaged', 'missing'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }
        return Database::fetchAll(
            'SELECT * FROM inventory_units WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC',
            $params
        );
    }

    public static function unitSummary(int $productId): array
    {
        $summary = ['total' => 0, 'in_stock' => 0, 'reserved' => 0, 'sold' => 0, 'attention' => 0];
        foreach (Database::fetchAll('SELECT status, COUNT(*) AS total FROM inventory_units WHERE product_id = ? GROUP BY status', [$productId]) as $row) {
            $count = (int) $row['total'];
            $summary['total'] += $count;
            if (isset($summary[$row['status']])) $summary[$row['status']] = $count;
            if (in_array($row['status'], ['damaged', 'missing'], true)) $summary['attention'] += $count;
        }
        return $summary;
    }

    /** @return string[] uniqueness errors for product identity fields. */
    public static function uniquenessErrors(array $data, ?int $excludeId = null): array
    {
        $errors = [];
        foreach (['sku' => 'SKU', 'barcode' => 'Barcode'] as $field => $label) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') continue;
            $sql = "SELECT COUNT(*) FROM products WHERE {$field} = ?";
            $params = [$value];
            if ($excludeId !== null) {
                $sql .= ' AND id != ?';
                $params[] = $excludeId;
            }
            if ((int) Database::fetchValue($sql, $params) > 0) $errors[] = "That {$label} is already in use.";
        }
        return $errors;
    }

    /** Gallery images for a product (primary first). */
    public static function images(int $productId): array
    {
        return Database::fetchAll(
            'SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order ASC, id ASC',
            [$productId]
        );
    }

    public static function movements(int $productId): array
    {
        return Database::fetchAll(
            "SELECT m.*, u.name AS user_name
               FROM stock_movements m
               LEFT JOIN users u ON u.id = m.user_id
              WHERE m.product_id = ?
              ORDER BY m.id DESC LIMIT 100",
            [$productId]
        );
    }

    public static function lowStock(): array
    {
        $rows = Database::fetchAll(
            "SELECT p.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM inventory_units u
                      WHERE u.product_id = p.id AND u.status = 'in_stock') AS units_stock,
                    (SELECT COALESCE(SUM(m.quantity), 0) FROM stock_movements m
                      WHERE m.product_id = p.id) AS qty_stock
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.is_active = 1 AND p.reorder_level > 0
              ORDER BY p.name ASC"
        );
        return array_values(array_filter($rows, fn ($r) => self::stockOf($r) <= (int) $r['reorder_level']));
    }

    public static function hasSales(int $id): bool
    {
        return (int) Database::fetchValue('SELECT COUNT(*) FROM sale_items WHERE product_id = ?', [$id]) > 0;
    }

    private static function stockColumns(): string
    {
        return "(SELECT COUNT(*) FROM inventory_units u
                   WHERE u.product_id = p.id AND u.status = 'in_stock') AS units_stock,
                (SELECT COALESCE(SUM(m.quantity), 0) FROM stock_movements m
                   WHERE m.product_id = p.id) AS qty_stock";
    }

    /** Active, in-catalogue products for the storefront (newest first). */
    public static function featured(int $n = 4): array
    {
        $n = max(1, (int) $n);
        return Database::fetchAll(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name, "
            . self::stockColumns() . "
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN brands b     ON b.id = p.brand_id
              WHERE p.is_active = 1
              ORDER BY p.id DESC LIMIT {$n}"
        );
    }

    /** Storefront browsing with filters and sorting. */
    public static function shopBrowse(string $q = '', string $categorySlug = '', string $brand = '', string $sort = 'name'): array
    {
        $where  = ['p.is_active = 1'];
        $params = [];

        if ($q !== '') {
            $where[] = '(p.name LIKE :q OR p.sku LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($categorySlug !== '') {
            $where[] = 'c.slug = :cat';
            $params['cat'] = $categorySlug;
        }
        if ($brand !== '') {
            $where[] = 'b.name = :brand';
            $params['brand'] = $brand;
        }

        $order = match ($sort) {
            'price_asc'  => 'p.sell_price ASC',
            'price_desc' => 'p.sell_price DESC',
            'newest'     => 'p.id DESC',
            default      => 'p.name ASC',
        };

        return Database::fetchAll(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name, "
            . self::stockColumns() . "
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
               LEFT JOIN brands b     ON b.id = p.brand_id
              WHERE " . implode(' AND ', $where) . "
              ORDER BY {$order}",
            $params
        );
    }

    /** Other products to suggest on a product page. */
    public static function related(int $id, ?int $categoryId, int $n = 4): array
    {
        $params = ['id' => $id];
        $catWhere = '';
        if ($categoryId) {
            $catWhere = ' AND p.category_id = :cat';
            $params['cat'] = $categoryId;
        }
        $n = max(1, (int) $n);
        return Database::fetchAll(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug, "
            . self::stockColumns() . "
               FROM products p
               LEFT JOIN categories c ON c.id = p.category_id
              WHERE p.is_active = 1 AND p.id != :id {$catWhere}
              ORDER BY p.id DESC LIMIT {$n}",
            $params
        );
    }

    /** @return string[] validation errors (empty when valid) */
    public static function validate(array $d): array
    {
        $errors = [];
        if (trim((string) ($d['name'] ?? '')) === '') {
            $errors[] = 'Product name is required.';
        }
        if (trim((string) ($d['sku'] ?? '')) === '') {
            $errors[] = 'SKU is required.';
        }
        if (!is_numeric($d['cost_price'] ?? '') || (float) $d['cost_price'] < 0) {
            $errors[] = 'Cost price must be a non-negative number.';
        }
        if (!is_numeric($d['sell_price'] ?? '') || (float) $d['sell_price'] <= 0) {
            $errors[] = 'Selling price must be greater than zero.';
        }
        if (($d['warranty_months'] ?? '') !== '' && (!ctype_digit((string) $d['warranty_months']) || (int) $d['warranty_months'] < 0)) {
            $errors[] = 'Warranty months must be a whole number (0 or more).';
        }
        return $errors;
    }

    /** Variants for a product (active only for shop). */
    public static function variants(int $productId, bool $activeOnly = true): array
    {
        if (!Schema::tableExists('product_variants')) {
            return [];
        }
        return ProductVariant::allForProduct($productId, $activeOnly);
    }

    /** Lowest price among variants + base product for "From" display. */
    public static function fromPrice(array $product): float
    {
        $base = (float)($product['sell_price'] ?? 0);
        if (!Schema::tableExists('product_variants')) {
            return $base;
        }
        $variants = self::variants((int)$product['id'], true);
        if (!$variants) {
            return $base;
        }
        $prices = [$base];
        foreach ($variants as $v) {
            if (isset($v['price_override']) && $v['price_override'] !== null && (float)$v['price_override'] > 0) {
                $prices[] = (float)$v['price_override'];
            }
        }
        return min($prices);
    }

    /** Condition display helpers */
    public static function conditionLabel(array $product): string
    {
        $type = strtolower(trim((string)($product['condition_type'] ?? '')));
        if ($type === '') {
            return 'New';
        }
        return ucfirst($type);
    }
}
