<?php
$draft = is_array($draft ?? null) ? $draft : [];
$draftItems = [];
foreach((array)($draft['items']??[]) as $item) $draftItems[(int)($item['sale_item_id']??0)]=$item;
?>
<div class="return-workspace">
    <div class="page-head">
        <div><div class="section-kicker">After-sales workflow</div><h1>Start a return</h1><p class="lede"><?= $sale ? 'Select eligible items and prepare the refund request.' : 'Find the original sale by receipt, order number, customer, or phone.' ?></p></div>
        <a class="btn btn-ghost" href="<?= e(url('returns')) ?>">← Return history</a>
    </div>

    <ol class="workflow-steps" aria-label="Return progress">
        <li class="<?= $sale?'complete':'active' ?>"><span>1</span><b>Select sale</b></li>
        <li class="<?= $sale?'active':'' ?>"><span>2</span><b>Select items</b></li>
        <li class="<?= $sale?'active':'' ?>"><span>3</span><b>Refund and reason</b></li>
        <li><span>4</span><b>Review</b></li>
    </ol>

    <?php if (!$sale): ?>
    <section class="card return-sale-search">
        <div class="return-section-head"><div><div class="section-kicker">Step 1</div><h2>Find the original sale</h2><p>Search completed sales using the customer’s receipt, order number, name, or phone.</p></div></div>
        <form method="get" action="<?= e(url('returns/new')) ?>" class="return-search-form">
            <label class="field"><span>Receipt, order number, customer, or phone</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="e.g. S-20260908-0001 or 0712…" autofocus></label>
            <button class="btn btn-primary" type="submit">Search sales</button>
            <?php if($q!==''): ?><a class="btn btn-ghost" href="<?= e(url('returns/new')) ?>">Clear</a><?php endif; ?>
        </form>
        <div class="sale-choice-list">
        <?php if(!$recentSales): ?><div class="inline-empty"><b>No completed sales found</b><span>Check the receipt number, customer name, or phone and try again.</span></div><?php endif; ?>
        <?php foreach($recentSales as $s): ?>
            <a class="sale-choice" href="<?= e(url('returns/new?sale='.$s['id'])) ?>"><span><b><?= e($s['sale_number']) ?></b><small><?= e($s['customer_name']?:'Walk-in customer') ?> · <?= e($s['customer_phone']?:'No phone') ?></small></span><span><small><?= e(date('d M Y · h:i A',strtotime($s['created_at']))) ?></small><b><?= money($s['total']) ?></b></span><span aria-hidden="true">Select →</span></a>
        <?php endforeach; ?>
        </div>
    </section>
    <?php else: ?>
    <?php $returnableItems=array_values(array_filter($saleItems,fn($item)=>(int)$item['returnable']>0)); ?>
    <section class="return-sale-banner"><span><small>Selected sale</small><b><?= e($sale['sale_number']) ?></b></span><span><small>Customer</small><b><?= e($sale['customer_name']?:'Walk-in customer') ?></b></span><span><small>Order total</small><b><?= money($sale['total']) ?></b></span><a class="btn btn-ghost btn-sm" href="<?= e(url('returns/new')) ?>">Change sale</a></section>

    <?php if($errors): ?><div class="alert alert-danger" role="alert"><b>Resolve these details before review:</b><ul><?php foreach($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if(!$returnableItems): ?><section class="inline-empty"><b>Nothing left to return</b><span>All eligible quantities from this sale already belong to active or completed returns.</span><a class="btn btn-primary" href="<?= e(url('sales/'.$sale['id'])) ?>">View original sale</a></section>
    <?php else: ?>
    <form method="post" action="<?= e(url('returns/review')) ?>" id="return-form" class="return-form" novalidate>
        <?= csrf_field() ?><input type="hidden" name="sale_id" value="<?= (int)$sale['id'] ?>">
        <section class="card">
            <div class="return-section-head"><div><div class="section-kicker">Step 2</div><h2>Select returned items</h2><p>Refund limits follow the quantity selected and the price paid on this sale.</p></div><div class="return-live-total"><small>Refund total</small><strong data-return-total>KSh 0.00</strong><span data-return-count>0 items selected</span></div></div>
            <div class="return-item-list">
            <?php foreach($returnableItems as $item): $id=(int)$item['sale_item_id']; $saved=$draftItems[$id]??null; $checked=$saved!==null; ?>
                <article class="return-item-card <?= $checked?'is-selected':'' ?>" data-return-item data-unit-price="<?= e(number_format((float)$item['unit_price'],2,'.','')) ?>" data-max-qty="<?= (int)$item['returnable'] ?>">
                    <label class="return-item-check"><input type="checkbox" name="item_id[]" value="<?= $id ?>" <?= $checked?'checked':'' ?>><span aria-hidden="true"></span><span class="sr-only">Return <?= e($item['product_name']) ?></span></label>
                    <div class="return-item-identity"><b><?= e($item['product_name']) ?></b><small><?= e($item['sku']) ?><?= $item['serial_number']?' · Serial '.e($item['serial_number']):'' ?></small><span><?= money($item['unit_price']) ?> each · <?= (int)$item['returnable'] ?> of <?= (int)$item['quantity'] ?> returnable</span></div>
                    <label class="field"><span>Quantity</span><input type="number" inputmode="numeric" name="qty[<?= $id ?>]" min="1" max="<?= (int)$item['returnable'] ?>" step="1" value="<?= e((string)($saved['quantity']??1)) ?>"></label>
                    <label class="field"><span>Refund amount</span><span class="money-input"><span>KSh</span><input type="number" inputmode="decimal" name="amount[<?= $id ?>]" min="0" max="<?= e(number_format((float)$item['max_refund'],2,'.','')) ?>" step="0.01" value="<?= e(number_format((float)($saved['refund_amount']??$item['unit_price']),2,'.','')) ?>"></span><small data-refund-limit>Maximum <?= money($item['unit_price']) ?></small></label>
                </article>
            <?php endforeach; ?>
            </div>
        </section>
        <section class="card return-refund-card">
            <div class="return-section-head"><div><div class="section-kicker">Step 3</div><h2>Refund and reason</h2><p>Record enough context for a manager to make a clear decision.</p></div></div>
            <div class="return-reason-grid">
                <label class="field"><span>Refund method</span><select name="refund_method" required><?php foreach(['original'=>'Original payment method','cash'=>'Cash','mpesa'=>'M-PESA','bank'=>'Bank transfer','store_credit'=>'Store credit'] as $value=>$label): ?><option value="<?= $value ?>" <?= ($draft['refund_method']??'original')===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select><small>This records the intended refund channel.</small></label>
                <label class="field return-reason"><span>Reason for return</span><textarea name="reason" rows="3" required maxlength="500" placeholder="Describe the fault, mismatch, or customer request."><?= e($draft['reason']??'') ?></textarea></label>
                <label class="field return-evidence"><span>Evidence or inspection note <em>Optional</em></span><textarea name="evidence_note" rows="3" maxlength="1000" placeholder="Packaging condition, diagnostic result, accessories received…"><?= e($draft['evidence_note']??'') ?></textarea></label>
            </div>
        </section>
        <div class="return-review-bar"><div><small>Next step</small><b>No stock or refund is changed until approval</b></div><a class="btn btn-ghost" href="<?= e(url('sales/'.$sale['id'])) ?>">Cancel</a><button class="btn btn-primary" type="submit">Review return →</button></div>
    </form>
    <?php endif; endif; ?>
</div>

<script>
(function(){
  var form=document.getElementById('return-form'); if(!form)return;
  var cards=Array.prototype.slice.call(form.querySelectorAll('[data-return-item]'));
  var totalNode=form.querySelector('[data-return-total]'), countNode=form.querySelector('[data-return-count]');
  function money(value){return 'KSh '+value.toLocaleString('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2});}
  function updateCard(card){
    var check=card.querySelector('input[type="checkbox"]'), qty=card.querySelector('input[name^="qty"]'), amount=card.querySelector('input[name^="amount"]');
    card.classList.toggle('is-selected',check.checked); qty.disabled=!check.checked; amount.disabled=!check.checked;
    var maxQty=parseInt(card.dataset.maxQty,10), unit=parseFloat(card.dataset.unitPrice), selectedQty=parseInt(qty.value,10)||0, max=Math.max(0,Math.min(maxQty,selectedQty)*unit);
    amount.max=max.toFixed(2); card.querySelector('[data-refund-limit]').textContent='Maximum '+money(max);
    if(check.checked && parseFloat(amount.value)>max) amount.setCustomValidity('Refund cannot exceed '+money(max)); else amount.setCustomValidity('');
  }
  function update(){var total=0,count=0;cards.forEach(function(card){updateCard(card);var check=card.querySelector('input[type="checkbox"]');if(check.checked){count++;total+=parseFloat(card.querySelector('input[name^="amount"]').value)||0;}});totalNode.textContent=money(total);countNode.textContent=count+' item'+(count===1?'':'s')+' selected';}
  cards.forEach(function(card){card.addEventListener('input',update);card.addEventListener('change',update);});
  form.addEventListener('submit',function(event){update();var checked=form.querySelectorAll('[data-return-item] input[type="checkbox"]:checked');if(!checked.length){event.preventDefault();window.KC.toast('Select at least one item to return.','error');cards[0].querySelector('input[type="checkbox"]').focus();return;}if(!form.checkValidity()){event.preventDefault();form.reportValidity();}});
  update();
})();
</script>
