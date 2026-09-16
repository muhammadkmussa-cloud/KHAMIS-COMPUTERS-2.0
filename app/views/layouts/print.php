<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($title ?? 'Print') ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(url('assets/css/print.css')) ?>">
</head>
<body class="printing">
<?= $content ?>
</body>
</html>