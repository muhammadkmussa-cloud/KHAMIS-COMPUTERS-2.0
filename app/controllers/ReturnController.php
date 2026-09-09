<?php
declare(strict_types=1);

class ReturnController
{
    public function index(): void
    {
        Auth::requireLogin();
        View::render('returns/index', [
            'title'   => 'Returns',
            'returns' => SalesReturn::all(),
        ]);
    }

    /** Form to create a return. With ?sale=ID it shows that sale's returnable items. */
    public function create(): void
    {
        Auth::requireLogin();

        $saleId = (int) ($_GET['sale'] ?? 0);
        $sale   = $saleId ? Sale::find($saleId) : null;

        $recentSales = [];
        if (!$sale) {
            $recentSales = Sale::search(['page' => 1, 'status' => 'completed'])['rows'];
        }

        View::render('returns/form', [
            'title'        => 'New return',
            'sale'         => $sale,
            'saleItems'    => $sale ? SalesReturn::availableItems($saleId) : [],
            'recentSales'  => $recentSales,
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $saleId = (int) ($_POST['sale_id'] ?? 0);
        $reason = trim((string) ($_POST['reason'] ?? ''));
        $sale   = $saleId ? Sale::find($saleId) : null;
        if (!$sale) {
            flash('error', 'Please choose a sale to return items from.');
            redirect('returns/new');
        }

        $itemIds  = (array) ($_POST['item_id'] ?? []);
        $qtys     = (array) ($_POST['qty'] ?? []);
        $amounts  = (array) ($_POST['amount'] ?? []);

        // qty/amount are keyed by sale_item_id, so a skipped checkbox can't
        // misalign the submitted quantities and refunds.
        $items = [];
        foreach ($itemIds as $siId) {
            $siId = (int) $siId;
            $qty  = max(1, (int) ($qtys[$siId] ?? 1));
            $amt  = (float) ($amounts[$siId] ?? 0);
            if ($siId <= 0 || $amt < 0) {
                continue;
            }
            $items[] = ['sale_item_id' => $siId, 'quantity' => $qty, 'refund_amount' => $amt];
        }

        if (!$items) {
            flash('error', 'Select at least one item to return.');
            redirect('returns/new?sale=' . $saleId);
        }

        try {
            $retId = SalesReturn::create($saleId, $items, $reason, Auth::id());
        } catch (Throwable $e) {
            flash('error', 'Could not create the return: ' . $e->getMessage());
            redirect('returns/new?sale=' . $saleId);
        }

        flash('success', 'Return created — it is pending approval.');
        redirect('returns/' . $retId);
    }

    public function show(int $id): void
    {
        Auth::requireLogin();
        $r = SalesReturn::find($id);
        if (!$r) {
            flash('error', 'Return not found.');
            redirect('returns');
        }
        View::render('returns/show', [
            'title' => $r['return_number'],
            'r'     => $r,
            'items' => SalesReturn::items($id),
        ]);
    }

    public function approve(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        if (!Auth::isAdmin()) {
            flash('error', 'Only admins can approve returns.');
            redirect('returns/' . $id);
        }
        try {
            SalesReturn::approve($id);
        } catch (Throwable $e) {
            flash('error', 'Could not approve: ' . $e->getMessage());
            redirect('returns/' . $id);
        }
        flash('success', 'Return approved — stock has been restored.');
        redirect('returns/' . $id);
    }

    public function reject(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        if (!Auth::isAdmin()) {
            flash('error', 'Only admins can reject returns.');
            redirect('returns/' . $id);
        }
        SalesReturn::reject($id);
        flash('success', 'Return rejected.');
        redirect('returns/' . $id);
    }
}
