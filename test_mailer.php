<?php
/**
 * Interactive Email Diagnostic & SMTP Configuration Tool — Admin Only.
 * Allows testing and saving SMTP configuration for GAMBIA 2026.
 */
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo '<h2 style="font-family:sans-serif;padding:20px;">Access denied. Log in as admin first.</h2>';
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

// The send below runs inside an output buffer. A fatal there (execution timeout, memory
// exhaustion) discards the buffer and renders a blank page with no clue what happened —
// which is exactly what an unreachable SMTP host produces. Surface it instead.
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code(500);
    echo '<pre style="font:13px/1.6 ui-monospace,Consolas,monospace;background:#fdecea;'
       . 'border:1px solid #f5c6cb;color:#8a1c1c;padding:18px;margin:24px;white-space:pre-wrap;">'
       . "SMTP TEST FAILED — fatal error\n\n"
       . htmlspecialchars($err['message']) . "\n\n"
       . 'at ' . htmlspecialchars($err['file']) . ':' . (int) $err['line'] . "\n\n"
       . "If this says \"Maximum execution time exceeded\", the SMTP host never answered.\n"
       . "The usual cause is a port/encryption mismatch: 465 needs SSL, 587 needs TLS.</pre>";
});

$result      = null;
$step        = '';
$detail      = '';
$smtpDebugLog = '';

// Load defaults from POST, otherwise from the LIVE config the site actually sends with.
// Never hardcode a host here: this tool must test the same path mailer.php uses,
// or a passing test proves nothing about real delegate emails.
$mailHost   = $_POST['mail_host']   ?? (defined('MAIL_HOST')       ? MAIL_HOST       : 'localhost');
$mailPort   = $_POST['mail_port']   ?? (defined('MAIL_PORT')       ? MAIL_PORT       : 587);
$mailEnc    = $_POST['mail_enc']    ?? (defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'tls');
$mailUser   = $_POST['mail_user']   ?? (defined('MAIL_USERNAME')   ? MAIL_USERNAME   : '');
$mailPass   = $_POST['mail_pass']   ?? (defined('MAIL_PASSWORD')   ? MAIL_PASSWORD   : '');
$mailFrom   = $_POST['mail_from']   ?? (defined('MAIL_FROM')       ? MAIL_FROM       : '');
$mailName   = $_POST['mail_name']   ?? (defined('MAIL_FROM_NAME')  ? MAIL_FROM_NAME  : 'GAMBIA 2026 Secretariat');
$toEmail    = trim($_POST['to_email'] ?? '');
$testPdf    = !empty($_POST['test_pdf']);
$saveConfig = !empty($_POST['save_config']);

// ── Save config action ───────────────────────────────────────────────────────
// Writes the MAIL_* keys to .env and leaves mail_config.php untouched. mail_config.php
// is the env() reader, not a value store — overwriting it with flat define()s would
// silently orphan .env and drop keys this form never asks about (BADGE_SECRET, which
// signs every badge URL already emailed to delegates).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $saveConfig) {
    $envPath = __DIR__ . '/.env';

    // Start from whatever .env already holds so unrelated keys survive untouched.
    $envVars = function_exists('load_dotenv') ? load_dotenv($envPath) : [];

    $envVars['MAIL_HOST']       = $mailHost;
    $envVars['MAIL_PORT']       = (string)(int)$mailPort;
    $envVars['MAIL_ENCRYPTION'] = $mailEnc;
    $envVars['MAIL_USERNAME']   = $mailUser;
    $envVars['MAIL_PASSWORD']   = $mailPass;
    $envVars['MAIL_FROM']       = $mailFrom;
    $envVars['MAIL_FROM_NAME']  = $mailName;

    // A value with a newline would corrupt every key after it — reject before writing.
    $badKey = '';
    foreach ($envVars as $k => $v) {
        if (preg_match('/[\r\n]/', (string)$v)) { $badKey = $k; break; }
    }

    if ($badKey !== '') {
        $result = 'error';
        $detail = "Refusing to write .env: the value for $badKey contains a line break.";
    } else {
        $envContent = '';
        foreach ($envVars as $k => $v) {
            $envContent .= $k . '=' . $v . "\n";
        }

        // Back up the current .env before replacing it — this file is gitignored,
        // so a bad save is otherwise unrecoverable.
        if (is_readable($envPath)) {
            @copy($envPath, $envPath . '.bak');
        }

        if (@file_put_contents($envPath, $envContent, LOCK_EX) !== false) {
            @chmod($envPath, 0600);
            $result = 'success';
            $detail = ".env has been updated on the live server successfully!\n\n"
                    . "New settings:\nHOST: $mailHost\nPORT: $mailPort\nENC: $mailEnc\nUSER: $mailUser\nFROM: $mailFrom\n\n"
                    . "Previous .env saved as .env.bak\n"
                    . "Preserved keys: " . implode(', ', array_diff(array_keys($envVars), [
                        'MAIL_HOST','MAIL_PORT','MAIL_ENCRYPTION','MAIL_USERNAME',
                        'MAIL_PASSWORD','MAIL_FROM','MAIL_FROM_NAME',
                      ])) . "\n\n"
                    . "Note: constants are already defined for this request, so this page still\n"
                    . "shows the OLD values until you reload. Send a test after reloading.";

            // Warn if mail_config.php was previously flattened and no longer reads .env.
            $cfgSrc = @file_get_contents(__DIR__ . '/mail_config.php');
            if ($cfgSrc !== false && strpos($cfgSrc, 'load_dotenv') === false) {
                $result = 'error';
                $detail .= "\n\nWARNING: mail_config.php does not call load_dotenv(), so it is NOT\n"
                         . "reading .env. Your saved settings will have no effect until\n"
                         . "mail_config.php is restored from mail_config.example.php.";
            }
        } else {
            $result = 'error';
            $detail = "Failed to write .env. Check file permissions on the server.";
        }
    }
}

