<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'A valid expense ID is required.'], 422);
}

try {
    $pdo = Database::connect();
    $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Expense not found.'], 404);
    }

    jsonResponse(['message' => 'Expense deleted successfully.']);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Failed to delete expense.', 'details' => $e->getMessage()], 500);
}
