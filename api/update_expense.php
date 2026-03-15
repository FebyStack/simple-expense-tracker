<?php
/**
 * POST /api/update_expense.php
 *
 * Body: id, title, category, amount, date, description, csrf_token
 * Requires: logged-in user (can only update own expenses)
 */

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';   // sets $currentUserId

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

validateCsrfToken();

// ---------------------------------------------------------------------------
// Collect & validate
// ---------------------------------------------------------------------------
$id          = (int) ($_POST['id'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$category    = trim($_POST['category'] ?? '');
$amount      = $_POST['amount'] ?? '';
$date        = trim($_POST['date'] ?? '');
$description = trim($_POST['description'] ?? '');

$errors = [];

if ($id <= 0) {
    $errors[] = 'A valid expense ID is required.';
}
if ($title === '') {
    $errors[] = 'Title is required.';
}
if (!is_numeric($amount) || (float) $amount <= 0) {
    $errors[] = 'Amount must be a positive number.';
}
if ($date === '' || !strtotime($date)) {
    $errors[] = 'A valid date is required.';
}

if ($errors) {
    jsonResponse(['error' => implode(' ', $errors)], 422);
}

$amount = (float) $amount;

// ---------------------------------------------------------------------------
// Update — user_id guard prevents editing other users' data
// ---------------------------------------------------------------------------
try {
    $pdo  = Database::connect();
    $stmt = $pdo->prepare(
        'UPDATE exptrack.expenses
         SET title       = :title,
             category    = :category,
             amount      = :amount,
             expense_date = :date,
             description = :description
         WHERE id = :id AND user_id = :user_id'
    );

    $stmt->execute([
        ':id'          => $id,
        ':user_id'     => $currentUserId,
        ':title'       => $title,
        ':category'    => $category,
        ':amount'      => $amount,
        ':date'        => $date,
        ':description' => $description,
    ]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Expense not found.'], 404);
    }

    jsonResponse(['message' => 'Expense updated successfully.']);
} catch (PDOException $e) {
    error_log('Update expense error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to update expense.'], 500);
}
