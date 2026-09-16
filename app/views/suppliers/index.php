<?php
include APP_PATH . '/views/partials/inventory-tabs.php';
$hasFilters = $filters['q'] !== '' || $filters['status'] !== '';
?>
<div class="page-head supplier-page-head">
    <div><div class="section-kicker">Purchasing directory</div><h1>Suppliers</h1><p class="lede">Manage supplier contacts and open their delivery history without leaving the directory.</p></div>
    <div class="page-actions"><a class="btn btn-outline" href="<?= e(url('grn/new')) ?>">Receive stock</a><button class="btn btn-primary" type="button" data-open-supplier>Add supplier</button></div>
</div>

<section class="inventory-kpis supplier-kpis" aria-label="Supplier summary">
    <div><span>Suppliers</span><b><?= number_format($summary['total']) ?></b><small><?= number_format($summary['active']) ?> active</small></div>
    <div><span>Deliveries</span><b><?= number_format($summary['deliveries']) ?></b><small>Posted GRNs</small></div>
    <div><span>Purchase value</span><b><?= money($summary['spend']) ?></b><small>All recorded deliveries</small></div>
</section>

<form method="get" action="<?= e(url('suppliers')) ?>" class="supplier-filter-bar">
    <label for="supplier-q"><span>Search suppliers</span><input id="supplier-q" type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Name, contact, phone, or email"></label>
    <label for="supplier-status"><span>Status</span><select id="supplier-status" name="status"><option value="">Active and inactive</option><option value="active" <?= $filters['status']==='active'?'selected':'' ?>>Active</option><option value="inactive" <?= $filters['status']==='inactive'?'selected':'' ?>>Inactive</option></select></label>
    <button class="btn btn-primary" type="submit">Show suppliers</button>
    <?php if($hasFilters): ?><a class="btn btn-ghost" href="<?= e(url('suppliers')) ?>">Clear</a><?php endif; ?>
</form>

