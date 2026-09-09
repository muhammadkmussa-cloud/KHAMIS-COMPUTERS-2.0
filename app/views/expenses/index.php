<div class="page-head">
    <div>
        <h1>Expenses</h1>
        <p class="lede">Running costs — rent, salaries, supplies and more.</p>
    </div>
</div>

<div class="grid-2" style="margin-top:0;align-items:start">
    <div class="card">
        <h3>Record expense</h3>
        <form method="post" action="<?= e(url('expenses')) ?>" class="form">
            <?= csrf_field() ?>
            <div class="field">
                <span>Description</span>
                <input type="text" name="description" required placeholder="e.g. Shop rent — September">
            </div>
            <div class="form-row">
                <div class="field">
                    <span>Amount (KSh)</span>
                    <input type="number" name="amount" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="field">
                    <span>Date</span>
                    <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div class="field">
                <span>Category</span>
                <select name="category">
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= e($c) ?>"><?= e(ucfirst($c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div><button class="btn btn-primary" type="submit">Add expense</button></div>
        </form>
    </div>

    <div>
        <form method="get" action="<?= e(url('expenses')) ?>" class="toolbar">
            <input type="date" name="from" value="<?= e($from) ?>">
            <input type="date" name="to" value="<?= e($to) ?>">
            <button class="btn btn-primary btn-sm" type="submit">Filter</button>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('expenses')) ?>">This month</a>
        </form>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>Description</th><th>Category</th><th>Date</th><th class="num">Amount</th><th></th></tr>
                </thead>
                <tbody>
                <?php if (!$expenses): ?>
                    <tr><td colspan="5" class="muted" style="text-align:center;padding:22px">No expenses in this period.</td></tr>
                <?php endif; ?>
                <?php foreach ($expenses as $x): ?>
                    <tr>
                        <td>
                            <div class="cell-main"><?= e($x['description']) ?></div>
                            <?php if ($x['user_name']): ?><div class="cell-sub">by <?= e($x['user_name']) ?></div><?php endif; ?>
                        </td>
                        <td><span class="badge badge-gray"><?= e($x['category']) ?></span></td>
                        <td class="cell-sub"><?= e($x['expense_date']) ?></td>
                        <td class="num" style="font-weight:700"><?= money($x['amount']) ?></td>
                        <td>
                            <form method="post" action="<?= e(url('expenses/' . $x['id'] . '/delete')) ?>" onsubmit="return confirm('Delete this expense?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-danger-ghost btn-sm" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <?php if ($expenses): ?>
                <tfoot>
                    <tr><td colspan="3" style="text-align:right;font-weight:700">Total</td>
                        <td class="num" style="font-weight:700"><?= money($total) ?></td><td></td></tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
