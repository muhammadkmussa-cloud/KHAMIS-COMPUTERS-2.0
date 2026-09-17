<?php
declare(strict_types=1);

class SaleController
{
    public function index(): void
    {
        Auth::requireLogin();

        $filters = [
            'q'       => trim((string) ($_GET['q'] ?? '')),
            'channel' => (string) ($_GET['channel'] ?? ''),
            'status'  => (string) ($_GET['status'] ?? ''),
            'payment' => (string) ($_GET['payment'] ?? ''),
            'source'  => strtolower(trim((string) ($_GET['source'] ?? ''))),
            'from'    => (string) ($_GET['from'] ?? ''),
            'to'      => (string) ($_GET['to'] ?? ''),
            'page'    => (int) ($_GET['page'] ?? 1),
        ];

        $result = Sale::search($filters);

        View::render('sales/index', [
            'title'   => 'Sales',
            'rows'    => $result['rows'],
            'total'   => $result['total'],
            'page'    => $result['page'],
            'pages'   => $result['pages'],
            'filters' => $filters,
            'summary' => Sale::summary(),
        ]);
    }

    /** Export the current sales view (respecting filters) as CSV. */
    public function export(): void
    {
        Auth::requireAdmin();

        $filters = [
            'q'       => trim((string) ($_GET['q'] ?? '')),
            'channel' => (string) ($_GET['channel'] ?? ''),
            'status'  => (string) ($_GET['status'] ?? ''),
            'payment' => (string) ($_GET['payment'] ?? ''),
            'source'  => strtolower(trim((string) ($_GET['source'] ?? ''))),
            'from'    => (string) ($_GET['from'] ?? ''),
            'to'      => (string) ($_GET['to'] ?? ''),
        ];

        $rows = Sale::exportAll($filters);
        $csv  = [['Sale #', 'Date', 'Channel', 'Source', 'Customer', 'Phone', 'Payment', 'Items', 'Subtotal (KSh)', 'Discount (KSh)', 'VAT (KSh)', 'Total (KSh)', 'Status', 'WhatsApp Enquiry ID']];
        foreach ($rows as $s) {
            $csv[] = [
                $s['sale_number'],
                $s['created_at'],
                $s['channel'],
                $s['sale_source'] ?? 'walk-in',
                $s['customer_name'] ?? '',
                $s['customer_phone'] ?? '',
                $s['payment_method'],
                (int) $s['item_count'],
                number_format((float) $s['subtotal'], 2, '.', ''),
                number_format((float) $s['discount'], 2, '.', ''),
                number_format((float) $s['tax_amount'], 2, '.', ''),
                number_format((float) $s['total'], 2, '.', ''),
                $s['status'],
                $s['whatsapp_enquiry_id'] ?? '',
            ];
        }

        csv_response($csv, 'sales-' . date('Ymd-His') . '.csv');
    }

    public function show(int $id): void
    {
        Auth::requireLogin();
        $sale = Sale::find($id);
        if (!$sale) {
            flash('error', 'Sale not found.');
            redirect('sales');
        }

        View::render('sales/show', [
            'title'     => $sale['sale_number'],
            'sale'      => $sale,
            'items'     => Sale::items($id),
            'returnable'=> SalesReturn::availableItems($id),
            'returns'   => self::returnsForSale($id),
            'mpesa'     => $sale['payment_method'] === 'mpesa' ? MpesaService::latestForSale($id) : null,
            'voidAudit' => $sale['status'] === 'cancelled' ? self::voidAuditForSale($id) : null,
        ]);
    }

    private function returnsForSale(int $saleId): array
    {
        return Database::fetchAll(
            'SELECT * FROM returns WHERE sale_id = ? ORDER BY id DESC',
            [$saleId]
        );
    }

