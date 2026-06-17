<?php
session_start();
require_once 'session_guard.php';
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }
require_once 'db.php';
require_once 'logger.php';

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

$filename = 'gambia2026_delegates_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');

// BOM for Excel UTF-8
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, [
    'Reference', 'Status', 'Representation Type', 'Organisation',
    'Title', 'First Name', 'Last Name', 'Gender', 'Email',
    'Phone', 'Nationality', 'Passport Number', 'Passport Expiry',
    'Arrival', 'Departure', 'Postal Address', 'Address in Country', 'Position',
    'Submitted At',
]);

foreach ($rows as $r) {
    $ref = 'GAM26-' . str_pad($r['id'], 5, '0', STR_PAD_LEFT);
    fputcsv($out, [
        $ref,
        $r['status'],
        $r['representation_type'],
        $r['organisation_name'],
        $r['title'] ?? '',
        $r['first_name'],
        $r['last_name'],
        $r['gender'],
        $r['email'],
        $r['contact_number'],
        $r['passport_nationality'],
        $r['passport_number'],
        $r['passport_expiration'],
        $r['arrival_date'],
        $r['departure_date'],
        $r['home_address'] ?? '',
        $r['address_in_country'],
        $r['position'] ?? '',
        $r['submitted_at'],
    ]);
}

fclose($out);
exit;
