<?php
/**
 * POST /api/register.php
 *
 * Body: username, email, password, confirm_password, csrf_token
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
$username        = trim($_POST['username'] ?? '');
$email           = trim($_POST['email'] ?? '');
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

$errors = [];

if ($username === '' || strlen($username) < 2) {
    $errors[] = 'Username must be at least 2 characters.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (strlen($password) < 6) {
    $errors[] = 'Password must be at least 6 characters.';
}
if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}

if ($errors) {
    jsonResponse(['error' => implode(' ', $errors)], 422);
}

// ---------------------------------------------------------------------------
// Check for duplicate email
// ---------------------------------------------------------------------------
try {
    $pdo = Database::connect();

    $stmt = $pdo->prepare('SELECT id FROM exptrack.users WHERE LOWER(email) = LOWER(:email)');
    $stmt->execute([':email' => $email]);

    if ($stmt->fetch()) {
        jsonResponse(['error' => 'An account with this email already exists.'], 409);
    }

    // -----------------------------------------------------------------------
    // Create the user
    // -----------------------------------------------------------------------
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO exptrack.users (username, email, password_hash)
         VALUES (:username, :email, :password_hash)
         RETURNING id'
    );
    $stmt->execute([
        ':username'      => $username,
        ':email'         => $email,
        ':password_hash' => $hash,
    ]);

    $userId = (int) $stmt->fetchColumn();

    // Auto-login after registration
    session_regenerate_id(true);
    $_SESSION['user_id']  = $userId;
    $_SESSION['username'] = $username;

    jsonResponse([
        'message'  => 'Registration successful.',
        'username' => $username,
    ], 201);
} catch (PDOException $e) {
    error_log('Registration error: ' . $e->getMessage());
    jsonResponse(['error' => 'Registration failed. Please try again.'], 500);
}
