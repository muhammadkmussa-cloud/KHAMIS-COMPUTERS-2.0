<?php
declare(strict_types=1);

/**
 * Delivery-zone management for the online shop.
 *
 * Admin-only CRUD. Zones define a flat delivery fee per area; the chosen
 * zone is snapshotted onto each online order so past orders are unaffected
 * by later edits or deletions.
 */
class DeliveryZoneController
{
    public function store(): void
    {
        Auth::requireLogin();
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $name      = trim((string) ($_POST['name'] ?? ''));
        $rawFee    = trim((string) ($_POST['fee'] ?? ''));
        $fee       = preg_match('/^\d+(?:\.\d{1,2})?$/', $rawFee) ? (float) $rawFee : -1;
        $sortOrder = max(0, min(9999, (int) ($_POST['sort_order'] ?? 0)));
        $isActive  = isset($_POST['is_active']) ? ((int) $_POST['is_active'] === 1) : true;

        $errors = DeliveryZone::validate($name, $fee);
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('settings#delivery-zones');
        }

        DeliveryZone::create($name, $fee, $sortOrder, $isActive);
        Activity::log('delivery_zone.created', $name);
        flash('success', 'Delivery zone added.');
        redirect('settings#delivery-zones');
    }

    public function update(int $id): void
    {
        Auth::requireLogin();
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $zone = DeliveryZone::find($id);
        if (!$zone) {
            flash('error', 'Delivery zone not found.');
            redirect('settings#delivery-zones');
        }

        $name      = trim((string) ($_POST['name'] ?? ''));
        $rawFee    = trim((string) ($_POST['fee'] ?? ''));
        $fee       = preg_match('/^\d+(?:\.\d{1,2})?$/', $rawFee) ? (float) $rawFee : -1;
        $sortOrder = max(0, min(9999, (int) ($_POST['sort_order'] ?? $zone['sort_order'])));
        $isActive  = (int) ($_POST['is_active'] ?? 0) === 1;

        $errors = DeliveryZone::validate($name, $fee, $id);
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('settings#delivery-zones');
        }

        DeliveryZone::update($id, $name, $fee, $sortOrder, $isActive);
        Activity::log('delivery_zone.updated', $name);
        flash('success', 'Delivery zone updated.');
        redirect('settings#delivery-zones');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $zone = DeliveryZone::find($id);
        if (!$zone) {
            flash('error', 'Delivery zone not found.');
            redirect('settings#delivery-zones');
        }

        DeliveryZone::delete($id);
        Activity::log('delivery_zone.deleted', $zone['name']);
        flash('success', 'Delivery zone deleted.');
        redirect('settings#delivery-zones');
    }
}
