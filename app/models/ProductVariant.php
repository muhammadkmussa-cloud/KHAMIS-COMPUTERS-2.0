<?php
declare(strict_types=1);

/**
 * ProductVariant model — lightweight variant support for catalogue mode.
 * Each variant belongs to a product and can override price, SKU, and attributes.
 */
class ProductVariant
{
    public static function allForProduct(int $productId, bool $activeOnly = false): array
    {
        $where = 'product_id = :pid';
        $params = ['pid' => $productId];
        if ($activeOnly) {
            $where .= ' AND is_active = 1';
        }
        return Database::fetchAll(
            "SELECT * FROM product_variants WHERE {$where} ORDER BY sort_order ASC, id ASC",
            $params
        );
    }

    public static function find(int $id): ?array
    {
        $row = Database::fetch('SELECT * FROM product_variants WHERE id = ?', [$id]);
        return $row ?: null;
    }

    public static function findForProduct(int $id, int $productId): ?array
    {
        $row = Database::fetch('SELECT * FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1', [$id, $productId]);
        return $row ?: null;
    }

    public static function create(int $productId, array $data): int
    {
        return Database::insert('product_variants', [
            'product_id' => $productId,
            'sku' => trim($data['sku'] ?? '') !== '' ? trim($data['sku']) : null,
            'label' => trim($data['label'] ?? '') !== '' ? trim($data['label']) : null,
            'ram' => trim($data['ram'] ?? '') !== '' ? trim($data['ram']) : null,
            'storage' => trim($data['storage'] ?? '') !== '' ? trim($data['storage']) : null,
            'colour' => trim($data['colour'] ?? '') !== '' ? trim($data['colour']) : null,
            'condition_type' => trim($data['condition_type'] ?? '') !== '' ? strtolower(trim($data['condition_type'])) : null,
            'grade' => trim($data['grade'] ?? '') !== '' ? trim($data['grade']) : null,
            'price_override' => isset($data['price_override']) && $data['price_override'] !== '' ? round((float)$data['price_override'], 2) : null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'created_at' => Database::now(),
        ]);
    }

    public static function update(int $id, array $data): int
    {
        $fields = [];
        $params = ['id' => $id];
        foreach (['sku','label','ram','storage','colour','condition_type','grade'] as $f) {
            if (array_key_exists($f, $data)) {
                $fields[] = "{$f} = :{$f}";
                $val = trim((string)$data[$f]);
                $params[$f] = $val !== '' ? ($f === 'condition_type' ? strtolower($val) : $val) : null;
            }
        }
        if (array_key_exists('price_override', $data)) {
            $fields[] = "price_override = :price_override";
            $params['price_override'] = $data['price_override'] !== '' && $data['price_override'] !== null ? round((float)$data['price_override'], 2) : null;
        }
        if (array_key_exists('is_active', $data)) {
            $fields[] = "is_active = :is_active";
            $params['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }
        if (array_key_exists('sort_order', $data)) {
            $fields[] = "sort_order = :sort_order";
            $params['sort_order'] = (int)$data['sort_order'];
        }
        if (!$fields) {
            return 0;
        }
        $set = implode(', ', $fields);
        return Database::pdo()->prepare("UPDATE product_variants SET {$set} WHERE id = :id")->execute($params) ? 1 : 0;
    }

    public static function delete(int $id): int
    {
        return Database::delete('product_variants', 'id = ?', [$id]);
    }

    /** Build a human-readable variant string from attributes. */
    public static function displayLabel(array $variant): string
    {
        if (!empty($variant['label'])) {
            return (string)$variant['label'];
        }
        $parts = [];
        if (!empty($variant['ram'])) $parts[] = $variant['ram'] . ' RAM';
        if (!empty($variant['storage'])) $parts[] = $variant['storage'];
        if (!empty($variant['colour'])) $parts[] = $variant['colour'];
        if (!empty($variant['condition_type'])) $parts[] = ucfirst($variant['condition_type']);
        return $parts ? implode(' / ', $parts) : 'Standard';
    }

    public static function effectivePrice(array $variant, array $product): float
    {
        if (isset($variant['price_override']) && $variant['price_override'] !== null && (float)$variant['price_override'] > 0) {
            return (float)$variant['price_override'];
        }
        return (float)($product['sell_price'] ?? 0);
    }

    /** Get distinct values for a given attribute across product variants (for selector UI). */
    public static function distinctValues(int $productId, string $field): array
    {
        $allowed = ['ram','storage','colour','condition_type'];
        if (!in_array($field, $allowed, true)) {
            return [];
        }
        $rows = Database::fetchAll(
            "SELECT DISTINCT {$field} FROM product_variants WHERE product_id = ? AND {$field} IS NOT NULL AND {$field} != '' AND is_active = 1 ORDER BY {$field} ASC",
            [$productId]
        );
        return array_column($rows, $field);
    }
}
