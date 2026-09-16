<?php
declare(strict_types=1);

/**
 * Splits schema/schema.sql on "-- @@" markers and executes each statement.
 * Works for both MySQL and SQLite. Used by the CLI installer and the setup page.
 */
class Schema
{
    public static function apply(): void
    {
        $sql    = file_get_contents(BASE_PATH . '/schema/schema.sql');
        $pdo    = Database::pdo();
        $stmts  = array_values(array_filter(array_map('trim', preg_split('/--\s*@@kc@@/', $sql))));

        foreach ($stmts as $stmt) {
            if (Database::driver() === 'sqlite') {
                // Translate MySQL-specific syntax into SQLite-compatible SQL.
                $stmt = str_replace('INTEGER PRIMARY KEY AUTO_INCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT', $stmt);
                $stmt = str_replace('ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci', '', $stmt);
                $stmt = str_replace('NOW()', 'CURRENT_TIMESTAMP', $stmt);
            }
            $pdo->exec($stmt);
        }
    }

    /** Seed baseline settings rows (idempotent, per key). */
    public static function seedBaseline(): void
    {
        $rows = [
            ['shop_name', 'Khamis Computers'],
            ['shop_tagline', 'The latest laptops, phones and accessories — order online or in store.'],
            ['shop_phone', '+254 700 000 000'],
            ['shop_email', 'sales@khamiscomputers.com'],
            ['shop_address', 'Nairobi, Kenya'],
            ['vat_rate', '16'],
            ['receipt_footer', 'Thank you for shopping with Khamis Computers.'],
            ['discount_pin_threshold', '1000'],
            ['mail_enabled', '0'],
            ['mail_transport', 'mail'],
            ['mail_from', ''],
            ['mail_from_name', ''],
            ['smtp_host', ''],
            ['smtp_port', '587'],
            ['smtp_encryption', 'tls'],
            ['smtp_username', ''],
            ['smtp_password', ''],
            ['low_stock_threshold', '5'],
            ['low_stock_recipient', ''],
            ['mpesa_enabled', '0'],
            ['mpesa_env', 'sandbox'],
            ['mpesa_shortcode', ''],
            ['mpesa_passkey', ''],
            ['mpesa_consumer_key', ''],
            ['mpesa_consumer_secret', ''],
            ['mpesa_shortcode_type', 'paybill'],
        ];
        foreach ($rows as [$key, $value]) {
            $exists = (int) Database::fetchValue('SELECT COUNT(*) FROM shop_settings WHERE setting_key = ?', [$key]);
            if ($exists === 0) {
                Database::insert('shop_settings', [
                    'setting_key' => $key, 'setting_value' => $value, 'updated_at' => Database::now(),
                ]);
            }
        }
    }

    /** Seed default delivery zones (idempotent — only when the table is empty). */
    public static function seedDeliveryZones(): void
    {
        $count = (int) Database::fetchValue('SELECT COUNT(*) FROM delivery_zones');
        if ($count > 0) {
            return;
        }
        $zones = [
            ['name' => 'Nairobi CBD',             'fee' => 150, 'sort_order' => 1],
            ['name' => 'Nairobi Suburbs',         'fee' => 250, 'sort_order' => 2],
            ['name' => 'Kiambu / Thika',          'fee' => 350, 'sort_order' => 3],
            ['name' => 'Rest of Kenya',           'fee' => 500, 'sort_order' => 4],
        ];
        foreach ($zones as $z) {
            Database::insert('delivery_zones', [
                'name'       => $z['name'],
                'fee'        => $z['fee'],
                'is_active'  => 1,
                'sort_order' => $z['sort_order'],
                'created_at' => Database::now(),
            ]);
        }
    }

    /**
     * Create secondary indexes with driver-aware existence checks.
     * (Not done in schema.sql, because MySQL rejects CREATE INDEX IF NOT EXISTS.)
     * Includes the unique (device_id, client_ref) index that makes offline-sync
     * dedup safe against concurrent double-submission.
     */
    public static function createIndexes(): void
    {
        self::dedupeOfflineSales();

        $indexes = [
            // [name, table, columns, unique]
            ['idx_products_category', 'products', 'category_id', false],
            ['idx_products_active',   'products', 'is_active', false],
            ['idx_units_product',     'inventory_units', 'product_id', false],
            ['idx_units_status',      'inventory_units', 'status', false],
            ['idx_sales_created',     'sales', 'created_at', false],
            ['idx_sales_channel',     'sales', 'channel', false],
            ['idx_saleitems_sale',    'sale_items', 'sale_id', false],
            ['idx_returns_sale',      'returns', 'sale_id', false],
            ['idx_movements_product', 'stock_movements', 'product_id', false],
            ['idx_sales_dedup',       'sales', 'device_id, client_ref', true],
        ];
        foreach ($indexes as [$name, $table, $cols, $unique]) {
            if (self::indexExists($name, $table)) {
                continue;
            }
            $u = $unique ? 'UNIQUE ' : '';
            Database::pdo()->exec("CREATE {$u}INDEX {$name} ON {$table} ({$cols})");
        }
    }

