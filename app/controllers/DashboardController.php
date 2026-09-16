<?php
declare(strict_types=1);

class DashboardController
{
    public function index(): void
    {
        Auth::requireLogin();
        $today = Database::today();
        $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
        $scopeUser = Auth::isAdmin() ? null : Auth::id();
        $sales = self::dailySalesSummary($today, $scopeUser);
        $yRevenue = (float) self::dailySalesSummary($yesterday, $scopeUser)['revenue'];
        $params = ['today'=>$today] + (Auth::isAdmin()?[]:['user_id'=>Auth::id()]);
        $refundParams = ['today'=>$today] + (Auth::isAdmin()?[]:['user_id'=>Auth::id()]);
        $cashRefunds = (float) Database::fetchValue(
            "SELECT COALESCE(SUM(CASE
                        WHEN r.refund_method='cash' THEN r.refund_amount
                        WHEN r.refund_method='original' AND s.payment_method='cash' THEN r.refund_amount
                        ELSE 0 END),0)
               FROM returns r JOIN sales s ON s.id=r.sale_id
              WHERE r.status='completed' AND DATE(r.created_at)=:today" . (Auth::isAdmin()?'':' AND r.user_id=:user_id'),
            $refundParams
        );
        $cogs = (float) Database::fetchValue(
            "SELECT COALESCE(SUM(si.unit_cost*si.quantity),0) FROM sale_items si JOIN sales s ON s.id=si.sale_id
              WHERE s.status='completed' AND DATE(s.created_at)=:today" . (Auth::isAdmin()?'':' AND s.user_id=:user_id'), $params
        );
        $stats = [
            'revenue'=>(float)$sales['revenue'], 'transactions'=>(int)$sales['transactions'],
            'revenue_change'=>$yRevenue>0?round(((float)$sales['revenue']-$yRevenue)/$yRevenue*100,1):null,
            'gross_profit'=>(float)$sales['revenue']-$cogs, 'expected_cash'=>(float)$sales['cash']-$cashRefunds,
            'pending_returns'=>(int)Database::fetchValue("SELECT COUNT(*) FROM returns WHERE status='pending'" . (Auth::isAdmin()?'':' AND user_id=?'), Auth::isAdmin()?[]:[Auth::id()]),
            'pending_online'=>(int)Database::fetchValue("SELECT COUNT(*) FROM sales WHERE status='pending' AND channel='online'"),
            'offline_synced'=>(int)Database::fetchValue("SELECT COUNT(*) FROM sales WHERE offline_created=1 AND DATE(created_at)=?" . (Auth::isAdmin()?'':' AND user_id=?'), Auth::isAdmin()?[$today]:[$today,Auth::id()]),
        ];
        $recent = Database::fetchAll(
            "SELECT s.id,s.sale_number,s.channel,s.customer_name,s.total,s.status,s.payment_method,s.created_at FROM sales s WHERE 1=1" . (Auth::isAdmin()?'':' AND s.user_id=?') . " ORDER BY s.id DESC LIMIT 7",
            Auth::isAdmin()?[]:[Auth::id()]
        );
        View::render('dashboard/home', [
            'title'=>'Dashboard','today'=>$today,'stats'=>$stats,'recent'=>$recent,
            'lowStock'=>Auth::isAdmin()?array_slice(Product::lowStock(),0,5):[],
            'activity'=>Auth::isAdmin()?Database::fetchAll("SELECT a.*,u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT 7"):[],
        ]);
    }

    public static function dailySalesSummary(string $date, ?int $userId = null): array
    {
        $where = $userId === null ? '' : ' AND user_id = :user_id';
        return Database::fetch(
            "SELECT COUNT(*) AS transactions,
                    COALESCE(SUM(subtotal-discount),0) AS revenue,
                    COALESCE(SUM(CASE WHEN payment_method='cash' THEN total ELSE 0 END),0) AS cash
               FROM sales
              WHERE status='completed' AND DATE(created_at)=:day{$where}",
            ['day'=>$date] + ($userId === null ? [] : ['user_id'=>$userId])
        );
    }
}
