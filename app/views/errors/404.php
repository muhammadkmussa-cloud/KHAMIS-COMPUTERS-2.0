<div class="empty-state">
    <div class="empty-code">404</div>
    <h2>Page not found</h2>
    <p class="muted">The page you're looking for doesn't exist or has been moved.</p>
    <div style="margin-top:24px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a class="btn btn-primary" href="<?= e(url(Auth::check() ? 'dashboard' : 'shop')) ?>">
            <?= Auth::check() ? 'Back to dashboard' : 'Back to shop' ?>
        </a>
        <a class="btn btn-outline" href="<?= e(url('settings')) ?>">Settings</a>
    </div>
    <p class="muted" style="margin-top:16px;font-size:12px">If you typed a URL, double-check the address or try searching the menu above.</p>
</div>
