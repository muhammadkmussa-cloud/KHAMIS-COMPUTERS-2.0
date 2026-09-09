<?php
declare(strict_types=1);

/**
 * Staff authentication (admins & cashiers). Customers buy as guests online,
 * so there are no customer accounts — only staff log in to the system.
 */
class Auth
{
    private static ?array $user = null;

    public static function attempt(string $email, string $password): bool
    {
        $email = strtolower(trim($email));
        $user  = Database::fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);

        if (!$user || (int) $user['is_active'] !== 1 || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Rehash if the configured cost has changed since the password was stored.
        if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => (int) config('security.bcrypt_rounds', 12)])) {
            $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => (int) config('security.bcrypt_rounds', 12)]);
            Database::update('users', ['password_hash' => $newHash], 'id = :id', ['id' => $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id']   = (int) $user['id'];
        $_SESSION['user_role'] = $user['role'];
        self::$user = null;

        Database::update('users', ['last_login_at' => Database::now()], 'id = :id', ['id' => $user['id']]);
        return true;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        return $_SESSION['user_role'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$user === null) {
            self::$user = Database::fetch(
                'SELECT id, name, email, role, is_active, last_login_at, created_at FROM users WHERE id = ?',
                [self::id()]
            );
        }
        return self::$user;
    }

    /** Redirect to the login page unless a staff member is signed in. */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('error', 'Please sign in to continue.');
            redirect('login');
        }
    }

    /** Redirect to the dashboard unless the signed-in user is an admin. */
    public static function requireAdmin(): void
    {
        if (!self::isAdmin()) {
            flash('error', 'You do not have permission to access that page.');
            redirect('dashboard');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
