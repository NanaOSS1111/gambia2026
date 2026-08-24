<?php
/**
 * Email delivery statistics — Admin Only.
 *
 * Mirrors the useful half of Brevo's dashboard inside the admin, so delivery can be
 * checked without leaving the platform. The bounce table is the point: it names the
 * addresses that failed and why, which is the only part that needs acting on.
 */
session_start();
require_once 'session_guard.php';
require_once 'db.php';
require_once 'mail_transport.php';   // BREVO_API_KEY, MAIL_PROVIDER

if (!isset($_SESSION['admin'])) {
    header('Location: admin.php');
    exit;
}

/** Call a Brevo statistics endpoint. */
function brevo_stats(string $path, array $query = []): array {
    if (BREVO_API_KEY === '') {
        return ['ok' => false, 'body' => null, 'error' => 'No Brevo API key configured in .env'];
    }

    $url = 'https://api.brevo.com/v3' . $path . ($query ? '?' . http_build_query($query) : '');
    $ch  = curl_init($url);
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
        return [
            'ok'    => false,
            'body'  => null,
            'error' => $status ? "Brevo returned HTTP $status" : ('Request failed: ' . ($err ?: 'no response')),
        ];
    }
    return ['ok' => true, 'body' => json_decode((string) $raw, true), 'error' => ''];
}

// ── Date range ───────────────────────────────────────────────────────────────
$ranges = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];
$days   = (int) ($_GET['days'] ?? 7);
if (!isset($ranges[$days])) {
    $days = 7;
}
$startDate = date('Y-m-d', strtotime("-" . ($days - 1) . " days"));
$endDate   = date('Y-m-d');

$agg    = brevo_stats('/smtp/statistics/aggregatedReport', ['startDate' => $startDate, 'endDate' => $endDate]);

// Brevo rejects `days` alongside startDate/endDate — they are mutually exclusive, and
// sending both returns HTTP 400.
$daily  = brevo_stats('/smtp/statistics/reports', ['startDate' => $startDate, 'endDate' => $endDate]);

$bounce = brevo_stats('/smtp/statistics/events', [
    'startDate' => $startDate, 'endDate' => $endDate, 'event' => 'bounces', 'limit' => 50, 'sort' => 'desc',
]);

// Name the failing call. A bare "HTTP 400" gives no clue which of three requests broke.
$apiErrors = [];
foreach (['Totals' => $agg, 'Daily chart' => $daily, 'Bounce list' => $bounce] as $what => $res) {
    if ($res['error']) {
        $apiErrors[] = $what . ': ' . $res['error'];
    }
}

// ── Aggregate figures ────────────────────────────────────────────────────────
$a           = $agg['body'] ?? [];
$requests    = (int) ($a['requests'] ?? 0);
$delivered   = (int) ($a['delivered'] ?? 0);
$hardBounces = (int) ($a['hardBounces'] ?? 0);
$softBounces = (int) ($a['softBounces'] ?? 0);
$bounced     = $hardBounces + $softBounces;
$opens       = (int) ($a['uniqueOpens'] ?? $a['opens'] ?? 0);
$clicks      = (int) ($a['uniqueClicks'] ?? $a['clicks'] ?? 0);
$blocked     = (int) ($a['blocked'] ?? 0);
$spam        = (int) ($a['spamReports'] ?? 0);

/** Percentage of sent volume, guarding against division by zero. */
function pct(int $part, int $whole): string {
    return $whole > 0 ? number_format($part / $whole * 100, 1) . '%' : '—';
}

// ── Per-day series, zero-filled so gaps show as gaps ─────────────────────────
$byDate = [];
foreach ($daily['body']['reports'] ?? [] as $r) {
    $byDate[$r['date']] = $r;
}
$series = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-$i days"));
    $row = $byDate[$d] ?? [];
    $del = (int) ($row['delivered'] ?? 0);
    $bnc = (int) ($row['hardBounces'] ?? 0) + (int) ($row['softBounces'] ?? 0);
    $series[] = ['date' => $d, 'delivered' => $del, 'bounced' => $bnc, 'total' => $del + $bnc];
}
$peak = max(1, max(array_column($series, 'total')));

