<?php
/**
 * GET /api/session.php
 *
 * Returns the current session state:
 *   - logged_in: bool
 *   - username:  string (only when logged in)
 *   - csrf_token: string (always)
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

jsonResponse([
    'logged_in'  => !empty($_SESSION['user_id']),
    'username'   => $_SESSION['username'] ?? null,
    'csrf_token' => generateCsrfToken(),
]);
