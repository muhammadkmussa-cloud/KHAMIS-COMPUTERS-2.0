<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? config('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="layout-app">
<header class="global-nav">
    <div class="nav-inner">
        <a class="nav-brand" href="<?= e(url('dashboard')) ?>">
            <?= brand_mark(24) ?>
            <?= e(config('app.name')) ?>
        </a>
        <?php include APP_PATH . '/views/partials/topnav.php'; ?>
        <div class="nav-actions">
            <?php if (Auth::check()): $u = Auth::user(); ?>
                <a class="btn btn-primary btn-sm" href="<?= e(url('shop')) ?>" target="_blank" rel="noopener">Shop ↗</a>
                <span class="nav-user">
                    <span class="avatar"><?= e(strtoupper(substr($u['name'] ?? 'U', 0, 1))) ?></span>
                    <span class="name"><?= e(explode(' ', $u['name'])[0]) ?>
                        <span class="badge badge-<?= $u['role'] === 'admin' ? 'blue' : 'gray' ?>"><?= e($u['role']) ?></span>
                    </span>
                </span>
                <form method="post" action="<?= e(url('logout')) ?>" style="display:inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="nav-signout">Sign out</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="content<?= !empty($wide) ? ' wide' : '' ?>">
    <?php include APP_PATH . '/views/partials/flash.php'; ?>
    <?= $content ?>
</main>

<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
