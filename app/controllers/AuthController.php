<?php
declare(strict_types=1);

class AuthController
{
    private const LOGIN_MAX_ATTEMPTS   = 5;
    private const LOGIN_LOCK_SECONDS   = 900; // 15 minutes

    public function showLogin(): void
    {
        if (Auth::check()) {
            redirect('dashboard');
        }
        View::render('auth/login', ['title' => 'Sign in — ' . config('app.name')], 'auth');
    }

    public function login(): void
    {
        Csrf::checkOrFail();

        $email    = trim($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        if ($email === '' || $password === '') {
            flash('error', 'Please enter your email and password.');
            set_old(['email' => $email]);
            redirect('login');
        }

        // Brute-force protection: per email+IP lockout after N failures.
        $key = Throttle::loginKey($email);
        if (Throttle::tooManyAttempts($key, self::LOGIN_MAX_ATTEMPTS, self::LOGIN_LOCK_SECONDS)) {
            Activity::log('auth.login_locked', $email);
            flash('error', 'Too many failed attempts. Please wait 15 minutes and try again.');
            set_old(['email' => $email]);
            redirect('login');
        }

        if (Auth::attempt($email, $password)) {
            Throttle::clear($key);
            Activity::log('auth.login', $email);
            flash('success', 'Welcome back, ' . (Auth::user()['name'] ?? '') . '!');
            redirect('dashboard');
        }

        Throttle::hit($key, self::LOGIN_MAX_ATTEMPTS, self::LOGIN_LOCK_SECONDS);
        Activity::log('auth.login_failed', $email);
        flash('error', 'Invalid email or password.');
        set_old(['email' => $email]);
        redirect('login');
    }

    public function logout(): void
    {
        // POST + CSRF only (see routes) — a GET logout is CSRF-abusable.
        Csrf::checkOrFail();
        Activity::log('auth.logout');
        Auth::logout();
        flash('success', 'You have been signed out.');
        redirect('login');
    }
}
