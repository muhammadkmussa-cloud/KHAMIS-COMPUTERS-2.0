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
        $rawAmount = trim((string) ($_POST['amount'] ?? ''));
        $amt  = preg_match('/^\d+(?:\.\d{1,2})?$/', $rawAmount) ? (float) $rawAmount : 0.0;
        $date = (string) ($_POST['expense_date'] ?? date('Y-m-d'));
        $cat  = (string) ($_POST['category'] ?? 'general');

        if ($desc === '' || mb_strlen($desc) > 255 || $amt <= 0) {
            flash('error', 'Enter a description and an amount greater than zero.');
            redirect('expenses');
        }
        $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            flash('error', 'Enter a valid date.');
            redirect('expenses');
        }

        if (!in_array($cat, Expense::categories(), true)) {
            flash('error', 'Choose a valid expense category.');
            redirect('expenses');
        }
        $receipt = $this->storeReceipt($_FILES['receipt'] ?? null);
        if ($receipt === false) {
            redirect('expenses?add=1');
        }
        $id = Expense::create($desc, $amt, $cat, $date, Auth::id(), $receipt, isset($_POST['is_recurring']));
        Activity::log('expense.created', json_encode(['expense_id'=>$id,'amount'=>$amt,'category'=>$cat], JSON_UNESCAPED_SLASHES));
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
        $reason = trim((string) ($_POST['delete_reason'] ?? ''));
        if ($reason === '' || mb_strlen($reason) > 500) {
            flash('error', 'Enter a deletion reason within 500 characters.');
            redirect('expenses');
        }
        $expense = Expense::findActive($id);
        if (!$expense || $expense['deleted_at'] !== null) {
            flash('error', 'Expense not found or already deleted.');
            redirect('expenses');
        }
        Expense::delete($id, $reason, (int) Auth::id());
        Activity::log('expense.deleted', json_encode(['expense_id'=>$id,'reason'=>$reason], JSON_UNESCAPED_SLASHES));
        flash('success', 'Expense deleted.');
        redirect('expenses');
    }

    public function receipt(int $id): void
    {
        Auth::requireLogin();
        $expense = Expense::findActive($id);
        $file = basename((string) ($expense['receipt_file'] ?? ''));
        $path = BASE_PATH . '/storage/expense-receipts/' . $file;
        if (!$expense || $file === '' || !is_file($path)) {
            http_response_code(404);
            echo 'Receipt not found.';
            return;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="expense-receipt-' . $id . '.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
    }

    private function storeReceipt(?array $file)
    {
        if (!$file || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        if ((int) $file['error'] !== UPLOAD_ERR_OK || (int) $file['size'] > 5 * 1024 * 1024) {
            flash('error', 'Receipt upload failed or exceeds 5 MB.');
            return false;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $file['tmp_name']);
        $extensions = ['image/jpeg'=>'jpg','image/png'=>'png','application/pdf'=>'pdf'];
        if (!isset($extensions[$mime])) {
            flash('error', 'Receipt must be a JPG, PNG, or PDF file.');
            return false;
        }
        $dir = BASE_PATH . '/storage/expense-receipts';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            flash('error', 'Could not prepare receipt storage.');
            return false;
        }
        $name = bin2hex(random_bytes(20)) . '.' . $extensions[$mime];
        if (!move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            flash('error', 'Could not store the receipt attachment.');
            return false;
        }
        return $name;
    }
}
