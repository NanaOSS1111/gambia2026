<?php
/**
 * Brevo configuration check — Admin Only.
 *
 * Verifies each step of the Brevo setup independently, so a failure points at one
 * specific thing rather than "email doesn't work". Read-only until the send button
 * is pressed.
 */
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo '<h2 style="font-family:sans-serif;padding:20px;">Access denied. Log in as admin first.</h2>';
    exit;
}

require_once __DIR__ . '/mailer.php';

/** GET a Brevo API endpoint with the configured key. */
function brevo_get(string $path): array {
    if (BREVO_API_KEY === '') {
        return ['ok' => false, 'status' => 0, 'body' => null, 'error' => 'No API key configured'];
    }

    $ch = curl_init('https://api.brevo.com/v3' . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => ['accept: application/json', 'api-key: ' . BREVO_API_KEY],
    ]);
    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    unset($ch);

    return [
        'ok'     => $status >= 200 && $status < 300,
        'status' => $status,
        'body'   => json_decode((string) $raw, true),
        'error'  => $err ?: '',
    ];
}

/** All TXT strings published at a hostname. */
function txt_records(string $host): array {
    if (!function_exists('dns_get_record')) {
        return [];
    }
    $out = [];
    foreach (@dns_get_record($host, DNS_TXT) ?: [] as $rec) {
        if (!empty($rec['entries'])) {
            $out[] = implode('', $rec['entries']);
        } elseif (isset($rec['txt'])) {
            $out[] = $rec['txt'];
        }
    }
    return $out;
}

// The domain we actually send as — every check below tests this domain.
$sendingDomain = substr(strrchr(MAIL_FROM, '@') ?: '@', 1);

