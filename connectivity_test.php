<?php
/**
 * Outbound connectivity preflight — Admin Only.
 *
 * Answers one question before any email migration work starts: which escape routes
 * out of this server actually work?
 *
 * The host redirects outbound SMTP (25/465/587) to its own mail server, so any SMTP
 * host you configure silently lands on bhs108.truehost.cloud. This script detects that
 * by reading the greeting banner: if you connect to Brevo and Truehost answers, the
 * connection was intercepted.
 *
 * Safe to run repeatedly — it opens sockets and closes them. It sends no mail and
 * uses no credentials.
 */
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo '<h2 style="font-family:sans-serif;padding:20px;">Access denied. Log in as admin first.</h2>';
    exit;
}

// The local mail server. If its name shows up in a banner from someone else's host,
// the connection was redirected.
const LOCAL_MTA_HINTS = ['truehost', 'superfasthost', 'bhs108'];

/** Open a TCP socket and read the greeting line, if any. */
function probe_tcp(string $host, int $port, bool $readBanner, int $timeout = 8): array {
    $start = microtime(true);
    $errNo = 0;
    $errStr = '';

    $sock = @fsockopen($host, $port, $errNo, $errStr, $timeout);
    if (!$sock) {
        return [
            'ok'     => false,
            'ms'     => (int) ((microtime(true) - $start) * 1000),
            'banner' => '',
            'error'  => trim("$errStr ($errNo)"),
        ];
    }

    $banner = '';
    if ($readBanner) {
        stream_set_timeout($sock, $timeout);
        $banner = (string) fgets($sock, 1024);
    }
    fclose($sock);

    return [
        'ok'     => true,
        'ms'     => (int) ((microtime(true) - $start) * 1000),
        'banner' => trim($banner),
        'error'  => '',
    ];
}

/** Did we reach the host we asked for, or did the firewall hand us the local MTA? */
function banner_verdict(string $host, string $banner): array {
    if ($banner === '') {
        return ['unknown', 'Connected, no banner read'];
    }
    $lower = strtolower($banner);
    foreach (LOCAL_MTA_HINTS as $hint) {
        if (str_contains($lower, $hint)) {
            return ['bad', 'REDIRECTED to the local mail server — not ' . $host];
        }
    }
    return ['good', 'Reached the intended host'];
}

/** Can we make a real outbound HTTPS request? */
function probe_https(string $url, int $timeout = 10): array {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_NOBODY         => true,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        // Any HTTP status proves the TLS connection completed. 401/404 is a fine result:
        // it means we reached the API and it declined an unauthenticated request.
        return $code > 0
            ? ['ok' => true,  'detail' => "HTTP $code via cURL"]
            : ['ok' => false, 'detail' => 'cURL: ' . ($err ?: 'no response')];
    }

    $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => $timeout, 'ignore_errors' => true]]);
    $res = @file_get_contents($url, false, $ctx);
    if ($res === false && empty($http_response_header)) {
        return ['ok' => false, 'detail' => 'file_get_contents failed (no cURL available)'];
    }
    return ['ok' => true, 'detail' => 'Reached via file_get_contents'];
}

// ── What to probe ────────────────────────────────────────────────────────────────
$smtpTargets = [
    ['smtp-relay.brevo.com',  2525, 'Brevo on the alternate port'],
    ['smtp.mailgun.org',      2525, 'Mailgun on the alternate port'],
    ['smtp.sendgrid.net',     2525, 'SendGrid on the alternate port'],
    ['smtp-relay.brevo.com',   587, 'Brevo on the standard port (expect redirect)'],
    ['send.one.com',           465, 'one.com (known redirected — control test)'],
];

$httpsTargets = [
    ['https://api.brevo.com/v3/account',        'Brevo API'],
    ['https://api.mailgun.net/v3/domains',      'Mailgun API'],
    ['https://api.sendgrid.com/v3/scopes',      'SendGrid API'],
];

$smtpResults = [];
foreach ($smtpTargets as [$host, $port, $label]) {
    $r = probe_tcp($host, $port, true);
    [$verdict, $note] = $r['ok'] ? banner_verdict($host, $r['banner']) : ['bad', $r['error']];
    $smtpResults[] = compact('host', 'port', 'label', 'r', 'verdict', 'note');
}

$httpsResults = [];
foreach ($httpsTargets as [$url, $label]) {
    $httpsResults[] = ['label' => $label, 'url' => $url, 'r' => probe_https($url)];
}

$anySmtpClean = false;
foreach ($smtpResults as $s) { if ($s['verdict'] === 'good') { $anySmtpClean = true; break; } }

