<div class="page-head">
    <div>
        <h1>Suppliers</h1>
        <p class="lede">Vendors you receive stock from — linked to goods-received notes.</p>
    </div>
    <div class="page-actions">
        <a class="btn btn-ghost" href="<?= e(url('grn/new')) ?>">Receive stock</a>
    </div>
</div>

<div class="card">
    <div class="section-kicker">Add supplier</div>
    <form method="post" action="<?= e(url('suppliers')) ?>" class="form">
        <?= csrf_field() ?>
        <div class="form-row">
            <div class="field">
                <span>Supplier name</span>
                <input type="text" name="name" required placeholder="e.g. Nairobi Distributors Ltd">
            </div>
            <div class="field">
                <span>Contact person</span>
                <input type="text" name="contact_name" placeholder="optional">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <span>Phone</span>
                <input type="text" name="phone" placeholder="07…">
            </div>
            <div class="field">
                <span>Email</span>
                <input type="email" name="email" placeholder="orders@…">
            </div>
        </div>
        <div class="field">
            <span>Address</span>
            <input type="text" name="address" placeholder="optional">
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Add supplier</button>
        </div>
    </form>
</div>

<div class="card" style="margin-top:18px">
    <table class="table">
        <thead>
            <tr><th>Supplier</th><th>Contact</th><th>Phone</th><th class="num">GRNs</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$suppliers): ?>
            <tr><td colspan="6" class="muted" style="text-align:center;padding:26px">No suppliers yet — add your first above.</td></tr>
        <?php endif; ?>
        <?php foreach ($suppliers as $s): ?>
            <tr>
                <td>
                    <div class="cell-main"><?= e($s['name']) ?></div>
                    <?php if ($s['email']): ?><div class="cell-sub"><?= e($s['email']) ?></div><?php endif; ?>
                </td>
                <td class="cell-sub"><?= e($s['contact_name'] ?? '—') ?></td>
                <td class="cell-sub"><?= e($s['phone'] ?? '—') ?></td>
                <td class="num"><?= (int) $s['grn_count'] ?></td>
                <td><?= (int) $s['is_active'] === 1
                        ? '<span class="badge badge-green">active</span>'
                        : '<span class="badge badge-gray">inactive</span>' ?></td>
                <td><div class="row-actions">
                    <details>
                        <summary class="btn btn-outline btn-sm">Edit</summary>
                        <div class="card" style="margin-top:8px;text-align:left">
                            <form method="post" action="<?= e(url('suppliers/' . $s['id'])) ?>" class="form">
                                <?= csrf_field() ?>
                                <div class="form-row">
                                    <div class="field"><span>Name</span>
                                        <input type="text" name="name" required value="<?= e($s['name']) ?>"></div>
                                    <div class="field"><span>Contact</span>
                                        <input type="text" name="contact_name" value="<?= e($s['contact_name'] ?? '') ?>"></div>
                                </div>
                                <div class="form-row">
                                    <div class="field"><span>Phone</span>
                                        <input type="text" name="phone" value="<?= e($s['phone'] ?? '') ?>"></div>
                                    <div class="field"><span>Email</span>
                                        <input type="email" name="email" value="<?= e($s['email'] ?? '') ?>"></div>
                                </div>
                                <div class="field"><span>Address</span>
                                    <input type="text" name="address" value="<?= e($s['address'] ?? '') ?>"></div>
                                <label class="field field-check">
                                    <input type="checkbox" name="is_active" value="1" <?= (int) $s['is_active'] === 1 ? 'checked' : '' ?>>
                                    <span>Active</span>
                                </label>
                                <div class="form-actions">
                                    <button class="btn btn-primary btn-sm" type="submit">Save</button>
                                </div>
                            </form>
                        </div>
                    </details>
                    <form method="post" action="<?= e(url('suppliers/' . $s['id'] . '/delete')) ?>"
                          onsubmit="return confirm('<?= (int) $s['grn_count'] > 0 ? 'This supplier has GRN history — it will be deactivated instead of deleted. Continue?' : 'Delete this supplier?' ?>');" style="display:inline">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-danger-ghost btn-sm"><?= (int) $s['grn_count'] > 0 ? 'Deactivate' : 'Delete' ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