// ── Test email action ────────────────────────────────────────────────────────
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $step = 'Validating inputs';
        if (empty($mailUser) || empty($mailPass)) {
            throw new \RuntimeException('SMTP Username and Password are required.');
        }

        $detail .= "Testing SMTP connection with settings:\n";
        $detail .= "  MAIL_HOST:       $mailHost\n";
        $detail .= "  MAIL_PORT:       $mailPort\n";
        $detail .= "  MAIL_ENCRYPTION: $mailEnc\n";
        $detail .= "  MAIL_USERNAME:   $mailUser\n";
        $detail .= "  MAIL_FROM:       $mailFrom\n\n";

        $step = 'Connecting to SMTP server';
        $mail = new PHPMailer(true);

        // Capture SMTP debug log
        ob_start();
        $mail->SMTPDebug   = 2; // Client & server messages
        $mail->Debugoutput = function($str, $level) {
            echo htmlspecialchars($str) . "\n";
        };

        $mail->isSMTP();
        $mail->Host        = $mailHost;
        $mail->SMTPAuth    = true;
        $mail->Username    = $mailUser;
        $mail->Password    = $mailPass;
        $mail->SMTPSecure  = $mailEnc;
        $mail->Port        = (int)$mailPort;
        $mail->Timeout     = 15;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $mail->CharSet     = 'UTF-8';
        $mail->Encoding    = 'base64';

        $step = 'Setting up sender and recipient';
        $mail->setFrom($mailFrom, $mailName);
        $mail->addAddress($toEmail ?: $mailUser);
        $mail->addReplyTo($mailFrom, $mailName);

        if ($testPdf) {
            $step = 'Building sample PDF attachment';
            require_once __DIR__ . '/nomination_letter_pdf.php';
            $fakeData = [
                'id' => 99999, 'title' => 'Mr.', 'first_name' => 'Test', 'last_name' => 'Delegate',
                'position' => 'Director', 'organisation_name' => 'Test NGO',
                'email' => $toEmail ?: $mailUser, 'contact_number' => '+1 000 000 0000',
                'home_address' => '123 Test Street', 'address_in_country' => '', 'country' => 'The Gambia',
            ];
            $pdf = build_nomination_letter_pdf($fakeData);
            if ($pdf) {
                $mail->addStringAttachment($pdf, 'Test_Nomination_Letter.pdf', 'base64', 'application/pdf');
                $detail .= "PDF generated successfully (" . strlen($pdf) . " bytes)\n\n";
            }
        }

        $step = 'Sending test email';
        $mail->isHTML(true);
        $mail->Subject = 'GAMBIA 2026 — SMTP Test Email';
        $mail->Body    = '<div style="font-family:Arial,sans-serif;padding:20px;background:#f0f4f8;border-radius:8px;">'
                       . '<h2 style="color:#0a2540;">SMTP Configuration Successful!</h2>'
                       . '<p>This is a test email sent from the <strong>GAMBIA 2026</strong> system.</p>'
                       . '<p>Recipient: <strong>' . htmlspecialchars($toEmail ?: $mailUser) . '</strong></p>'
                       . '</div>';
        $mail->AltBody = "SMTP Configuration Successful!\nThis is a test email sent from GAMBIA 2026 system.";

        $mail->send();
        $smtpDebugLog = ob_get_clean();

        $result  = 'success';
        $detail .= "Email sent successfully to: " . ($toEmail ?: $mailUser) . "\n";

    } catch (MailerException $e) {
        $smtpDebugLog = ob_get_clean();
        $result  = 'error';
        $detail .= "PHPMailer error at step [$step]: " . $e->getMessage() . "\n";
        if (isset($mail)) $detail .= "SMTP info: " . $mail->ErrorInfo . "\n";

        // NOTE: an "auto-diagnostic" used to live here that brute-forced 96 combinations
        // (4 hosts x 4 usernames x 3 password variants x 2 auth types) on any auth failure.
        // It was removed on 13 Aug 2026 because it: (1) blew past max_execution_time and left
        // a blank page, since the fatal happened inside ob_start(); (2) transmitted the entered
        // password to hardcoded third-party hosts the operator never typed; and (3) generated
        // 96 failed logins per run, which trips fail2ban and the outbound defer/failure limit.
        // Diagnose auth failures from the SMTP log below instead — never by guessing.
        if (str_contains($e->getMessage(), '535') || str_contains($e->getMessage(), 'authenticate')) {
            $detail .= "\nAuthentication was refused by the server. Check, in order:\n"
                     . "  1. The username is the FULL email address, and the mailbox exists.\n"
                     . "  2. The password is correct (retype it — do not paste with trailing spaces).\n"
                     . "  3. Port and encryption match: 465 needs SSL, 587 needs TLS/STARTTLS.\n"
                     . "  4. The provider allows SMTP sending for this mailbox.\n"
                     . "  5. If 'From' differs from the username, the provider may be refusing to\n"
                     . "     send on behalf of another domain. Try setting From = the username.\n";
        }
    } catch (\Throwable $e) {
        $smtpDebugLog = ob_get_clean();
        $result  = 'error';
        $detail .= "System error at step [$step]: " . $e->getMessage() . "\n";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SMTP Tester & Configurator — GAMBIA 2026</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', sans-serif; background: #f0f4f8; color: #1a2332; padding: 30px 16px; }
  .container { max-width: 760px; margin: 0 auto; background: #fff; border-radius: 14px; box-shadow: 0 4px 20px rgba(0,0,0,.08); padding: 32px; }
  h1 { font-size: 22px; color: #0a2540; margin-bottom: 6px; }
  p.sub { font-size: 13px; color: #6b7280; margin-bottom: 24px; }
  .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .full { grid-column: span 2; }
  .field label { display: block; font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 6px; }
  .field input, .field select { width: 100%; padding: 10px 14px; border: 1.5px solid #d1dce8; border-radius: 8px; font-size: 14px; font-family: inherit; outline: none; }
  .field input:focus, .field select:focus { border-color: #0d6e8c; box-shadow: 0 0 0 3px rgba(13,110,140,.12); }
  .btn-group { display: flex; gap: 12px; margin-top: 24px; }
  button { padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; font-family: inherit; transition: all .15s; }
  .btn-primary { background: #0a2540; color: #fff; }
  .btn-primary:hover { background: #0d6e8c; }
  .btn-save { background: #059669; color: #fff; }
  .btn-save:hover { background: #047857; }
  .result-box { margin-top: 24px; padding: 20px; border-radius: 10px; font-size: 14px; line-height: 1.6; }
  .result-box.success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
  .result-box.error { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
  pre { background: rgba(0,0,0,.05); padding: 12px; border-radius: 6px; font-size: 12px; white-space: pre-wrap; word-break: break-all; margin-top: 10px; font-family: monospace; }
  .preset-bar { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 20px; font-size: 13px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
  .preset-btn { background: #e2e8f0; color: #334155; padding: 5px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; cursor: pointer; border: none; }
  .preset-btn:hover { background: #cbd5e1; }
</style>
</head>
<body>

<div class="container">
  <h1>SMTP Configuration &amp; Diagnostic Tool</h1>
  <p class="sub">
    Test your email settings live and optionally save them to <code>.env</code>.
    The form loads the <strong>live settings the site sends with</strong>, so a passing
    test here means real delegate emails work too.
  </p>

  <div class="preset-bar">
    <strong>Quick Presets:</strong>
    <button type="button" class="preset-btn" onclick="applyPreset('bhs108.truehost.cloud', 465, 'ssl')">&#10003; bhs108.truehost.cloud:465 SSL (verified working)</button>
    <button type="button" class="preset-btn" onclick="applyPreset('localhost', 587, 'tls')">localhost:587 TLS</button>
    <button type="button" class="preset-btn" onclick="applyPreset('localhost', 25, '')">localhost:25 direct</button>
    <button type="button" class="preset-btn" onclick="applyPreset('mail.ngocsocd.org', 465, 'ssl')">mail.ngocsocd.org:465 SSL</button>
  </div>
  <p class="sub" style="margin-top:-10px;">
    <code>bhs108.truehost.cloud:465/ssl</code> is the live config and was verified sending
    on 12 Aug 2026 (auth OK, message accepted). Port 465 outbound is <strong>not</strong>
    blocked on this server.
    <br>
    Beware <code>bhs108<strong>b</strong>.superfasthost.cloud</code> &mdash; that hostname does
    not resolve from this server and was a stale default here until 12 Aug 2026. If you see
    <code>getaddrinfo ... Name or service not known</code>, the host is misspelled.
  </p>

  <form method="POST" id="smtpForm">
    <div class="grid">
      <div class="field">
        <label>SMTP Host</label>
        <input type="text" name="mail_host" id="f_host" value="<?= htmlspecialchars($mailHost) ?>" required placeholder="e.g. mail.ngocsocd.org">
      </div>
      <div class="field">
        <label>SMTP Port</label>
        <input type="number" name="mail_port" id="f_port" value="<?= htmlspecialchars($mailPort) ?>" required placeholder="465 or 587">
      </div>
      <div class="field">
        <label>Encryption</label>
        <select name="mail_enc" id="f_enc">
          <option value="ssl" <?= $mailEnc === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
          <option value="tls" <?= $mailEnc === 'tls' ? 'selected' : '' ?>>TLS / STARTTLS (Port 587)</option>
          <option value=""    <?= $mailEnc === ''    ? 'selected' : '' ?>>None (Port 25)</option>
        </select>
      </div>
      <div class="field">
        <label>System Email (Sender &amp; Username)</label>
        <input type="email" name="mail_user" value="<?= htmlspecialchars($mailUser) ?>" required placeholder="registration@ngocsocd.org">
      </div>
      <div class="field full">
        <label>SMTP Password</label>
        <input type="password" name="mail_pass" value="<?= htmlspecialchars($mailPass) ?>" placeholder="Enter password for registration@ngocsocd.org" required>
      </div>
      <div class="field">
        <label>From Email Address</label>
        <input type="email" name="mail_from" value="<?= htmlspecialchars($mailFrom) ?>" required>
      </div>
      <div class="field">
        <label>From Name</label>
        <input type="text" name="mail_name" value="<?= htmlspecialchars($mailName) ?>" required>
      </div>
      <div class="field full">
        <label>Send Test Email To (Recipient)</label>
        <input type="email" name="to_email" value="<?= htmlspecialchars($toEmail) ?>" placeholder="netflixossgh@gmail.com">
      </div>
      <div class="field full" style="display:flex;align-items:center;gap:8px;">
        <input type="checkbox" name="test_pdf" id="test_pdf" value="1" <?= $testPdf ? 'checked' : '' ?> style="width:auto;">
        <label for="test_pdf" style="margin:0;font-weight:normal;">Include test PDF attachment (Dompdf verification)</label>
      </div>
    </div>

    <div class="btn-group">
      <button type="submit" class="btn-primary">Run SMTP Test</button>
      <?php if ($result === 'success' && !$saveConfig): ?>
        <button type="submit" name="save_config" value="1" class="btn-save">✓ Save Working Settings to .env</button>
      <?php endif; ?>
    </div>
  </form>

  <?php if ($result): ?>
    <div class="result-box <?= $result ?>">
      <strong><?= $result === 'success' ? '✓ Success!' : '✕ Error Occurred' ?></strong>
      <pre><?= htmlspecialchars($detail) ?></pre>
      <?php if ($smtpDebugLog): ?>
        <strong style="margin-top:12px;display:block;">SMTP Server Log:</strong>
        <pre><?= htmlspecialchars($smtpDebugLog) ?></pre>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div style="margin-top:24px;text-align:right;">
    <a href="admin.php" style="color:#0d6e8c;text-decoration:none;font-size:13px;font-weight:600;">← Back to Admin Dashboard</a>
  </div>
</div>

<script>
function applyPreset(host, port, enc) {
  document.getElementById('f_host').value = host;
  document.getElementById('f_port').value = port;
  document.getElementById('f_enc').value  = enc;
}
</script>
</body>
</html>
