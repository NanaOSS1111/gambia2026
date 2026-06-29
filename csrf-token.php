<?php
// Returns a fresh CSRF token for the current session.
// Called by JS just before form submission so the cached HTML token is never used.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Content-Type: application/json');

session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

echo json_encode(['csrf_token' => $_SESSION['csrf_token']]);
