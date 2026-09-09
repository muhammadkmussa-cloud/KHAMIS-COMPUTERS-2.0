<?php
declare(strict_types=1);

/**
 * Thin PDO wrapper supporting both MySQL (cPanel production) and SQLite
 * (quick local/preview testing). The rest of the app only talks to this class,
 * so switching drivers is a one-line config change.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function driver(): string
    {
        return (string) config('db.driver', 'mysql');
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        if (self::driver() === 'sqlite') {
            $path = (string) config('db.sqlite_path');
            $dir  = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            self::$pdo = new PDO('sqlite:' . $path, null, null, $options);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        } else {
            $m = config('db.mysql');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $m['host'],
                $m['port'],
                $m['database'],
                $m['charset'] ?? 'utf8mb4'
            );
            self::$pdo = new PDO($dsn, $m['username'], $m['password'], $options);
        }

        return self::$pdo;
    }

    /** Run a prepared statement. */
    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchValue(string $sql, array $params = [])
    {
        $value = self::run($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql  = 'INSERT INTO ' . $table
              . ' (' . implode(', ', $cols) . ')'
              . ' VALUES (:' . implode(', :', $cols) . ')';
        self::run($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets   = [];
        $params = [];
        foreach ($data as $col => $val) {
            $sets[]             = $col . ' = :set_' . $col;
            $params['set_' . $col] = $val;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        return self::run($sql, array_merge($params, $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $whereParams = []): int
    {
        return self::run('DELETE FROM ' . $table . ' WHERE ' . $where, $whereParams)->rowCount();
    }

    /** Current timestamp string, portable across MySQL and SQLite. */
    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function today(): string
    {
        return date('Y-m-d');
    }

    /** True if an exception is a duplicate-key violation (portable MySQL/SQLite). */
    public static function isDuplicateKey(Throwable $e): bool
    {
        $m = $e->getMessage();
        return str_contains($m, 'UNIQUE')
            || str_contains($m, 'Duplicate entry')
            || str_contains($m, 'duplicate key');
    }
}