    private static function voidAuditForSale(int $saleId): ?array
    {
        $row = Database::fetch(
            "SELECT a.*, u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id
              WHERE a.action = 'sale.voided' AND a.details LIKE ? ORDER BY a.id DESC LIMIT 1",
            ['{"sale_id":' . $saleId . ',%']
        );
        if (!$row) return null;
        $details = json_decode((string) ($row['details'] ?? ''), true);
        $row['reason'] = is_array($details) ? (string) ($details['reason'] ?? '') : '';
        return $row;
    }

    /** Void (cancel) a sale — admin only — and restore its stock. */
    public function void(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        try {
            $reason = self::validateVoidRequest($_POST);
            SaleService::void($id, Auth::id());
            Activity::log('sale.voided', json_encode(['sale_id' => $id, 'reason' => $reason], JSON_UNESCAPED_SLASHES));
            flash('success', 'Sale voided — stock has been restored.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('sales/' . $id);
    }

    /** Manual fallback: mark a pending M-PESA order as paid. */
    public function mpesaMarkPaid(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $sale = Sale::find($id);
        if (!$sale) {
            flash('error', 'Sale not found.');
            redirect('sales');
        }
        if ($sale['status'] !== 'pending') {
            flash('error', 'Only pending orders can be marked as paid.');
            redirect('sales/' . $id);
        }
        if ($sale['payment_method'] !== 'mpesa') {
            flash('error', 'Only pending M-PESA orders can be marked as paid here.');
            redirect('sales/' . $id);
        }
        try {
            $receipt = self::validateManualMpesaRequest($_POST);
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
            redirect('sales/' . $id);
        }
        $latest = MpesaService::latestForSale($id);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $updated = Database::update('sales', [
                'status' => 'completed', 'payment_ref' => $receipt,
            ], 'id = :id AND status = :pending', ['id' => $id, 'pending' => 'pending']);
            if ($updated !== 1) throw new RuntimeException('This order is no longer pending. Refresh and check its status.');
            if ($latest) {
                Database::update('mpesa_transactions', [
                    'status' => 'success', 'receipt_number' => $receipt,
                    'result_code' => 0, 'result_desc' => 'Manually verified from customer confirmation',
                    'updated_at' => Database::now(),
                ], 'id = :id', ['id' => (int) $latest['id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('error', $e->getMessage());
            redirect('sales/' . $id);
        }
        Activity::log('mpesa.manual_paid', json_encode(['sale_id' => $id, 'receipt' => $receipt], JSON_UNESCAPED_SLASHES));
        flash('success', 'Order marked as paid.');
        redirect('sales/' . $id);
    }

    /** Re-send an STK push to the customer's phone for a pending order. */
    public function mpesaRetry(int $id): void
    {
        Auth::requireAdmin();
        Csrf::checkOrFail();

        $sale = Sale::find($id);
        if (!$sale) {
            flash('error', 'Sale not found.');
            redirect('sales');
        }
        if ($sale['status'] !== 'pending') {
            flash('error', 'This order is not awaiting payment.');
            redirect('sales/' . $id);
        }
        if ($sale['payment_method'] !== 'mpesa') {
            flash('error', 'Only pending M-PESA orders can receive another prompt.');
            redirect('sales/' . $id);
        }
        $latest = MpesaService::latestForSale($id);
        if ($latest && $latest['status'] === 'requested' && strtotime((string) $latest['created_at']) > time() - 120) {
            flash('error', 'A prompt was sent recently. Wait two minutes before sending another.');
            redirect('sales/' . $id);
        }
        $phone = (string) ($sale['customer_phone'] ?? '');
        $push  = MpesaService::stkPush($phone, (float) $sale['total'], (string) $sale['sale_number'], $id);
        if ($push['ok']) {
            Activity::log('mpesa.retry', json_encode(['sale_id' => $id, 'phone' => $phone], JSON_UNESCAPED_SLASHES));
            flash('success', 'M-PESA prompt re-sent to ' . $phone . '.');
        } else {
            flash('error', $push['error'] ?? 'Could not reach M-PESA.');
        }
        redirect('sales/' . $id);
    }

    public static function validateVoidRequest(array $data): string
    {
        if (($data['confirm_void'] ?? '') !== '1') {
            throw new InvalidArgumentException('Confirm that you understand the stock and payment impact.');
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 5) {
            throw new InvalidArgumentException('Enter a clear reason for voiding this sale.');
        }
        return mb_substr($reason, 0, 500);
    }

    public static function validateManualMpesaRequest(array $data): string
    {
        $receipt = strtoupper(trim((string) ($data['receipt'] ?? '')));
        if (!preg_match('/^[A-Z0-9]{6,20}$/', $receipt)) {
            throw new InvalidArgumentException('Enter a valid M-PESA receipt code.');
        }
        return $receipt;
    }

    /** Download the sale as a PDF receipt (no external libraries). */
    public function pdf(int $id): void
    {
        Auth::requireLogin();
        $sale = Sale::find($id);
        if (!$sale) {
            flash('error', 'Sale not found.');
            redirect('sales');
        }
        $items = Sale::items($id);

        $L = 40.0;
        $R = 572.0;
        $right = fn (string $t, float $size) => round($R - strlen($t) * $size * 0.52, 2);

        $lines = [];
        $lines[] = ['t' => Setting::get('shop_name', config('app.name')), 'x' => $L, 'y' => 800, 's' => 18, 'b' => true];
        $lines[] = ['t' => 'Receipt ' . $sale['sale_number'], 'x' => $L, 'y' => 780, 's' => 11];
        $lines[] = ['t' => date('d M Y, h:i A', strtotime($sale['created_at'])), 'x' => $L, 'y' => 765, 's' => 9];
        $y = 740;
        foreach ($items as $it) {
            $lines[] = ['t' => $it['product_name'] . ' x' . (int)$it['quantity'], 'x' => $L, 'y' => $y, 's' => 10];
            $lines[] = ['t' => money((float)$it['line_total']), 'x' => $right(money((float)$it['line_total']), 10), 'y' => $y, 's' => 10];
            $y -= 15;
            if ($y < 80) break;
        }
        $y -= 10;
        $lines[] = ['t' => 'Total: ' . money((float)$sale['total']), 'x' => $L, 'y' => $y, 's' => 12, 'b' => true];

        $pdf = "%PDF-1.4\n";
        $objs = [];
        $objs[] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objs[] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $content = "BT\n/F1 10 Tf\n";
        foreach ($lines as $ln) {
            $font = !empty($ln['b']) ? '/F2' : '/F1';
            $size = $ln['s'] ?? 10;
            $content .= sprintf("%s %s Tf\n%s %s Td\n(%s) Tj\n", $font, $size, $ln['x'], $ln['y'], str_replace(['\\','(',')'], ['\\\\','\\(','\\)'], $ln['t'])) . "0 0 Td\n";
        }
        $content .= "ET\n";
        $objs[] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 850] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>\nendobj\n";
        $objs[] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $objs[] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>\nendobj\n";
        $objs[] = "6 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n";
        $offset = strlen($pdf);
        $xref = [];
        foreach ($objs as $obj) {
            $xref[] = $offset;
            $pdf .= $obj;
            $offset = strlen($pdf);
        }
        $pdf .= "xref\n0 " . (count($objs)+1) . "\n0000000000 65535 f \n";
        foreach ($xref as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= "trailer\n<< /Size " . (count($objs)+1) . " /Root 1 0 R >>\nstartxref\n" . $offset . "\n%%EOF";
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $sale['sale_number'] . '.pdf"');
        echo $pdf;
    }

    public function print(int $id): void
    {
        Auth::requireLogin();
        $sale = Sale::find($id);
        if (!$sale) {
            flash('error', 'Sale not found.');
            redirect('sales');
        }
        View::render('sales/print', [
            'title' => $sale['sale_number'],
            'sale'  => $sale,
            'items' => Sale::items($id),
            'size'  => (string) ($_GET['size'] ?? '80'),
        ]);
    }
}
