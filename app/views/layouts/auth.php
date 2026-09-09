<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? config('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
</head>
<body class="layout-auth">
<div class="auth-wrap">
    <div class="auth-card">
        <div class="auth-brand">
            <?= brand_mark(44) ?>
            <h1><?= e(config('app.name')) ?></h1>
            <p><?= e(config('app.tagline')) ?></p>
        </div>
        <?php include APP_PATH . '/views/partials/flash.php'; ?>
        <?= $content ?>
    </div>
</div>
</body>
</html>
