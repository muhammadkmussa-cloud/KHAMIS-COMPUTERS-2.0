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
        ]);
    }

    /** Export the current sales view (respecting filters) as CSV. */
    public function export(): void
    {
        Auth::requireLogin();

        $filters = [
            'q'       => trim((string) ($_GET['q'] ?? '')),
            'channel' => (string) ($_GET['channel'] ?? ''),
            'status'  => (string) ($_GET['status'] ?? ''),
            'payment' => (string) ($_GET['payment'] ?? ''),
            'from'    => (string) ($_GET['from'] ?? ''),
            'to'      => (string) ($_GET['to'] ?? ''),
        ];

        $rows = Sale::exportAll($filters);
        $csv  = [['Sale #', 'Date', 'Channel', 'Customer', 'Phone', 'Payment', 'Items', 'Subtotal (KSh)', 'Discount (KSh)', 'VAT (KSh)', 'Total (KSh)', 'Status']];
        foreach ($rows as $s) {
            $csv[] = [
                $s['sale_number'],
                $s['created_at'],
                $s['channel'],
                $s['customer_name'] ?? '',
                $s['customer_phone'] ?? '',
                $s['payment_method'],
                (int) $s['item_count'],
                number_format((float) $s['subtotal'], 2, '.', ''),
                number_format((float) $s['discount'], 2, '.', ''),
                number_format((float) $s['tax_amount'], 2, '.', ''),
                number_format((float) $s['total'], 2, '.', ''),
                $s['status'],
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
        ]);
    }

    private function returnsForSale(int $saleId): array
    {
        return Database::fetchAll(
            'SELECT * FROM returns WHERE sale_id = ? ORDER BY id DESC',
            [$saleId]
        );
    }

    /** Void (cancel) a sale — admin only — and restore its stock. */
    public function void(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        try {
            SaleService::void($id, Auth::id());
            Activity::log('sale.voided', 'Voided sale #' . $id);
            flash('success', 'Sale voided — stock has been restored.');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('sales/' . $id);
    }

    /** Manual fallback: mark a pending M-PESA order as paid. */
    public function mpesaMarkPaid(int $id): void
    {
        Auth::requireLogin();
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
        Database::update('sales', [
            'status'      => 'completed',
            'payment_ref' => trim((string) ($_POST['receipt'] ?? 'Manual confirmation')) ?: null,
        ], 'id = :id', ['id' => $id]);
        Activity::log('mpesa.manual_paid', 'sale #' . $id);
        flash('success', 'Order marked as paid.');
        redirect('sales/' . $id);
    }

    /** Re-send an STK push to the customer's phone for a pending order. */
    public function mpesaRetry(int $id): void
    {
        Auth::requireLogin();
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
        $phone = (string) ($sale['customer_phone'] ?? '');
        $push  = MpesaService::stkPush($phone, (float) $sale['total'], (string) $sale['sale_number'], $id);
        if ($push['ok']) {
            flash('success', 'M-PESA prompt re-sent to ' . $phone . '.');
        } else {
            flash('error', $push['error'] ?? 'Could not reach M-PESA.');
        }
        redirect('sales/' . $id);
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

        $shop  = Setting::get('shop_name', config('app.name'));
        $addr  = Setting::get('shop_address', '');
        $phone = Setting::get('shop_phone', '');
        $foot  = Setting::get('receipt_footer', 'Thank you for shopping with us.');

        $y = 758.0;
        $lines = [];
        $push = function (array $l) use (&$lines): void { $lines[] = $l; };

        $push(['text' => $shop, 'x' => $L, 'y' => $y, 'size' => 16, 'bold' => true]);
        $y -= 15;
        if ($addr !== '') { $push(['text' => $addr, 'x' => $L, 'y' => $y, 'size' => 9]); $y -= 12; }
        if ($phone !== '') { $push(['text' => $phone, 'x' => $L, 'y' => $y, 'size' => 9]); $y -= 12; }

        $push(['text' => 'TAX RECEIPT', 'x' => $right('TAX RECEIPT', 11), 'y' => 742, 'size' => 11, 'bold' => true]);
        $y -= 4;
        $push(['text' => str_repeat('-', 78), 'x' => $L, 'y' => $y, 'size' => 8]);
        $y -= 16;

        $meta = [
            'Receipt'   => $sale['sale_number'],
            'Date'      => date('d M Y  h:i A', strtotime($sale['created_at'])),
            'Channel'   => strtoupper($sale['channel']),
            'Payment'   => strtoupper($sale['payment_method']) . ($sale['payment_ref'] ? '  ' . $sale['payment_ref'] : ''),
            'Customer'  => $sale['customer_name'] ?? 'Walk-in',
            'Served by' => $sale['user_name'] ?? '-',
        ];
        if (!empty($sale['customer_phone'])) {
            $meta['Phone'] = $sale['customer_phone'];
        }
        foreach ($meta as $k => $v) {
            $push(['text' => $k . ':', 'x' => $L, 'y' => $y, 'size' => 10, 'bold' => true]);
            $push(['text' => (string) $v, 'x' => $L + 90, 'y' => $y, 'size' => 10]);
            $y -= 14;
        }
        $y -= 2;
        $push(['text' => str_repeat('-', 78), 'x' => $L, 'y' => $y, 'size' => 8]);
        $y -= 18;

        foreach ($items as $i) {
            $push(['text' => (string) $i['product_name'], 'x' => $L, 'y' => $y, 'size' => 9, 'bold' => true]);
            $y -= 12;
            $q = money($i['unit_price']) . '  x  ' . (int) $i['quantity'];
            $push(['text' => $q, 'x' => $L + 12, 'y' => $y, 'size' => 9]);
            $push(['text' => money($i['line_total']), 'x' => $right(money($i['line_total']), 9), 'y' => $y, 'size' => 9, 'bold' => true]);
            $y -= 12;
            if ($i['serial_number']) {
                $push(['text' => 'SN: ' . $i['serial_number'], 'x' => $L + 12, 'y' => $y, 'size' => 8]);
                $y -= 11;
            }
            if (!empty($i['warranty_expires'])) {
                $push(['text' => 'Warranty until: ' . $i['warranty_expires'], 'x' => $L + 12, 'y' => $y, 'size' => 8]);
                $y -= 11;
            }
        }
        $y -= 4;
        $push(['text' => str_repeat('-', 78), 'x' => $L, 'y' => $y, 'size' => 8]);
        $y -= 16;

        $totals = ['Subtotal' => money($sale['subtotal'])];
        if ((float) $sale['discount'] > 0) {
            $totals['Discount'] = '- ' . money($sale['discount']);
        }
        $totals['VAT (' . vat_rate() . '%)'] = money($sale['tax_amount']);
        foreach ($totals as $k => $v) {
            $push(['text' => $k, 'x' => $L, 'y' => $y, 'size' => 10]);
            $push(['text' => $v, 'x' => $right($v, 10), 'y' => $y, 'size' => 10]);
            $y -= 14;
        }
        $totalTxt = money($sale['total']);
        $push(['text' => 'TOTAL', 'x' => $L, 'y' => $y, 'size' => 13, 'bold' => true]);
        $push(['text' => $totalTxt, 'x' => $right($totalTxt, 13), 'y' => $y, 'size' => 13, 'bold' => true]);
        $y -= 34;

        $push(['text' => $foot, 'x' => $L, 'y' => $y, 'size' => 9]);
        $y -= 12;
        $push(['text' => 'Prices include VAT unless stated. Warranty claims require this receipt.', 'x' => $L, 'y' => $y, 'size' => 8]);

        $pdf = Pdf::generate($lines, $sale['sale_number']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $sale['sale_number'] . '.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    /**
     * Standalone 80mm thermal receipt — prints like a normal shop/supermarket
     * till receipt on 80mm roll paper (change the single "size: 80mm" value to
     * "58mm" for smaller printers). Rendered as its own page so the app layout
     * never gets in the way; auto-opens the browser's print dialog.
     */
    public function printThermal(int $id): void
    {
        Auth::requireLogin();
        $sale = Sale::find($id);
        if (!$sale) {
            flash('error', 'Sale not found.');
            redirect('sales');
        }
        $items = Sale::items($id);

        $shop  = Setting::get('shop_name', config('app.name'));
        $addr  = Setting::get('shop_address', '');
        $phone = Setting::get('shop_phone', '');
        $foot  = Setting::get('receipt_footer', 'Thank you for shopping with us.');
        $num   = fn ($v) => number_format((float) $v, 2, '.', ',');
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($sale['sale_number']) ?></title>
<style>
  @page { size: 80mm auto; margin: 0; }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: #fff; }
  body { width: 80mm; padding: 4mm 3mm; font-family: "Courier New", ui-monospace, Menlo, Consolas, monospace;
         font-size: 11px; line-height: 1.4; color: #000; }
  .center { text-align: center; }
  .b { font-weight: 700; }
  h1 { font-size: 15px; margin: 0 0 1px; letter-spacing: .4px; }
  .sub { font-size: 10px; margin: 0; }
  hr.d { border: none; border-top: 1px dashed #000; margin: 5px 0; }
  .row { display: flex; justify-content: space-between; gap: 6px; }
  .row span:last-child { text-align: right; white-space: nowrap; }
  .item { margin: 3px 0; }
  .sn { font-size: 9.5px; }
  .totals .row { margin: 1px 0; }
  .grand { font-size: 14px; font-weight: 700; border-top: 1px solid #000; margin-top: 3px; padding-top: 2px; }
  .footer { text-align: center; font-size: 10px; margin-top: 4px; }
  .footer p { margin: 1px 0; }
  .voided { text-align: center; font-weight: 700; font-size: 13px; letter-spacing: 2px; margin: 4px 0; }
  .toolbar { text-align: center; margin-top: 8px; }
  .toolbar button { font-family: system-ui, sans-serif; font-size: 13px; padding: 8px 18px; border: 1px solid #000; background: #fff; border-radius: 6px; cursor: pointer; }
  @media print { .toolbar { display: none; } }
</style>
</head>
<body>
  <div class="center">
    <h1 class="b"><?= e($shop) ?></h1>
    <?php if ($addr !== ''): ?><p class="sub"><?= e($addr) ?></p><?php endif; ?>
    <?php if ($phone !== ''): ?><p class="sub">Tel: <?= e($phone) ?></p><?php endif; ?>
  </div>
  <hr class="d">
  <div class="row"><span>Receipt</span><span class="b"><?= e($sale['sale_number']) ?></span></div>
  <div class="row"><span>Date</span><span><?= e(date('d M Y  h:i A', strtotime($sale['created_at']))) ?></span></div>
  <div class="row"><span>Cashier</span><span><?= e($sale['user_name'] ?? '-') ?></span></div>
  <?php if (!empty($sale['customer_name'])): ?><div class="row"><span>Customer</span><span><?= e($sale['customer_name']) ?></span></div><?php endif; ?>
  <?php if (!empty($sale['customer_phone'])): ?><div class="row"><span>Phone</span><span><?= e($sale['customer_phone']) ?></span></div><?php endif; ?>
  <hr class="d">
  <?php foreach ($items as $i): ?>
    <div class="item">
      <div class="row"><span class="b"><?= e($i['product_name']) ?></span></div>
      <?php if ($i['serial_number']): ?><div class="sn">SN: <?= e($i['serial_number']) ?></div><?php endif; ?>
      <div class="row"><span><?= (int) $i['quantity'] ?> x <?= $num($i['unit_price']) ?></span><span><?= $num($i['line_total']) ?></span></div>
    </div>
  <?php endforeach; ?>
  <hr class="d">
  <div class="totals">
    <div class="row"><span>Subtotal</span><span><?= $num($sale['subtotal']) ?></span></div>
    <?php if ((float) $sale['discount'] > 0): ?><div class="row"><span>Discount</span><span>-<?= $num($sale['discount']) ?></span></div><?php endif; ?>
    <div class="row"><span>VAT (<?= e((string) vat_rate()) ?>%)</span><span><?= $num($sale['tax_amount']) ?></span></div>
    <?php if ((float) $sale['delivery_fee'] > 0): ?><div class="row"><span>Delivery</span><span><?= $num($sale['delivery_fee']) ?></span></div><?php endif; ?>
    <div class="row grand"><span>TOTAL</span><span><?= money((float) $sale['total']) ?></span></div>
  </div>
  <hr class="d">
  <div class="row"><span>Payment</span><span><?= e(strtoupper($sale['payment_method'])) ?><?= $sale['payment_ref'] ? ' · ' . e($sale['payment_ref']) : '' ?></span></div>
  <?php if ($sale['status'] === 'pending'): ?><div class="row"><span>Status</span><span>PENDING</span></div><?php endif; ?>
  <?php if ($sale['status'] === 'cancelled'): ?><div class="voided">*** CANCELLED ***</div><?php endif; ?>
  <hr class="d">
  <div class="footer">
    <p><?= e($foot) ?></p>
    <p>Prices include VAT unless stated. Warranty claims require this receipt.</p>
    <p>Thank you for shopping with us!</p>
  </div>
  <div class="toolbar"><button type="button" onclick="window.print()">🖨 Print receipt</button></div>
  <script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 150); });</script>
</body>
</html><?php
        exit;
    }
}
