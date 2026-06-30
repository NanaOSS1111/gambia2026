<?php
/**
 * Include at the top of any admin page (after session_start()) to enforce
 * 30-minute inactivity timeout.
 */
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds

function enforce_admin_session(): void {
    if (!isset($_SESSION['admin'])) return; // not logged in — let the page handle redirect

    $now = time();

    if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        $name = $_SESSION['admin_name'] ?? 'unknown';
        session_unset();
        session_destroy();
        session_start(); // restart to allow flash
        $_SESSION['flash'] = ['type' => 'warning', 'title' => 'Session Expired', 'text' => 'You were logged out after 30 minutes of inactivity.'];
        header('Location: admin.php');
        exit;
    }

    $_SESSION['last_activity'] = $now;
}

enforce_admin_session();

// Ensure a per-session CSRF token exists for admin forms
if (!isset($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}
