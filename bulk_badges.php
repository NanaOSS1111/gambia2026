<?php
session_start();
require_once 'session_guard.php';
if (!isset($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

require_once 'db.php';
require_once 'mail_config.php';

// Increase execution time and memory for batch operations
@ini_set('memory_limit', '512M');
@set_time_limit(300);

// Parameters
$scope       = $_GET['scope'] ?? ($_POST['scope'] ?? 'approved');
$idsParam    = $_GET['ids'] ?? ($_POST['ids'] ?? '');
$layout      = $_GET['layout'] ?? 'badge'; // 'badge' (86x118mm) or 'a4' (4 per page)
$batchLimit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 0;
$batchOffset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$typeFilter  = trim($_GET['type'] ?? '');
$search      = trim($_GET['search'] ?? '');
$action      = $_GET['action'] ?? '';

// Build Query
$where  = ["status = 'approved'"];
$params = [];

if ($scope === 'selected' || !empty($idsParam)) {
    $ids = is_array($idsParam) ? array_map('intval', $idsParam) : array_map('intval', explode(',', (string)$idsParam));
    $ids = array_filter($ids, fn($id) => $id > 0);
    if (!empty($ids)) {
        $inClause = implode(',', array_fill(0, count($ids), '?'));
        $where = ["id IN ($inClause)"];
        $params = array_values($ids);
    }
} else {
    if ($typeFilter !== '') {
        $where[] = "representation_type = ?";
        $params[] = $typeFilter;
    }
    if ($search !== '') {
        $where[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR organisation_name LIKE ?)";
        $params  = array_merge($params, array_fill(0, 4, "%$search%"));
    }
}

$whereSql = 'WHERE ' . implode(' AND ', $where);

// Total count matching query
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations $whereSql");
$countStmt->execute($params);
$totalMatching = (int)$countStmt->fetchColumn();

// Fetch records
$sql = "SELECT * FROM registrations $whereSql ORDER BY id ASC";
if ($batchLimit > 0) {
    $sql .= " LIMIT $batchLimit OFFSET $batchOffset";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$delegates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Representation types for filter dropdown
$typesList = $pdo->query("SELECT DISTINCT representation_type FROM registrations WHERE status='approved' AND representation_type != '' ORDER BY representation_type ASC")->fetchAll(PDO::FETCH_COLUMN);

// Handle ZIP Download (Single offline HTML package with badges)
if ($action === 'zip') {
    if (!class_exists('ZipArchive')) {
        die('ZipArchive extension is not available on this server.');
    }
    $zip = new ZipArchive();
    $zipFilename = sys_get_temp_dir() . '/GAMBIA2026_Badges_' . date('Ymd_His') . '.zip';
    if ($zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die('Unable to create temporary zip archive.');
    }

    // Build self-contained HTML file
    ob_start();
    $isExport = true;
    include __DIR__ . '/_badge_template.php';
    $htmlContent = ob_get_clean();

    $zip->addFromString('GAMBIA2026_Printable_Badges.html', $htmlContent);
    
    // Add delegate CSV index
    $csv = "Reference,First Name,Last Name,Organisation,Representation Type,Country,Email\n";
    foreach ($delegates as $d) {
        $ref = 'GAM26-' . str_pad($d['id'], 5, '0', STR_PAD_LEFT);
        $csv .= sprintf('"%s","%s","%s","%s","%s","%s","%s"' . "\n",
            $ref,
            str_replace('"', '""', $d['first_name']),
            str_replace('"', '""', $d['last_name']),
            str_replace('"', '""', $d['organisation_name'] ?? ''),
            str_replace('"', '""', $d['representation_type'] ?? ''),
            str_replace('"', '""', $d['passport_nationality'] ?? ''),
            str_replace('"', '""', $d['email'])
        );
    }
    $zip->addFromString('delegates_list.csv', $csv);
    $zip->close();

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="GAMBIA2026_Badges_' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . filesize($zipFilename));
    readfile($zipFilename);
    @unlink($zipFilename);
    exit;
}

$isExport = false;
include __DIR__ . '/_badge_template.php';
