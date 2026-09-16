<?php
$editing = $product !== null;
$field = fn(string $name, string $default='') => $editing ? (string)($product[$name] ?? $default) : old($name, $default);
$selectedCategory = $editing ? (string)($product['category_id'] ?? '') : old('category_id');
$selectedBrand = $editing ? (string)($product['brand_id'] ?? '') : old('brand_id');
$serialized = $editing ? (int)$product['is_serialized']===1 : (bool)old('is_serialized');
$productActive = $editing ? (int)$product['is_active']===1 : true;
include APP_PATH . '/views/partials/inventory-tabs.php';
?>
<div class="product-form-shell">
    <div class="page-head product-form-head">
        <div><div class="section-kicker"><?= $editing?'Catalogue maintenance':'Catalogue setup' ?></div><h1><?= $editing?'Edit product':'Add product' ?></h1><p class="lede"><?= $editing?e($product['name']):'Create the product record before receiving or adjusting stock.' ?></p></div>
        <?php if($editing && $productActive): ?><div class="page-actions"><a class="btn btn-outline" target="_blank" rel="noopener" href="<?= e(url('shop/product/'.$product['id'])) ?>">Preview in shop ↗</a></div><?php endif; ?>
    </div>

    <?php if($editing && $hasHistory): ?><div class="alert alert-info product-history-note"><b>Stock history is protected.</b> Serial/IMEI tracking can no longer be changed because this product has units or stock movements.</div><?php endif; ?>

    <form id="product-form" method="post" action="<?= e(url($editing?'products/'.$product['id']:'products')) ?>" class="product-editor">
        <?= csrf_field() ?>
        <div class="product-save-bar"><label class="dialog-confirm-check"><input type="checkbox" name="print_labels" value="1"><span>Open label preview after saving</span></label><div><span class="save-state" aria-live="polite">No unsaved changes</span><a class="btn btn-ghost" href="<?= e(url($editing?'products/'.$product['id']:'products')) ?>">Cancel</a><button class="btn btn-primary" type="submit"><?= $editing?'Save changes':'Create product' ?></button></div></div>
        <section class="card product-form-section" aria-labelledby="identity-title">
            <div class="product-section-head"><div><span>01</span><div><h2 id="identity-title">Identity</h2><p>Name and scan codes staff use to find this product.</p></div></div><small>Required fields</small></div>
            <label class="field" for="product-name"><span>Product name</span><input id="product-name" type="text" name="name" required maxlength="190" value="<?= e($field('name')) ?>" placeholder="e.g. HP ProBook 450 G10"></label>
            <div class="form-row">
                <label class="field" for="product-sku"><span>SKU</span><input id="product-sku" type="text" name="sku" required maxlength="80" value="<?= e($field('sku')) ?>" placeholder="KC-LAP-001" data-unique-field="sku"><small class="field-feedback" data-feedback-for="sku">Use a unique internal stock code.</small></label>
                <label class="field" for="fld-barcode"><span>Barcode</span><div class="input-action"><input id="fld-barcode" type="text" name="barcode" maxlength="80" value="<?= e($field('barcode')) ?>" placeholder="Optional scan code" data-unique-field="barcode"><button type="button" id="gen-barcode" class="btn btn-outline btn-sm">Generate</button></div><small class="field-feedback" data-feedback-for="barcode">Printed on shelf labels as Code 128.</small></label>
            </div>
            <div class="form-row"><label class="field" for="product-category"><span>Category</span><select id="product-category" name="category_id"><option value="">Uncategorised</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (string)$c['id']===$selectedCategory?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label><label class="field" for="product-brand"><span>Brand</span><select id="product-brand" name="brand_id"><option value="">No brand</option><?php foreach($brands as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (string)$b['id']===$selectedBrand?'selected':'' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></label></div>
            <label class="field" for="product-description"><span>Description</span><textarea id="product-description" name="description" rows="4" maxlength="4000" placeholder="Specifications and useful buying details"><?= e($field('description')) ?></textarea><small>This description appears in the public shop.</small></label>
        </section>

        <section class="card product-form-section" aria-labelledby="pricing-title">
            <div class="product-section-head"><div><span>02</span><div><h2 id="pricing-title">Pricing</h2><p>Record cost and customer price before VAT.</p></div></div><small>VAT <?= e((string)vat_rate()) ?>%</small></div>
            <div class="pricing-editor-grid">
                <label class="field" for="product-cost"><span>Cost price (KSh)</span><input id="product-cost" type="number" step="0.01" min="0" name="cost_price" required value="<?= e($field('cost_price')) ?>"></label>
                <label class="field" for="product-sell"><span>Selling price excl. VAT (KSh)</span><input id="product-sell" type="number" step="0.01" min="0.01" name="sell_price" required value="<?= e($field('sell_price')) ?>"></label>
                <div class="gross-price-preview"><span>Customer price incl. VAT</span><b id="gross-price"><?= money(gross_of((float)$field('sell_price','0'))) ?></b><small>Updates as you type</small></div>
            </div>
        </section>

        <section class="card product-form-section" aria-labelledby="inventory-policy-title">
            <div class="product-section-head"><div><span>03</span><div><h2 id="inventory-policy-title">Inventory policy</h2><p>Choose how stock is counted and when staff should reorder.</p></div></div></div>
            <div class="policy-grid">
                <label class="policy-choice <?= $serialized?'selected':'' ?>"><input type="checkbox" name="is_serialized" value="1" <?= $serialized?'checked':'' ?> <?= $editing&&$hasHistory?'disabled':'' ?>><span><b>Track each serial / IMEI</b><small>Every physical unit must have a unique identifier before it can be sold.</small></span></label>
                <?php if($editing&&$hasHistory): ?><input type="hidden" name="is_serialized" value="<?= $serialized?'1':'0' ?>"><?php endif; ?>
                <label class="field" for="product-reorder"><span>Reorder level</span><input id="product-reorder" type="number" step="1" min="0" name="reorder_level" value="<?= e($field('reorder_level','0')) ?>"><small>The inventory list flags stock at or below this number.</small></label>
            </div>
            <div class="serialized-impact" data-serialized-impact><b>Serialized stock workflow</b><p>Stock is counted from available serials. Receive or add every serial before sale; sold serials can only return through the return or void workflow.</p></div>
        </section>

        <section class="card product-form-section" aria-labelledby="warranty-title">
            <div class="product-section-head"><div><span>04</span><div><h2 id="warranty-title">Warranty</h2><p>Set the standard cover applied to newly received serials.</p></div></div><small>Optional</small></div>
            <label class="field product-short-field" for="product-warranty"><span>Warranty period (months)</span><input id="product-warranty" type="number" step="1" min="0" name="warranty_months" value="<?= e($field('warranty_months')) ?>" placeholder="e.g. 12"><small>Expiry is calculated from each unit’s received date.</small></label>
        </section>

        <section class="card product-form-section" aria-labelledby="visibility-title">
            <div class="product-section-head"><div><span>05</span><div><h2 id="visibility-title">Shop visibility</h2><p>Control whether customers can discover and order this product.</p></div></div></div>
            <label class="policy-choice <?= $productActive?'selected':'' ?>"><input type="checkbox" name="is_active" value="1" <?= $productActive?'checked':'' ?>><span><b>Active in catalogue and online shop</b><small>Turning this off hides the product from customers while preserving stock and sales history.</small></span></label>
        </section>

    </form>
</div>

<script>
(function(){
  var form=document.getElementById('product-form'), sell=document.getElementById('product-sell'), gross=document.getElementById('gross-price');
  var dirty=false, state=document.querySelector('.save-state'), initial=new FormData(form), vat=<?= json_encode((float)vat_rate()) ?>;
  function money(value){return 'KSh '+new Intl.NumberFormat('en-KE',{minimumFractionDigits:2,maximumFractionDigits:2}).format(value);}
  function updateGross(){gross.textContent=money((parseFloat(sell.value)||0)*(1+vat/100));}
  function markDirty(){dirty=true;state.textContent='Unsaved changes';}
  sell.addEventListener('input',updateGross); form.addEventListener('input',markDirty); form.addEventListener('change',markDirty);
  form.addEventListener('submit',function(){dirty=false;state.textContent='Saving…';});
  window.addEventListener('beforeunload',function(e){if(!dirty)return;e.preventDefault();e.returnValue='';});
  document.querySelectorAll('.policy-choice input').forEach(function(input){input.addEventListener('change',function(){input.closest('.policy-choice').classList.toggle('selected',input.checked);});});
  var timer;
  document.querySelectorAll('[data-unique-field]').forEach(function(input){
    input.addEventListener('input',function(){clearTimeout(timer);var feedback=document.querySelector('[data-feedback-for="'+input.dataset.uniqueField+'"]');feedback.className='field-feedback';if(!input.value.trim())return;feedback.textContent='Checking…';timer=setTimeout(function(){var params=new URLSearchParams({field:input.dataset.uniqueField,value:input.value.trim(),exclude:<?= json_encode($editing?(int)$product['id']:0) ?>});fetch(<?= json_encode(url('products/check-unique')) ?>+'?'+params).then(function(r){return r.json();}).then(function(d){feedback.textContent=d.message;feedback.className='field-feedback '+(d.available?'is-available':'is-taken');input.setCustomValidity(d.available?'':d.message);}).catch(function(){feedback.textContent='Could not check right now; the server will verify when saved.';});},350);});
  });
  var btn=document.getElementById('gen-barcode'), barcode=document.getElementById('fld-barcode');
  btn.addEventListener('click',function(){btn.disabled=true;btn.textContent='Generating…';fetch(<?= json_encode(url('products/barcode/generate')) ?>).then(function(r){return r.json();}).then(function(d){if(!d.ok)throw new Error(d.error);barcode.value=d.barcode;barcode.dispatchEvent(new Event('input',{bubbles:true}));window.KC.toast('Barcode generated.','success');}).catch(function(e){window.KC.toast(e.message||'Could not generate a barcode.','error');}).finally(function(){btn.disabled=false;btn.textContent='Generate';});});
  updateGross();
})();
</script>
