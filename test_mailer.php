<?php
/**
 * Email diagnostic tool — admin-only.
 * Upload to server, visit in browser, then DELETE after diagnosis.
 */
session_start();
if (!isset($_SESSION['admin'])) {
    http_response_code(403);
    echo '<h2>Access denied. Log in as admin first.</h2>';
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailerException;

$result  = null;
$step    = '';
$detail  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $toEmail = trim($_POST['to_email'] ?? '');
    $testPdf = !empty($_POST['test_pdf']);

    try {
        // Step 1: constants
        $step = 'Loading mail config';
        if (empty(MAIL_USERNAME) || empty(MAIL_PASSWORD)) {
            throw new \RuntimeException('MAIL_USERNAME or MAIL_PASSWORD is empty in mail_config.php');
        }
        $detail .= "MAIL_HOST: " . MAIL_HOST . "\n";
        $detail .= "MAIL_PORT: " . MAIL_PORT . "\n";
        $detail .= "MAIL_USERNAME: " . MAIL_USERNAME . "\n";
        $detail .= "MAIL_ENCRYPTION: " . MAIL_ENCRYPTION . "\n";
        $detail .= "MAIL_FROM: " . MAIL_FROM . "\n\n";

        // Step 2: SMTP connect test
        $step = 'Creating PHPMailer instance';
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host        = MAIL_HOST;
        $mail->SMTPAuth    = true;
        $mail->Username    = MAIL_USERNAME;
        $mail->Password    = MAIL_PASSWORD;
        $mail->SMTPSecure  = MAIL_ENCRYPTION;
        $mail->Port        = MAIL_PORT;
        $mail->Timeout     = 15;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $mail->SMTPDebug   = 0;
        $mail->CharSet     = 'UTF-8';
        $mail->Encoding    = 'base64';

        $step = 'Setting up addresses';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail ?: MAIL_FROM);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);

        // Step 3: optional PDF attachment test
        if ($testPdf) {
            $step = 'Building nomination letter PDF (dompdf)';
            require_once __DIR__ . '/nomination_letter_pdf.php';
            $fakeData = [
                'id' => 99999,
                'title' => 'Mr.',
                'first_name' => 'Test',
                'last_name' => 'User',
                'position' => 'Director',
                'organisation_name' => 'Test NGO',
                'email' => $toEmail ?: MAIL_FROM,
                'contact_number' => '+1 000 000 0000',
                'home_address' => '123 Test Street',
                'address_in_country' => '',
                'country' => 'Test Country',
            ];
            $pdf = build_nomination_letter_pdf($fakeData);
            if ($pdf) {
                $mail->addStringAttachment($pdf, 'Test_Nomination_Letter.pdf', 'base64', 'application/pdf');
                $detail .= "PDF generated OK (" . strlen($pdf) . " bytes)\n\n";
            } else {
                $detail .= "PDF returned empty string\n\n";
            }
        }

        $step = 'Composing and sending email';
        $mail->isHTML(true);
        $mail->Subject = 'GAMBIA 2026 — Email Diagnostic Test';
        $mail->Body    = '<p style="font-family:Arial,sans-serif;">This is a diagnostic test email from the GAMBIA 2026 system.<br>If you received this, SMTP is working correctly.</p>';
        $mail->AltBody = 'This is a diagnostic test email. If you received this, SMTP is working correctly.';

        $mail->send();
        $result  = 'success';
        $detail .= "Email sent successfully to: " . ($toEmail ?: MAIL_FROM) . "\n";

    } catch (MailerException $e) {
        $result  = 'error';
        $detail .= "PHPMailer error at step [$step]: " . $e->getMessage() . "\n";
        if (isset($mail)) $detail .= "SMTP error info: " . $mail->ErrorInfo . "\n";
    } catch (\Throwable $e) {
        $result  = 'error';
        $detail .= "PHP error at step [$step]:\n";
        $detail .= get_class($e) . ': ' . $e->getMessage() . "\n";
        $detail .= "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Email Diagnostic — GAMBIA 2026</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 680px; margin: 40px auto; padding: 0 20px; background: #f5f5f5; }
  h1   { color: #0a2540; }
  form { background: #fff; padding: 28px; border-radius: 10px; border: 1px solid #e0e0e0; }
  label { display: block; margin-bottom: 6px; font-weight: bold; font-size: 14px; }
  input[type=email] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 14px; box-sizing: border-box; margin-bottom: 14px; }
  .check { margin-bottom: 18px; font-size: 14px; }
  button { background: #0a2540; color: #fff; padding: 11px 28px; border: none; border-radius: 8px; font-size: 15px; font-weight: bold; cursor: pointer; }
  .result { margin-top: 24px; padding: 18px 22px; border-radius: 8px; font-size: 14px; }
  .success { background: #dcfce7; border: 1px solid #86efac; color: #166534; }
  .error   { background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; }
  pre { background: #f0f0f0; padding: 14px; border-radius: 6px; font-size: 12px; white-space: pre-wrap; word-break: break-all; margin-top: 12px; }
  .warn { background: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 12px 16px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; }
</style>
</head>
<body>
<h1>Email Diagnostic Tool</h1>
<div class="warn">&#9888; Delete this file from the server after diagnosing the issue.</div>

<form method="POST">
  <label for="to_email">Send test email to:</label>
  <input type="email" id="to_email" name="to_email" placeholder="your@email.com (leave blank to send to from-address)" value="<?= htmlspecialchars($_POST['to_email'] ?? '') ?>">

  <div class="check">
    <label><input type="checkbox" name="test_pdf" value="1" <?= !empty($_POST['test_pdf']) ? 'checked' : '' ?>>
    Also test dompdf PDF generation (nomination letter) and attach it</label>
  </div>

  <button type="submit">Run Diagnostic</button>
</form>

<?php if ($result): ?>
<div class="result <?= $result ?>">
  <?= $result === 'success' ? '&#10003; ' : '&#10005; ' ?>
  <?= $result === 'success' ? 'Email sent successfully!' : 'Error occurred.' ?>
  <?php if ($detail): ?><pre><?= htmlspecialchars($detail) ?></pre><?php endif ?>
</div>
<?php endif ?>

</body>
</html>
