<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    $pdo = Database::connect();
    $stmt = $pdo->query(
        'SELECT id, title, category, amount, date, description
         FROM expenses
         ORDER BY date DESC, id DESC'
    );

    $expenses = $stmt->fetchAll();

    jsonResponse(['expenses' => $expenses]);
} catch (PDOException $e) {
    jsonResponse(['error' => 'Failed to fetch expenses.', 'details' => $e->getMessage()], 500);
}
