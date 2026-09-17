<?php
declare(strict_types=1);

class HeroSlideController
{
    public function create(): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        if (!Schema::tableExists('hero_slides')) {
            flash('error', 'The hero table is not installed yet. Run the installer (php tools/install.php).');
            redirect('settings?tab=online-shop');
            return;
        }
        $errors = [];
        $desktop = $_FILES['desktop_image'] ?? null;
        if (!$desktop || (int) ($desktop['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'A desktop hero image is required.';
        } else {
            $err = hero_image_validate($desktop['tmp_name'], $desktop['name'], $desktop['size']);
            if ($err !== '') {
                $errors[] = $err;
            }
        }
        $headline = trim((string) ($_POST['headline'] ?? ''));
        if ($headline === '') {
            $errors[] = 'Headline is required.';
        }
        $ctaType = (string) ($_POST['cta_type'] ?? 'shop');
        $allowedCta = ['product', 'category', 'shop', 'offers'];
        if (!in_array($ctaType, $allowedCta, true)) {
            $errors[] = 'Invalid CTA type.';
        }
        $ctaTarget = trim((string) ($_POST['cta_target'] ?? ''));
        if ($ctaTarget !== '' && $ctaType !== 'shop' && $ctaType !== 'offers') {
            if (!ctype_digit($ctaTarget) || (int) $ctaTarget <= 0) {
                $errors[] = 'Invalid CTA target.';
            }
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('settings?tab=online-shop');
            return;
        }
        $dir = HeroSlide::imageDir();
        $desktopExt = hero_image_ext($desktop['tmp_name']);
        $desktopName = 'h' . bin2hex(random_bytes(8)) . '.' . $desktopExt;
        if (!move_uploaded_file((string) $desktop['tmp_name'], $dir . '/' . $desktopName)) {
            flash('error', 'Could not save the desktop image.');
            redirect('settings?tab=online-shop');
            return;
        }
        hero_image_optimize($dir . '/' . $desktopName);
        $mobileName = '';
        if (!empty($_FILES['mobile_image']['tmp_name']) && (int) ($_FILES['mobile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $m = $_FILES['mobile_image'];
            $merr = hero_image_validate($m['tmp_name'], $m['name'], $m['size']);
            if ($merr === '') {
                $mExt = hero_image_ext($m['tmp_name']);
                $mobileName = 'h' . bin2hex(random_bytes(8)) . '_m.' . $mExt;
                if (move_uploaded_file((string) $m['tmp_name'], $dir . '/' . $mobileName)) {
                    hero_image_optimize($dir . '/' . $mobileName);
                } else {
                    $mobileName = '';
                    $errors[] = 'Could not save the mobile image.';
                }
            } else {
                $errors[] = 'Mobile image: ' . $merr;
            }
        }
        if ($errors) {
            @unlink($dir . '/' . $desktopName);
            flash('error', implode(' ', $errors));
            redirect('settings?tab=online-shop');
            return;
        }
        $productId = $ctaType === 'product' && $ctaTarget !== '' ? (int) $ctaTarget : null;
        $categoryId = $ctaType === 'category' && $ctaTarget !== '' ? (int) $ctaTarget : null;
        $id = HeroSlide::create([
            'desktop_image' => $desktopName,
            'mobile_image' => $mobileName,
            'eyebrow' => trim((string) ($_POST['eyebrow'] ?? '')),
            'headline' => $headline,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'product_id' => $productId !== null ? (string) $productId : '',
            'category_id' => $categoryId !== null ? (string) $categoryId : '',
            'cta_text' => trim((string) ($_POST['cta_text'] ?? '')),
            'cta_type' => $ctaType,
            'cta_target' => $ctaTarget,
            'secondary_cta_text' => trim((string) ($_POST['secondary_cta_text'] ?? '')),
            'secondary_cta_type' => trim((string) ($_POST['secondary_cta_type'] ?? 'shop')),
            'secondary_cta_target' => trim((string) ($_POST['secondary_cta_target'] ?? '')),
            'text_position' => in_array($_POST['text_position'] ?? '', ['left', 'center', 'right'], true) ? $_POST['text_position'] : 'left',
            'image_position' => in_array($_POST['image_position'] ?? '', ['left', 'center', 'right'], true) ? $_POST['image_position'] : 'center',
            'overlay_strength' => max(0, min(90, (int) ($_POST['overlay_strength'] ?? 45))),
            'display_order' => (int) ($_POST['display_order'] ?? HeroSlide::count()),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'starts_at' => hero_datetime($_POST['starts_at'] ?? ''),
            'ends_at' => hero_datetime($_POST['ends_at'] ?? ''),
        ]);
        flash('success', 'Hero slide added.');
        redirect('settings?tab=online-shop');
    }

    public function edit(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        if (!Schema::tableExists('hero_slides')) {
            flash('error', 'The hero table is not installed yet. Run the installer (php tools/install.php).');
            redirect('settings?tab=online-shop');
            return;
        }
        $slide = HeroSlide::find($id);
        if (!$slide) {
            flash('error', 'Slide not found.');
            redirect('settings?tab=online-shop');
            return;
        }
        $errors = [];
        $headline = trim((string) ($_POST['headline'] ?? ''));
        if ($headline === '') {
            $errors[] = 'Headline is required.';
        }
        $ctaType = (string) ($_POST['cta_type'] ?? 'shop');
        if (!in_array($ctaType, ['product', 'category', 'shop', 'offers'], true)) {
            $errors[] = 'Invalid CTA type.';
        }
        // Validate both uploads before touching any files.
        $desktopUpload = null;
        $desktop = $_FILES['desktop_image'] ?? null;
        if ($desktop && (int) ($desktop['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $err = hero_image_validate($desktop['tmp_name'], $desktop['name'], $desktop['size']);
            if ($err !== '') {
                $errors[] = $err;
            } else {
                $desktopUpload = $desktop;
            }
        }
        $mobileUpload = null;
        if (!empty($_FILES['mobile_image']['tmp_name']) && (int) ($_FILES['mobile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $m = $_FILES['mobile_image'];
            $merr = hero_image_validate($m['tmp_name'], $m['name'], $m['size']);
            if ($merr !== '') {
                $errors[] = 'Mobile image: ' . $merr;
            } else {
                $mobileUpload = $m;
            }
        }
        $ctaTarget = trim((string) ($_POST['cta_target'] ?? ''));
        if ($ctaTarget !== '' && $ctaType !== 'shop' && $ctaType !== 'offers' && (!ctype_digit($ctaTarget) || (int) $ctaTarget <= 0)) {
            $errors[] = 'Invalid CTA target.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('settings?tab=online-shop');
            return;
        }
        $dir = HeroSlide::imageDir();
        $desktopName = $slide['desktop_image'];
        $mobileName = $slide['mobile_image'];
        $newFiles = [];
        try {
            if ($desktopUpload) {
                $newName = 'h' . bin2hex(random_bytes(8)) . '.' . hero_image_ext($desktopUpload['tmp_name']);
                if (!move_uploaded_file((string) $desktopUpload['tmp_name'], $dir . '/' . $newName)) {
                    throw new RuntimeException('Could not save the desktop image.');
                }
                hero_image_optimize($dir . '/' . $newName);
                $newFiles[] = $newName;
                $desktopName = $newName;
            }
            if ($mobileUpload) {
                $newMobile = 'h' . bin2hex(random_bytes(8)) . '_m.' . hero_image_ext($mobileUpload['tmp_name']);
                if (!move_uploaded_file((string) $mobileUpload['tmp_name'], $dir . '/' . $newMobile)) {
                    throw new RuntimeException('Could not save the mobile image.');
                }
                hero_image_optimize($dir . '/' . $newMobile);
                $newFiles[] = $newMobile;
                $mobileName = $newMobile;
            }
        } catch (RuntimeException $e) {
            foreach ($newFiles as $nf) {
                @unlink($dir . '/' . $nf);
            }
            flash('error', $e->getMessage());
            redirect('settings?tab=online-shop');
            return;
        }
        // Remove replaced files only after every move succeeded.
        if ($desktopUpload && !empty($slide['desktop_image']) && $slide['desktop_image'] !== $desktopName) {
            $old = realpath($dir . '/' . $slide['desktop_image']);
            if ($old !== false && is_file($old)) { @unlink($old); }
        }
        if ($mobileUpload && !empty($slide['mobile_image']) && $slide['mobile_image'] !== $mobileName) {
            $old = realpath($dir . '/' . $slide['mobile_image']);
            if ($old !== false && is_file($old)) { @unlink($old); }
        }
        $productId = $ctaType === 'product' && $ctaTarget !== '' ? (int) $ctaTarget : null;
        $categoryId = $ctaType === 'category' && $ctaTarget !== '' ? (int) $ctaTarget : null;
        HeroSlide::update($id, [
            'desktop_image' => $desktopName,
            'mobile_image' => $mobileName,
            'eyebrow' => trim((string) ($_POST['eyebrow'] ?? '')),
            'headline' => $headline,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'product_id' => $productId !== null ? (string) $productId : '',
            'category_id' => $categoryId !== null ? (string) $categoryId : '',
            'cta_text' => trim((string) ($_POST['cta_text'] ?? '')),
            'cta_type' => $ctaType,
            'cta_target' => trim((string) ($_POST['cta_target'] ?? '')) ?: '',
            'secondary_cta_text' => trim((string) ($_POST['secondary_cta_text'] ?? '')),
            'secondary_cta_type' => trim((string) ($_POST['secondary_cta_type'] ?? 'shop')),
            'secondary_cta_target' => trim((string) ($_POST['secondary_cta_target'] ?? '')) ?: '',
            'text_position' => in_array($_POST['text_position'] ?? '', ['left', 'center', 'right'], true) ? $_POST['text_position'] : 'left',
            'image_position' => in_array($_POST['image_position'] ?? '', ['left', 'center', 'right'], true) ? $_POST['image_position'] : 'center',
            'overlay_strength' => max(0, min(90, (int) ($_POST['overlay_strength'] ?? 45))),
            'display_order' => (int) ($_POST['display_order'] ?? 0),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'starts_at' => hero_datetime($_POST['starts_at'] ?? ''),
            'ends_at' => hero_datetime($_POST['ends_at'] ?? ''),
        ]);
        flash('success', 'Hero slide updated.');
        redirect('settings?tab=online-shop');
    }

    public function delete(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        HeroSlide::delete($id);
        flash('success', 'Hero slide deleted.');
        redirect('settings?tab=online-shop');
    }

    public function toggle(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        $slide = HeroSlide::find($id);
        if (!$slide) {
            flash('error', 'Slide not found.');
            redirect('settings?tab=online-shop');
            return;
        }
        HeroSlide::setActive($id, !$slide['is_active']);
        flash('success', $slide['is_active'] ? 'Slide disabled.' : 'Slide enabled.');
        redirect('settings?tab=online-shop');
    }

    public function reorder(): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) ($_POST['ids'] ?? '')))));
        HeroSlide::reorder($ids);
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') {
            json_response(['ok' => true]);
        }
        flash('success', 'Slide order saved.');
        redirect('settings?tab=online-shop');
    }

    public function image(string $filename): void
    {
        stream_hero_image($filename);
    }
}
