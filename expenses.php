
<?php
require_once __DIR__ . '/config/config.php';
require_login();

$pdo = db();
$role = strtolower($_SESSION['user']['role'] ?? '');
$isAdmin = in_array($role, ['admin'], true);
/*
|--------------------------------------------------------------------------
| HANDLE FORM SUBMISSIONS BEFORE OUTPUT
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();

    // Only administrators may add or delete expenses.
    if (!$isAdmin) {
        http_response_code(403);
        exit('Access denied.');
    }

    // DELETE EXPENSE
    if (isset($_POST['delete_expense_id'])) {
        $expenseId = filter_input(
            INPUT_POST,
            'delete_expense_id',
            FILTER_VALIDATE_INT
        );

        if (!$expenseId || $expenseId < 1) {
            flash('error', 'Invalid expense selected.');
            redirect('expenses.php');
        }

        $stmt = $pdo->prepare(
            'DELETE FROM expenses WHERE id = ?'
        );
        $stmt->execute([$expenseId]);

        if ($stmt->rowCount() === 1) {
            flash('success', 'Expense deleted successfully.');
        } else {
            flash('error', 'Expense record was not found.');
        }

        redirect('expenses.php');
    }

    // ADD EXPENSE
    if (isset($_POST['save_expense'])) {
        $expenseTitle = trim($_POST['title'] ?? '');
        $amountRaw = $_POST['amount'] ?? '';
        $notes = trim($_POST['notes'] ?? '');

        if (
            $expenseTitle === '' ||
            !is_numeric($amountRaw) ||
            (float)$amountRaw <= 0
        ) {
            flash(
                'error',
                'Enter an expense title and an amount greater than zero.'
            );
            redirect('expenses.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO expenses
            (title, amount, notes, created_by)
            VALUES (?, ?, ?, ?)'
        );

        $stmt->execute([
            $expenseTitle,
            (float)$amountRaw,
            $notes,
            $_SESSION['user']['id']
        ]);

        flash('success', 'Expense recorded.');
        redirect('expenses.php');
    }
}

$title = 'Expenses';
require __DIR__ . '/includes/header.php';

$rows = $pdo->query(
    'SELECT * FROM expenses ORDER BY id DESC LIMIT 100'
)->fetchAll();
?>

<div class="page-header">
    <h1>Expenses</h1>
</div>

<?php if ($isAdmin): ?>
<div class="form-card">
    <h2>Record New Expense</h2>

    <form method="post">
        <input
            type="hidden"
            name="csrf"
            value="<?= e(csrf()) ?>"
        >

        <input type="hidden" name="save_expense" value="1">

        <div class="grid">
            <p>
                <label for="title">Expense Title</label>
                <input
                    id="title"
                    name="title"
                    maxlength="190"
                    required
                >
            </p>

            <p>
                <label for="amount">Amount (₦)</label>
                <input
                    id="amount"
                    type="number"
                    name="amount"
                    min="0.01"
                    step="0.01"
                    required
                >
            </p>

            <p>
                <label for="notes">Notes</label>
                <input
                    id="notes"
                    name="notes"
                    maxlength="255"
                >
            </p>
        </div>

        <button type="submit">Save Expense</button>
    </form>
</div>
<?php endif; ?>

<div class="form-card">
    <h2>Expense Records</h2>

    <div style="overflow-x:auto">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Amount</th>
                    <th>Notes</th>
                    <th>Date</th>
                    <?php if ($isAdmin): ?>
                        <th>Action</th>
                    <?php endif; ?>
                </tr>
            </thead>

            <tbody>
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="<?= $isAdmin ? 6 : 5 ?>">
                        No expenses recorded.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><?= e($r['title'] ?? '') ?></td>
                        <td><?= money($r['amount'] ?? 0) ?></td>
                        <td><?= e($r['notes'] ?? '') ?></td>
                        <td><?= e($r['created_at'] ?? '') ?></td>

                        <?php if ($isAdmin): ?>
                        <td>
                            <form
                                method="post"
                                onsubmit="return confirm('Are you sure you want to delete this expense? This action cannot be undone.');"
                            >
                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrf()) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="delete_expense_id"
                                    value="<?= (int)$r['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn red"
                                >
                                    Delete
                                </button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
