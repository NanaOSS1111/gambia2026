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

$result      = null;
$step        = '';
$detail      = '';
$smtpDebugLog = '';

// Load defaults from POST or cPanel SSL defaults (bhs108.truehost.cloud:465 SSL)
$mailHost   = $_POST['mail_host']   ?? 'bhs108.truehost.cloud';
$mailPort   = $_POST['mail_port']   ?? 465;
$mailEnc    = $_POST['mail_enc']    ?? 'ssl';
$mailUser   = $_POST['mail_user']   ?? 'registration@ngocsocd.org';
$mailPass   = $_POST['mail_pass']   ?? (defined('MAIL_PASSWORD')   ? MAIL_PASSWORD   : '');
$mailFrom   = $_POST['mail_from']   ?? 'registration@ngocsocd.org';
$mailName   = $_POST['mail_name']   ?? (defined('MAIL_FROM_NAME')  ? MAIL_FROM_NAME  : 'GAMBIA 2026 Secretariat');
$toEmail    = trim($_POST['to_email'] ?? '');
$testPdf    = !empty($_POST['test_pdf']);
$saveConfig = !empty($_POST['save_config']);

// ── Save config action ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $saveConfig) {
    $badgeSecret     = defined('BADGE_SECRET')         ? BADGE_SECRET         : bin2hex(random_bytes(16));
    $recaptchaSite   = defined('RECAPTCHA_SITE_KEY')   ? RECAPTCHA_SITE_KEY   : '';
    $recaptchaSecret = defined('RECAPTCHA_SECRET_KEY') ? RECAPTCHA_SECRET_KEY : '';

    $configContent = "<?php\n"
        . "// ── SMTP Configuration (Generated via test_mailer.php) ───────────────────────\n"
        . "define('MAIL_HOST',       " . var_export($mailHost, true) . ");\n"
        . "define('MAIL_PORT',       " . (int)$mailPort . ");\n"
        . "define('MAIL_ENCRYPTION', " . var_export($mailEnc, true) . ");\n"
        . "define('MAIL_USERNAME',   " . var_export($mailUser, true) . ");\n"
        . "define('MAIL_PASSWORD',   " . var_export($mailPass, true) . ");\n"
        . "define('MAIL_FROM',       " . var_export($mailFrom, true) . ");\n"
        . "define('MAIL_FROM_NAME',  " . var_export($mailName, true) . ");\n\n"
        . "// ── Security & reCAPTCHA ─────────────────────────────────────────────────────\n"
        . "define('BADGE_SECRET',         " . var_export($badgeSecret, true) . ");\n"
        . "define('RECAPTCHA_SITE_KEY',   " . var_export($recaptchaSite, true) . ");\n"
        . "define('RECAPTCHA_SECRET_KEY', " . var_export($recaptchaSecret, true) . ");\n";

    if (@file_put_contents(__DIR__ . '/mail_config.php', $configContent) !== false) {
        $result = 'success';
        $detail = "mail_config.php has been updated on the live server successfully!\n\nNew settings:\nHOST: $mailHost\nPORT: $mailPort\nENC: $mailEnc\nUSER: $mailUser\nFROM: $mailFrom";
    } else {
        $result = 'error';
        $detail = "Failed to write to mail_config.php. Check file permissions on the server.";
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

        // If 535 authentication error, try auto-diagnostic combinations
        if (str_contains($e->getMessage(), '535') || str_contains($e->getMessage(), 'authenticate')) {
            $detail .= "\n────── Running Auto-Diagnostic Combinations ──────\n";

            $userVariants = array_unique([
                $mailUser,
                explode('@', $mailUser)[0], // e.g. 'registration'
                'secretariat@ngocsocd.org',
                'secretariat',
            ]);
            $hostVariants = array_unique([
                $mailHost,
                'srv.ngocsocd.org',
                'bhs108.truehost.cloud',
                '127.0.0.1',
            ]);
            $passVariants = array_unique([
                $mailPass,
                trim($mailPass),
                html_entity_decode($mailPass, ENT_QUOTES, 'UTF-8'),
            ]);
            $authTypes = ['LOGIN', 'PLAIN'];

            $foundWorking = false;
            foreach ($hostVariants as $h) {
                foreach ($userVariants as $u) {
                    foreach ($passVariants as $p) {
                        foreach ($authTypes as $auth) {
                            try {
                                $diagMail = new PHPMailer(true);
                                $diagMail->isSMTP();
                                $diagMail->Host        = $h;
                                $diagMail->SMTPAuth    = true;
                                $diagMail->AuthType    = $auth;
                                $diagMail->Username    = $u;
                                $diagMail->Password    = $p;
                                $diagMail->SMTPSecure  = $mailEnc;
                                $diagMail->Port        = (int)$mailPort;
                                $diagMail->Timeout     = 5;
                                $diagMail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];

                                $diagMail->setFrom($mailFrom, $mailName);
                                $diagMail->addAddress($toEmail ?: $mailUser);
                                $diagMail->Subject = 'GAMBIA 2026 — Diagnostic Auto-Fix Success';
                                $diagMail->Body    = 'SMTP authentication succeeded with auto-detected settings.';
                                $diagMail->send();

                                $foundWorking = true;
                                $result = 'success';
                                $detail .= "✓ WORKING COMBINATION FOUND!\n";
                                $detail .= "  Host: $h\n  User: $u\n  AuthType: $auth\n  Port: $mailPort\n\n";

                                // Update current form variables to working settings
                                $mailHost = $h;
                                $mailUser = $u;
                                break 4;
                            } catch (\Throwable $ex) {
                                $detail .= "Tried Host: $h | User: $u | Auth: $auth => Failed (" . strtok($ex->getMessage(), "\n") . ")\n";
                            }
                        }
                    }
                }
            }

            if (!$foundWorking) {
                $detail .= "\nAll combination attempts failed. Please verify in cPanel -> Email Accounts that the account '$mailUser' exists and that the password was saved properly without typos or extra spaces.";
            }
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
  <p class="sub">Test your email settings live and optionally save them to <code>mail_config.php</code>.</p>

  <div class="preset-bar">
    <strong>Quick Presets:</strong>
    <button type="button" class="preset-btn" onclick="applyPreset('bhs108.truehost.cloud', 465, 'ssl')">Truehost Server (bhs108.truehost.cloud Port 465 SSL)</button>
    <button type="button" class="preset-btn" onclick="applyPreset('mail.ngocsocd.org', 465, 'ssl')">ngocsocd.org (Port 465 SSL)</button>
    <button type="button" class="preset-btn" onclick="applyPreset('localhost', 25, '')">localhost (Port 25 Direct)</button>
  </div>

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
        <button type="submit" name="save_config" value="1" class="btn-save">✓ Save Working Settings to mail_config.php</button>
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
