<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$id = (int) ($_POST['id'] ?? 0);
$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$amount = (float) ($_POST['amount'] ?? 0);
$date = trim($_POST['date'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($id <= 0 || $title === '' || $amount <= 0 || $date === '') {
    jsonResponse(['error' => 'ID, title, amount, and date are required.'], 422);
}

try {
    $pdo = Database::connect();
    $stmt = $pdo->prepare(
        'UPDATE expenses
         SET title = :title,
             category = :category,
             amount = :amount,
             date = :date,
             description = :description
         WHERE id = :id'
    );

    $stmt->execute([
        ':id' => $id,
        ':title' => $title,
        ':category' => $category,
        ':amount' => $amount,
        ':date' => $date,
        ':description' => $description,
    ]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Expense not found.'], 404);
    }

    jsonResponse(['message' => 'Expense updated successfully.']);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Failed to update expense.', 'details' => $e->getMessage()], 500);
}