// ── Send a test, only when asked ─────────────────────────────────────────────
$testResult = null;
$testTo     = trim($_POST['to'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $testTo !== '') {
    $testResult = deliver([
        'to_email' => $testTo,
        'to_name'  => 'Configuration Test',
        'subject'  => 'GAMBIA 2026 — Brevo configuration test',
        'html'     => '<p>This message was sent through the <strong>Brevo API</strong> '
                    . 'from the GAMBIA 2026 registration system.</p>'
                    . '<p>If you are reading it, sending works end to end.</p>',
        'text'     => 'Sent through the Brevo API from the GAMBIA 2026 registration system.',
    ]);
}

// ── Gather checks ────────────────────────────────────────────────────────────
$checks = [];

$checks[] = [
    'name'   => 'Provider selected',
    'pass'   => MAIL_PROVIDER === 'brevo',
    'detail' => MAIL_PROVIDER === 'brevo'
        ? 'MAIL_PROVIDER=brevo'
        : "MAIL_PROVIDER is '" . (MAIL_PROVIDER ?: '(unset)') . "' — add MAIL_PROVIDER=brevo to .env",
];

$checks[] = [
    'name'   => 'API key present',
    'pass'   => BREVO_API_KEY !== '',
    'detail' => BREVO_API_KEY !== ''
        ? 'Key ending ' . substr(BREVO_API_KEY, -6) . ' (' . strlen(BREVO_API_KEY) . ' chars)'
        : 'Add BREVO_API_KEY=xkeysib-... to .env on the server',
];

$account = brevo_get('/account');
$checks[] = [
    'name'   => 'API key valid',
    'pass'   => $account['ok'],
    'detail' => $account['ok']
        ? 'Authenticated as ' . ($account['body']['companyName'] ?? $account['body']['email'] ?? 'Brevo account')
        : ($account['status'] === 401
            ? 'HTTP 401 — Brevo rejected the key. Regenerate it under Settings > SMTP & API.'
            : 'HTTP ' . $account['status'] . ' ' . $account['error']),
];

// Domain authentication, as Brevo itself reports it.
$domains   = brevo_get('/senders/domains');
$domainRow = null;
foreach ($domains['body']['domains'] ?? [] as $d) {
    $name = $d['domain_name'] ?? $d['domain'] ?? '';
    if (strcasecmp($name, $sendingDomain) === 0) {
        $domainRow = $d;
        break;
    }
}
$domainAuthed = (bool) ($domainRow['authenticated'] ?? $domainRow['dkim'] ?? false);
$checks[] = [
    'name'   => 'Domain authenticated at Brevo',
    'pass'   => $domainAuthed,
    'detail' => $domainRow === null
        ? ($domains['ok']
            ? "'{$sendingDomain}' is not listed in Brevo. Add it under Senders, Domains & Dedicated IPs."
            : 'Could not read domains (HTTP ' . $domains['status'] . ')')
        : ($domainAuthed
            ? "'{$sendingDomain}' is authenticated"
            : "'{$sendingDomain}' is listed but NOT verified — add its DNS records, then press Verify in Brevo"),
];

// DNS checked directly, rather than trusting the dashboard.
$dkim = txt_records('brevo._domainkey.' . $sendingDomain);
$checks[] = [
    'name'   => 'DKIM record in DNS',
    'pass'   => $dkim !== [],
    'detail' => $dkim !== []
        ? 'Found at brevo._domainkey.' . $sendingDomain
        : 'Nothing at brevo._domainkey.' . $sendingDomain . ' — add the TXT record Brevo gives you',
];

$spf         = implode(' ', txt_records($sendingDomain));
$spfExists   = str_contains($spf, 'v=spf1');
$spfHasBrevo = str_contains($spf, 'spf.brevo.com') || str_contains($spf, 'spf.sendinblue.com');
$checks[] = [
    'name'   => 'SPF includes Brevo',
    'pass'   => $spfHasBrevo,
    'detail' => $spfHasBrevo
        ? 'include:spf.brevo.com present'
        : ($spfExists
            ? 'Recommended: add include:spf.brevo.com (costs 1 of the 10 permitted DNS lookups)'
            : 'No SPF record found for ' . $sendingDomain),
    // DKIM alone satisfies DMARC here, so a missing SPF include will not block delivery.
    'warn'   => true,
];

$dmarc  = implode(' ', txt_records('_dmarc.' . $sendingDomain));
$strict = str_contains($dmarc, 'p=quarantine') || str_contains($dmarc, 'p=reject');
$checks[] = [
    'name'   => 'DMARC policy',
    'pass'   => !$strict || $domainAuthed,
    'detail' => $dmarc === ''
        ? 'No DMARC record published'
        : ($strict
            ? ($domainAuthed
                ? 'Strict policy, but DKIM is authenticated so mail will align'
                : 'Strict policy (p=quarantine/reject). Until DKIM is authenticated above, mail WILL be filtered.')
            : 'Permissive policy'),
];

$blocking = 0;
foreach ($checks as $c) {
    if (!$c['pass'] && empty($c['warn'])) {
        $blocking++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Brevo Configuration Check — GAMBIA 2026</title>
<style>
  *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
         background: #f0f4f8; color: #1a2332; padding: 30px 16px; line-height: 1.6; }
  .container { max-width: 820px; margin: 0 auto; background: #fff; border-radius: 14px;
               box-shadow: 0 4px 20px rgba(0,0,0,.08); padding: 32px; }
  h1 { font-size: 22px; color: #0a2540; margin-bottom: 6px; }
  h2 { font-size: 15px; color: #0a2540; margin: 26px 0 10px; }
  p.sub { font-size: 13px; color: #6b7280; margin-bottom: 6px; }
  table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 13px; }
  th, td { text-align: left; padding: 9px 10px; border-bottom: 1px solid #e8edf2; vertical-align: top; }
  th { font-size: 11px; text-transform: uppercase; letter-spacing: .07em; color: #6b7280; }
  .pill { display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 10px; white-space: nowrap; }
  .ok  { background: #e3f5ec; color: #16794a; }
  .no  { background: #fdecea; color: #a3231f; }
  .adv { background: #fdf4e3; color: #8a5a12; }
  code { font-family: ui-monospace, Consolas, monospace; background: #f0f4f8; padding: 1px 5px; border-radius: 3px; font-size: 12px; }
  .banner { margin-top: 22px; padding: 15px 18px; border-radius: 10px; font-size: 14px; }
  .b-ok { background: #e3f5ec; border: 1px solid #b7e2cb; }
  .b-no { background: #fdecea; border: 1px solid #f5c6cb; }
  input[type=email] { width: 100%; padding: 10px 12px; border: 1px solid #d5dde5; border-radius: 8px; font-size: 14px; }
  button { margin-top: 10px; background: #0a2540; color: #fff; border: 0; padding: 11px 20px;
           border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; }
  button:disabled { background: #9aa6b2; cursor: not-allowed; }
  .back { display: inline-block; margin-top: 22px; font-size: 13px; color: #0a2540; }
</style>
</head>
<body>
<div class="container">
  <h1>Brevo Configuration Check</h1>
  <p class="sub">
    Sending as <code><?= htmlspecialchars(MAIL_FROM) ?></code> &mdash; every check below tests
    the domain <code><?= htmlspecialchars($sendingDomain) ?></code>.
  </p>

  <table>
    <thead><tr><th style="width:32%;">Check</th><th style="width:14%;">Status</th><th>Detail</th></tr></thead>
    <tbody>
    <?php foreach ($checks as $c): ?>
      <tr>
        <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
        <td>
          <?php if ($c['pass']): ?>
            <span class="pill ok">PASS</span>
          <?php elseif (!empty($c['warn'])): ?>
            <span class="pill adv">ADVISORY</span>
          <?php else: ?>
            <span class="pill no">FAIL</span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($c['detail']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="banner <?= $blocking === 0 ? 'b-ok' : 'b-no' ?>">
    <?php if ($blocking === 0): ?>
      <strong>Configuration complete.</strong> Send a test below, then check Brevo&rsquo;s
      dashboard for the real delivery result.
    <?php else: ?>
      <strong><?= $blocking ?> blocking item<?= $blocking === 1 ? '' : 's' ?> remaining.</strong>
      Work the FAIL rows in order &mdash; each depends on the one above it. Advisory rows will
      not stop delivery.
    <?php endif; ?>
  </div>

  <h2>Send a test message</h2>
  <p class="sub">
    Uses the same <code>deliver()</code> path as real registration mail. Unlike SMTP, Brevo
    reports genuine delivery status in its dashboard afterwards.
  </p>

  <?php if ($testResult !== null): ?>
    <div class="banner <?= $testResult ? 'b-ok' : 'b-no' ?>">
      <?php if ($testResult): ?>
        <strong>Brevo accepted the message.</strong> Check the inbox, then open
        Brevo &rarr; Transactional &rarr; Logs for the delivery result.
      <?php else: ?>
        <strong>Send failed.</strong> The reason was written to the server error log &mdash;
        look for a line beginning &ldquo;Mailer error&rdquo;.
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <input type="email" name="to" placeholder="you@example.com" required
           value="<?= htmlspecialchars($testTo) ?>">
    <button type="submit" <?= $blocking > 0 ? 'disabled' : '' ?>>
      <?= $blocking > 0 ? 'Resolve blocking items first' : 'Send test through Brevo' ?>
    </button>
  </form>

  <a class="back" href="admin.php">&larr; Back to Admin Dashboard</a>
</div>
</body>
</html>
