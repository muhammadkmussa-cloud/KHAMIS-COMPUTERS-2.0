<div class="page-head">
    <div>
        <h1>Staff</h1>
        <p class="lede">Admins and cashiers who can sign in to the system.</p>
    </div>
</div>

<div class="grid-2" style="margin-top:0;align-items:start">
    <div class="card">
        <h3>Add staff member</h3>
        <form method="post" action="<?= e(url('staff')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <span>Full name</span>
                <input type="text" name="name" value="<?= old('name') ?>" required placeholder="e.g. Jane Wanjiku" aria-label="Full name">
            </div>
            <div class="field">
                <span>Email</span>
                <input type="email" name="email" value="<?= old('email') ?>" required placeholder="jane@khamiscomputers.com" aria-label="Email">
            </div>
            <div class="form-row">
                <div class="field">
                    <span>Role</span>
                        <select name="role" aria-label="New staff role">
                        <option value="cashier">Cashier — POS, inventory, sales</option>
                        <option value="admin">Admin — everything + settings &amp; staff</option>
                    </select>
                </div>
                <div class="field">
                    <span>Password (min 8 chars)</span>
                    <input type="password" name="password" required minlength="8" aria-label="New staff password">
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
            <?php foreach ($users as $u): $self = (int) $u['id'] === Auth::id(); ?>
                <tr>
                    <td data-label="Staff member">
                        <div class="cell-main"><?= e($u['name']) ?><?= $self ? ' <span class="badge badge-blue">you</span>' : '' ?></div>
                        <div class="cell-sub"><?= e($u['email']) ?></div>
                    </td>
                    <td data-label="Role">
                        <form method="post" action="<?= e(url('staff/' . $u['id'])) ?>" style="display:flex;gap:6px;align-items:center">
                            <?= csrf_field() ?>
                            <input type="hidden" name="is_active" value="<?= (int) $u['is_active'] ?>">
                            <select name="role" aria-label="Role for <?= e($u['name']) ?>" <?= $self ? 'disabled' : '' ?>>
                                <option value="cashier" <?= $u['role'] === 'cashier' ? 'selected' : '' ?>>Cashier</option>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?php if (!$self): ?>
                                <button class="btn btn-outline btn-sm" type="submit">Save</button>
                            <?php endif; ?>
                        </form>
                    </td>
                    <td data-label="Status">
                        <form method="post" action="<?= e(url('staff/' . $u['id'])) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="name" value="<?= e($u['name']) ?>">
                            <input type="hidden" name="role" value="<?= e($u['role']) ?>">
                            <input type="hidden" name="is_active" value="0">
                            <label class="field field-check" style="gap:6px">
                                <input type="checkbox" name="is_active" value="1" aria-label="Active status for <?= e($u['name']) ?>" <?= (int) $u['is_active'] === 1 ? 'checked' : '' ?> <?= $self ? 'disabled' : '' ?> onchange="this.form.submit()">
                                <span>Active</span>
                            </label>
                        </form>
                    </td>
                    <td class="cell-sub" data-label="Last login"><?= $u['last_login_at'] ? e(date('d M Y', strtotime($u['last_login_at']))) : 'Never' ?></td>
                    <td data-label="Actions">
                        <div class="row-actions staff-row-actions">
                            <form method="post" action="<?= e(url('staff/' . $u['id'] . '/password')) ?>" style="display:flex;gap:6px">
                                <?= csrf_field() ?>
                                <input type="password" name="password" minlength="8" placeholder="new password" aria-label="New password for <?= e($u['name']) ?>" style="width:120px">
                                <button class="btn btn-outline btn-sm" type="submit">Reset</button>
                            </form>
                            <?php if (!$self): ?>
                                <form method="post" action="<?= e(url('staff/' . $u['id'] . '/delete')) ?>" data-confirm="Delete <?= e($u['name']) ?>?">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger-ghost btn-sm" type="submit">Delete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
