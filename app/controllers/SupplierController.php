<?php
declare(strict_types=1);

class SupplierController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('suppliers/index', [
            'title'     => 'Suppliers',
            'suppliers' => Supplier::all(),
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $errors = Supplier::validate($_POST);
        $name   = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '' && Database::fetchValue('SELECT COUNT(*) FROM suppliers WHERE name = ?', [$name]) > 0) {
            $errors[] = 'A supplier with that name already exists.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('suppliers');
        }

        Supplier::create($_POST);
        Activity::log('supplier.created', $name);
        flash('success', 'Supplier added.');
        redirect('suppliers');
    }

    public function update(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $existing = Supplier::find($id);
        if (!$existing) {
            flash('error', 'Supplier not found.');
            redirect('suppliers');
        }

        $errors = Supplier::validate($_POST);
        $name   = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '' && $name !== $existing['name']
            && Database::fetchValue('SELECT COUNT(*) FROM suppliers WHERE name = ? AND id != ?', [$name, $id]) > 0) {
            $errors[] = 'A supplier with that name already exists.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('suppliers');
        }

        Supplier::update($id, $_POST);
        Activity::log('supplier.updated', $name);
        flash('success', 'Supplier updated.');
        redirect('suppliers');
    }

    /** Delete a supplier; suppliers with GRN history are deactivated instead. */
    public function delete(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $supplier = Supplier::find($id);
        if (!$supplier) {
            flash('error', 'Supplier not found.');
            redirect('suppliers');
        }

        if (Supplier::grnCount($id) > 0) {
            Database::update('suppliers', ['is_active' => 0, 'updated_at' => Database::now()], 'id = :id', ['id' => $id]);
            Activity::log('supplier.deactivated', $supplier['name']);
            flash('info', 'Supplier has goods-received history — deactivated instead of deleted.');
        } else {
            Database::delete('suppliers', 'id = ?', [$id]);
            Activity::log('supplier.deleted', $supplier['name']);
            flash('success', 'Supplier deleted.');
        }
        redirect('suppliers');
    }
}
