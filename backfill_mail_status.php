<?php
/**
 * One-off backfill of email send timestamps — Admin Only.
 *
 * Per-delegate tracking was added on 24 Aug 2026, so delegates emailed before that read
 * "Not sent" on their record even though the mail went out. This reconstructs those
 * timestamps from Brevo's own delivery events, matching each event to a delegate by
 * address and to a mail type by subject line.
 *
 * Safe to run repeatedly: it only fills columns that are currently empty, and never
 * overwrites a timestamp already recorded.
 */
session_start();
require_once 'session_guard.php';
require_once 'db.php';
require_once 'logger.php';
require_once 'mail_transport.php';   // BREVO_API_KEY
require_once 'mail_tracking.php';

if (!isset($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
}

ensure_mail_tracking_columns($pdo);

/**
 * Which column a subject line corresponds to.
 *
 * Order matters: the official invitation subject also begins "Official Invitation", so
 * the VISA variant must be tested first or every official invitation is misfiled as a
 * plain invitation.
 */
function column_for_subject(string $subject): ?string {
    $s = strtolower($subject);

    if (str_contains($s, 'visa letter'))          return 'official_invite_sent_at';
    if (str_contains($s, 'official invitation'))  return 'invite_sent_at';
    if (str_contains($s, 'registration received'))return 'confirmation_sent_at';
    if (str_contains($s, 'registration approved'))return 'approval_sent_at';
    if (str_contains($s, 'registration update'))  return 'rejection_sent_at';

    return null;   // password resets, config tests, anything else
}

/** Pull delivered events from Brevo, paging until exhausted. */
function fetch_delivered_events(): array {
    if (BREVO_API_KEY === '') {
        return ['ok' => false, 'events' => [], 'error' => 'No Brevo API key configured'];
    }

    $all    = [];
    $offset = 0;
    $limit  = 100;

    for ($page = 0; $page < 25; $page++) {          // hard cap: 2500 events
        $url = 'https://api.brevo.com/v3/smtp/statistics/events?' . http_build_query([
            'event'  => 'delivered',
            'days'   => 90,
            'limit'  => $limit,
            'offset' => $offset,
            'sort'   => 'asc',                       // earliest first: the first delivery wins
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_HTTPHEADER     => ['accept: application/json', 'api-key: ' . BREVO_API_KEY],
        ]);
        $raw    = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        unset($ch);

        if ($status < 200 || $status >= 300) {
            return ['ok' => false, 'events' => $all,
                    'error' => $status ? "Brevo returned HTTP $status" : ($err ?: 'no response')];
        }

        $batch = json_decode((string) $raw, true)['events'] ?? [];
        $all   = array_merge($all, $batch);

        if (count($batch) < $limit) {
            break;                                   // last page
        }
        $offset += $limit;
    }

    return ['ok' => true, 'events' => $all, 'error' => ''];
}

// ── Build the plan ───────────────────────────────────────────────────────────
$fetch  = fetch_delivered_events();
$events = $fetch['events'];

// Delegates keyed by lowercased address.
$byEmail = [];
foreach ($pdo->query("SELECT * FROM registrations") as $row) {
    $byEmail[strtolower(trim($row['email']))][] = $row;
}

$plan       = [];   // rows we would write
$skipKnown  = 0;    // already recorded
$unmatched  = 0;    // no delegate with that address
$ignored    = 0;    // subject is not a delegate mail
$seen       = [];   // first delivery per delegate+column wins

foreach ($events as $ev) {
    $email   = strtolower(trim($ev['email'] ?? ''));
    $subject = (string) ($ev['subject'] ?? '');
    $col     = column_for_subject($subject);

    if ($col === null) { $ignored++;   continue; }
    if (!isset($byEmail[$email])) { $unmatched++; continue; }

    foreach ($byEmail[$email] as $reg) {
        $key = $reg['id'] . '|' . $col;
        if (isset($seen[$key])) {
            continue;                                 // earlier delivery already noted
        }
        $seen[$key] = true;

        $existing = $reg[$col] ?? null;
        if (!empty($existing) && $existing !== '0000-00-00 00:00:00') {
            $skipKnown++;
            continue;                                 // never overwrite
        }

        $plan[] = [
            'id'      => (int) $reg['id'],
            'name'    => trim(($reg['first_name'] ?? '') . ' ' . ($reg['last_name'] ?? '')),
            'email'   => $reg['email'],
            'column'  => $col,
            'label'   => MAIL_TYPES[$col]['label'] ?? $col,
            'when'    => date('Y-m-d H:i:s', strtotime($ev['date'] ?? 'now')),
            'subject' => $subject,
        ];
    }
}

// ── Apply ────────────────────────────────────────────────────────────────────
$applied = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply'])) {
    if (empty($_POST['admin_csrf']) || !hash_equals($_SESSION['admin_csrf'], $_POST['admin_csrf'])) {
        http_response_code(403);
        $applied = ['written' => 0, 'error' => 'Invalid security token. Reload and try again.'];
    } else {
        $written = 0;
        foreach ($plan as $p) {
            // The IS NULL guard makes a double submit harmless.
            $stmt = $pdo->prepare(
                "UPDATE registrations SET `{$p['column']}` = ?
                  WHERE id = ? AND (`{$p['column']}` IS NULL OR `{$p['column']}` = '0000-00-00 00:00:00')"
            );
            $stmt->execute([$p['when'], $p['id']]);
            $written += $stmt->rowCount();
        }
        log_action($pdo, 'backfill_mail_status', "Backfilled $written send timestamp(s) from Brevo delivery events");
        $applied = ['written' => $written, 'error' => ''];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Backfill Email Status — GAMBIA 2026</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1a2332;padding:32px 20px;}
  .wrap{max-width:900px;margin:0 auto;}
  h1{font-size:20px;color:#0a2540;margin-bottom:4px;}
  p.sub{font-size:13px;color:#6b7280;margin-bottom:20px;line-height:1.6;}
  .card{background:#fff;border-radius:14px;box-shadow:0 2px 16px rgba(0,0,0,.06);overflow:hidden;margin-bottom:20px;}
  .card-h{padding:16px 22px;border-bottom:1px solid #f0f4f8;font-size:15px;font-weight:700;color:#0a2540;}
  .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1px;background:#eef2f7;}
  .stat{background:#fff;padding:16px 20px;}
  .stat .v{font-size:24px;font-weight:700;color:#0a2540;font-variant-numeric:tabular-nums;}
  .stat .l{font-size:12px;color:#9aaabf;margin-top:2px;}
  table{width:100%;border-collapse:collapse;}
  thead th{background:#f8fbff;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#7a8fa8;padding:10px 18px;text-align:left;}
  tbody td{padding:10px 18px;font-size:13px;border-top:1px solid #f5f7fa;}
  .nm{font-weight:600;color:#0a2540;}
  .em{font-size:12px;color:#9aaabf;}
  .ty{font-size:12px;color:#4a6080;}
  .wh{font-size:12px;color:#9aaabf;font-variant-numeric:tabular-nums;white-space:nowrap;}
  .banner{padding:14px 18px;border-radius:10px;font-size:13px;margin-bottom:18px;line-height:1.6;}
  .b-ok{background:#e3f5ec;border:1px solid #b7e2cb;color:#14532d;}
  .b-warn{background:#fdf4e3;border:1px solid #f3dcae;color:#8a5a12;}
  .b-err{background:#fdecea;border:1px solid #f5c6cb;color:#8a1c1c;}
  .actions{padding:18px 22px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-top:1px solid #f0f4f8;}
  button{background:#0a2540;color:#fff;border:0;padding:11px 22px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;font-family:inherit;}
  button:disabled{background:#9aa6b2;cursor:not-allowed;}
  a.back{font-size:13px;color:#0a2540;text-decoration:none;}
  .scroll{max-height:460px;overflow-y:auto;}
</style>
</head>
<body>
<div class="wrap">
  <h1>Backfill email status</h1>
  <p class="sub">
    Reconstructs send timestamps from Brevo&rsquo;s delivery records, for delegates emailed
    before per-delegate tracking existed. Only fills entries that are currently blank &mdash;
    it never overwrites a timestamp already recorded, so running it twice is harmless.
  </p>

  <?php if ($fetch['error']): ?>
    <div class="banner b-err"><strong>Could not read Brevo events.</strong> <?= htmlspecialchars($fetch['error']) ?></div>
  <?php endif; ?>

  <?php if ($applied): ?>
    <?php if ($applied['error']): ?>
      <div class="banner b-err"><?= htmlspecialchars($applied['error']) ?></div>
    <?php else: ?>
      <div class="banner b-ok">
        <strong>Done — <?= (int) $applied['written'] ?> timestamp<?= $applied['written'] === 1 ? '' : 's' ?> written.</strong>
        Delegate records now show what was actually sent. You can close this page.
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div class="card">
    <div class="card-h">What Brevo reports</div>
    <div class="stats">
      <div class="stat"><div class="v"><?= count($events) ?></div><div class="l">Delivered events</div></div>
      <div class="stat"><div class="v"><?= count($plan) ?></div><div class="l">Timestamps to write</div></div>
      <div class="stat"><div class="v"><?= $skipKnown ?></div><div class="l">Already recorded</div></div>
      <div class="stat"><div class="v"><?= $unmatched ?></div><div class="l">No matching delegate</div></div>
      <div class="stat"><div class="v"><?= $ignored ?></div><div class="l">Not delegate mail</div></div>
    </div>
  </div>

  <?php if (!$plan): ?>
    <div class="banner b-warn">
      <strong>Nothing to backfill.</strong>
      <?= count($events) === 0
            ? 'Brevo returned no delivery events for the last 90 days.'
            : 'Every delivered message already has a timestamp, or belongs to an address with no delegate record.' ?>
    </div>
    <a class="back" href="admin.php">&larr; Back to Dashboard</a>
  <?php else: ?>
    <div class="card">
      <div class="card-h">Will set <?= count($plan) ?> timestamp<?= count($plan) === 1 ? '' : 's' ?></div>
      <div class="scroll">
        <table>
          <thead><tr><th>Delegate</th><th>Email type</th><th>Delivered</th></tr></thead>
          <tbody>
          <?php foreach ($plan as $p): ?>
            <tr>
              <td><div class="nm"><?= htmlspecialchars($p['name']) ?></div>
                  <div class="em"><?= htmlspecialchars($p['email']) ?></div></td>
              <td class="ty"><?= htmlspecialchars($p['label']) ?></td>
              <td class="wh"><?= htmlspecialchars(date('j M Y, H:i', strtotime($p['when']))) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <form method="POST" class="actions">
        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf']) ?>">
        <button type="submit" name="apply" value="1">Apply <?= count($plan) ?> timestamp<?= count($plan) === 1 ? '' : 's' ?></button>
        <a class="back" href="admin.php">Cancel</a>
      </form>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
