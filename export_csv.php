<?php
session_start();
require_once 'session_guard.php';
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }
require_once 'db.php';
require_once 'logger.php';

// CSRF check
if (
    empty($_POST['admin_csrf']) ||
    empty($_SESSION['admin_csrf']) ||
    !hash_equals($_SESSION['admin_csrf'], $_POST['admin_csrf'])
) {
    http_response_code(403);
    die('Forbidden: invalid security token.');
}

$ids = array_map('intval', (array)($_POST['ids'] ?? []));
$all = !empty($_POST['export_all']);

if (!$all && empty($ids)) {
    die('No records selected.');
}

if ($all) {
    $status = $_POST['status'] ?? 'all';
    $search = trim($_POST['search'] ?? '');
    $where  = [];
    $params = [];
    if ($status !== 'all') { $where[] = 'status = ?'; $params[] = $status; }
    if ($search !== '') {
        $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR organisation_name LIKE ?)';
        $params  = array_merge($params, array_fill(0, 4, "%$search%"));
    }
    $sql  = 'SELECT * FROM registrations' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY submitted_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
} else {
    $ph   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id IN ($ph) ORDER BY submitted_at DESC");
    $stmt->execute($ids);
}

$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

log_action($pdo, 'export_csv', 'Exported ' . count($rows) . ' records to CSV');

// All available fields: key => [label, resolver]
// Resolver is either a DB column name (string) or a callable.
$allFields = [
    'ref'             => ['Reference',               fn($r) => 'GAM26-' . str_pad($r['id'], 5, '0', STR_PAD_LEFT)],
    'status'          => ['Status',                  'status'],
    'first_name'      => ['First Name',              'first_name'],
    'last_name'       => ['Last Name',               'last_name'],
    'title'           => ['Title',                   'title'],
    'gender'          => ['Gender',                  'gender'],
    'email'           => ['Email',                   'email'],
    'personal_phone'  => ['Personal Phone',          'personal_phone'],
    'contact_number'  => ['Contact Number (Gambia)', 'contact_number'],
    'nationality'     => ['Nationality',             'passport_nationality'],
    'passport_number' => ['Passport Number',         'passport_number'],
    'passport_expiry' => ['Passport Expiry',         'passport_expiration'],
    'arrival'         => ['Arrival Date',            'arrival_date'],
    'departure'       => ['Departure Date',          'departure_date'],
    'postal_address'  => ['Postal Address',          'home_address'],
    'address_gambia'  => ['Address in Gambia',       'address_in_country'],
    'rep_type'        => ['Representation Type',     'representation_type'],
    'organisation'    => ['Organisation',            'organisation_name'],
    'position'        => ['Position',                'position'],
    'institution'     => ['Institution',             'institution'],
    'scholarship'     => ['Scholarship',             'scholarship'],
    'submitted_at'    => ['Submitted At',            'submitted_at'],
];

// Use requested fields, falling back to all if none specified
$requested = array_filter((array)($_POST['fields'] ?? []), fn($k) => isset($allFields[$k]));
$selected  = $requested ?: array_keys($allFields);

function csv_safe(string $v): string {
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

$filename = 'gambia2026_delegates_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
echo "\xEF\xBB\xBF"; // BOM for Excel UTF-8

$out = fopen('php://output', 'w');

// Header row — only selected columns
fputcsv($out, array_map(fn($k) => $allFields[$k][0], $selected));

foreach ($rows as $r) {
    $cells = [];
    foreach ($selected as $key) {
        $resolver = $allFields[$key][1];
        $cells[]  = is_callable($resolver) ? $resolver($r) : csv_safe((string)($r[$resolver] ?? ''));
    }
    // ref is already safe; apply csv_safe to non-callable cells (already done above)
    fputcsv($out, $cells);
}

fclose($out);
exit;
