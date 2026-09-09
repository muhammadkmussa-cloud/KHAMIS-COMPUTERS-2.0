<div class="page-head">
    <div>
        <h1><?= e($feature ?? $title) ?></h1>
        <p class="lede">This module is next on the build roadmap.</p>
    </div>
</div>

<div class="card" style="max-width:640px">
    <div class="section-kicker">Coming soon</div>
    <h3><?= e($feature ?? $title) ?> is being built</h3>
    <p class="sub">We're building Khamis Computers piece by piece. The modules are
       released in this order, so this screen will become a full working module
       shortly.</p>
    <ul class="checklist">
        <li class="done"><span>Foundation</span><em>done</em></li>
        <li class="done"><span>Inventory</span><em>done</em></li>
        <li class="done"><span>POS terminal</span><em>done</em></li>
        <li class="done"><span>Online shop</span><em>done</em></li>
        <li class="done"><span>Offline sync</span><em>done</em></li>
        <li class="done"><span>Orders &amp; reports</span><em>done</em></li>
        <li class="done"><span>Settings &amp; polish</span><em>done</em></li>
    </ul>
    <p class="sub" style="margin-top:12px">The full system is complete 🎉</p>
    <p style="margin-top:16px"><a class="btn btn-primary" href="<?= e(url('dashboard')) ?>">Back to dashboard</a></p>
</div>
