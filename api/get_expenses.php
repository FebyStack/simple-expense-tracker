<?php
/**
 * GET /api/get_expenses.php
 *
 * Optional query params: search, category, date_from, date_to, amount_min, amount_max
 * Requires: logged-in user
 */

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';   // sets $currentUserId

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// ---------------------------------------------------------------------------
// Build dynamic query with optional filters
// ---------------------------------------------------------------------------
$conditions = ['user_id = :user_id'];
$params     = [':user_id' => $currentUserId];

// Free-text search (title or category)
$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $conditions[]       = '(LOWER(title) LIKE :search OR LOWER(category) LIKE :search)';
    $params[':search']  = '%' . strtolower($search) . '%';
}

// Category exact filter
$category = trim($_GET['category'] ?? '');
if ($category !== '') {
    $conditions[]          = 'LOWER(category) = LOWER(:category)';
    $params[':category']   = $category;
}

// Date range
$dateFrom = trim($_GET['date_from'] ?? '');
if ($dateFrom !== '' && strtotime($dateFrom)) {
    $conditions[]          = 'expense_date >= :date_from';
    $params[':date_from']  = $dateFrom;
}

$dateTo = trim($_GET['date_to'] ?? '');
if ($dateTo !== '' && strtotime($dateTo)) {
    $conditions[]        = 'expense_date <= :date_to';
    $params[':date_to']  = $dateTo;
}

// Amount range
$amountMin = $_GET['amount_min'] ?? '';
if ($amountMin !== '' && is_numeric($amountMin)) {
    $conditions[]           = 'amount >= :amount_min';
    $params[':amount_min']  = (float) $amountMin;
}

$amountMax = $_GET['amount_max'] ?? '';
if ($amountMax !== '' && is_numeric($amountMax)) {
    $conditions[]           = 'amount <= :amount_max';
    $params[':amount_max']  = (float) $amountMax;
}

$where = implode(' AND ', $conditions);

try {
    $pdo  = Database::connect();
    $stmt = $pdo->prepare(
        "SELECT id, title, category, amount, expense_date AS date, description
         FROM exptrack.expenses
         WHERE {$where}
         ORDER BY expense_date DESC, id DESC"
    );
    $stmt->execute($params);
    $expenses = $stmt->fetchAll();

    jsonResponse(['expenses' => $expenses]);
} catch (PDOException $e) {
    error_log('Get expenses error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to fetch expenses.'], 500);
}
