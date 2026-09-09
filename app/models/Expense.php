<?php
declare(strict_types=1);

class Expense
{
    public static function all(string $from = '', string $to = ''): array
    {
        $where  = [];
        $params = [];
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'DATE(expense_date) >= :from';
            $params['from'] = $from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'DATE(expense_date) <= :to';
            $params['to'] = $to;
        }
        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        return Database::fetchAll(
            "SELECT e.*, u.name AS user_name
               FROM expenses e LEFT JOIN users u ON u.id = e.user_id
               {$whereSql}
              ORDER BY e.expense_date DESC, e.id DESC",
            $params
        );
    }

    public static function create(string $description, float $amount, string $category, string $date, ?int $userId): int
    {
        return Database::insert('expenses', [
            'description'  => trim($description),
            'amount'       => round($amount, 2),
            'category'     => $category !== '' ? $category : 'general',
            'expense_date' => $date,
            'user_id'      => $userId,
            'created_at'   => Database::now(),
        ]);
    }

    public static function delete(int $id): void
    {
        Database::delete('expenses', 'id = ?', [$id]);
    }

    public static function sumRange(string $from, string $to): float
    {
        return (float) Database::fetchValue(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE DATE(expense_date) >= ? AND DATE(expense_date) <= ?',
            [$from, $to]
        );
    }

    public static function categories(): array
    {
        return ['rent', 'salaries', 'utilities', 'supplies', 'transport', 'marketing', 'repairs', 'general'];
    }
}
