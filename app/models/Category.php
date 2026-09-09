<?php
declare(strict_types=1);

class Category
{
    public static function all(bool $withCounts = false): array
    {
        if (!$withCounts) {
            return Database::fetchAll('SELECT * FROM categories ORDER BY sort_order, name');
        }
        return Database::fetchAll(
            "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
               FROM categories c ORDER BY c.sort_order, c.name"
        );
    }

    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM categories WHERE id = ?', [$id]);
    }

    public static function create(string $name, string $description = ''): int
    {
        return Database::insert('categories', [
            'name'        => trim($name),
            'slug'        => self::uniqueSlug($name),
            'description' => trim($description),
            'sort_order'  => (int) Database::fetchValue('SELECT COALESCE(MAX(sort_order),0) FROM categories') + 1,
            'is_active'   => 1,
            'created_at'  => Database::now(),
        ]);
    }

    public static function update(int $id, string $name, string $description = ''): void
    {
        Database::update('categories', [
            'name'        => trim($name),
            'description' => trim($description),
        ], 'id = :id', ['id' => $id]);
    }

    public static function uniqueSlug(string $name): string
    {
        $base = slugify($name) ?: 'category';
        $slug = $base;
        $i = 2;
        while (Database::fetchValue('SELECT COUNT(*) FROM categories WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
