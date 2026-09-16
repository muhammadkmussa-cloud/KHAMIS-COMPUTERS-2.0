<?php
declare(strict_types=1);

/**
 * Staff management (admins only — enforced by the router's admin gate).
 */
class StaffController
{
    public function index(): void
    {
        Auth::requireLogin();
        $users = Database::fetchAll(
            'SELECT id, name, email, role, is_active, last_login_at, created_at FROM users ORDER BY id'
        );
        View::render('staff/index', [
            'title' => 'Staff',
            'users' => $users,
            'activity' => Activity::recent(10),
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $pass  = (string) ($_POST['password'] ?? '');
        $role  = ($_POST['role'] ?? 'cashier') === 'admin' ? 'admin' : 'cashier';

        $errors = [];
        if ($name === '' || mb_strlen($name) > 190) {
            $errors[] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email.';
        }
        if (strlen($pass) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (Database::fetchValue('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
            $errors[] = 'That email is already registered.';
        }

        if ($errors) {
            flash('error', implode(' ', $errors));
            set_old(['name' => $name, 'email' => $email]);
            redirect('staff');
        }

        Database::insert('users', [
            'name'          => $name,
            'email'         => $email,
            'password_hash' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => (int) config('security.bcrypt_rounds', 12)]),
            'role'          => $role,
            'is_active'     => 1,
            'created_at'    => Database::now(),
            'updated_at'    => Database::now(),
        ]);

        Activity::log('staff.created', $email . ' as ' . $role);
        flash('success', 'Staff member added.');
        redirect('staff');
    }

    public function update(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $u = Database::fetch('SELECT * FROM users WHERE id = ?', [$id]);
        if (!$u) {
            flash('error', 'User not found.');
            redirect('staff');
        }

        $name   = trim((string) ($_POST['name'] ?? $u['name']));
        $role   = ($_POST['role'] ?? $u['role']) === 'admin' ? 'admin' : 'cashier';
        // Role-save forms do not include the status checkbox. Preserve the
        // current state unless the dedicated status form explicitly posts it.
        $active = self::activeValue($u, $_POST);

        if ($name === '' || mb_strlen($name) > 190) {
            flash('error', 'Name is required and must be 190 characters or fewer.');
            redirect('staff');
        }
        if ($id === Auth::id()) {
            if ($role !== 'admin') {
                flash('error', 'You cannot demote yourself.');
                redirect('staff');
            }
            if (!$active) {
                flash('error', 'You cannot deactivate yourself.');
                redirect('staff');
            }
        }
        if ($u['role'] === 'admin' && $role !== 'admin' && self::adminCount() <= 1) {
            flash('error', 'You cannot remove the last admin.');
            redirect('staff');
        }
        if ($u['role'] === 'admin' && !$active && $id !== Auth::id() && self::adminCount() <= 1) {
            flash('error', 'You cannot deactivate the last admin.');
            redirect('staff');
        }

        Database::update('users', [
            'name'       => $name,
            'role'       => $role,
            'is_active'  => $active,
            'updated_at' => Database::now(),
        ], 'id = :id', ['id' => $id]);

        Activity::log('staff.updated', 'user #' . $id . ' role=' . $role . ' active=' . ($active ? '1' : '0'));
        flash('success', 'Staff member updated.');
        redirect('staff');
    }

    public function password(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $pass = (string) ($_POST['password'] ?? '');
        if (!Database::fetchValue('SELECT COUNT(*) FROM users WHERE id = ?', [$id])) {
            flash('error', 'Staff member not found.');
            redirect('staff');
        }
        if (strlen($pass) < 8) {
            flash('error', 'Password must be at least 8 characters.');
            redirect('staff');
        }
        Database::update('users', [
            'password_hash' => password_hash($pass, PASSWORD_BCRYPT, ['cost' => (int) config('security.bcrypt_rounds', 12)]),
            'updated_at'    => Database::now(),
        ], 'id = :id', ['id' => $id]);

        Activity::log('staff.password_reset', 'user #' . $id);
        flash('success', 'Password updated.');
        redirect('staff');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        if ($id === Auth::id()) {
            flash('error', 'You cannot delete your own account.');
            redirect('staff');
        }
        $u = Database::fetch('SELECT role FROM users WHERE id = ?', [$id]);
        if ($u && $u['role'] === 'admin' && self::adminCount() <= 1) {
            flash('error', 'You cannot delete the last admin.');
            redirect('staff');
        }

        if (!$u) {
            flash('error', 'Staff member not found.');
            redirect('staff');
        }
        Database::delete('users', 'id = ?', [$id]);
        Activity::log('staff.deleted', 'user #' . $id);
        flash('success', 'Staff member deleted.');
        redirect('staff');
    }

    private static function adminCount(): int
    {
        return (int) Database::fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    }

    public static function activeValue(array $user, array $posted): int
    {
        return array_key_exists('is_active', $posted)
            ? ((int) $posted['is_active'] === 1 ? 1 : 0)
            : (int) ($user['is_active'] ?? 0);
    }
}
