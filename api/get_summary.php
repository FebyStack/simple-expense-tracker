<?php
/**
 * GET /api/get_summary.php
 *
 * Returns dashboard statistics for the logged-in user:
 *   - total_amount, expense_count, highest_expense
 *   - categories  (array of {category, total})
 *   - monthly     (array of {month, total})
 */

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';   // sets $currentUserId

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

try {
    $pdo = Database::connect();

    // -- Totals ---------------------------------------------------------------
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(amount), 0)   AS total_amount,
                COUNT(*)                    AS expense_count,
                COALESCE(MAX(amount), 0)    AS highest_expense
         FROM exptrack.expenses
         WHERE user_id = :uid'
    );
    $stmt->execute([':uid' => $currentUserId]);
    $totals = $stmt->fetch();

    // -- By category ----------------------------------------------------------
    $stmt = $pdo->prepare(
        'SELECT COALESCE(NULLIF(category, \'\'), \'Uncategorized\') AS category,
                SUM(amount) AS total
         FROM exptrack.expenses
         WHERE user_id = :uid
         GROUP BY category
         ORDER BY total DESC'
    );
    $stmt->execute([':uid' => $currentUserId]);
    $categories = $stmt->fetchAll();

    // -- By month -------------------------------------------------------------
    $stmt = $pdo->prepare(
        'SELECT TO_CHAR(expense_date, \'YYYY-MM\') AS month,
                SUM(amount) AS total
         FROM exptrack.expenses
         WHERE user_id = :uid
         GROUP BY month
         ORDER BY month DESC
         LIMIT 12'
    );
    $stmt->execute([':uid' => $currentUserId]);
    $monthly = $stmt->fetchAll();

    jsonResponse([
        'total_amount'    => (float) $totals['total_amount'],
        'expense_count'   => (int)   $totals['expense_count'],
        'highest_expense' => (float) $totals['highest_expense'],
        'categories'      => $categories,
        'monthly'         => $monthly,
    ]);
} catch (PDOException $e) {
    error_log('Summary error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to load summary.'], 500);
}
