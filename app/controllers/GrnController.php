<?php
declare(strict_types=1);

class GrnController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'supplier_id' => (string) ($_GET['supplier_id'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to' => (string) ($_GET['date_to'] ?? ''),
        ];
        $grns = GoodsReceived::search($filters);
        View::render('grn/index', [
            'title' => 'Goods Received',
            'grns'  => $grns,
            'filters' => $filters,
            'suppliers' => Supplier::all(),
            'summary' => GoodsReceived::summary($grns),
        ]);
    }

    public function create(): void
    {
        Auth::requireAdmin();
        $draft = null;
        $token = (string) ($_GET['draft'] ?? '');
        if ($token !== '' && isset($_SESSION['grn_review'][$token])) {
            $review = $_SESSION['grn_review'][$token];
            if ((int) ($review['created_at'] ?? 0) >= time() - 1800) $draft = $review['draft'];
        }
        View::render('grn/form', [
            'title'     => 'Receive stock',
            'products'  => Product::all(),
            'suppliers' => Supplier::active(),
            'draft'     => $draft,
            'prefillProduct' => max(0, (int) ($_GET['product'] ?? 0)),
            'prefillSupplierName' => trim((string) ($_GET['supplier_name'] ?? '')),
            'errors' => [],
        ]);
    }

    public function review(): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        $input = self::draftFromRequest($_POST);
        $validated = GoodsReceived::validateDraft($input);
        if ($validated['errors']) {
            http_response_code(422);
            View::render('grn/form', [
                'title' => 'Receive stock',
                'products' => Product::all(),
                'suppliers' => Supplier::active(),
                'draft' => $validated['draft'],
                'prefillProduct' => 0,
                'prefillSupplierName' => '',
                'errors' => $validated['errors'],
            ]);
            return;
        }
        $token = bin2hex(random_bytes(24));
        $_SESSION['grn_review'] = [$token => ['draft' => $validated['draft'], 'created_at' => time()]];
        View::render('grn/review', [
            'title' => 'Review stock receipt',
            'draft' => $validated['draft'],
            'reviewToken' => $token,
        ]);
    }

    public function store(): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        $token = (string) ($_POST['review_token'] ?? '');
        $review = $token !== '' ? ($_SESSION['grn_review'][$token] ?? null) : null;
        if (!$review || (int) ($review['created_at'] ?? 0) < time() - 1800) {
            flash('error', 'The receiving review expired. Check the delivery again before posting it.');
            redirect('grn/new');
        }
        try {
            $grnId = GoodsReceived::createFromDraft($review['draft'], Auth::id());
        } catch (Throwable $e) {
            flash('error', 'Could not save the GRN: ' . $e->getMessage());
            redirect('grn/new?draft=' . rawurlencode($token));
        }
        unset($_SESSION['grn_review'][$token]);
        Activity::log('grn.posted', json_encode(['grn_id' => $grnId, 'supplier' => $review['draft']['supplier'], 'total_cost' => $review['draft']['total_cost']], JSON_UNESCAPED_SLASHES));
        flash('success', 'Goods received and stock updated.');
        redirect('grn/' . $grnId);
    }

    public function show(int $id): void
    {
        Auth::requireAdmin();
        $grn = GoodsReceived::find($id);
        if (!$grn) {
            flash('error', 'GRN not found.');
            redirect('grn');
        }
        View::render('grn/show', [
            'title' => $grn['grn_number'],
            'grn'   => $grn,
            'items' => GoodsReceived::items($id),
            'serialsByItem' => GoodsReceived::serialsByItem($id),
        ]);
    }

    public function export(): void
    {
        Auth::requireAdmin();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'supplier_id' => (string) ($_GET['supplier_id'] ?? ''),
            'date_from' => (string) ($_GET['date_from'] ?? ''),
            'date_to' => (string) ($_GET['date_to'] ?? ''),
        ];
        $csv = [['GRN', 'Supplier', 'Supplier reference', 'Lines', 'Units', 'Total cost (KSh)', 'Status', 'Received by', 'Received at']];
        foreach (GoodsReceived::search($filters) as $grn) {
            $csv[] = [$grn['grn_number'], csv_safe_cell((string) $grn['supplier']), csv_safe_cell((string) ($grn['supplier_reference'] ?? '')), $grn['item_count'], $grn['total_quantity'], number_format((float) $grn['total_cost'], 2, '.', ''), 'Posted', csv_safe_cell((string) ($grn['user_name'] ?? '')), $grn['created_at']];
        }
        csv_response($csv, 'goods-received-' . date('Ymd-His') . '.csv');
    }

    private static function draftFromRequest(array $post): array
    {
        $ids = (array) ($post['product_id'] ?? []);
        $qtys = (array) ($post['qty'] ?? []);
        $costs = (array) ($post['cost'] ?? []);
        $serialBlocks = (array) ($post['serials'] ?? []);
        $lines = [];
        foreach ($ids as $index => $productId) {
            $serials = preg_split('/\r\n|\r|\n/', trim((string) ($serialBlocks[$index] ?? ''))) ?: [];
            $lines[] = [
                'product_id' => (int) $productId,
                'quantity' => trim((string) ($qtys[$index] ?? '')),
                'unit_cost' => $costs[$index] ?? '',
                'serials' => $serials,
            ];
        }
        return [
            'supplier_id' => (int) ($post['supplier_id'] ?? 0) ?: null,
            'supplier' => trim((string) ($post['supplier'] ?? '')),
            'supplier_reference' => trim((string) ($post['supplier_reference'] ?? '')),
            'note' => trim((string) ($post['note'] ?? '')),
            'lines' => $lines,
        ];
    }
}
