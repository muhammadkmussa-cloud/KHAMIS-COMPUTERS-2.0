<?php
declare(strict_types=1);

class ReportController
{
    public function zReport(): void
    {
        Auth::requireLogin();

        $date   = self::validDate($_GET['date'] ?? '', date('Y-m-d'));
        $userId = isset($_GET['user']) ? (int) $_GET['user'] : null;
        if ($userId === 0) {
            $userId = null; // "All staff"
        }
        if ($userId !== null && !Auth::isAdmin() && $userId !== Auth::id()) {
            $userId = Auth::id(); // cashiers only see their own day
        }
        if (!Auth::isAdmin()) {
            $userId = Auth::id();
        }

        View::render('reports/z', [
            'title'    => 'Z-report (close of day)',
            'date'     => $date,
            'userId'   => $userId,
            'users'    => Database::fetchAll('SELECT id, name, role, is_active FROM users ORDER BY id'),
            'summary'  => ZReport::summary($date, $userId),
            'closed'   => $userId !== null
                ? Database::fetch(
                    'SELECT z.*, u.name AS user_name FROM z_reports z LEFT JOIN users u ON u.id = z.user_id WHERE z.user_id = ? AND z.report_date = ?',
                    [$userId, $date]
                )
                : null,
            'history'  => ZReport::all(30),
        ]);
    }

    public function closeDay(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $date   = self::validDate($_POST['date'] ?? '', date('Y-m-d'));
        $userId = (int) ($_POST['user_id'] ?? Auth::id());
        if (!Auth::isAdmin() && $userId !== Auth::id()) {
            flash('error', 'You can only close your own day.');
            redirect('reports/z');
        }

        $id    = ZReport::close($date, $userId, (string) ($_POST['notes'] ?? ''));
        $user  = Database::fetch('SELECT name FROM users WHERE id = ?', [$userId]);
        Activity::log('zreport.closed', ($user['name'] ?? 'user') . ' ' . $date);
        flash('success', 'Day closed — Z-report #' . $id . ' saved for ' . $date . '.');
        redirect('reports/z?date=' . $date . '&user=' . $userId);
    }

    public function index(): void
    {
        Auth::requireLogin();

        [$from, $to] = self::range($_GET['from'] ?? '', $_GET['to'] ?? '');
        $m           = self::metrics($from, $to);

        // Revenue by day (cap the window to 60 days for a readable chart).
        $chartFrom = $from;
        $dayDiff   = (int) ((strtotime($to) - strtotime($chartFrom)) / 86400);
        if ($dayDiff > 60) {
            $chartFrom = date('Y-m-d', strtotime($to . ' -13 days'));
        }
        $days = [];
        for ($d = strtotime($chartFrom); $d <= strtotime($to); $d += 86400) {
            $days[date('Y-m-d', $d)] = 0.0;
        }
        $dailyRows = Database::fetchAll(
            "SELECT DATE(created_at) AS d, SUM(total) AS rev
               FROM sales WHERE status = 'completed' AND DATE(created_at) >= :from AND DATE(created_at) <= :to
              GROUP BY DATE(created_at)",
            ['from' => $chartFrom, 'to' => $to]
        );
        foreach ($dailyRows as $row) {
            if (isset($days[$row['d']])) {
                $days[$row['d']] = (float) $row['rev'];
            }
        }

        View::render('reports/index', [
            'title'       => 'Reports',
            'from'        => $from,
            'to'          => $to,
            'revenue'     => $m['revenue'],
            'orders'      => $m['orders'],
            'avgOrder'    => $m['orders'] > 0 ? $m['revenue'] / $m['orders'] : 0,
            'refunds'     => $m['refunds'],
            'expenses'    => $m['expenses'],
            'cogs'        => $m['cogs'],
            'grossProfit' => $m['revenue'] - $m['cogs'],
            'net'         => $m['revenue'] - $m['refunds'] - $m['expenses'],
            'channels'    => $m['channels'],
            'days'        => $days,
            'topProducts' => $m['topProducts'],
            'lowStock'    => Product::lowStock(),
        ]);
    }

