<h2 class="form-title">Install <?= e(config('app.name')) ?></h2>
<p class="form-subtitle">First-run setup — create the database tables and your admin account.</p>

<?php foreach ($errors as $err): ?>
    <div class="alert alert-error"><?= e($err) ?></div>
<?php endforeach; ?>

<?php if (!db_ready()): ?>
    <div class="alert alert-error">
        Could not connect to the database. Check your configuration
        (driver: <strong><?= e($driver) ?></strong>).
    </div>
<?php endif; ?>

<form method="post" action="<?= e(url('install')) ?>" class="form">
    <?= csrf_field() ?>
    <label class="field">
        <span>Your name</span>
        <input type="text" name="name" required placeholder="Khamis Admin">
    </label>
    <label class="field">
        <span>Admin email</span>
        <input type="email" name="email" required placeholder="admin@khamiscomputers.com">
    </label>
    <label class="field">
        <span>Password (min 8 characters)</span>
        <input type="password" name="password" required minlength="8" placeholder="••••••••">
    </label>
    <button class="btn btn-primary btn-block" type="submit">Install system</button>
</form>
