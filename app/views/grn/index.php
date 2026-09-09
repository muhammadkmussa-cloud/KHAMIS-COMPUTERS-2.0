<div class="page-head">
    <div>
        <h1>Goods Received</h1>
        <p class="lede">Stock-in records from suppliers.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('grn/new')) ?>">+ Receive stock</a>
</div>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>GRN #</th>
                <th>Supplier</th>
                <th class="num">Items</th>
                <th class="num">Total cost</th>
                <th>Received</th>
                <th>By</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php if (!$grns): ?>
            <tr><td colspan="7" class="muted" style="text-align:center;padding:26px">
                Nothing received yet. <a href="<?= e(url('grn/new')) ?>">Receive your first delivery</a>.
            </td></tr>
        <?php endif; ?>
        <?php foreach ($grns as $g): ?>
            <tr>
                <td class="cell-main"><a href="<?= e(url('grn/' . $g['id'])) ?>"><?= e($g['grn_number']) ?></a></td>
                <td><?= e($g['supplier']) ?></td>
                <td class="num"><?= (int) $g['item_count'] ?></td>
                <td class="num"><?= money($g['total_cost']) ?></td>
                <td class="cell-sub"><?= e(substr($g['created_at'], 0, 16)) ?></td>
                <td class="cell-sub"><?= e($g['user_name'] ?? '—') ?></td>
                <td><div class="row-actions"><a class="btn btn-primary btn-sm" href="<?= e(url('grn/' . $g['id'])) ?>">View</a></div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