    /** Export the report summary + top products for the same date window. */
    public function export(): void
    {
        Auth::requireLogin();

        [$from, $to] = self::range($_GET['from'] ?? '', $_GET['to'] ?? '');
        $m           = self::metrics($from, $to);
        $orders      = $m['orders'];
        $net         = $m['revenue'] - $m['refunds'] - $m['expenses'];
        $gross       = $m['revenue'] - $m['cogs'];

        $csv = [
            ['Metric', 'Value'],
            ['Report period', $from . ' to ' . $to],
            ['Revenue (KSh)', number_format($m['revenue'], 2, '.', '')],
            ['Completed orders', $orders],
            ['Average order value (KSh)', number_format($orders > 0 ? $m['revenue'] / $orders : 0, 2, '.', '')],
            ['Refunds (KSh)', number_format($m['refunds'], 2, '.', '')],
            ['Expenses (KSh)', number_format($m['expenses'], 2, '.', '')],
            ['Cost of goods (KSh)', number_format($m['cogs'], 2, '.', '')],
            ['Gross profit (KSh)', number_format($gross, 2, '.', '')],
            ['Net result (KSh)', number_format($net, 2, '.', '')],
            [],
            ['Top products'],
            ['Product', 'SKU', 'Qty sold', 'Revenue (KSh)'],
        ];
        foreach ($m['topProducts'] as $p) {
            $csv[] = [
                $p['name'], $p['sku'], (int) $p['qty'], number_format((float) $p['rev'], 2, '.', ''),
            ];
        }

        csv_response($csv, 'report-' . $from . '-to-' . $to . '.csv');
    }

    /* --------------------------- VAT report (KRA) --------------------------- */

    public function vatReport(): void
    {
        Auth::requireLogin();

        [$from, $to] = self::range($_GET['from'] ?? '', $_GET['to'] ?? '');
        View::render('reports/vat', [
            'title' => 'VAT report (KRA)',
            'from'  => $from,
            'to'    => $to,
            'rate'  => vat_rate(),
            'vat'   => self::vatData($from, $to),
        ]);
    }

    public function vatExport(): void
    {
        Auth::requireLogin();

        [$from, $to] = self::range($_GET['from'] ?? '', $_GET['to'] ?? '');
        $d = self::vatData($from, $to);

        $csv = [
            ['Khamis Computers — VAT report (KRA)'],
            ['Period', $from . ' to ' . $to],
            ['VAT rate (%)', number_format(vat_rate(), 2, '.', '')],
            [],
            ['Summary'],
            ['Output VAT — sales (KSh)', number_format($d['output'], 2, '.', '')],
            ['Less: VAT on returns (KSh)', number_format($d['return_vat'], 2, '.', '')],
            ['Input VAT — purchases, estimated (KSh)', number_format($d['input'], 2, '.', '')],
            ['Net VAT payable (KSh)', number_format($d['net'], 2, '.', '')],
            [],
            ['Sales detail (completed)'],
            ['Date', 'Sale #', 'Customer', 'Payment', 'Net excl. VAT', 'Output VAT'],
        ];
        foreach ($d['sales'] as $s) {
            $csv[] = [
                substr((string) $s['created_at'], 0, 10),
                $s['sale_number'],
                (string) ($s['customer_name'] ?? 'Walk-in'),
                (string) $s['payment_method'],
                number_format((float) ((float) $s['subtotal'] - (float) $s['discount']), 2, '.', ''),
                number_format((float) $s['tax_amount'], 2, '.', ''),
            ];
        }
        $csv[] = [];
        $csv[] = ['Purchases detail (goods received)'];
        $csv[] = ['Date', 'GRN #', 'Supplier', 'Net cost (excl. VAT)', 'Est. input VAT'];
        foreach ($d['purchases'] as $p) {
            $csv[] = [
                substr((string) $p['created_at'], 0, 10),
                $p['grn_number'],
                (string) $p['supplier'],
                number_format((float) $p['net'], 2, '.', ''),
                number_format(round((float) $p['net'] * vat_rate() / 100, 2), 2, '.', ''),
            ];
        }
        $csv[] = [];
        $csv[] = ['Returns detail (completed)'];
        $csv[] = ['Date', 'Return #', 'Sale #', 'Refund (net)', 'VAT reversed'];
        foreach ($d['returns'] as $r) {
            $csv[] = [
                substr((string) $r['created_at'], 0, 10),
                $r['return_number'],
                (string) $r['sale_number'],
                number_format((float) $r['refund_amount'], 2, '.', ''),
                number_format((float) $r['vat'], 2, '.', ''),
            ];
        }
        $csv[] = [];
        $csv[] = ['Note', 'Input VAT assumes purchase costs are recorded VAT-exclusive at the current rate. Adjust VAT-exempt purchases manually.'];

        csv_response($csv, 'vat-report-' . $from . '-to-' . $to . '.csv');
    }

