<?php ?>
<div class="page-head">
    <div>
        <h1>Staff</h1>
        <p class="lede">Admins and cashiers who can sign in to the system.</p>
    </div>
</div>

<div class="grid-2" style="margin-top:0;align-items:start">
    <div class="card">
        <h3>Add staff member</h3>
        <form method="post" action="<?= e(url('staff')) ?>" class="form" id="add-staff-form">
            <?= csrf_field() ?>
            <div class="field">
                <label for="staff-name">Full name</label>
                <input type="text" id="staff-name" name="name" value="<?= old('name') ?>" required placeholder="e.g. Jane Wanjiku">
            </div>
            <div class="field">
                <label for="staff-email">Email</label>
                <input type="email" id="staff-email" name="email" value="<?= old('email') ?>" required placeholder="jane@khamiscomputers.com">
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="staff-role">Role</label>
                    <select id="staff-role" name="role" aria-label="New staff role">
                        <option value="cashier">Cashier — POS, inventory, sales</option>
                        <option value="admin">Admin — everything + settings &amp; staff</option>
                    </select>
                </div>
                <div class="field">
                    <label for="staff-password">Password (min 8 chars)</label>
                    <input type="password" id="staff-password" name="password" required minlength="8" aria-label="New staff password">
                </div>
            </div>
            <div><button class="btn btn-primary" type="submit">Add staff member</button></div>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table mobile-cards staff-table">
            <thead>
                <tr><th>Staff member</th><th>Role</th><th>Status</th><th>Last login</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (empty($users)): ?>
                <tr><td colspan="5"><div class="empty-state"><h2>No staff members yet</h2><p class="muted">Add your first team member to get started.</p></div></td></tr>
            <?php else: ?>
            <?php foreach ($users as $u): $self = (int) $u['id'] === Auth::id(); ?>
                <tr>
                    <td data-label="Staff member">
                        <div class="cell-main"><?= e($u['name']) ?><?= $self ? ' <span class="badge badge-blue">you</span>' : '' ?></div>
                        <div class="cell-sub"><?= e($u['email']) ?></div>
                    </td>
                    <td data-label="Role">
                        <form method="post" action="<?= e(url('staff/' . $u['id'])) ?>" class="staff-role-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="name" value="<?= e($u['name']) ?>">
                            <input type="hidden" name="is_active" value="<?= (int) $u['is_active'] ?>">
                            <label for="staff-role-<?= (int) $u['id'] ?>" class="sr-only">Role for <?= e($u['name']) ?></label>
                            <select id="staff-role-<?= (int) $u['id'] ?>" name="role" <?= $self ? 'disabled' : '' ?> onchange="this.closest('form').submit()">
                                <option value="cashier" <?= $u['role'] === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                        </form>
                    </td>
                    <td data-label="Status">
                        <div class="staff-status">
                            <span class="<?= (int) $u['is_active'] === 1 ? 'badge badge-green' : 'badge badge-gray' ?>"><?= (int) $u['is_active'] === 1 ? 'Active' : 'Inactive' ?></span>
                            <?php if (!$self): ?>
                                <button class="btn btn-ghost btn-sm staff-status-toggle" type="button" aria-label="Toggle status for <?= e($u['name']) ?>">
                                    <?= (int) $u['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <form method="post" action="<?= e(url('staff/' . $u['id'])) ?>" class="staff-status-form" style="display:none">
                            <?= csrf_field() ?>
                            <input type="hidden" name="name" value="<?= e($u['name']) ?>">
                            <input type="hidden" name="role" value="<?= e($u['role']) ?>">
                            <input type="hidden" name="is_active" value="<?= (int) $u['is_active'] === 1 ? '0' : '1' ?>">
                            <button class="btn btn-outline btn-sm" type="submit">Confirm</button>
                            <button class="btn btn-ghost btn-sm staff-cancel-status" type="button">Cancel</button>
                        </form>
                    </td>
                    <td class="cell-sub" data-label="Last login"><?= $u['last_login_at'] ? e(date('d M Y', strtotime($u['last_login_at']))) : 'Never' ?></td>
                    <td data-label="Actions">
                        <div class="row-actions staff-row-actions">
                            <form method="post" action="<?= e(url('staff/' . $u['id'] . '/password')) ?>" class="staff-password-form" style="display:flex;gap:6px;align-items:center">
                                <?= csrf_field() ?>
                                <label for="staff-pass-<?= (int) $u['id'] ?>" class="sr-only">New password for <?= e($u['name']) ?></label>
                                <input type="password" id="staff-pass-<?= (int) $u['id'] ?>" name="password" minlength="8" placeholder="New password" aria-label="New password for <?= e($u['name']) ?>">
                                <button class="btn btn-outline btn-sm" type="submit">Reset</button>
                            </form>
                            <?php if (!$self): ?>
                                <form method="post" action="<?= e(url('staff/' . $u['id'] . '/delete')) ?>" class="staff-delete-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="staff_name" value="<?= e($u['name']) ?>">
                                    <button class="btn btn-danger-ghost btn-sm" type="submit" data-confirm="Delete <?= e($u['name']) ?>? This action cannot be undone." data-confirm-action="Delete" data-confirm-tone="danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($activity)): ?>
<div class="card" style="margin-top:18px">
    <h3>Audit trail</h3>
    <div class="dashboard-activity" style="padding:8px 0">
        <?php foreach ($activity as $event): ?>
            <div style="padding:6px 0;display:flex;gap:9px;align-items:flex-start">
                <span style="display:grid;place-items:center;width:22px;height:22px;border-radius:50%;background:var(--surface-2);color:var(--text-tertiary);font-size:10px;font-weight:700;flex:0 0 auto"><?= e(substr($event['action'], 0, 1)) ?></span>
                <p style="margin:0"><b><?= e(ucwords(str_replace(['.','_'],' ', $event['action']))) ?></b> <small><?= e($event['user_name']?:'System') ?> · <?= e(date('d M, h:i A', strtotime($event['created_at']))) ?></small></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
(function() {
    document.querySelectorAll('.staff-status-toggle').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var form = this.closest('td').querySelector('.staff-status-form');
            if (form) form.style.display = form.style.display === 'none' ? '' : 'none';
        });
    });
    document.querySelectorAll('.staff-cancel-status').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var form = this.closest('.staff-status-form');
            if (form) form.style.display = 'none';
        });
    });
})();
</script>