<?php if (flash_has('success')): ?>
    <div class="alert alert-success" role="status"><?= e((string) flash('success')) ?></div>
<?php endif; ?>
<?php if (flash_has('error')): ?>
    <div class="alert alert-error" role="alert"><?= e((string) flash('error')) ?></div>
<?php endif; ?>
<?php if (flash_has('info')): ?>
    <div class="alert alert-info" role="status"><?= e((string) flash('info')) ?></div>
<?php endif; ?>
