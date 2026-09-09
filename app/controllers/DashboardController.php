<?php
declare(strict_types=1);

class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();

        $driver   = Database::driver();
        $today    = Database::today();

        $count = fn (string $sql, array $p = []) => (int) Database::fetchValue($sql, $p);

        // Stock value: serialized products value their in_stock units; the rest
        // value their running stock-movement quantity.
        $serializedValue = (float) Database::fetchValue(
            "SELECT COALESCE(SUM(p.cost_price), 0)
               FROM products p
               JOIN inventory_units u ON u.product_id = p.id AND u.status = 'in_stock'"
        );
        $bulkValue = (float) Database::fetchValue(
            "SELECT COALESCE(SUM(p.cost_price * q.qty), 0) FROM (
                 SELECT product_id, COALESCE(SUM(quantity), 0) AS qty
                 FROM stock_movements GROUP BY product_id
             ) q JOIN products p ON p.id = q.product_id
             WHERE p.is_serialized = 0 AND q.qty > 0"
        );

        $stats = [
            'products'        => $count('SELECT COUNT(*) FROM products WHERE is_active = 1'),
            'stock_value'     => round($serializedValue + $bulkValue, 2),
            'sales_today'     => $count('SELECT COUNT(*) FROM sales WHERE DATE(created_at) = ?', [$today]),
            'revenue_today'   => (float) Database::fetchValue("SELECT COALESCE(SUM(total),0) FROM sales WHERE DATE(created_at) = ? AND status = 'completed'", [$today]),
            'units_stock'     => $count("SELECT COUNT(*) FROM inventory_units WHERE status = 'in_stock'"),
            'pending_returns' => $count("SELECT COUNT(*) FROM returns WHERE status = 'pending'"),
        ];

        View::render('dashboard/home', [
            'title'   => 'Dashboard',
            'stats'   => $stats,
            'driver'  => $driver,
            'today'   => $today,
            'recent'  => Database::fetchAll(
                "SELECT s.id, s.sale_number, s.channel, s.customer_name, s.total, s.status, s.created_at,
                        (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
                   FROM sales s ORDER BY s.id DESC LIMIT 6"
            ),
            'lowStock' => Product::lowStock(),
        ]);
    }
}
