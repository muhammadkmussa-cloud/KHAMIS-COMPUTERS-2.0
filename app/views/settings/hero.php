<?php
$slides = $slides ?? HeroSlide::all();
$migrated = $migrated ?? Schema::tableExists('hero_slides');
$csrf = csrf_field();
?>
<?php if (!$migrated): ?>
<div class="card">
    <div class="alert alert-warning" role="alert">
        <b>Database update required.</b> The hero table is missing. Run <code>php tools/install.php</code> (no &ndash;fresh) or re-import <code>schema/schema.sql</code> then run the installer to add it.
    </div>
</div>
<?php endif; ?>

<div class="hero-manage-page">
    <div class="page-head">
        <div>
            <h2>Online Shop &mdash; Hero Carousel</h2>
            <p class="lede">Control the full-screen promotional carousel shown to customers on the shop home page.</p>
        </div>
        <a class="btn btn-primary" href="#add-slide" id="add-slide-link">+ Add Slide</a>
    </div>

    <?php if (empty($slides)): ?>
        <div class="card">
            <p class="muted" style="padding:24px">No slides yet. Add one to get started.</p>
        </div>
    <?php else: ?>
    <div class="slide-list" id="slide-list">
        <?php foreach ($slides as $slide): ?>
        <div class="slide-card card" data-slide-id="<?= (int) $slide['id'] ?>">
            <div class="slide-card-body">
                <div class="slide-card-header">
                    <span class="slide-order">#<?= (int) $slide['display_order'] ?></span>
                    <span class="slide-status <?= (int) $slide['is_active'] === 1 ? 'is-active' : 'is-disabled' ?>">
                        <?= (int) $slide['is_active'] === 1 ? 'Active' : 'Disabled' ?>
                    </span>
                </div>
                <div class="slide-card-media">
                    <?php if (!empty($slide['desktop_image'])): ?>
                    <img src="<?= e(url('uploads/h/' . rawurlencode($slide['desktop_image']))) ?>" alt="<?= e($slide['headline'] ?? '') ?>" width="160" height="70" style="object-fit:cover;border-radius:10px">
                    <?php else: ?>
                    <div class="slide-thumb-placeholder">No image</div>
                    <?php endif; ?>
                </div>
                <div class="slide-card-info">
                    <?php if (!empty($slide['eyebrow'])): ?><div class="slide-eyebrow"><?= e($slide['eyebrow']) ?></div><?php endif; ?>
                    <h3><?= e($slide['headline']) ?></h3>
                    <?php if (!empty($slide['description'])): ?><p class="slide-desc"><?= e($slide['description']) ?></p><?php endif; ?>
                    <div class="slide-links">
                        <?php if (!empty($slide['product_name'])): ?><span>Product: <?= e($slide['product_name']) ?></span><?php endif; ?>
                        <?php if (!empty($slide['category_name'])): ?><span>Category: <?= e($slide['category_name']) ?></span><?php endif; ?>
                    </div>
                </div>
                <div class="slide-card-actions">
                    <form method="post" action="<?= e(url('settings/hero/' . $slide['id'] . '/toggle')) ?>" style="display:inline">
                        <?= $csrf ?>
                        <button type="submit" class="btn btn-sm <?= (int) $slide['is_active'] === 1 ? 'btn-outline' : 'btn-primary' ?>">
                            <?= (int) $slide['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                        </button>
                    </form>
                    <button type="button" class="btn btn-sm btn-outline" data-edit-toggle="<?= (int) $slide['id'] ?>" aria-expanded="false" aria-controls="edit-slide-<?= (int) $slide['id'] ?>">Edit</button>
                    <form method="post" action="<?= e(url('settings/hero/' . $slide['id'] . '/delete')) ?>" style="display:inline" onsubmit="return confirm('Delete this slide?');">
                        <?= $csrf ?>
                        <button type="submit" class="btn btn-sm" style="background:var(--red);color:#fff;border-color:var(--red)">Delete</button>
                    </form>
                </div>
            </div>
            <div class="slide-edit" id="edit-slide-<?= (int) $slide['id'] ?>" hidden>
                <form method="post" action="<?= e(url('settings/hero/' . $slide['id'] . '/edit')) ?>" enctype="multipart/form-data" class="form">
                    <?= $csrf ?>
                    <div class="form-row">
                        <div class="field">
                            <label for="e-desktop-<?= (int) $slide['id'] ?>">Replace desktop image</label>
                            <input type="file" id="e-desktop-<?= (int) $slide['id'] ?>" name="desktop_image" accept="image/jpeg,image/png,image/webp">
                            <small>Leave blank to keep the current image.</small>
                        </div>
                        <div class="field">
                            <label for="e-mobile-<?= (int) $slide['id'] ?>">Replace mobile image</label>
                            <input type="file" id="e-mobile-<?= (int) $slide['id'] ?>" name="mobile_image" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="e-eyebrow-<?= (int) $slide['id'] ?>">Eyebrow / label</label>
                            <input type="text" id="e-eyebrow-<?= (int) $slide['id'] ?>" name="eyebrow" maxlength="120" value="<?= e($slide['eyebrow']) ?>">
                        </div>
                        <div class="field">
                            <label for="e-headline-<?= (int) $slide['id'] ?>">Headline</label>
                            <input type="text" id="e-headline-<?= (int) $slide['id'] ?>" name="headline" maxlength="190" value="<?= e($slide['headline']) ?>" required>
                        </div>
                    </div>
                    <div class="field">
                        <label for="e-description-<?= (int) $slide['id'] ?>">Description</label>
                        <textarea id="e-description-<?= (int) $slide['id'] ?>" name="description" maxlength="500" rows="2"><?= e($slide['description']) ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="e-cta_type-<?= (int) $slide['id'] ?>">Primary CTA type</label>
                            <select id="e-cta_type-<?= (int) $slide['id'] ?>" name="cta_type">
                                <?php foreach (['shop' => 'Shop all', 'category' => 'Category', 'product' => 'Product', 'offers' => 'Offers'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($slide['cta_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="e-cta_target-<?= (int) $slide['id'] ?>">CTA target (category/product ID)</label>
                            <input type="text" id="e-cta_target-<?= (int) $slide['id'] ?>" name="cta_target" value="<?= e($slide['cta_target'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="e-cta_text-<?= (int) $slide['id'] ?>">Primary button text</label>
                            <input type="text" id="e-cta_text-<?= (int) $slide['id'] ?>" name="cta_text" maxlength="60" value="<?= e($slide['cta_text'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="e-secondary_cta_text-<?= (int) $slide['id'] ?>">Secondary CTA text</label>
                            <input type="text" id="e-secondary_cta_text-<?= (int) $slide['id'] ?>" name="secondary_cta_text" maxlength="60" value="<?= e($slide['secondary_cta_text'] ?? '') ?>">
                        </div>
                        <div class="field">
                            <label for="e-secondary_cta_type-<?= (int) $slide['id'] ?>">Secondary CTA type</label>
                            <select id="e-secondary_cta_type-<?= (int) $slide['id'] ?>" name="secondary_cta_type">
                                <?php foreach (['shop' => 'Shop all', 'category' => 'Category', 'product' => 'Product'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($slide['secondary_cta_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="e-secondary_cta_target-<?= (int) $slide['id'] ?>">Secondary target (ID)</label>
                            <input type="text" id="e-secondary_cta_target-<?= (int) $slide['id'] ?>" name="secondary_cta_target" value="<?= e($slide['secondary_cta_target'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="e-text_position-<?= (int) $slide['id'] ?>">Text position</label>
                            <select id="e-text_position-<?= (int) $slide['id'] ?>" name="text_position">
                                <?php foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($slide['text_position'] ?? 'left') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="e-image_position-<?= (int) $slide['id'] ?>">Image focal position</label>
                            <select id="e-image_position-<?= (int) $slide['id'] ?>" name="image_position">
                                <?php foreach (['left' => 'Left', 'center' => 'Center', 'right' => 'Right'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($slide['image_position'] ?? 'center') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label for="e-overlay_strength-<?= (int) $slide['id'] ?>">Overlay strength</label>
                            <input type="range" id="e-overlay_strength-<?= (int) $slide['id'] ?>" name="overlay_strength" min="0" max="90" value="<?= (int) $slide['overlay_strength'] ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="field">
                            <label for="e-display_order-<?= (int) $slide['id'] ?>">Display order</label>
                            <input type="number" id="e-display_order-<?= (int) $slide['id'] ?>" name="display_order" min="0" value="<?= (int) $slide['display_order'] ?>">
                        </div>
                        <div class="field">
                            <label for="e-starts_at-<?= (int) $slide['id'] ?>">Start date/time</label>
                            <input type="datetime-local" id="e-starts_at-<?= (int) $slide['id'] ?>" name="starts_at" value="<?= e(!empty($slide['starts_at']) ? date('Y-m-d\TH:i', strtotime($slide['starts_at'])) : '') ?>">
                        </div>
                        <div class="field">
                            <label for="e-ends_at-<?= (int) $slide['id'] ?>">End date/time</label>
                            <input type="datetime-local" id="e-ends_at-<?= (int) $slide['id'] ?>" name="ends_at" value="<?= e(!empty($slide['ends_at']) ? date('Y-m-d\TH:i', strtotime($slide['ends_at'])) : '') ?>">
                        </div>
                        <div class="field" style="display:flex;align-items:end">
                            <label style="display:flex;gap:8px;cursor:pointer">
                                <input type="checkbox" id="e-is_active-<?= (int) $slide['id'] ?>" name="is_active" value="1" <?= (int) $slide['is_active'] === 1 ? 'checked' : '' ?>>
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit">Save changes</button>
                        <button class="btn btn-outline" type="button" data-edit-cancel="<?= (int) $slide['id'] ?>">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <form method="post" action="<?= e(url('settings/hero/reorder')) ?>" id="reorder-form">
        <?= $csrf ?>
        <div class="form-actions">
            <button class="btn btn-outline" type="button" id="reorder-up">Move up</button>
            <button class="btn btn-outline" type="button" id="reorder-down">Move down</button>
            <button class="btn btn-primary" type="submit">Save order</button>
        </div>
    </form>
    <?php endif; ?>

    <div class="card" id="add-slide" style="margin-top:24px">
        <h2 style="margin:0 0 16px">Add Slide</h2>
        <form method="post" action="<?= e(url('settings/hero')) ?>" enctype="multipart/form-data" class="form">
            <?= $csrf ?>
            <div class="form-row">
                <div class="field">
                    <label for="hs-desktop_image">Desktop image <span class="muted">(required)</span></label>
                    <input type="file" id="hs-desktop_image" name="desktop_image" accept="image/jpeg,image/png,image/webp" required>
                    <small>JPG, PNG or WebP. Max 5 MB. Recommended 1920&times;1080.</small>
                </div>
                <div class="field">
                    <label for="hs-mobile_image">Mobile image (optional)</label>
                    <input type="file" id="hs-mobile_image" name="mobile_image" accept="image/jpeg,image/png,image/webp">
                    <small>Separate image for mobile. Optional.</small>
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="hs-eyebrow">Eyebrow / label</label>
                    <input type="text" id="hs-eyebrow" name="eyebrow" maxlength="120" placeholder="e.g. OUR BEST SELLER">
                </div>
                <div class="field">
                    <label for="hs-headline">Headline <span class="muted">(required)</span></label>
                    <input type="text" id="hs-headline" name="headline" maxlength="190" required placeholder="e.g. Samsung Galaxy A15">
                </div>
            </div>
            <div class="field">
                <label for="hs-description">Description</label>
                <textarea id="hs-description" name="description" maxlength="500" rows="2"></textarea>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="hs-cta_type">Primary CTA type</label>
                    <select id="hs-cta_type" name="cta_type">
                        <option value="shop">Shop all</option>
                        <option value="category">Category</option>
                        <option value="product">Product</option>
                        <option value="offers">Offers</option>
                    </select>
                </div>
                <div class="field">
                    <label for="hs-cta_target">CTA target (category/product ID)</label>
                    <input type="text" id="hs-cta_target" name="cta_target" placeholder="Category or product ID">
                    <small>Required when type is Category or Product.</small>
                </div>
                <div class="field">
                    <label for="hs-cta_text">Primary button text</label>
                    <input type="text" id="hs-cta_text" name="cta_text" placeholder="e.g. SHOP NOW">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="hs-secondary_cta_text">Secondary CTA text</label>
                    <input type="text" id="hs-secondary_cta_text" name="secondary_cta_text" placeholder="e.g. VIEW PRODUCT">
                </div>
                <div class="field">
                    <label for="hs-secondary_cta_type">Secondary CTA type</label>
                    <select id="hs-secondary_cta_type" name="secondary_cta_type">
                        <option value="shop">Shop all</option>
                        <option value="category">Category</option>
                        <option value="product">Product</option>
                    </select>
                </div>
                <div class="field">
                    <label for="hs-secondary_cta_target">Secondary target (category/product ID)</label>
                    <input type="text" id="hs-secondary_cta_target" name="secondary_cta_target" placeholder="Category or product ID">
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="hs-text_position">Text position</label>
                    <select id="hs-text_position" name="text_position">
                        <option value="left">Left</option>
                        <option value="center" selected>Center</option>
                        <option value="right">Right</option>
                    </select>
                </div>
                <div class="field">
                    <label for="hs-image_position">Image focal position</label>
                    <select id="hs-image_position" name="image_position">
                        <option value="left">Left</option>
                        <option value="center" selected>Center</option>
                        <option value="right">Right</option>
                    </select>
                </div>
                <div class="field">
                    <label for="hs-overlay_strength">Overlay strength</label>
                    <input type="range" id="hs-overlay_strength" name="overlay_strength" min="0" max="90" value="45">
                    <small>0 = light, 90 = dark. Controls text readability.</small>
                </div>
            </div>
            <div class="form-row">
                <div class="field">
                    <label for="hs-display_order">Display order</label>
                    <input type="number" id="hs-display_order" name="display_order" value="0" min="0">
                </div>
                <div class="field">
                    <label for="hs-starts_at">Start date/time (optional)</label>
                    <input type="datetime-local" id="hs-starts_at" name="starts_at">
                </div>
                <div class="field">
                    <label for="hs-ends_at">End date/time (optional)</label>
                    <input type="datetime-local" id="hs-ends_at" name="ends_at">
                </div>
                <div class="field" style="display:flex;align-items:end">
                    <label style="display:flex;gap:8px;cursor:pointer">
                        <input type="checkbox" id="hs-is_active" name="is_active" value="1" checked>
                        Active
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Add slide</button>
                <a class="btn btn-outline" href="#slide-list">Cancel</a>
            </div>
        </form>
    </div>
</div>

<style>
.hero-manage-page .page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
.hero-manage-page .page-head h1, .hero-manage-page .page-head h2 { margin:0; }
.slide-list { display:grid; gap:14px; margin-bottom:20px; }
.slide-card { padding:18px; }
.slide-card-header { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
.slide-order { font-weight:800; color:var(--text-tertiary); font-size:12px; }
.slide-status { font-size:11px; font-weight:800; padding:3px 9px; border-radius:99px; }
.slide-status.is-active { background:var(--green-soft); color:var(--green); }
.slide-status.is-disabled { background:var(--surface-3); color:var(--text-tertiary); }
.slide-card-media { margin-bottom:12px; }
.slide-card-media img { max-width:100%; }
.slide-thumb-placeholder { width:160px; height:70px; display:grid; place-items:center; background:var(--surface-3); border-radius:10px; color:var(--text-tertiary); font-size:12px; }
.slide-card-info h3 { margin:4px 0; font-size:18px; }
.slide-eyebrow { color:var(--blue); font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; }
.slide-desc { color:var(--text-secondary); font-size:13px; margin:4px 0; }
.slide-links { display:flex; gap:14px; font-size:12px; color:var(--text-secondary); margin-top:6px; }
.slide-card-actions { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
.slide-edit { margin-top:16px; padding-top:16px; border-top:1px solid var(--border); }
.slide-card.is-editing { outline:2px solid var(--blue); }
</style>

<script>
(function() {
    var form = document.querySelector('#add-slide form');
    if (form) {
        form.addEventListener('submit', function() {
            var btn = form.querySelector('button[type=submit]');
            btn.disabled = true;
            btn.textContent = 'Saving\u2026';
        });
    }
    var link = document.getElementById('add-slide-link');
    if (link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var el = document.getElementById('add-slide');
            if (el) el.scrollIntoView({behavior:'smooth'});
        });
    }

    document.querySelectorAll('[data-edit-toggle]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = btn.dataset.editToggle;
            var panel = document.getElementById('edit-slide-' + id);
            if (!panel) return;
            var open = !panel.hidden;
            panel.hidden = open;
            btn.setAttribute('aria-expanded', String(!open));
            var card = btn.closest('.slide-card');
            if (card) card.classList.toggle('is-editing', !open);
            if (!open) panel.scrollIntoView({behavior:'smooth', block:'nearest'});
        });
    });
    document.querySelectorAll('[data-edit-cancel]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = btn.dataset.editCancel;
            var panel = document.getElementById('edit-slide-' + id);
            if (panel) panel.hidden = true;
            var card = btn.closest('.slide-card');
            if (card) card.classList.remove('is-editing');
            var toggle = document.querySelector('[data-edit-toggle="' + id + '"]');
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        });
    });

    var list = document.getElementById('slide-list');
    var up = document.getElementById('reorder-up');
    var down = document.getElementById('reorder-down');
    function move(dir) {
        if (!list) return;
        var cards = Array.prototype.slice.call(list.querySelectorAll('.slide-card'));
        for (var i = 0; i < cards.length; i++) {
            if (cards[i].classList.contains('selected-order')) {
                var j = dir < 0 ? i - 1 : i + 1;
                if (j >= 0 && j < cards.length) {
                    if (dir < 0) list.insertBefore(cards[i], cards[j]);
                    else list.insertBefore(cards[j], cards[i]);
                    updateOrderNumbers();
                }
                return;
            }
        }
        if (cards.length) { cards[0].classList.add('selected-order'); updateOrderNumbers(); }
    }
    function updateOrderNumbers() {
        if (!list) return;
        list.querySelectorAll('.slide-card').forEach(function(card, idx) {
            var el = card.querySelector('.slide-order');
            if (el) el.textContent = '#' + idx;
        });
        var form = document.getElementById('reorder-form');
        if (form) {
            var hidden = form.querySelector('input[name="ids"]');
            if (!hidden) {
                hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'ids';
                form.appendChild(hidden);
            }
            hidden.value = Array.prototype.map.call(list.querySelectorAll('.slide-card'), function(c) { return c.dataset.slideId; }).join(',');
        }
    }
    if (list) {
        list.addEventListener('click', function(e) {
            var card = e.target.closest('.slide-card');
            if (!card) return;
            list.querySelectorAll('.slide-card').forEach(function(c) { c.classList.remove('selected-order'); });
            card.classList.add('selected-order');
        });
    }
    if (up) up.addEventListener('click', function() { move(-1); });
    if (down) down.addEventListener('click', function() { move(1); });
    updateOrderNumbers();
})();
</script>
