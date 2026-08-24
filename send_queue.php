<?php
/**
 * Bulk mail queue runner — Admin Only.
 *
 * Bulk sends used to call flush_and_continue() and then loop, relying on the PHP process
 * surviving after the response closed. That depends on fastcgi_finish_request(), a PHP-FPM
 * function which does not exist under LiteSpeed's lsapi — so on this server the process was
 * terminated at the flush and the loop never ran. The admin log entry (written before the
 * flush) appeared while not one message was sent, which is exactly what happened to a
 * 115-delegate batch on 24 Aug 2026.
 *
 * This runs the same work in visible chunks instead: each request sends a few messages and
 * redirects to itself until the queue is empty. No background execution, so it behaves the
 * same on any server, and progress is visible rather than assumed.
 */
session_start();
require_once 'session_guard.php';
require_once 'db.php';
require_once 'logger.php';
require_once 'mailer.php';
require_once 'mail_tracking.php';

if (!isset($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

// How many to send per request. Small enough to finish well inside any execution limit,
// large enough that a few hundred delegates do not take many round trips.
const QUEUE_CHUNK = 8;

/** Mail types this runner can send, and the column that records each. */
const QUEUE_TYPES = [
    'approval' => [
        'column'   => 'approval_sent_at',
        'sender'   => 'send_approval_email',
        'label'    => 'Approval emails',
        'log'      => 'bulk_approve_result',
        'requires' => 'approved',
    ],
    'rejection' => [
        'column'   => 'rejection_sent_at',
        'sender'   => 'send_rejection_email',
        'label'    => 'Rejection emails',
        'log'      => 'bulk_reject_result',
        'requires' => 'rejected',
    ],
    'invitation' => [
        'column'   => 'invite_sent_at',
        'sender'   => 'send_invitation_email',
        'label'    => 'Invitation letters',
        'log'      => 'bulk_invite_result',
        'requires' => 'approved',
    ],
    'official_invitation' => [
        'column'   => 'official_invite_sent_at',
        'sender'   => 'send_official_invitation_email',
        'label'    => 'Official invitations',
        'log'      => 'bulk_official_invite_result',
        'requires' => 'approved',
    ],
];

$job = $_SESSION['mail_job'] ?? null;
if (!$job || !isset(QUEUE_TYPES[$job['type']]) || empty($job['ids'])) {
    unset($_SESSION['mail_job']);
    header('Location: admin.php');
    exit;
}

$cfg = QUEUE_TYPES[$job['type']];
ensure_mail_tracking_columns($pdo);

// Outstanding = selected, in the required status, and not already sent. Deriving this from
// the database each pass makes the run resumable and safe to repeat: anything already sent
// simply drops out of the query.
$ph   = implode(',', array_fill(0, count($job['ids']), '?'));
$col  = $cfg['column'];
$stmt = $pdo->prepare(
    "SELECT * FROM registrations
      WHERE id IN ($ph) AND status = ? AND (`$col` IS NULL OR `$col` = '0000-00-00 00:00:00')
      ORDER BY id
      LIMIT " . QUEUE_CHUNK
);
$stmt->execute([...$job['ids'], $cfg['requires']]);
$batch = $stmt->fetchAll();

// Count what is still outstanding overall, for the progress display.
$remainStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM registrations
      WHERE id IN ($ph) AND status = ? AND (`$col` IS NULL OR `$col` = '0000-00-00 00:00:00')"
);
$remainStmt->execute([...$job['ids'], $cfg['requires']]);
$remaining = (int) $remainStmt->fetchColumn();

$total = (int) ($job['total'] ?? count($job['ids']));

// ── Nothing left: report the real outcome and finish ─────────────────────────
if (!$batch) {
    $sent     = (int) ($job['sent'] ?? 0);
    $failed   = (int) ($job['failed'] ?? 0);
    $failures = $job['failures'] ?? [];

    log_action($pdo, $cfg['log'], $cfg['label'] . ': ' . bulk_result_summary([
        'sent' => $sent, 'failed' => $failed, 'skipped' => max(0, $total - $sent - $failed),
        'failures' => $failures,
    ]));

    $_SESSION['flash'] = [
        'type' => $failed ? 'warning' : 'success',
        'msg'  => $cfg['label'] . ": {$sent} sent"
                . ($failed ? ", {$failed} failed — see Activity Logs" : '')
                . '.',
    ];
    unset($_SESSION['mail_job']);
    header('Location: ' . ($job['return'] ?? 'admin.php'));
    exit;
}

// ── Send this chunk ──────────────────────────────────────────────────────────
$result = send_bulk($pdo, $batch, $col, static function (array $row) use ($cfg, $job): bool {
    return $cfg['sender'] === 'send_rejection_email'
        ? send_rejection_email($row, (string) ($job['reason'] ?? ''))
        : ($cfg['sender'])($row);
});

$job['sent']     = (int) ($job['sent'] ?? 0) + $result['sent'];
$job['failed']   = (int) ($job['failed'] ?? 0) + $result['failed'];
$job['failures'] = array_merge($job['failures'] ?? [], $result['failures']);
$_SESSION['mail_job'] = $job;

$done    = $job['sent'] + $job['failed'];
$percent = $total > 0 ? min(100, (int) round($done / $total * 100)) : 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="1;url=send_queue.php">
<title>Sending — GAMBIA 2026</title>
<style>
  *,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
         background:#f0f4f8; color:#1a2332; display:flex; align-items:center;
         justify-content:center; min-height:100vh; padding:24px; }
  .card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08);
          padding:36px; max-width:460px; width:100%; text-align:center; }
  h1 { font-size:19px; color:#0a2540; margin-bottom:6px; }
  p.sub { font-size:13px; color:#6b7280; margin-bottom:22px; }
  .bar { height:10px; background:#e8f0f8; border-radius:6px; overflow:hidden; }
  .bar span { display:block; height:100%; background:#0a2540; border-radius:6px;
              transition:width .3s ease; }
  .counts { display:flex; justify-content:space-between; font-size:12px;
            color:#6b7280; margin-top:10px; font-variant-numeric:tabular-nums; }
  .warn { margin-top:20px; padding:12px 14px; background:#fdf4e3; border-radius:8px;
          font-size:12px; color:#8a5a12; line-height:1.6; }
  .fail { margin-top:14px; font-size:12px; color:#a3231f; }
</style>
</head>
<body>
<div class="card">
  <h1><?= htmlspecialchars($cfg['label']) ?></h1>
  <p class="sub">Sending in batches of <?= QUEUE_CHUNK ?>. Keep this tab open.</p>

  <div class="bar"><span style="width:<?= $percent ?>%"></span></div>
  <div class="counts">
    <span><?= $done ?> of <?= $total ?> processed</span>
    <span><?= $percent ?>%</span>
  </div>

  <?php if ($job['failed']): ?>
    <div class="fail"><?= (int) $job['failed'] ?> failed so far — they are named in Activity Logs.</div>
  <?php endif; ?>

  <div class="warn">
    Do not close this tab until it finishes. If you do, re-run the same action &mdash;
    delegates already sent to are skipped automatically, so nobody receives a duplicate.
  </div>
</div>
</body>
</html>
