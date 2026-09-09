<div class="page-head">
    <div>
        <h1><?= e($grn['grn_number']) ?></h1>
        <p class="lede"><?= e($grn['supplier']) ?> · received <?= e(substr($grn['created_at'], 0, 16)) ?></p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('grn')) ?>">← Goods received</a>
</div>

<?php if ($grn['note']): ?>
<div class="card" style="margin-bottom:18px"><p class="muted" style="margin:0"><?= nl2br(e($grn['note'])) ?></p></div>
<?php endif; ?>

<div class="table-wrap">
    <table class="table">
        <thead>
            <tr><th>Product</th><th>SKU</th><th class="num">Qty</th><th class="num">Unit cost</th><th class="num">Line total</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items as $i): ?>
            <tr>
                <td class="cell-main"><a href="<?= e(url('products/' . $i['product_id'])) ?>"><?= e($i['product_name']) ?></a></td>
                <td class="cell-sub"><?= e($i['sku']) ?></td>
                <td class="num"><?= (int) $i['quantity'] ?></td>
                <td class="num"><?= money($i['unit_cost']) ?></td>
                <td class="num"><?= money($i['unit_cost'] * $i['quantity']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;font-weight:700">Total cost</td>
                <td class="num" style="font-weight:700"><?= money($grn['total_cost']) ?></td>
            </tr>
        </tfoot>
    </table>
</div>
