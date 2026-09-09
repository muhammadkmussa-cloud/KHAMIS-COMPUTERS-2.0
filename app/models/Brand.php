<?php
declare(strict_types=1);

class Brand
{
    public static function all(bool $withCounts = false): array
    {
        if (!$withCounts) {
            return Database::fetchAll('SELECT * FROM brands ORDER BY name');
        }
        return Database::fetchAll(
            "SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id) AS product_count
               FROM brands b ORDER BY b.name"
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM brands WHERE id = ?', [$id]);
    }

    public static function create(string $name): int
    {
        return Database::insert('brands', [
            'name' => trim($name), 'is_active' => 1, 'created_at' => Database::now(),
        ]);
    }

    public static function update(int $id, string $name): void
    {
        Database::update('brands', ['name' => trim($name)], 'id = :id', ['id' => $id]);
    }
}
