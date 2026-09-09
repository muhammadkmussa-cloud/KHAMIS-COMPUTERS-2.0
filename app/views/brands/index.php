<?php $editId = (int) ($_GET['edit'] ?? 0); ?>
<div class="page-head">
    <div>
        <h1>Brands</h1>
        <p class="lede">Manufacturers carried in the shop.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('products')) ?>">← Inventory</a>
</div>

<div class="grid-2" style="margin-top:0;align-items:start">
    <div class="card">
        <h3>Add brand</h3>
        <form method="post" action="<?= e(url('brands')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <span>Name</span>
                <input type="text" name="name" required placeholder="e.g. HP">
            </div>
            <div><button class="btn btn-primary" type="submit">Add brand</button></div>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Brand</th><th class="num">Products</th><th></th></tr></thead>
            <tbody>
            <?php if (!$brands): ?>
                <tr><td colspan="3" class="muted" style="text-align:center;padding:20px">No brands yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($brands as $b): ?>
                <tr>
                    <?php if ($editId === (int) $b['id']): ?>
                        <td colspan="2">
                            <form method="post" action="<?= e(url('brands/' . $b['id'])) ?>" style="display:flex;gap:8px">
                                <?= csrf_field() ?>
                                <input type="text" name="name" value="<?= e($b['name']) ?>" required style="flex:1">
                                <button class="btn btn-primary btn-sm" type="submit">Save</button>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('brands')) ?>">Cancel</a>
                            </form>
                        </td>
                    <?php else: ?>
                        <td class="cell-main"><?= e($b['name']) ?></td>
                        <td class="num"><?= (int) $b['product_count'] ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('brands?edit=' . $b['id'])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('brands/' . $b['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this brand?');">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-danger-ghost btn-sm" type="submit">Delete</button>
                                </form>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
