<?php
/**
 * Database connection singleton and shared utilities.
 *
 * Environment variables (with defaults):
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Session — start once, shared by every script that includes this file
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------------------------------------------------------------------------
// Database singleton
// ---------------------------------------------------------------------------
class Database
{
    private static ?PDO $instance = null;

    public static function connect(): PDO
    {
        if (self::$instance instanceof PDO) {
            return self::$instance;
        }

        $host = getenv('DB_HOST') ?: 'localhost';
        $port = getenv('DB_PORT') ?: '5432';
        $dbname = getenv('DB_NAME') ?: 'Expense-Tracker';
        $user = getenv('DB_USER') ?: 'postgres';
        $password = getenv('DB_PASSWORD') ?: 'bingbong321';

        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $dbname);

        try {
            self::$instance = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);

            self::$instance->exec('SET search_path TO exptrack, public');
        } catch (PDOException $e) {
            // Log the real error; never expose it to the client
            error_log('DB connection failed: ' . $e->getMessage());
            jsonResponse(['error' => 'Database connection failed.'], 500);
        }

        return self::$instance;
    }
}

// ---------------------------------------------------------------------------
// JSON response helper
// ---------------------------------------------------------------------------
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// ---------------------------------------------------------------------------
// CSRF helpers
// ---------------------------------------------------------------------------

/**
 * Generate (or return existing) CSRF token stored in the session.
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate the CSRF token sent with a form submission.
 * Aborts with 403 if the token is missing or wrong.
 */
function validateCsrfToken(): void
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!hash_equals(generateCsrfToken(), (string) $token)) {
        jsonResponse(['error' => 'Invalid or missing CSRF token.'], 403);
    }
}
