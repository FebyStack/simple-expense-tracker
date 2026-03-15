<?php
/**
 * POST /api/login.php
 *
 * Body: email, password, csrf_token
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

validateCsrfToken();

// ---------------------------------------------------------------------------
// Collect & validate input
// ---------------------------------------------------------------------------
$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    jsonResponse(['error' => 'Email and password are required.'], 422);
}

// ---------------------------------------------------------------------------
// Authenticate
// ---------------------------------------------------------------------------
try {
    $pdo = Database::connect();

    $stmt = $pdo->prepare(
        'SELECT id, username, password_hash
         FROM exptrack.users
         WHERE LOWER(email) = LOWER(:email)'
    );
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['error' => 'Invalid email or password.'], 401);
    }

    // Regenerate session to prevent fixation attacks
    session_regenerate_id(true);
    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['username'] = $user['username'];

    jsonResponse([
        'message'  => 'Login successful.',
        'username' => $user['username'],
    ]);
} catch (PDOException $e) {
    error_log('Login error: ' . $e->getMessage());
    jsonResponse(['error' => 'Login failed. Please try again.'], 500);
}
