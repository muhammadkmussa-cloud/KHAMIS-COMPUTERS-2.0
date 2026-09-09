<div class="content-narrow">
    <div class="page-head">
        <div>
            <h2>New return</h2>
            <p class="lede"><?= $sale ? 'Select the items being returned from ' . e($sale['sale_number']) : 'Choose a sale to return items from.' ?></p>
        </div>
        <a class="btn btn-ghost" href="<?= e(url('returns')) ?>">← Returns</a>
    </div>

    <?php if (!$sale): ?>
        <div class="card">
            <h3>Recent sales</h3>
            <p class="sub">Pick the order that contains the item being returned.</p>
            <table class="table" style="margin-top:8px">
                <thead><tr><th>Sale</th><th>Customer</th><th>Date</th><th class="num">Total</th><th></th></tr></thead>
                <tbody>
                <?php if (!$recentSales): ?>
                    <tr><td colspan="5" class="muted" style="text-align:center;padding:20px">No sales yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($recentSales as $s): ?>
                    <tr>
                        <td class="cell-main"><?= e($s['sale_number']) ?></td>
                        <td><?= e($s['customer_name'] ?? 'Walk-in') ?></td>
                        <td class="cell-sub"><?= e(date('d M Y', strtotime($s['created_at']))) ?></td>
                        <td class="num"><?= money($s['total']) ?></td>
                        <td><div class="row-actions"><a class="btn btn-primary btn-sm" href="<?= e(url('returns/new?sale=' . $s['id'])) ?>">Select</a></div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?php $returnableItems = array_values(array_filter($saleItems, fn ($it) => (int) $it['returnable'] > 0)); ?>
        <div class="card">
            <div class="section-kicker"><?= e($sale['sale_number']) ?> · <?= e($sale['customer_name'] ?? 'Walk-in') ?> · <?= money($sale['total']) ?></div>
            <?php if (!$returnableItems): ?>
                <p class="muted">There are no items left to return on this order.</p>
                <a class="btn btn-primary" href="<?= e(url('sales/' . $sale['id'])) ?>">Back to sale</a>
            <?php else: ?>
            <form method="post" action="<?= e(url('returns')) ?>" class="form" id="return-form">
                <?= csrf_field() ?>
                <input type="hidden" name="sale_id" value="<?= (int) $sale['id'] ?>">

                <table class="table">
                    <thead>
                        <tr><th></th><th>Item</th><th class="num">Qty to return</th><th class="num">Refund (KSh)</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($returnableItems as $it): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="item_id[]" value="<?= (int) $it['sale_item_id'] ?>"
                                       class="ret-check" data-max="<?= (int) $it['returnable'] ?>"
                                       data-unit="<?= e(number_format($it['unit_price'], 2, '.', '')) ?>">
                            </td>
                            <td>
                                <div class="cell-main"><?= e($it['product_name']) ?></div>
                                <div class="cell-sub">
                                    <?= e($it['sku']) ?><?= $it['serial_number'] ? ' · SN ' . e($it['serial_number']) : '' ?>
                                    · <?= (int) $it['returnable'] ?> of <?= (int) $it['quantity'] ?> returnable
                                </div>
                            </td>
                            <td class="num">
                                <input type="number" name="qty[<?= (int) $it['sale_item_id'] ?>]" value="1" min="1" max="<?= (int) $it['returnable'] ?>" style="width:70px">
                            </td>
                            <td class="num">
                                <input type="number" name="amount[<?= (int) $it['sale_item_id'] ?>]" step="0.01" min="0" value="<?= e(number_format($it['unit_price'], 2, '.', '')) ?>" style="width:120px">
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="field">
                    <span>Reason for return</span>
                    <textarea name="reason" rows="2" placeholder="e.g. faulty screen, changed mind, wrong item…"></textarea>
                </div>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">Create return request</button>
                    <a class="btn btn-ghost" href="<?= e(url('sales/' . $sale['id'])) ?>">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
  var form = document.getElementById('return-form');
  if (!form) return;
  form.addEventListener('submit', function (e) {
    var checked = form.querySelectorAll('.ret-check:checked');
    if (!checked.length) {
      e.preventDefault();
      alert('Select at least one item to return.');
    }
  });
})();
</script>
