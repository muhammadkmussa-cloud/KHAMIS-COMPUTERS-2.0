<?php
declare(strict_types=1);

class HeroSlide
{
    public static function active(): array
    {
        try {
            if (!Schema::tableExists('hero_slides')) {
                return [];
            }
            $now = Database::now();
            return Database::fetchAll(
                "SELECT h.*, p.name AS product_name, p.sell_price AS product_price, "
                . "c.name AS category_name, c.slug AS category_slug "
                . "FROM hero_slides h "
                . "LEFT JOIN products p ON p.id = h.product_id AND p.is_active = 1 "
                . "LEFT JOIN categories c ON c.id = h.category_id AND c.is_active = 1 "
                . "WHERE h.is_active = 1 "
                . "AND (h.starts_at IS NULL OR h.starts_at <= :now_start) "
                . "AND (h.ends_at IS NULL OR h.ends_at >= :now_end) "
                . "AND h.headline <> '' "
                . "AND h.desktop_image <> '' "
                . "ORDER BY h.display_order ASC, h.id ASC",
                ['now_start' => $now, 'now_end' => $now]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function all(): array
    {
        if (!Schema::tableExists('hero_slides')) {
            return [];
        }
        return Database::fetchAll(
            "SELECT h.*, p.name AS product_name, c.name AS category_name "
            . "FROM hero_slides h "
            . "LEFT JOIN products p ON p.id = h.product_id "
            . "LEFT JOIN categories c ON c.id = h.category_id "
            . "ORDER BY h.display_order ASC, h.id ASC"
        );
    }

    public static function find(int $id): ?array
    {
        if (!Schema::tableExists('hero_slides')) {
            return null;
        }
        return Database::fetch(
            "SELECT * FROM hero_slides WHERE id = ?", [$id]
        ) ?: null;
    }

    public static function count(): int
    {
        if (!Schema::tableExists('hero_slides')) {
            return 0;
        }
        return (int) Database::fetchValue('SELECT COUNT(*) FROM hero_slides');
    }

    public static function create(array $data): int
    {
        $fields = [
            'desktop_image', 'mobile_image', 'eyebrow', 'headline', 'description',
            'product_id', 'category_id', 'cta_text', 'cta_type', 'cta_target',
            'secondary_cta_text', 'secondary_cta_type', 'secondary_cta_target',
            'text_position', 'image_position', 'overlay_strength',
            'display_order', 'is_active', 'starts_at', 'ends_at',
        ];
        $values = [];
        foreach ($fields as $f) {
            $values[$f] = $data[$f] ?? null;
        }
        $values['created_at'] = Database::now();
        $values['updated_at'] = Database::now();
        if ($values['product_id'] !== '') {
            $values['product_id'] = (int) $values['product_id'] ?: null;
        } else {
            $values['product_id'] = null;
        }
        if ($values['category_id'] !== '') {
            $values['category_id'] = (int) $values['category_id'] ?: null;
        } else {
            $values['category_id'] = null;
        }
        if ($values['overlay_strength'] === '') {
            $values['overlay_strength'] = 45;
        }
        if ($values['display_order'] === '') {
            $values['display_order'] = 0;
        }
        if ($values['is_active'] === '') {
            $values['is_active'] = 1;
        }
        return Database::insert('hero_slides', $values);
    }

    public static function update(int $id, array $data): void
    {
        $values = [];
        $allowed = [
            'desktop_image', 'mobile_image', 'eyebrow', 'headline', 'description',
            'product_id', 'category_id', 'cta_text', 'cta_type', 'cta_target',
            'secondary_cta_text', 'secondary_cta_type', 'secondary_cta_target',
            'text_position', 'image_position', 'overlay_strength',
            'display_order', 'is_active', 'starts_at', 'ends_at',
        ];
        foreach ($allowed as $f) {
            if (!array_key_exists($f, $data)) {
                continue;
            }
            $val = $data[$f];
            if ($f === 'product_id' || $f === 'category_id') {
                $val = $val !== '' && $val !== null ? (int) $val : null;
            }
            if ($f === 'overlay_strength' && $val === '') {
                $val = 45;
            }
            if ($f === 'display_order' && $val === '') {
                $val = 0;
            }
            if ($f === 'is_active' && $val === '') {
                $val = 1;
            }
            $values[$f] = $val;
        }
        $values['updated_at'] = Database::now();
        if ($values) {
            Database::update('hero_slides', $values, 'id = :id', ['id' => $id]);
        }
    }

    public static function delete(int $id): void
    {
        $slide = self::find($id);
        if (!$slide) {
            return;
        }
        $dir = BASE_PATH . '/storage/uploads/hero';
        foreach (['desktop_image', 'mobile_image'] as $col) {
            if (!empty($slide[$col])) {
                $path = realpath($dir . '/' . $slide[$col]);
                if ($path !== false && strpos($path, realpath($dir) . DIRECTORY_SEPARATOR) === 0 && is_file($path)) {
                    @unlink($path);
                }
            }
        }
        Database::delete('hero_slides', 'id = ?', [$id]);
    }

    public static function setActive(int $id, bool $active): void
    {
        Database::update('hero_slides', ['is_active' => $active ? 1 : 0, 'updated_at' => Database::now()], 'id = :id', ['id' => $id]);
    }

    public static function reorder(array $ids): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($ids as $order => $id) {
                Database::update('hero_slides', ['display_order' => $order, 'updated_at' => Database::now()], 'id = :id', ['id' => (int) $id]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function resolveCta(array $slide): array
    {
        $type = $slide['cta_type'] ?? 'shop';
        $target = $slide['cta_target'] ?? '';
        $pid = (int) ($slide['product_id'] ?? 0);
        $cid = (int) ($slide['category_id'] ?? 0);
        switch ($type) {
            case 'product':
                $id = $pid > 0 ? $pid : (is_numeric($target) ? (int) $target : 0);
                return [
                    'url'   => $id > 0 ? url('shop/product/' . $id) : url('shop/products'),
                    'label' => $slide['cta_text'] ?? 'Shop now',
                ];
            case 'category':
                $id = $cid > 0 ? $cid : (is_numeric($target) ? (int) $target : 0);
                $slug = $id > 0 ? Database::fetchValue('SELECT slug FROM categories WHERE id = ?', [$id]) : null;
                return [
                    'url'   => $slug ? url('shop/products?category=' . urlencode((string) $slug)) : url('shop/products'),
                    'label' => $slide['cta_text'] ?? 'Explore',
                ];
            case 'shop':
                return ['url' => url('shop/products'), 'label' => $slide['cta_text'] ?? 'Shop now'];
            case 'offers':
                return ['url' => url('shop/products'), 'label' => $slide['cta_text'] ?? 'View offers'];
            default:
                return ['url' => url('shop'), 'label' => $slide['cta_text'] ?? 'Shop now'];
        }
    }

    public static function resolveSecondaryCta(array $slide): array
    {
        $text = trim((string) ($slide['secondary_cta_text'] ?? ''));
        $type = (string) ($slide['secondary_cta_type'] ?? '');
        if ($text === '' || !in_array($type, ['product', 'category', 'shop', 'offers'], true)) {
            return [];
        }
        $target = trim((string) ($slide['secondary_cta_target'] ?? ''));
        $pid = $type === 'product' && is_numeric($target) ? (int) $target : 0;
        $cid = $type === 'category' && is_numeric($target) ? (int) $target : 0;
        return self::resolveCta([
            'cta_type'    => $type,
            'cta_target'  => $target,
            'cta_text'    => $text,
            'product_id'  => $pid,
            'category_id' => $cid,
        ]);
    }

    public static function fromPrice(array $slide): ?string
    {
        $price = null;
        if (!empty($slide['product_price']) && (float) $slide['product_price'] > 0) {
            $price = (float) $slide['product_price'];
        } elseif (!empty($slide['product_id'])) {
            $row = Database::fetchValue(
                'SELECT sell_price FROM products WHERE id = ? AND is_active = 1',
                [(int) $slide['product_id']]
            );
            if ($row !== null && (float) $row > 0) {
                $price = (float) $row;
            }
        }
        return $price !== null ? money(gross_of($price)) : null;
    }

    public static function imageDir(): string
    {
        $dir = BASE_PATH . '/storage/uploads/hero';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