    /** Output vs input VAT for a date window (KRA-style). */
    private static function vatData(string $from, string $to): array
    {
        $params   = ['from' => $from, 'to' => $to];
        $rangeSql = 'DATE(created_at) >= :from AND DATE(created_at) <= :to';

        // Output VAT — the VAT actually charged, stored per completed sale.
        $output = (float) Database::fetchValue(
            "SELECT COALESCE(SUM(tax_amount), 0) FROM sales WHERE status = 'completed' AND {$rangeSql}",
            $params
        );
        $salesCount = (int) Database::fetchValue(
            "SELECT COUNT(*) FROM sales WHERE status = 'completed' AND {$rangeSql}",
            $params
        );

        // VAT reversed by completed returns. Refunds are VAT-exclusive, so each
        // is re-taxed at the parent sale's effective rate — accurate even if the
        // configured VAT rate changed between the sale and the return.
        $returnVat = (float) Database::fetchValue(
            "SELECT COALESCE(SUM(r.refund_amount * ((s.tax_amount * 1.0) / NULLIF(s.subtotal - s.discount, 0))), 0)
               FROM returns r
               JOIN sales s ON s.id = r.sale_id
              WHERE r.status = 'completed' AND DATE(r.created_at) >= :from AND DATE(r.created_at) <= :to",
            $params
        );

        // Input VAT — estimated from goods received. Purchase costs are stored
        // VAT-exclusive; estimated at the current configured rate.
        $purchaseNet = (float) Database::fetchValue(
            "SELECT COALESCE(SUM(i.unit_cost * i.quantity), 0)
               FROM goods_received_items i
               JOIN goods_received g ON g.id = i.grn_id
              WHERE DATE(g.created_at) >= :from AND DATE(g.created_at) <= :to",
            $params
        );
        $input = round($purchaseNet * vat_rate() / 100, 2);

        $net = round($output - $returnVat - $input, 2);

        // Daily series for the breakdown table.
        $dayKeys = [];
        for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
            $dayKeys[date('Y-m-d', $t)] = ['out' => 0.0, 'in' => 0.0];
        }
        $salesDaily = Database::fetchAll(
            "SELECT DATE(created_at) AS d, SUM(tax_amount) AS v
               FROM sales WHERE status = 'completed' AND {$rangeSql}
              GROUP BY DATE(created_at)",
            $params
        );
        foreach ($salesDaily as $row) {
            if (isset($dayKeys[$row['d']])) {
                $dayKeys[$row['d']]['out'] = (float) $row['v'];
            }
        }
        $purchDaily = Database::fetchAll(
            "SELECT DATE(g.created_at) AS d, SUM(i.unit_cost * i.quantity) AS v
               FROM goods_received_items i
               JOIN goods_received g ON g.id = i.grn_id
              WHERE DATE(g.created_at) >= :from AND DATE(g.created_at) <= :to
              GROUP BY DATE(g.created_at)",
            $params
        );
        foreach ($purchDaily as $row) {
            if (isset($dayKeys[$row['d']])) {
                $dayKeys[$row['d']]['in'] = round((float) $row['v'] * vat_rate() / 100, 2);
            }
        }

