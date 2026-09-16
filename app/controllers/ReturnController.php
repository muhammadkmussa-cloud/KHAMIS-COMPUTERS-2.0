<?php
declare(strict_types=1);

class ReturnController
{
    public function index(): void
    {
        Auth::requireLogin();
        $filters = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
            'from' => (string) ($_GET['from'] ?? ''),
            'to' => (string) ($_GET['to'] ?? ''),
        ];
        View::render('returns/index', [
            'title' => 'Returns',
            'returns' => SalesReturn::search($filters),
            'summary' => SalesReturn::summary(),
            'filters' => $filters,
        ]);
    }

    /** Search for a completed sale or show its returnable items. */
    public function create(): void
    {
        Auth::requireLogin();
        $saleId = (int) ($_GET['sale'] ?? 0);
        $sale = $saleId ? Sale::find($saleId) : null;
        $q = trim((string) ($_GET['q'] ?? ''));
        $recentSales = [];
        if (!$sale) {
            $recentSales = Sale::search(['page' => 1, 'status' => 'completed', 'q' => $q])['rows'];
        }
        $draft = $_SESSION['return_form_draft'] ?? null;
        $review = $_SESSION['return_review'] ?? null;
        if (!$draft && $sale && is_array($review) && (int) ($review['expires'] ?? 0) >= time()
            && (int) ($review['draft']['sale_id'] ?? 0) === $saleId) {
            $draft = $review['draft'];
        }
        View::render('returns/form', [
            'title' => 'New return',
            'sale' => $sale,
            'saleItems' => $sale ? SalesReturn::availableItems($saleId) : [],
            'recentSales' => $recentSales,
            'q' => $q,
            'draft' => $draft,
            'errors' => [],
        ]);
        unset($_SESSION['return_form_draft']);
    }

    /** Validate and render an immutable review; this does not write stock or return rows. */
    public function review(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        $raw = self::draftFromRequest();
        $validated = SalesReturn::validateDraft(
            $raw['sale_id'], $raw['items'], $raw['reason'], $raw['evidence_note'], $raw['refund_method']
        );
        if ($validated['errors']) {
            http_response_code(422);
            $sale = $raw['sale_id'] ? Sale::find($raw['sale_id']) : null;
            View::render('returns/form', [
                'title' => 'New return',
                'sale' => $sale,
                'saleItems' => $sale ? SalesReturn::availableItems((int) $raw['sale_id']) : [],
                'recentSales' => [],
                'q' => '',
                'draft' => $raw,
                'errors' => $validated['errors'],
            ]);
            return;
        }
        $token = bin2hex(random_bytes(24));
        $_SESSION['return_review'] = ['token' => $token, 'draft' => $validated['draft'], 'expires' => time() + 1800];
        View::render('returns/review', ['title' => 'Review return', 'draft' => $validated['draft'], 'reviewToken' => $token]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        $token = (string) ($_POST['review_token'] ?? '');
        $review = $_SESSION['return_review'] ?? null;
        if (!is_array($review) || $token === '' || !hash_equals((string) ($review['token'] ?? ''), $token) || (int) ($review['expires'] ?? 0) < time()) {
            unset($_SESSION['return_review']);
            flash('error', 'Review the return details before creating the request.');
            redirect('returns/new');
        }
        try {
            $retId = SalesReturn::createFromDraft((array) $review['draft'], Auth::id());
        } catch (Throwable $e) {
            $_SESSION['return_form_draft'] = $review['draft'];
            flash('error', 'Could not create the return: ' . $e->getMessage());
            redirect('returns/new?sale=' . (int) ($review['draft']['sale_id'] ?? 0));
        }
        unset($_SESSION['return_review']);
        Activity::log('return.created', json_encode(['return_id' => $retId, 'refund_amount' => $review['draft']['refund_total']], JSON_UNESCAPED_SLASHES));
        flash('success', 'Return request created and sent for approval.');
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
        View::render('returns/show', ['title' => $r['return_number'], 'r' => $r, 'items' => SalesReturn::items($id)]);
    }

    public function approve(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        try {
            SalesReturn::approve($id, (array) ($_POST['outcome'] ?? []), (string) ($_POST['decision_note'] ?? ''), Auth::id());
            Activity::log('return.approved', json_encode(['return_id' => $id, 'outcomes' => (array) ($_POST['outcome'] ?? [])], JSON_UNESCAPED_SLASHES));
            flash('success', 'Return approved with the recorded stock outcomes.');
        } catch (Throwable $e) {
            flash('error', 'Could not approve: ' . $e->getMessage());
        }
        redirect('returns/' . $id);
    }

    public function reject(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();
        try {
            SalesReturn::reject($id, (string) ($_POST['decision_note'] ?? ''), Auth::id());
            Activity::log('return.rejected', json_encode(['return_id' => $id], JSON_UNESCAPED_SLASHES));
            flash('success', 'Return rejected with a recorded decision note.');
        } catch (Throwable $e) {
            flash('error', 'Could not reject: ' . $e->getMessage());
        }
        redirect('returns/' . $id);
    }

    private static function draftFromRequest(): array
    {
        $selected = array_values(array_unique(array_map('intval', (array) ($_POST['item_id'] ?? []))));
        $qty = (array) ($_POST['qty'] ?? []);
        $amount = (array) ($_POST['amount'] ?? []);
        $items = [];
        foreach ($selected as $saleItemId) {
            $items[] = [
                'sale_item_id' => $saleItemId,
                'quantity' => (string) ($qty[$saleItemId] ?? ''),
                'refund_amount' => (string) ($amount[$saleItemId] ?? ''),
            ];
        }
        return [
            'sale_id' => (int) ($_POST['sale_id'] ?? 0),
            'items' => $items,
            'reason' => trim((string) ($_POST['reason'] ?? '')),
            'evidence_note' => trim((string) ($_POST['evidence_note'] ?? '')),
            'refund_method' => (string) ($_POST['refund_method'] ?? 'original'),
        ];
    }
}