    private static function indexExists(string $name, string $table): bool
    {
        if (Database::driver() === 'sqlite') {
            foreach (Database::fetchAll("PRAGMA index_list({$table})") as $row) {
                if ($row['name'] === $name) {
                    return true;
                }
            }
            return false;
        }
        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM information_schema.statistics
              WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
            [$table, $name]
        ) > 0;
    }

    /**
     * Remove any double-inserted offline sales (same device_id + client_ref)
     * before creating the unique index on those columns. Keeps the earliest
     * record and deletes the rest along with their stock movements.
     */
    private static function dedupeOfflineSales(): void
    {
        $dupes = Database::fetchAll(
            "SELECT device_id, client_ref, MIN(id) AS keep_id
               FROM sales
              WHERE device_id IS NOT NULL AND client_ref IS NOT NULL
              GROUP BY device_id, client_ref
             HAVING COUNT(*) > 1"
        );
        foreach ($dupes as $d) {
            $rows = Database::fetchAll(
                'SELECT id, sale_number FROM sales WHERE device_id = ? AND client_ref = ? AND id != ?',
                [$d['device_id'], $d['client_ref'], $d['keep_id']]
            );
            foreach ($rows as $r) {
                Database::delete('stock_movements', 'reference = ?', [$r['sale_number']]);
                Database::delete('sales', 'id = ?', [$r['id']]);
            }
        }
    }

    public static function isInstalled(): bool
    {
        // "Installed" means the admin account has been created, not merely that
        // the schema exists. Otherwise importing schema.sql via phpMyAdmin
        // (empty users table) would make the web installer 404 before anyone
        // could seed the first admin.
        try {
            return (int) Database::fetchValue('SELECT COUNT(*) FROM users') > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** True if a column exists on a table (portable MySQL / SQLite). */
    public static function columnExists(string $table, string $column): bool
    {
        if (Database::driver() === 'sqlite') {
            foreach (Database::fetchAll("PRAGMA table_info({$table})") as $row) {
                if ($row['name'] === $column) {
                    return true;
                }
            }
            return false;
        }
        return (int) Database::fetchValue(
            "SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?",
            [$table, $column]
        ) > 0;
    }

    /** Lightweight forward migrations for already-installed databases. */
    public static function migrateColumns(): void
    {
        $backfillSaleItemCosts = !self::columnExists('sale_items', 'unit_cost');
        $columns = [
            'sales' => [
                'fulfillment'      => "VARCHAR(20) NOT NULL DEFAULT 'pickup'",
                'delivery_address' => 'TEXT NULL',
                'delivery_fee'     => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'delivery_zone'    => 'VARCHAR(120) NULL',
            ],
            'sale_items' => [
                'unit_cost' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
            ],
            'products' => [
                'warranty_months' => 'INT NULL',
                'image'           => 'VARCHAR(255) NULL',
            ],
            'product_images' => [
                'alt_text' => 'VARCHAR(255) NULL',
            ],
            'inventory_units' => [
                'warranty_expires' => 'DATE NULL',
                'grn_item_id' => 'INT NULL',
            ],
            'goods_received' => [
                'supplier_id' => 'INT NULL',
                'supplier_reference' => 'VARCHAR(120) NULL',
            ],
            'returns' => [
                'refund_method'   => "VARCHAR(20) NOT NULL DEFAULT 'original'",
                'evidence_note'   => 'TEXT NULL',
                'decision_note'   => 'TEXT NULL',
                'decision_user_id'=> 'INT NULL',
                'decided_at'      => 'DATETIME NULL',
            ],
            'return_items' => [
                'stock_outcome' => 'VARCHAR(20) NULL',
            ],
            'expenses' => [
                'receipt_file'  => 'VARCHAR(255) NULL',
                'is_recurring'  => 'TINYINT NOT NULL DEFAULT 0',
                'deleted_at'    => 'DATETIME NULL',
                'deleted_by'    => 'INT NULL',
                'delete_reason' => 'VARCHAR(500) NULL',
            ],
            'z_reports' => [
                'counted_cash' => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'variance'     => 'DECIMAL(12,2) NOT NULL DEFAULT 0',
                'closed_by'    => 'INT NULL',
            ],
        ];
        foreach ($columns as $table => $defs) {
            foreach ($defs as $col => $def) {
                if (!self::columnExists($table, $col)) {
                    Database::pdo()->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$def}");
                }
            }
        }

        // Preserve truthful history for decisions recorded before outcome and
        // decision-note fields existed. The previous workflow always restocked
        // every approved item and stored no separate decision timestamp.
        Database::run(
            "UPDATE return_items SET stock_outcome = 'restock'
              WHERE stock_outcome IS NULL AND return_id IN (SELECT id FROM returns WHERE status = 'completed')"
        );
        Database::run(
            "UPDATE returns SET decided_at = NULL, decision_user_id = NULL,
                    decision_note = 'Historical approval; decision details were not recorded.'
              WHERE status = 'completed' AND (decision_note IS NULL OR decision_note = 'Approved before decision notes were introduced.')"
        );
        Database::run(
            "UPDATE returns SET decided_at = NULL, decision_user_id = NULL,
                    decision_note = 'Historical rejection; decision details were not recorded.'
              WHERE status = 'rejected' AND (decision_note IS NULL OR decision_note = 'Rejected before decision notes were introduced.')"
        );
        Database::run(
            'UPDATE z_reports SET counted_cash = expected_cash, variance = 0 WHERE closed_by IS NULL AND counted_cash = 0 AND variance = 0'
        );
        if ($backfillSaleItemCosts) {
            Database::run(
                'UPDATE sale_items SET unit_cost = COALESCE((SELECT cost_price FROM products WHERE products.id = sale_items.product_id), 0)'
            );
        }
    }
}