        $sales = Database::fetchAll(
            "SELECT sale_number, customer_name, payment_method, subtotal, discount, tax_amount, created_at
               FROM sales WHERE status = 'completed' AND {$rangeSql}
              ORDER BY created_at, id",
            $params
        );
        $purchases = Database::fetchAll(
            "SELECT g.grn_number, g.supplier, g.created_at, SUM(i.unit_cost * i.quantity) AS net
               FROM goods_received g
               JOIN goods_received_items i ON i.grn_id = g.id
              WHERE DATE(g.created_at) >= :from AND DATE(g.created_at) <= :to
              GROUP BY g.id, g.grn_number, g.supplier, g.created_at
              ORDER BY g.created_at, g.id",
            $params
        );
        $returns = Database::fetchAll(
            "SELECT r.return_number, r.refund_amount, r.created_at, s.sale_number,
                    r.refund_amount * ((s.tax_amount * 1.0) / NULLIF(s.subtotal - s.discount, 0)) AS vat
               FROM returns r
               JOIN sales s ON s.id = r.sale_id
              WHERE r.status = 'completed' AND DATE(r.created_at) >= :from AND DATE(r.created_at) <= :to
              ORDER BY r.created_at, r.id",
            $params
        );

        return [
            'output'       => $output,
            'return_vat'   => $returnVat,
            'input'        => $input,
            'net'          => $net,
            'sales_count'  => $salesCount,
            'purchase_net' => $purchaseNet,
            'days'         => $dayKeys,
            'sales'        => $sales,
            'purchases'    => $purchases,
            'returns'      => $returns,
        ];
    }

    /* --------------------- Purchases by supplier --------------------- */

    public function purchases(): void
    {
        Auth::requireLogin();

        [$from, $to] = self::range($_GET['from'] ?? '', $_GET['to'] ?? '');
        $filter = trim((string) ($_GET['supplier'] ?? ''));

        View::render('reports/purchases', [
            'title'     => 'Purchases by supplier',
            'from'      => $from,
            'to'        => $to,
            'filter'    => $filter,
            'rate'      => vat_rate(),
            'suppliers' => Supplier::all(),
            'purch'     => self::purchasesData($from, $to, $filter),
        ]);
    }

    public function purchasesExport(): void
    {
        Auth::requireLogin();

        [$from, $to] = self::range($_GET['from'] ?? '', $_GET['to'] ?? '');
        $filter = trim((string) ($_GET['supplier'] ?? ''));
        $d      = self::purchasesData($from, $to, $filter);
        $rate   = vat_rate();

        $csv = [
            ['Khamis Computers — Purchases by supplier'],
            ['Period', $from . ' to ' . $to],
            ['Supplier filter', $filter === '' ? 'All suppliers' : self::purchasesFilterLabel($d)],
            ['VAT rate (%)', number_format($rate, 2, '.', '')],
            [],
            ['Summary'],
            ['Supplier', 'Type', 'Deliveries', 'Net purchases (KSh)', 'Est. input VAT (KSh)', 'Last delivery'],
        ];
        foreach ($d['summary'] as $s) {
            $csv[] = [
                $s['name'],
                $s['free'] ? 'Free-text' : 'Saved supplier',
                $s['deliveries'],
                number_format($s['net'], 2, '.', ''),
                number_format($s['vat'], 2, '.', ''),
                substr($s['last'], 0, 10),
            ];
        }
        $csv[] = [];
        $csv[] = ['Total', '', $d['grn_count'], number_format($d['grand_net'], 2, '.', ''), number_format($d['grand_vat'], 2, '.', ''), ''];
        $csv[] = [];
        $csv[] = ['Detail — goods received in period'];
        $csv[] = ['GRN #', 'Date', 'Supplier', 'Product', 'SKU', 'Qty', 'Unit cost (KSh)', 'Line net (KSh)'];
        foreach ($d['summary'] as $s) {
            foreach ($s['grns'] as $g) {
                $items = $g['items'] ?? [];
                if (!$items) {
                    $csv[] = [$g['grn_number'], substr((string) $g['created_at'], 0, 10), $s['name'], '(no items)', '', '', '', ''];
                    continue;
                }
                foreach ($items as $it) {
                    $csv[] = [
                        $g['grn_number'],
                        substr((string) $g['created_at'], 0, 10),
                        $s['name'],
                        $it['product_name'],
                        $it['sku'],
                        (int) $it['quantity'],
                        number_format((float) $it['unit_cost'], 2, '.', ''),
                        number_format((float) $it['line_net'], 2, '.', ''),
                    ];
                }
            }
        }
        $csv[] = [];
        $csv[] = ['Note', 'Purchase costs are recorded VAT-exclusive; input VAT is estimated at the current rate.'];

        csv_response($csv, 'purchases-by-supplier-' . $from . '-to-' . $to . '.csv');
    }

    /** Purchases grouped by supplier for a date window (linked + free-text names). */
    private static function purchasesData(string $from, string $to, string $filter = ''): array
    {
        $params = ['from' => $from, 'to' => $to];

        $grns = Database::fetchAll(
            "SELECT g.id, g.grn_number, g.supplier, g.supplier_id, g.created_at,
                    COALESCE(SUM(i.unit_cost * i.quantity), 0) AS net
               FROM goods_received g
               LEFT JOIN goods_received_items i ON i.grn_id = g.id
              WHERE DATE(g.created_at) >= :from AND DATE(g.created_at) <= :to
              GROUP BY g.id, g.grn_number, g.supplier, g.supplier_id, g.created_at
              ORDER BY g.created_at, g.id",
            $params
        );

        // Item lines for every GRN in the window (for drill-down + export).
        $byGrn = [];
        foreach (Database::fetchAll(
            "SELECT i.grn_id, p.name AS product_name, p.sku, i.quantity, i.unit_cost,
                    i.quantity * i.unit_cost AS line_net
               FROM goods_received_items i
               JOIN products p ON p.id = i.product_id
               JOIN goods_received g ON g.id = i.grn_id
              WHERE DATE(g.created_at) >= :from AND DATE(g.created_at) <= :to
              ORDER BY i.id",
            $params
        ) as $it) {
            $byGrn[(int) $it['grn_id']][] = $it;
        }

        // Current names for linked suppliers.
        $names = [];
        foreach (Supplier::all() as $s) {
            $names[(int) $s['id']] = $s['name'];
        }

        // Group GRNs: linked suppliers by id, free-text snapshots by name.
        $groups = [];
        foreach ($grns as $r) {
            $sid = $r['supplier_id'] !== null ? (int) $r['supplier_id'] : null;
            if ($sid !== null) {
                $key  = 's' . $sid;
                $name = $names[$sid] ?? (string) $r['supplier'];
                $free = false;
            } else {
                $name = trim((string) $r['supplier']) !== '' ? (string) $r['supplier'] : 'Walk-in supplier';
                $key  = 't' . $name;
                $free = true;
            }
            $r['items'] = $byGrn[(int) $r['id']] ?? [];
            $groups[$key]['id']     = $sid;
            $groups[$key]['name']   = $name;
            $groups[$key]['free']   = $free;
            $groups[$key]['grns'][] = $r;
        }

        // Optional supplier filter (numeric id, or name:... for a free-text supplier).
        if ($filter !== '') {
            if (str_starts_with($filter, 'name:')) {
                $wanted = substr($filter, 5);
                $groups = array_filter($groups, fn ($g) => $g['free'] && $g['name'] === $wanted);
            } else {
                $wanted = (int) $filter;
                $groups = array_filter($groups, fn ($g) => !$g['free'] && $g['id'] === $wanted);
            }
        }

        $summary   = [];
        $grandNet  = 0.0;
        $grandGrns = 0;
        foreach ($groups as $g) {
            $net  = 0.0;
            $last = '';
            foreach ($g['grns'] as $r) {
                $net += (float) $r['net'];
                if ((string) $r['created_at'] > $last) {
                    $last = (string) $r['created_at'];
                }
            }
            $summary[] = [
                'id'         => $g['id'],
                'name'       => $g['name'],
                'free'       => $g['free'],
                'deliveries' => count($g['grns']),
                'net'        => $net,
                'vat'        => round($net * vat_rate() / 100, 2),
                'last'       => $last,
                'grns'       => $g['grns'],
            ];
            $grandNet  += $net;
            $grandGrns += count($g['grns']);
        }
        usort($summary, fn ($a, $b) => $b['net'] <=> $a['net']);

        return [
            'summary'   => $summary,
            'grand_net' => $grandNet,
            'grand_vat' => round($grandNet * vat_rate() / 100, 2),
            'grn_count' => $grandGrns,
        ];
    }

    /** Human label for the active supplier filter. */
    private static function purchasesFilterLabel(array $d): string
    {
        return $d['summary'][0]['name'] ?? '';
    }

    /** Parse + normalise the date window. */
    private static function validDate(string $raw, string $fallback): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($raw)) ? trim($raw) : $fallback;
    }

    private static function range(string $rawFrom, string $rawTo): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($rawFrom))) {
            $rawFrom = date('Y-m-d', strtotime('-29 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($rawTo))) {
            $rawTo = date('Y-m-d');
        }
        if (strtotime($rawFrom) > strtotime($rawTo)) {
            [$rawFrom, $rawTo] = [$rawTo, $rawFrom];
        }
        return [trim($rawFrom), trim($rawTo)];
    }

    /** Core metrics for a date window (completed sales only). */
    private static function metrics(string $from, string $to): array
    {
        $rangeSql    = 'DATE(created_at) >= :from AND DATE(created_at) <= :to';
        $rangeParams = ['from' => $from, 'to' => $to];

        $revenue  = (float) Database::fetchValue("SELECT COALESCE(SUM(total),0) FROM sales WHERE status = 'completed' AND {$rangeSql}", $rangeParams);
        $orders   = (int) Database::fetchValue("SELECT COUNT(*) FROM sales WHERE status = 'completed' AND {$rangeSql}", $rangeParams);
        $refunds  = (float) Database::fetchValue("SELECT COALESCE(SUM(refund_amount),0) FROM returns WHERE status = 'completed' AND {$rangeSql}", $rangeParams);
        $expenses = Expense::sumRange($from, $to);
        $cogs     = (float) Database::fetchValue(
            "SELECT COALESCE(SUM(p.cost_price * si.quantity), 0)
               FROM sale_items si
               JOIN sales s ON s.id = si.sale_id
               JOIN products p ON p.id = si.product_id
              WHERE s.status = 'completed' AND DATE(s.created_at) >= :from AND DATE(s.created_at) <= :to",
            $rangeParams
        );

        $channels = Database::fetchAll(
            "SELECT channel, SUM(total) AS rev, COUNT(*) AS n
               FROM sales WHERE status = 'completed' AND {$rangeSql}
              GROUP BY channel",
            $rangeParams
        );

        $topProducts = Database::fetchAll(
            "SELECT p.name, p.sku, SUM(si.quantity) AS qty, SUM(si.line_total) AS rev
               FROM sale_items si
               JOIN sales s ON s.id = si.sale_id
               JOIN products p ON p.id = si.product_id
              WHERE s.status = 'completed' AND DATE(s.created_at) >= :from AND DATE(s.created_at) <= :to
              GROUP BY si.product_id
              ORDER BY rev DESC LIMIT 8",
            ['from' => $from, 'to' => $to]
        );

        return compact('revenue', 'orders', 'refunds', 'expenses', 'cogs', 'channels', 'topProducts');
    }
}
