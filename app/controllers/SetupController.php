<?php
declare(strict_types=1);

/**
 * Web installer — first-run setup. Creates the schema and the default admin.
 * IMPORTANT: delete this controller + its routes after first deploy, or the
 * /install route stays publicly accessible.
 */
class SetupController
{
    public function form(): void
    {
        if (Schema::isInstalled()) {
            flash('info', 'The system is already installed.');
            redirect('login');
        }

        $errors = (array) flash('install_errors');
        $driver = Database::driver();

        View::render('setup/install', [
            'title'  => 'Install ' . config('app.name'),
            'driver' => $driver,
            'errors' => $errors,
        ], 'auth');
    }

    public function run(): void
    {
        // CRITICAL: once installed, the installer must never run again —
        // otherwise anyone could POST here and mint a new admin account.
        if (Schema::isInstalled()) {
            http_response_code(404);
            View::render('errors/404', ['title' => 'Page not found'], 'auth');
            return;
        }

        Csrf::checkOrFail();

        $errors = [];

        $name  = trim($_POST['name'] ?? '');
        $email = strtolower(trim($_POST['email'] ?? ''));
        $pass  = (string) ($_POST['password'] ?? '');

        if ($name === '')                        { $errors[] = 'Please enter your name.'; }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid email.'; }
        if (strlen($pass) < 8)                   { $errors[] = 'Password must be at least 8 characters.'; }

        if ($errors) {
            flash('install_errors', $errors);
            redirect('install');
        }

        try {
            Schema::apply();
            Schema::migrateColumns();
            Schema::createIndexes();
            Schema::seedBaseline();
            Schema::seedDeliveryZones();
            Schema::seedHeroSlides();
            Database::insert('users', [
                'name'          => $name,
                'email'         => $email,
                'password_hash' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => (int) config('security.bcrypt_rounds', 12)]),
                'role'          => 'admin',
                'is_active'     => 1,
                'created_at'    => Database::now(),
                'updated_at'    => Database::now(),
            ]);
        } catch (Throwable $e) {
            flash('install_errors', ['Database error: ' . $e->getMessage()]);
            redirect('install');
        }

        flash('success', 'Installation complete. Sign in with your new admin account.');
        Activity::log('system.installed', $email);
        redirect('login');
    }
}