$anyHttps = false;
foreach ($httpsResults as $h) { if ($h['r']['ok']) { $anyHttps = true; break; } }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Outbound Connectivity Preflight — GAMBIA 2026</title>
<style>
  *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
         background: #f0f4f8; color: #1a2332; padding: 30px 16px; line-height: 1.6; }
  .container { max-width: 860px; margin: 0 auto; background: #fff; border-radius: 14px;
               box-shadow: 0 4px 20px rgba(0,0,0,.08); padding: 32px; }
  h1 { font-size: 22px; color: #0a2540; margin-bottom: 6px; }
  h2 { font-size: 15px; color: #0a2540; margin: 28px 0 10px; }
  p.sub { font-size: 13px; color: #6b7280; margin-bottom: 8px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 13px; }
  th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid #e8edf2; vertical-align: top; }
  th { font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: #6b7280; }
  td.host { font-family: ui-monospace, Consolas, monospace; white-space: nowrap; }
  .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 8px;
          border-radius: 10px; white-space: nowrap; }
  .good    { background: #e3f5ec; color: #16794a; }
  .bad     { background: #fdecea; color: #a3231f; }
  .unknown { background: #fdf4e3; color: #8a5a12; }
  .banner { font-family: ui-monospace, Consolas, monospace; font-size: 11px; color: #4b5563;
            word-break: break-all; }
  .verdict { margin-top: 24px; padding: 16px 18px; border-radius: 10px; font-size: 14px; }
  .v-ok   { background: #e3f5ec; border: 1px solid #b7e2cb; }
  .v-bad  { background: #fdecea; border: 1px solid #f5c6cb; }
  code { font-family: ui-monospace, Consolas, monospace; background: #f0f4f8; padding: 1px 5px; border-radius: 3px; }
  .back { display: inline-block; margin-top: 22px; font-size: 13px; color: #0a2540; }
</style>
</head>
<body>
<div class="container">
  <h1>Outbound Connectivity Preflight</h1>
  <p class="sub">
    Checks which routes out of this server work, before committing to an email migration.
    Sends no mail and uses no credentials.
  </p>

  <h2>SMTP — is the connection being intercepted?</h2>
  <p class="sub">
    A greeting banner naming <code>truehost</code> means the firewall redirected the
    connection to the local mail server instead of the host requested.
  </p>
  <table>
    <thead>
      <tr><th>Target</th><th>Result</th><th>Greeting banner</th></tr>
    </thead>
    <tbody>
    <?php foreach ($smtpResults as $s): ?>
      <tr>
        <td class="host">
          <?= htmlspecialchars($s['host']) ?>:<?= (int) $s['port'] ?><br>
          <span style="font-family:inherit;color:#6b7280;font-size:11px;"><?= htmlspecialchars($s['label']) ?></span>
        </td>
        <td>
          <span class="pill <?= htmlspecialchars($s['verdict']) ?>">
            <?= $s['verdict'] === 'good' ? 'CLEAN' : ($s['verdict'] === 'bad' ? 'BLOCKED' : 'UNCLEAR') ?>
          </span><br>
          <span style="font-size:11px;color:#6b7280;">
            <?= htmlspecialchars($s['note']) ?><?= $s['r']['ok'] ? ' · ' . (int) $s['r']['ms'] . 'ms' : '' ?>
          </span>
        </td>
        <td class="banner"><?= htmlspecialchars($s['r']['banner'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <h2>HTTPS — can we reach an email API on port 443?</h2>
  <p class="sub">
    A <code>401</code> or <code>404</code> here is a PASS: it proves the connection completed and
    the API answered, declining a request that carried no key.
  </p>
  <table>
    <thead>
      <tr><th>API</th><th>Result</th><th>Detail</th></tr>
    </thead>
    <tbody>
    <?php foreach ($httpsResults as $h): ?>
      <tr>
        <td class="host"><?= htmlspecialchars($h['label']) ?></td>
        <td><span class="pill <?= $h['r']['ok'] ? 'good' : 'bad' ?>"><?= $h['r']['ok'] ? 'REACHABLE' : 'FAILED' ?></span></td>
        <td class="banner"><?= htmlspecialchars($h['r']['detail']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="verdict <?= ($anyHttps || $anySmtpClean) ? 'v-ok' : 'v-bad' ?>">
    <strong>Verdict:</strong>
    <?php if ($anySmtpClean): ?>
      At least one SMTP route is not intercepted. An external provider can be used on that
      port with no code changes — only new <code>.env</code> values.
    <?php elseif ($anyHttps): ?>
      All SMTP routes are intercepted, but outbound HTTPS works. Migrating to an email API
      will bypass the local mail server entirely. This is the path to take.
    <?php else: ?>
      Neither SMTP nor HTTPS could reach an external provider. That is unusual — check whether
      a proxy is required for outbound requests, and send these results to the host.
    <?php endif; ?>
  </div>

  <a class="back" href="admin.php">&larr; Back to Admin Dashboard</a>
</div>
</body>
</html>
