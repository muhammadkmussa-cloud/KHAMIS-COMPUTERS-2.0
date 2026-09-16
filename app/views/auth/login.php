<h2 class="form-title">Staff sign in</h2>
<p class="form-subtitle">Access the POS and admin console.</p>

<form method="post" action="<?= e(url('login')) ?>" class="form" autocomplete="on">
    <?= csrf_field() ?>
    <label class="field">
        <span>Email</span>
        <input type="email" name="email" value="<?= old('email') ?>" required autofocus placeholder="you@khamiscomputers.com" autocomplete="username">
    </label>
    <label class="field">
        <span>Password</span>
        <input type="password" name="password" required placeholder="••••••••" autocomplete="current-password">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
</form>

<?php if (config('app.env') !== 'production'): ?>
<p class="auth-hint">Demo account: <code>admin@khamis.local</code> / <code>admin1234</code></p>
<?php endif; ?>
