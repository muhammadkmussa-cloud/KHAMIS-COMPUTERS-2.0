<?php
declare(strict_types=1);

/**
 * CSRF token generation and validation.
 */
class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(?string $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals(self::token(), $token);
    }

    /** Validate the token on a POST request, or abort with 419. */
    public static function checkOrFail(): void
    {
        $token = $_POST['csrf_token'] ?? '';
        if ($token === '') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        if (!self::validate($token)) {
            http_response_code(419);
            exit('Invalid or expired form token. Please go back and try again.');
        }
    }
}
