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

    public static function units(int $productId): array
    {
        return Database::fetchAll(
            'SELECT * FROM inventory_units WHERE product_id = ? ORDER BY id DESC',
            [$productId]
        );
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
}
