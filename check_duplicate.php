<?php
/**
 * Soft duplicate-name check — returns similar names for a warning.
 * Called via AJAX from the registration form.
 */
session_start();
header('Content-Type: application/json');
require_once 'db.php';

$first = trim($_GET['first'] ?? '');
$last  = trim($_GET['last']  ?? '');

if (strlen($first) < 2 || strlen($last) < 2) {
    echo json_encode(['matches' => []]);
    exit;
}

// SOUNDEX match gives phonetic similarity across languages
$stmt = $pdo->prepare(
    "SELECT first_name, last_name, organisation_name, status FROM registrations
     WHERE (SOUNDEX(first_name) = SOUNDEX(?) AND SOUNDEX(last_name) = SOUNDEX(?))
        OR (LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?))
     ORDER BY submitted_at DESC LIMIT 5"
);
$stmt->execute([$first, $last, $first, $last]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$matches = array_map(fn($r) => [
    'name' => htmlspecialchars($r['first_name'] . ' ' . $r['last_name']),
    'org'  => htmlspecialchars($r['organisation_name']),
    'status' => $r['status'],
], $rows);

echo json_encode(['matches' => $matches]);
