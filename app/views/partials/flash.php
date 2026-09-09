<?php if (flash_has('success')): ?>
    <div class="alert alert-success"><?= e((string) flash('success')) ?></div>
<?php endif; ?>
<?php if (flash_has('error')): ?>
    <div class="alert alert-error"><?= e((string) flash('error')) ?></div>
<?php endif; ?>
<?php if (flash_has('info')): ?>
    <div class="alert alert-info"><?= e((string) flash('info')) ?></div>
<?php endif; ?>