<div class="supplier-results-head"><div><h2>Supplier records</h2><p><?= count($suppliers) ?> result<?= count($suppliers)===1?'':'s' ?> · open a record to edit or review deliveries</p></div></div>
<?php if(!$suppliers): ?><div class="empty-state"><h2>No suppliers match</h2><p class="muted">Try another search or add a new supplier.</p><button class="btn btn-primary" type="button" data-open-supplier>Add supplier</button></div><?php else: ?>
<div class="supplier-directory">
<?php foreach($suppliers as $supplier): $supplierId=(int)$supplier['id']; $recent=$history[$supplierId]??[]; ?>
    <details class="supplier-record" <?= (string)($_GET['edit']??'')===(string)$supplierId?'open':'' ?>>
        <summary>
            <div class="supplier-avatar" aria-hidden="true"><?= e(strtoupper(substr($supplier['name'],0,2))) ?></div>
            <div class="supplier-summary-name"><b><?= e($supplier['name']) ?></b><span><?= e($supplier['contact_name']?:'No contact person') ?><?= $supplier['phone']?' · '.e($supplier['phone']):'' ?></span></div>
            <div><span>Deliveries</span><b><?= number_format($supplier['grn_count']) ?></b></div>
            <div><span>Purchase value</span><b><?= money($supplier['total_received']) ?></b></div>
            <div><span>Last received</span><b><?= $supplier['last_received_at']?e(date('d M Y',strtotime($supplier['last_received_at']))):'Never' ?></b></div>
            <span class="badge badge-<?= $supplier['is_active']?'green':'gray' ?>"><?= $supplier['is_active']?'Active':'Inactive' ?></span>
            <span class="supplier-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="supplier-record-body">
            <section><div class="product-panel-head"><div><div class="section-kicker">Contact details</div><h2>Edit supplier</h2></div></div>
                <form method="post" action="<?= e(url('suppliers/'.$supplierId)) ?>" class="form supplier-edit-form"><?= csrf_field() ?>
                    <div class="form-row"><label class="field">Name<input type="text" name="name" maxlength="190" required value="<?= e($supplier['name']) ?>"></label><label class="field">Contact person<input type="text" name="contact_name" maxlength="190" value="<?= e($supplier['contact_name']??'') ?>"></label></div>
                    <div class="form-row"><label class="field">Phone<input type="tel" name="phone" maxlength="60" value="<?= e($supplier['phone']??'') ?>"></label><label class="field">Email<input type="email" name="email" maxlength="190" value="<?= e($supplier['email']??'') ?>"></label></div>
                    <label class="field">Address<input type="text" name="address" maxlength="255" value="<?= e($supplier['address']??'') ?>"></label>
                    <label class="field">Internal notes<textarea name="notes" rows="3" maxlength="2000"><?= e($supplier['notes']??'') ?></textarea></label>
                    <div class="form-actions"><button class="btn btn-primary" type="submit">Save details</button></div>
                </form>
            </section>
            <aside><div class="product-panel-head"><div><div class="section-kicker">Delivery history</div><h2>Recent GRNs</h2></div><?php if($supplier['is_active']): ?><a class="btn btn-outline btn-sm" href="<?= e(url('grn/new?supplier='.$supplierId)) ?>">Receive</a><?php endif; ?></div>
                <?php if(!$recent): ?><div class="inline-empty"><b>No deliveries yet</b><span>This supplier can be deleted while no history exists.</span></div><?php else: ?><div class="supplier-history"><?php foreach($recent as $grn): ?><a href="<?= e(url('grn/'.$grn['id'])) ?>"><span><b><?= e($grn['grn_number']) ?></b><small><?= e($grn['supplier_reference']?:date('d M Y',strtotime($grn['created_at']))) ?></small></span><strong><?= money($grn['total_cost']) ?></strong></a><?php endforeach; ?></div><?php endif; ?>
                <div class="supplier-danger-zone">
                    <form method="post" action="<?= e(url('suppliers/'.$supplierId.'/status')) ?>" data-confirm="<?= $supplier['is_active']?'Deactivate this supplier? Existing GRNs remain available.':'Reactivate this supplier for new deliveries?' ?>"><?= csrf_field() ?><input type="hidden" name="active" value="<?= $supplier['is_active']?'0':'1' ?>"><button class="btn btn-outline btn-sm" type="submit"><?= $supplier['is_active']?'Deactivate':'Reactivate' ?></button></form>
                    <?php if((int)$supplier['grn_count']===0): ?><form method="post" action="<?= e(url('suppliers/'.$supplierId.'/delete')) ?>" data-confirm="Delete <?= e($supplier['name']) ?> permanently?" data-confirm-action="Delete supplier"><?= csrf_field() ?><button class="btn btn-danger-ghost btn-sm" type="submit">Delete permanently</button></form><?php else: ?><small>Deletion is unavailable because delivery history must be preserved.</small><?php endif; ?>
                </div>
            </aside>
        </div>
    </details>
<?php endforeach; ?>
</div>
<?php endif; ?>

<dialog class="workflow-dialog supplier-dialog" id="supplier-dialog"><form method="post" action="<?= e(url('suppliers')) ?>"><?= csrf_field() ?><div class="workflow-dialog-head"><span aria-hidden="true">＋</span><div><div class="section-kicker">New purchasing contact</div><h2>Add supplier</h2></div></div><div class="form-row"><label class="field">Supplier name<input type="text" name="name" maxlength="190" required value="<?= old('name') ?>" placeholder="Nairobi Distributors Ltd"></label><label class="field">Contact person<input type="text" name="contact_name" maxlength="190" value="<?= old('contact_name') ?>"></label></div><div class="form-row"><label class="field">Phone<input type="tel" name="phone" maxlength="60" value="<?= old('phone') ?>"></label><label class="field">Email<input type="email" name="email" maxlength="190" value="<?= old('email') ?>"></label></div><label class="field">Address<input type="text" name="address" maxlength="255" value="<?= old('address') ?>"></label><label class="field">Internal notes<textarea name="notes" rows="3" maxlength="2000"><?= old('notes') ?></textarea></label><div class="workflow-dialog-actions"><button class="btn btn-ghost" type="button" data-close-supplier>Cancel</button><button class="btn btn-primary" type="submit">Add supplier</button></div></form></dialog>
<script>(function(){var dialog=document.getElementById('supplier-dialog');document.querySelectorAll('[data-open-supplier]').forEach(function(button){button.addEventListener('click',function(){dialog.showModal();});});document.querySelector('[data-close-supplier]').addEventListener('click',function(){dialog.close();});dialog.addEventListener('click',function(event){if(event.target===dialog)dialog.close();});<?php if(($_GET['add']??'')==='1'): ?>dialog.showModal();<?php endif; ?>})();</script>
