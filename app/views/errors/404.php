<div class="empty-state">
    <div class="empty-code">404</div>
    <h2>Page not found</h2>
    <p class="muted">The page you're looking for doesn't exist.</p>
    <a class="btn btn-primary" href="<?= e(url(Auth::check() ? 'dashboard' : 'shop')) ?>">
        <?= Auth::check() ? 'Back to dashboard' : 'Back to shop' ?>
    </a>
</div>
