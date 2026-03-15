<?php
/**
 * POST /api/add_expense.php
 *
 * Body: title, category, amount, date, description, csrf_token
 * Requires: logged-in user
 */

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';   // sets $currentUserId

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

validateCsrfToken();

// ---------------------------------------------------------------------------
// Collect & validate input
// ---------------------------------------------------------------------------
$title       = trim($_POST['title'] ?? '');
$category    = trim($_POST['category'] ?? '');
$amount      = $_POST['amount'] ?? '';
$date        = trim($_POST['date'] ?? '');
$description = trim($_POST['description'] ?? '');

$errors = [];

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
// Insert
// ---------------------------------------------------------------------------
try {
    $pdo  = Database::connect();
    $stmt = $pdo->prepare(
        'INSERT INTO exptrack.expenses (user_id, title, category, amount, expense_date, description)
         VALUES (:user_id, :title, :category, :amount, :date, :description)
         RETURNING id'
    );

    $stmt->execute([
        ':user_id'     => $currentUserId,
        ':title'       => $title,
        ':category'    => $category,
        ':amount'      => $amount,
        ':date'        => $date,
        ':description' => $description,
    ]);

    $newId = (int) $stmt->fetchColumn();

    jsonResponse(['message' => 'Expense added successfully.', 'id' => $newId], 201);
} catch (PDOException $e) {
    error_log('Add expense error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to add expense.'], 500);
}
