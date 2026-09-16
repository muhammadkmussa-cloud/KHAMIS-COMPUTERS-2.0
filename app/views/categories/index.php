<?php $editId = (int) ($_GET['edit'] ?? 0); ?>
<?php include APP_PATH . '/views/partials/inventory-tabs.php'; ?>
<div class="page-head">
    <div>
        <h1>Categories</h1>
        <p class="lede">Organise your catalogue.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('products')) ?>">← Inventory</a>
</div>

<div class="grid-2" style="margin-top:0;align-items:start">
    <div class="card">
        <h3>Add category</h3>
        <form method="post" action="<?= e(url('categories')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <span>Name</span>
                <input type="text" name="name" required placeholder="e.g. Laptops">
            </div>
            <div class="field">
                <span>Description (optional)</span>
                <input type="text" name="description" placeholder="Short description">
            </div>
            <div><button class="btn btn-primary" type="submit">Add category</button></div>
        </form>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Category</th><th class="num">Products</th><th></th></tr></thead>
            <tbody>
            <?php if (!$categories): ?>
                <tr><td colspan="3" class="muted" style="text-align:center;padding:20px">No categories yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($categories as $c): ?>
                <tr>
                    <?php if ($editId === (int) $c['id']): ?>
                        <td colspan="2">
                            <form method="post" action="<?= e(url('categories/' . $c['id'])) ?>" style="display:flex;gap:8px">
                                <?= csrf_field() ?>
                                <input type="text" name="name" value="<?= e($c['name']) ?>" required style="flex:1">
                                <input type="text" name="description" value="<?= e($c['description'] ?? '') ?>" placeholder="description" style="flex:1">
                                <button class="btn btn-primary btn-sm" type="submit">Save</button>
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('categories')) ?>">Cancel</a>
                            </form>
                        </td>
                    <?php else: ?>
                        <td>
                            <div class="cell-main"><?= e($c['name']) ?></div>
                            <?php if (!empty($c['description'])): ?><div class="cell-sub"><?= e($c['description']) ?></div><?php endif; ?>
                        </td>
                        <td class="num"><?= (int) $c['product_count'] ?></td>
                        <td>
                            <div class="row-actions">
                                <a class="btn btn-ghost btn-sm" href="<?= e(url('categories?edit=' . $c['id'])) ?>">Edit</a>
                                <form method="post" action="<?= e(url('categories/' . $c['id'] . '/delete')) ?>" data-confirm="Delete this category? Products keep working, they become uncategorised.">
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
