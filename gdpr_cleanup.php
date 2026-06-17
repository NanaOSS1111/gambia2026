<?php
/**
 * GDPR Data Retention Cleanup
 *
 * Anonymizes rejected registrations that are older than 90 days.
 * Personal data (name, email, phone, passport details, uploaded files) is
 * wiped; the anonymized record is kept for statistics.
 *
 * Run this script:
 *   - Manually from the admin panel (button below), OR
 *   - Via a cron job on the server:
 *       0 3 * * * /usr/bin/php /home/.../registration/gdpr_cleanup.php --cron
 */

$isCron = in_array('--cron', $argv ?? []);

if (!$isCron) {
    session_start();
    require_once 'session_guard.php';
    if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }
}

require_once 'db.php';
require_once 'logger.php';

$cutoff = date('Y-m-d H:i:s', strtotime('-90 days'));

// Find records to anonymize
$stmt = $pdo->prepare(
    "SELECT id, picture, passport_file, nomination_letter
     FROM registrations
     WHERE status = 'rejected'
       AND submitted_at < ?
       AND (email NOT LIKE '%[anonymized]%')"
);
$stmt->execute([$cutoff]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$count = 0;
foreach ($rows as $r) {
    // Delete uploaded files
    foreach (['picture', 'passport_file', 'nomination_letter'] as $field) {
        if (!empty($r[$field])) {
            $path = __DIR__ . '/uploads/' . $r[$field];
            if (file_exists($path)) @unlink($path);
        }
    }

    // Anonymize the record
    $pdo->prepare(
        "UPDATE registrations SET
            first_name           = '[Anonymized]',
            last_name            = '[Anonymized]',
            email                = CONCAT(id, '[anonymized]@deleted.invalid'),
            contact_number       = '[Removed]',
            passport_number      = '[Removed]',
            passport_nationality = '[Removed]',
            address_in_country   = '[Removed]',
            picture              = NULL,
            passport_file        = NULL,
            nomination_letter    = NULL,
            organisation_name    = COALESCE(organisation_name, '[Removed]'),
            admin_notes          = NULL
         WHERE id = ?"
    )->execute([$r['id']]);
    $count++;
}

if (!$isCron) {
    log_action($pdo, 'gdpr_cleanup', "Anonymized {$count} records older than 90 days (rejected)");
}

if ($isCron) {
    echo date('Y-m-d H:i:s') . " — GDPR cleanup: {$count} records anonymized.\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>GDPR Cleanup — GAMBIA 2026 Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
  .card{background:#fff;border-radius:20px;padding:44px 40px;width:100%;max-width:520px;box-shadow:0 8px 40px rgba(0,0,0,.12);}
  h2{font-size:20px;font-weight:700;color:#0a2540;margin-bottom:6px;}
  .sub{font-size:13px;color:#9aaabf;margin-bottom:24px;line-height:1.5;}
  .result{background:#dcfce7;border:1px solid #86efac;border-radius:12px;padding:20px 24px;text-align:center;margin-bottom:20px;}
  .result h3{color:#166534;font-size:18px;margin-bottom:6px;}
  .result p{color:#166534;font-size:14px;}
  .warn{background:#fef9c3;border:1px solid #fde047;border-radius:10px;padding:14px 18px;font-size:13px;color:#713f12;margin-bottom:24px;line-height:1.6;}
  a.btn{display:inline-block;background:#0a2540;color:#fff;text-decoration:none;padding:11px 28px;border-radius:8px;font-size:14px;font-weight:700;transition:background .2s;}
  a.btn:hover{background:#0d6e8c;}
  .info{background:#f0f4f8;border-radius:10px;padding:16px 18px;font-size:13px;color:#4a6080;margin-bottom:20px;line-height:1.6;}
  code{background:#e8edf2;padding:1px 5px;border-radius:4px;font-size:12px;}
</style>
</head>
<body>
<div class="card">
  <h2>GDPR Data Retention Cleanup</h2>
  <p class="sub">Anonymizes personal data from <strong>rejected</strong> registrations that are older than <strong>90 days</strong>.</p>

  <div class="result">
    <h3>✓ Cleanup Complete</h3>
    <p><?= $count ?> record<?= $count !== 1 ? 's' : '' ?> anonymized.</p>
  </div>

  <div class="info">
    <strong>What was removed:</strong><br>
    Name, email, phone, passport details, uploaded files, and admin notes for each affected record.
    The anonymized records remain for statistical purposes (status, type, date).
  </div>

  <div class="warn">
    &#8987; <strong>Tip:</strong> Automate this with a daily cron job:<br>
    <code>0 3 * * * php /path/to/registration/gdpr_cleanup.php --cron</code>
  </div>

  <a href="admin.php" class="btn">← Back to Admin</a>
</div>
</body>
</html>
