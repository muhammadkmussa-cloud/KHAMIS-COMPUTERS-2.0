<?php
declare(strict_types=1);

/**
 * Khamis Computers — CLI installer.
 *
 * Usage (from the project root):
 *   php tools/install.php              # run migrations + seed demo data
 *   php tools/install.php --fresh      # drop & recreate everything
 *
 * Reads app config from .env / app/config/config.php. On cPanel you would
 * normally run the equivalent through phpMyAdmin (import schema/schema.sql) —
 * this CLI is a convenience for local and preview environments.
 */

require dirname(__DIR__) . '/app/bootstrap.php';

function cli_out(string $msg): void { echo $msg . PHP_EOL; }

$fresh = in_array('--fresh', $argv ?? [], true);
$driver = Database::driver();
cli_out('Khamis Computers installer');
cli_out('  driver : ' . $driver);

if ($driver === 'sqlite') {
    $path = (string) config('db.sqlite_path');
    if ($fresh && is_file($path)) {
        @unlink($path);
        cli_out('  removed existing SQLite file');
    }
} else {
    if ($fresh) {
        $m  = config('db.mysql');
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $m['host'], $m['port']),
            $m['username'],
            $m['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $pdo->exec('DROP DATABASE IF EXISTS `' . $m['database'] . '`');
        $pdo->exec('CREATE DATABASE `' . $m['database'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        cli_out('  recreated database ' . $m['database']);
    }
}

Schema::apply();
Schema::migrateColumns();
Schema::createIndexes();
Schema::seedBaseline();
Schema::seedDeliveryZones();
cli_out('  schema applied');

// Default admin account (admin@khamis.local / admin1234) ---------------------
$exists = Database::fetchValue('SELECT COUNT(*) FROM users WHERE email = ?', ['admin@khamis.local']);
if ((int) $exists === 0) {
    Database::insert('users', [
        'name'          => 'Khamis Admin',
        'email'         => 'admin@khamis.local',
        'password_hash' => password_hash('admin1234', PASSWORD_BCRYPT, ['cost' => (int) config('security.bcrypt_rounds', 12)]),
        'role'          => 'admin',
        'is_active'     => 1,
        'created_at'    => Database::now(),
        'updated_at'    => Database::now(),
    ]);
    cli_out('  seeded admin   : admin@khamis.local / admin1234');
}

// Demo cashier account (cashier@khamis.local / cashier1234) ------------------
$exists = Database::fetchValue('SELECT COUNT(*) FROM users WHERE email = ?', ['cashier@khamis.local']);
if ((int) $exists === 0) {
    Database::insert('users', [
        'name'          => 'Khamis Cashier',
        'email'         => 'cashier@khamis.local',
        'password_hash' => password_hash('cashier1234', PASSWORD_BCRYPT, ['cost' => (int) config('security.bcrypt_rounds', 12)]),
        'role'          => 'cashier',
        'is_active'     => 1,
        'created_at'    => Database::now(),
        'updated_at'    => Database::now(),
    ]);
    cli_out('  seeded cashier : cashier@khamis.local / cashier1234');
}

// Demo categories ------------------------------------------------------------
$hasCats = Database::fetchValue('SELECT COUNT(*) FROM categories');
if ((int) $hasCats === 0) {
    $cats = ['Laptops' => 'Notebooks and ultrabooks', 'Desktops' => 'Towers, all-in-ones', 'Phones & Tablets' => 'Smartphones and tablets', 'Accessories' => 'Keyboards, mice, cables', 'Monitors' => 'Displays and screens', 'Components' => 'RAM, SSDs, PSUs', 'Networking' => 'Routers, switches, adapters'];
    foreach ($cats as $name => $desc) {
        Database::insert('categories', [
            'name' => $name, 'slug' => slugify($name), 'description' => $desc,
            'sort_order' => 0, 'is_active' => 1, 'created_at' => Database::now(),
        ]);
    }
    cli_out('  seeded ' . count($cats) . ' categories');
}

// Demo brands ----------------------------------------------------------------
$hasBrands = Database::fetchValue('SELECT COUNT(*) FROM brands');
if ((int) $hasBrands === 0) {
    foreach (['HP', 'Dell', 'Lenovo', 'Apple', 'Samsung', 'Asus', 'Acer'] as $b) {
        Database::insert('brands', ['name' => $b, 'is_active' => 1, 'created_at' => Database::now()]);
    }
    cli_out('  seeded brands');
}

// Demo products (with serialized units) --------------------------------------
$hasProducts = Database::fetchValue('SELECT COUNT(*) FROM products');
if ((int) $hasProducts === 0) {
    $catId = function (string $name): int {
        $id = Database::fetchValue('SELECT id FROM categories WHERE name = ?', [$name]);
        return (int) $id;
    };
    $brandId = function (string $name): int {
        $id = Database::fetchValue('SELECT id FROM brands WHERE name = ?', [$name]);
        return (int) $id;
    };

    $laptops = $catId('Laptops'); $phones = $catId('Phones & Tablets');
    $acc     = $catId('Accessories'); $comp = $catId('Components');

    $products = [
        // name, sku, category, brand, cost, sell, serialized, warranty_months
        ['HP ProBook 450 G10 (i5/16GB/512GB)', 'KC-LAP-001', $laptops, 'HP', 52000, 65000, 1, 12],
        ['Dell Latitude 5440 (i7/16GB/512GB)', 'KC-LAP-002', $laptops, 'Dell', 68000, 84000, 1, 12],
        ['Lenovo ThinkPad E14 (i5/8GB/256GB)', 'KC-LAP-003', $laptops, 'Lenovo', 45000, 56000, 1, 12],
        ['Samsung Galaxy A55 5G 256GB',        'KC-PHN-001', $phones, 'Samsung', 32000, 41000, 1, 12],
        ['Samsung Galaxy S24 256GB',           'KC-PHN-002', $phones, 'Samsung', 78000, 95000, 1, 12],
        ['Logitech Wireless Mouse M221',       'KC-ACC-001', $acc, 'Logitech', 1200, 2200, 0, 6],
        ['Kingston 512GB NVMe SSD',            'KC-COM-001', $comp, 'Kingston', 5200, 7500, 0, 12],
        ['TP-Link AC1200 Router',              'KC-ACC-002', $acc, 'TP-Link', 2600, 4200, 0, 12],
    ];

    $now = Database::now();
    foreach ($products as [$name, $sku, $cid, $bid, $cost, $sell, $serialized, $warranty]) {
        $pid = Database::insert('products', [
            'name' => $name, 'sku' => $sku, 'category_id' => $cid,
            'brand_id' => ($bid === 'Logitech' || $bid === 'Kingston' || $bid === 'TP-Link') ? null : $brandId($bid),
            'cost_price' => $cost, 'sell_price' => $sell,
            'is_active' => 1, 'is_serialized' => $serialized,
            'warranty_months' => $warranty, 'reorder_level' => 2,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        if ($serialized) {
            $qty = 3;
            $expires = $warranty > 0 ? date('Y-m-d', strtotime($now . ' +' . $warranty . ' months')) : null;
            for ($i = 1; $i <= $qty; $i++) {
                $serial = strtoupper(substr(md5($sku . '-' . $i), 0, 14));
                Database::insert('inventory_units', [
                    'product_id' => $pid, 'serial_number' => $serial,
                    'status' => 'in_stock', 'received_at' => $now,
                    'warranty_expires' => $expires,
                ]);
            }
            Database::insert('stock_movements', [
                'product_id' => $pid, 'type' => 'received', 'quantity' => $qty,
                'reference' => 'SEED', 'created_at' => $now,
            ]);
        } else {
            Database::insert('stock_movements', [
                'product_id' => $pid, 'type' => 'received', 'quantity' => 10,
                'reference' => 'SEED', 'created_at' => $now,
            ]);
        }
    }
    cli_out('  seeded demo products & stock');
}

cli_out('Done. Admin login: admin@khamis.local / admin1234');
