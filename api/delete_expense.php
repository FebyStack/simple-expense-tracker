<?php
/**
 * POST /api/delete_expense.php
 *
 * Body: id, csrf_token
 * Requires: logged-in user (can only delete own expenses)
 */

declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';   // sets $currentUserId

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

validateCsrfToken();

$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0) {
    jsonResponse(['error' => 'A valid expense ID is required.'], 422);
}

try {
    $pdo  = Database::connect();
    $stmt = $pdo->prepare(
        'DELETE FROM exptrack.expenses WHERE id = :id AND user_id = :user_id'
    );
    $stmt->execute([':id' => $id, ':user_id' => $currentUserId]);

    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Expense not found.'], 404);
    }

    jsonResponse(['message' => 'Expense deleted successfully.']);
} catch (PDOException $e) {
    error_log('Delete expense error: ' . $e->getMessage());
    jsonResponse(['error' => 'Failed to delete expense.'], 500);
}