// Chart geometry
$chartH = 200;
$barGap = 6;
$barW   = $days > 30 ? 8 : ($days > 7 ? 18 : 46);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Email Statistics — GAMBIA 2026</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1a2332;min-height:100vh;}

  /* Series colours. Blue/orange rather than green/red: the green-red pair fails
     colour-vision separation (deutan ΔE 5.7), which is the commonest chart mistake. */
  :root{
    --delivered:#1d6fa5;
    --bounced:#c2410c;
    --ink:#1a2332; --ink-2:#4a6080; --muted:#9aaabf;
    --grid:#e8f0f8; --surface:#fff;
  }

  .nav{background:#fff;border-bottom:1px solid #e8f0f8;height:64px;display:flex;align-items:center;padding:0 32px;gap:16px;position:sticky;top:0;z-index:200;box-shadow:0 1px 6px rgba(0,0,0,.06);}
  .nav-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
  .nav-brand-text{font-size:15px;font-weight:700;color:#0a2540;line-height:1.2;}
  .nav-brand-text small{display:block;font-size:11px;font-weight:500;color:var(--muted);}
  .nav-spacer{flex:1;}
  .nav-link{display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;color:var(--ink-2);text-decoration:none;background:#f0f4f8;}
  .nav-link:hover{background:#e2eaf4;}

  .main{max-width:1150px;margin:0 auto;padding:32px 28px;}
  h1{font-size:20px;color:#0a2540;margin-bottom:4px;}
  p.sub{font-size:13px;color:var(--muted);margin-bottom:22px;}

  .rangebar{display:flex;gap:6px;margin-bottom:22px;}
  .rangebar a{padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;color:var(--ink-2);background:#fff;border:1px solid #e2eaf4;}
  .rangebar a.active{background:#0a2540;color:#fff;border-color:#0a2540;}
  .autorefresh{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:var(--ink-2);background:#fff;border:1px solid #e2eaf4;border-radius:20px;padding:6px 14px;cursor:pointer;margin-left:6px;}
  .autorefresh input{cursor:pointer;}
  .updated{display:flex;align-items:center;font-size:12px;color:var(--muted);margin-left:auto;font-variant-numeric:tabular-nums;}

  .tiles{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:24px;}
  .tile{background:var(--surface);border-radius:12px;padding:18px 20px;box-shadow:0 2px 12px rgba(0,0,0,.06);}
  .tile .val{font-size:26px;font-weight:700;color:#0a2540;line-height:1.1;font-variant-numeric:tabular-nums;}
  .tile .lbl{font-size:12px;color:var(--muted);margin-top:3px;}
  .tile .rate{font-size:12px;font-weight:600;margin-top:6px;font-variant-numeric:tabular-nums;}
  .r-good{color:var(--delivered);} .r-bad{color:var(--bounced);} .r-mute{color:var(--muted);}

  .card{background:var(--surface);border-radius:14px;box-shadow:0 2px 16px rgba(0,0,0,.06);overflow:hidden;margin-bottom:22px;}
  .card-h{padding:16px 22px;border-bottom:1px solid #f0f4f8;display:flex;align-items:center;gap:14px;flex-wrap:wrap;}
  .card-t{font-size:15px;font-weight:700;color:#0a2540;}
  .legend{display:flex;gap:14px;margin-left:auto;}
  .legend span{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--ink-2);}
  .swatch{width:10px;height:10px;border-radius:2px;flex-shrink:0;}

  .chart-wrap{padding:20px 22px 8px;overflow-x:auto;}
  .bar{cursor:pointer;}
  .bar:hover .seg{opacity:.82;}
  .axis-lbl{font-size:10px;fill:var(--muted);}
  .gridline{stroke:var(--grid);stroke-width:1;}

  table{width:100%;border-collapse:collapse;}
  thead th{background:#f8fbff;font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#7a8fa8;padding:10px 18px;text-align:left;border-bottom:1px solid #e8f0f8;}
  tbody td{padding:11px 18px;font-size:13px;border-bottom:1px solid #f5f7fa;vertical-align:top;}
  tbody tr:hover{background:#f8fbff;}
  .mail{font-weight:600;color:#0a2540;}
  .reason{font-size:12px;color:var(--ink-2);max-width:420px;}
  .when{font-size:12px;color:var(--muted);white-space:nowrap;}
  .chip{display:inline-block;padding:2px 9px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}
  .chip-hard{background:#fdecea;color:#a3231f;}
  .chip-soft{background:#fdf4e3;color:#8a5a12;}

  .empty{padding:40px 22px;text-align:center;color:var(--muted);font-size:13px;}
  .err{margin-bottom:20px;padding:14px 18px;background:#fdecea;border:1px solid #f5c6cb;border-radius:10px;font-size:13px;color:#8a1c1c;}
  .note{padding:14px 22px;font-size:12px;color:var(--muted);line-height:1.6;border-top:1px solid #f0f4f8;}

  #tip{position:fixed;pointer-events:none;background:#0a2540;color:#fff;padding:8px 11px;border-radius:7px;font-size:12px;line-height:1.5;opacity:0;transition:opacity .12s;z-index:400;white-space:nowrap;}
  #tip b{font-weight:600;}
</style>
</head>
<body>
<nav class="nav">
  <a class="nav-brand" href="admin.php">
    <img src="asset/organizationLOGO.png" alt="" style="width:38px;height:38px;object-fit:contain;">
    <span class="nav-brand-text">GAMBIA 2026 <small>Email Statistics</small></span>
  </a>
  <div class="nav-spacer"></div>
  <a href="logs.php" class="nav-link">Activity Logs</a>
  <a href="admin.php" class="nav-link">← Dashboard</a>
</nav>

<div class="main">
  <h1>Email delivery</h1>
  <p class="sub">
    Live from Brevo, <?= htmlspecialchars(date('j M', strtotime($startDate))) ?>–<?= htmlspecialchars(date('j M Y', strtotime($endDate))) ?>.
    Covers every message the site sends: confirmations, approvals and invitations.
  </p>

  <div class="rangebar">
    <?php foreach ($ranges as $d => $lbl): ?>
      <a href="?days=<?= $d ?>" class="<?= $d === $days ? 'active' : '' ?>"><?= htmlspecialchars($lbl) ?></a>
    <?php endforeach; ?>
    <label class="autorefresh" title="Reload every 30 seconds">
      <input type="checkbox" id="autoRefresh"> Auto-refresh
    </label>
    <span class="updated">Updated <?= date('H:i:s') ?><span id="countdown"></span></span>
  </div>

  <?php if ($apiErrors): ?>
    <div class="err">
      <strong>Some data could not be loaded.</strong>
      <?= htmlspecialchars(implode(' · ', $apiErrors)) ?>.
      Everything else on this page is still accurate.
    </div>
  <?php endif; ?>

  <div class="tiles">
    <div class="tile">
      <div class="val"><?= number_format($requests) ?></div>
      <div class="lbl">Sent</div>
      <div class="rate r-mute"><?= $days ?> day<?= $days === 1 ? '' : 's' ?></div>
    </div>
    <div class="tile">
      <div class="val"><?= number_format($delivered) ?></div>
      <div class="lbl">Delivered</div>
      <div class="rate r-good"><?= pct($delivered, $requests) ?> of sent</div>
    </div>
    <div class="tile">
      <div class="val"><?= number_format($bounced) ?></div>
      <div class="lbl">Bounced</div>
      <div class="rate <?= $bounced ? 'r-bad' : 'r-mute' ?>"><?= pct($bounced, $requests) ?> of sent</div>
    </div>
    <div class="tile">
      <div class="val"><?= number_format($opens) ?></div>
      <div class="lbl">Opened</div>
      <div class="rate r-mute"><?= pct($opens, $delivered) ?> of delivered</div>
    </div>
    <div class="tile">
      <div class="val"><?= number_format($clicks) ?></div>
      <div class="lbl">Clicked</div>
      <div class="rate r-mute"><?= pct($clicks, $delivered) ?> of delivered</div>
    </div>
  </div>

  <!-- Daily volume -->
  <div class="card">
    <div class="card-h">
      <span class="card-t">Daily volume</span>
      <div class="legend">
        <span><i class="swatch" style="background:var(--delivered)"></i>Delivered</span>
        <span><i class="swatch" style="background:var(--bounced)"></i>Bounced</span>
      </div>
    </div>
    <div class="chart-wrap">
      <?php
      $chartW = max(600, count($series) * ($barW + $barGap) + 40);
      $topPad = 18;
      ?>
      <svg width="100%" viewBox="0 0 <?= $chartW ?> <?= $chartH + 44 ?>" role="img"
           aria-label="Daily delivered and bounced email volume" style="min-width:<?= $chartW ?>px;">
        <?php // Recessive gridlines at quarter steps of the peak. ?>
        <?php for ($g = 0; $g <= 4; $g++):
            $y   = $topPad + $chartH - ($chartH * $g / 4);
            $val = (int) round($peak * $g / 4); ?>
          <line class="gridline" x1="34" y1="<?= $y ?>" x2="<?= $chartW ?>" y2="<?= $y ?>"/>
          <text class="axis-lbl" x="28" y="<?= $y + 3 ?>" text-anchor="end"><?= $val ?></text>
        <?php endfor; ?>

        <?php foreach ($series as $i => $s):
            $x     = 40 + $i * ($barW + $barGap);
            $hDel  = $s['delivered'] > 0 ? max(2, $chartH * $s['delivered'] / $peak) : 0;
            $hBnc  = $s['bounced']   > 0 ? max(2, $chartH * $s['bounced']   / $peak) : 0;
            // 2px surface gap between stacked segments so they read as separate marks.
            // Take it out of the larger segment rather than adding to the total, or a bar
            // at the peak overflows the plot area by exactly the gap.
            if ($hDel > 0 && $hBnc > 0) {
                $hDel = max(2, $hDel - 2);
            }
            $yBnc  = $topPad + $chartH - $hBnc;
            $yDel  = $yBnc - $hDel - ($hBnc > 0 ? 2 : 0);
            // Both segments carry a 2px minimum so a single message stays visible, which can
            // push a full-height stack past the top. Trim the upper segment to the plot area.
            if ($hDel > 0 && $yDel < $topPad) {
                $hDel = max(1, $hDel - ($topPad - $yDel));
                $yDel = $topPad;
            }
            $label = date('j M', strtotime($s['date']));
        ?>
          <g class="bar" data-date="<?= htmlspecialchars(date('D j M Y', strtotime($s['date']))) ?>"
             data-del="<?= $s['delivered'] ?>" data-bnc="<?= $s['bounced'] ?>">
            <?php if ($hBnc > 0): ?>
              <rect class="seg" x="<?= $x ?>" y="<?= $yBnc ?>" width="<?= $barW ?>" height="<?= $hBnc ?>"
                    fill="var(--bounced)" rx="<?= $hDel > 0 ? 0 : 4 ?>"/>
            <?php endif; ?>
            <?php if ($hDel > 0): ?>
              <rect class="seg" x="<?= $x ?>" y="<?= $yDel ?>" width="<?= $barW ?>" height="<?= $hDel ?>"
                    fill="var(--delivered)" rx="4"/>
            <?php endif; ?>
            <?php // Invisible hit area, larger than the mark. ?>
            <rect x="<?= $x - 3 ?>" y="<?= $topPad ?>" width="<?= $barW + 6 ?>" height="<?= $chartH ?>" fill="transparent"/>
            <?php if ($s['total'] === $peak && $peak > 0): ?>
              <text class="axis-lbl" x="<?= $x + $barW / 2 ?>" y="<?= $yDel - 6 ?>" text-anchor="middle"
                    style="font-weight:700;fill:var(--ink-2);"><?= $s['total'] ?></text>
            <?php endif; ?>
          </g>
          <?php if ($days <= 30 || $i % 7 === 0): ?>
            <text class="axis-lbl" x="<?= $x + $barW / 2 ?>" y="<?= $topPad + $chartH + 16 ?>"
                  text-anchor="middle"><?= htmlspecialchars($label) ?></text>
          <?php endif; ?>
        <?php endforeach; ?>
      </svg>
    </div>
    <?php if ($requests === 0): ?>
      <div class="note">No messages in this period.</div>
    <?php endif; ?>
  </div>

  <!-- Bounces: the actionable list -->
  <div class="card">
    <div class="card-h">
      <span class="card-t">Bounced addresses</span>
      <span style="font-size:12px;color:var(--muted);">
        <?= count($bounce['body']['events'] ?? []) ?> in this period
      </span>
    </div>
    <?php $events = $bounce['body']['events'] ?? []; ?>
    <?php if (!$events): ?>
      <div class="empty">No bounces — every message reached its recipient.</div>
    <?php else: ?>
      <table>
        <thead>
          <tr><th style="width:26%">Address</th><th style="width:12%">Type</th><th>Reason</th><th style="width:16%">When</th></tr>
        </thead>
        <tbody>
        <?php foreach ($events as $e):
            $hard = str_contains(strtolower($e['event'] ?? ''), 'hard'); ?>
          <tr>
            <td class="mail"><?= htmlspecialchars($e['email'] ?? '—') ?></td>
            <td><span class="chip <?= $hard ? 'chip-hard' : 'chip-soft' ?>"><?= $hard ? 'Hard' : 'Soft' ?></span></td>
            <td class="reason"><?= htmlspecialchars($e['reason'] ?? 'No reason given') ?></td>
            <td class="when"><?= htmlspecialchars(date('j M, H:i', strtotime($e['date'] ?? 'now'))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="note">
        <strong>Hard</strong> means the address does not exist — correct it on the delegate&rsquo;s record,
        then use the resend button on their page. <strong>Soft</strong> is temporary (full mailbox, server
        busy) and often succeeds on a retry.
      </div>
    <?php endif; ?>
  </div>

  <?php if ($blocked || $spam): ?>
    <div class="card">
      <div class="card-h"><span class="card-t">Needs attention</span></div>
      <div class="note" style="border-top:0;color:var(--ink-2);">
        <?php if ($blocked): ?><div><strong><?= $blocked ?> blocked</strong> — recipients on Brevo&rsquo;s suppression list, usually after an earlier hard bounce.</div><?php endif; ?>
        <?php if ($spam): ?><div style="margin-top:6px;"><strong><?= $spam ?> spam complaint<?= $spam === 1 ? '' : 's' ?></strong> — recipients marked the mail as spam. Repeated complaints damage sending reputation.</div><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<div id="tip"></div>
<script>
(function () {
  var tip = document.getElementById('tip');

  document.querySelectorAll('.bar').forEach(function (bar) {
    bar.addEventListener('mousemove', function (ev) {
      var del = bar.dataset.del, bnc = bar.dataset.bnc;
      tip.innerHTML = '<b>' + bar.dataset.date + '</b><br>' +
                      del + ' delivered' + (bnc !== '0' ? '<br>' + bnc + ' bounced' : '');
      tip.style.opacity = '1';
      var x = ev.clientX + 14, y = ev.clientY - 10;
      if (x + tip.offsetWidth > window.innerWidth - 8) { x = ev.clientX - tip.offsetWidth - 14; }
      tip.style.left = x + 'px';
      tip.style.top  = y + 'px';
    });
    bar.addEventListener('mouseleave', function () { tip.style.opacity = '0'; });
  });

  // Opt-in polling. Each reload costs three Brevo API calls, so it is off by default
  // and the choice is remembered rather than re-toggled every visit.
  var box  = document.getElementById('autoRefresh');
  var note = document.getElementById('countdown');
  var timer = null;
  var left = 30;

  function stop() { if (timer) { clearInterval(timer); timer = null; } note.textContent = ''; }

  function start() {
    left = 30;
    note.textContent = ' · reloading in 30s';
    timer = setInterval(function () {
      left--;
      note.textContent = ' · reloading in ' + left + 's';
      if (left <= 0) { clearInterval(timer); location.reload(); }
    }, 1000);
  }

  try {
    if (localStorage.getItem('emailStatsAutoRefresh') === '1') { box.checked = true; start(); }
  } catch (e) { /* storage unavailable — the toggle still works for this visit */ }

  box.addEventListener('change', function () {
    try { localStorage.setItem('emailStatsAutoRefresh', box.checked ? '1' : '0'); } catch (e) {}
    box.checked ? start() : stop();
  });
})();
</script>
</body>
</html>
