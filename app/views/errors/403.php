<div class="empty-state">
    <div class="empty-code" style="color:var(--orange)">403</div>
    <h2>Access denied</h2>
    <p class="muted">You don't have permission to view this page. Contact an administrator if you believe this is a mistake.</p>
    <div style="margin-top:24px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
        <a class="btn btn-primary" href="<?= e(url(Auth::check() ? 'dashboard' : 'shop')) ?>">Go to dashboard</a>
        <a class="btn btn-outline" href="<?= e(url('settings')) ?>">Settings</a>
    </div>
</div>