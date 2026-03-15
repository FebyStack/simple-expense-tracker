<?php
/**
 * Auth-guard middleware.
 *
 * Include this file at the top of any endpoint that requires
 * a logged-in user. It exposes $currentUserId for the calling script.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';          // session_start + helpers

if (empty($_SESSION['user_id'])) {
    jsonResponse(['error' => 'Unauthorized. Please log in.'], 401);
}

$currentUserId = (int) $_SESSION['user_id'];
