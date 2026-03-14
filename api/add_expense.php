<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$title = trim($_POST['title'] ?? '');
$category = trim($_POST['category'] ?? '');
$amount = (float) ($_POST['amount'] ?? 0);
$date = trim($_POST['date'] ?? '');
$description = trim($_POST['description'] ?? '');

if ($title === '' || $amount <= 0 || $date === '') {
    jsonResponse(['error' => 'Title, amount, and date are required.'], 422);
}

try {
    $pdo = Database::connect();
    $stmt = $pdo->prepare(
        'INSERT INTO expenses (title, category, amount, "date", description)
         VALUES (:title, :category, :amount, :date, :description)
         RETURNING id'
    );

    $stmt->execute([
        ':title' => $title,
        ':category' => $category,
        ':amount' => $amount,
        ':date' => $date,
        ':description' => $description,
    ]);

    $newId = (int) $stmt->fetchColumn();

    jsonResponse(['message' => 'Expense added successfully.', 'id' => $newId], 201);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Failed to add expense.', 'details' => $e->getMessage()], 500);
}
