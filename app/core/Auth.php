<?php
declare(strict_types=1);

/**
 * Staff authentication (admins & cashiers). Customers buy as guests online,
 * so there are no customer accounts — only staff log in to the system.
 */
class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

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
        $_SESSION['user_id'] = (int) $user['id'];
        self::$user = null;
        self::$loaded = false;

        Database::update('users', ['last_login_at' => Database::now()], 'id = :id', ['id' => $user['id']]);
        return true;
    }

    /**
     * True when a staff member is signed in and their account is still active.
     * The role/active state is re-read from the database each request, so a
     * deactivated, deleted or demoted account loses access immediately rather
     * than at the end of the session lifetime.
     */
    public static function check(): bool
    {
        return self::currentActive() !== null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * The current role, always read from the database (never a login-time
     * snapshot), so a promotion/demotion applies on the next request.
     */
    public static function role(): ?string
    {
        $user = self::currentActive();
        return $user !== null ? (string) $user['role'] : null;
    }

    public static function isAdmin(): bool
    {
        return self::role() === 'admin';
    }

    public static function user(): ?array
    {
        return self::currentActive();
    }

    /**
     * The signed-in user's DB row, cached once per request — or null when no
     * staff member is signed in, the account is missing, or it was deactivated
     * (in which case the session is ended so the change takes effect at once).
     */
    private static function currentActive(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $user = self::load();
        if ($user === null || (int) $user['is_active'] !== 1) {
            // Account was deactivated or deleted while signed in — end the
            // session now so the change takes effect right away.
            unset($_SESSION['user_id'], $_SESSION['user_role']);
            self::$user = null;
            self::$loaded = false;
            return null;
        }
        return $user;
    }

    /** Load (and cache for the request) the signed-in user's DB row. */
    private static function load(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }
        if (self::$user === null && !self::$loaded) {
            self::$user   = Database::fetch(
                'SELECT id, name, email, role, is_active, last_login_at, created_at FROM users WHERE id = ?',
                [$id]
            );
            self::$loaded = true;
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

    /** Redirect to the dashboard unless the signed-in user is an active admin. */
    public static function requireAdmin(): void
    {
        if (!self::check() || !self::isAdmin()) {
            flash('error', 'You do not have permission to access that page.');
            redirect('dashboard');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        self::$user = null;
        self::$loaded = false;
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
