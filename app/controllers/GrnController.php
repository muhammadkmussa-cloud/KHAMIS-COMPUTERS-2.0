<?php
declare(strict_types=1);

class GrnController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('grn/index', [
            'title' => 'Goods Received',
            'grns'  => GoodsReceived::all(),
        ]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        View::render('grn/form', [
            'title'     => 'Receive stock',
            'products'  => Product::all(),
            'suppliers' => Supplier::active(),
            'grn'       => null,
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $ids   = (array) ($_POST['product_id'] ?? []);
        $qtys  = (array) ($_POST['qty'] ?? []);
        $costs = (array) ($_POST['cost'] ?? []);
        $serialBlocks = (array) ($_POST['serials'] ?? []);

        $lines = [];
        foreach ($ids as $i => $pid) {
            $pid = (int) $pid;
            $qty = max(1, (int) ($qtys[$i] ?? 1));
            if ($pid <= 0) {
                continue;
            }
            $serials = [];
            if (isset($serialBlocks[$i])) {
                $serials = preg_split('/\r\n|\r|\n/', trim((string) $serialBlocks[$i]));
                $serials = array_values(array_filter(array_map('trim', $serials)));
            }
            $lines[] = [
                'product_id' => $pid,
                'quantity'   => $qty,
                'unit_cost'  => (float) ($costs[$i] ?? 0),
                'serials'    => $serials,
            ];
        }

        if (!$lines) {
            flash('error', 'Add at least one product to receive.');
            redirect('grn/new');
        }

        try {
            $supplierId = (int) ($_POST['supplier_id'] ?? 0) ?: null;
            $supplier   = trim((string) ($_POST['supplier'] ?? ''));
            if ($supplierId) {
                $sp = Supplier::find($supplierId);
                if ($sp && $supplier === '') {
                    $supplier = $sp['name']; // snapshot the chosen supplier's name
                }
            }
            $grnId = GoodsReceived::create(
                $supplier,
                $lines,
                Auth::id(),
                (string) ($_POST['note'] ?? ''),
                $supplierId
            );
        } catch (Throwable $e) {
            flash('error', 'Could not save the GRN: ' . $e->getMessage());
            redirect('grn/new');
        }

        flash('success', 'Goods received and stock updated.');
        redirect('grn/' . $grnId);
    }

    public function show(int $id): void
    {
        Auth::requireLogin();
        $grn = GoodsReceived::find($id);
        if (!$grn) {
            flash('error', 'GRN not found.');
            redirect('grn');
        }
        View::render('grn/show', [
            'title' => $grn['grn_number'],
            'grn'   => $grn,
            'items' => GoodsReceived::items($id),
        ]);
    }
}
