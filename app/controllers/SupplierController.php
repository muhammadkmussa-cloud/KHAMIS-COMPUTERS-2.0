<?php
declare(strict_types=1);

class SupplierController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
        ];
        $suppliers = Supplier::all($filters);
        $history = [];
        foreach ($suppliers as $supplier) {
            $history[(int) $supplier['id']] = Supplier::recentGrns((int) $supplier['id']);
        }
        View::render('suppliers/index', [
            'title'     => 'Suppliers',
            'suppliers' => $suppliers,
            'filters'   => $filters,
            'summary'   => Supplier::summary(),
            'history'   => $history,
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $errors = Supplier::validate($_POST);
        $name   = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '' && Database::fetchValue('SELECT COUNT(*) FROM suppliers WHERE LOWER(name) = LOWER(?)', [$name]) > 0) {
            $errors[] = 'A supplier with that name already exists.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            set_old($_POST);
            redirect('suppliers?add=1');
        }

        try {
            Supplier::create($_POST);
        } catch (Throwable $e) {
            if (!Database::isDuplicateKey($e)) throw $e;
            set_old($_POST);
            flash('error', 'That supplier name was just used. Choose a unique name.');
            redirect('suppliers?add=1');
        }
        Activity::log('supplier.created', $name);
        flash('success', 'Supplier added.');
        redirect('suppliers');
    }

    public function update(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $existing = Supplier::find($id);
        if (!$existing) {
            flash('error', 'Supplier not found.');
            redirect('suppliers');
        }

        $errors = Supplier::validate($_POST);
        $name   = trim((string) ($_POST['name'] ?? ''));
        if ($name !== '' && $name !== $existing['name']
            && Database::fetchValue('SELECT COUNT(*) FROM suppliers WHERE LOWER(name) = LOWER(?) AND id != ?', [$name, $id]) > 0) {
            $errors[] = 'A supplier with that name already exists.';
        }
        if ($errors) {
            flash('error', implode(' ', $errors));
            redirect('suppliers?edit=' . $id);
        }

        try {
            Supplier::update($id, $_POST);
        } catch (Throwable $e) {
            if (!Database::isDuplicateKey($e)) throw $e;
            flash('error', 'That supplier name was just used. Choose a unique name.');
            redirect('suppliers?edit=' . $id);
        }
        Activity::log('supplier.updated', $name);
        flash('success', 'Supplier updated.');
        redirect('suppliers');
    }

    /** Delete a supplier; suppliers with GRN history are deactivated instead. */
    public function delete(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $supplier = Supplier::find($id);
        if (!$supplier) {
            flash('error', 'Supplier not found.');
            redirect('suppliers');
        }

        if (Supplier::grnCount($id) > 0) {
            flash('error', 'This supplier has delivery history. Deactivate it instead of deleting it.');
            redirect('suppliers?edit=' . $id);
        }
        Database::delete('suppliers', 'id = ?', [$id]);
        Activity::log('supplier.deleted', $supplier['name']);
        flash('success', 'Supplier deleted.');
        redirect('suppliers');
    }

    public function setStatus(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        $supplier = Supplier::find($id);
        if (!$supplier) {
            flash('error', 'Supplier not found.');
            redirect('suppliers');
        }
        $active = ($_POST['active'] ?? '') === '1' ? 1 : 0;
        Database::update('suppliers', ['is_active' => $active, 'updated_at' => Database::now()], 'id = :id', ['id' => $id]);
        Activity::log($active ? 'supplier.reactivated' : 'supplier.deactivated', $supplier['name']);
        flash('success', $active ? 'Supplier reactivated.' : 'Supplier deactivated. Existing delivery history is preserved.');
        redirect('suppliers');
    }
}
