<?php
declare(strict_types=1);

class ExpenseController
{
    public function index(): void
    {
        Auth::requireLogin();
        $from = trim((string) ($_GET['from'] ?? ''));
        $to   = trim((string) ($_GET['to'] ?? ''));

        if ($from === '' && $to === '') {
            $from = date('Y-m-01'); // first day of current month
            $to   = date('Y-m-t');  // last day of current month
        }

        View::render('expenses/index', [
            'title'     => 'Expenses',
            'expenses'  => Expense::all($from, $to),
            'total'     => Expense::sumRange($from, $to),
            'categories'=> Expense::categories(),
            'from'      => $from,
            'to'        => $to,
        ]);
    }

    public function store(): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();

        $desc = trim((string) ($_POST['description'] ?? ''));
        $amt  = (float) ($_POST['amount'] ?? 0);
        $date = (string) ($_POST['expense_date'] ?? date('Y-m-d'));
        $cat  = (string) ($_POST['category'] ?? 'general');

        if ($desc === '' || $amt <= 0) {
            flash('error', 'Enter a description and an amount greater than zero.');
            redirect('expenses');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            flash('error', 'Enter a valid date.');
            redirect('expenses');
        }

        Expense::create($desc, $amt, $cat, $date, Auth::id());
        flash('success', 'Expense recorded.');
        redirect('expenses');
    }

    public function delete(int $id): void
    {
        Auth::requireLogin();
        Csrf::checkOrFail();
        if (!Auth::isAdmin()) {
            flash('error', 'Only admins can delete expenses.');
            redirect('expenses');
        }
        Expense::delete($id);
        flash('success', 'Expense deleted.');
        redirect('expenses');
    }
}
