<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/confirmation_pdf.php';
require_once __DIR__ . '/nomination_letter_pdf.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ── Attach both logos as inline CID images (call before setting Body) ─────────
function attach_email_logos(PHPMailer $mail): void {
    $orgPath  = __DIR__ . '/asset/organizationLOGO.png';
    $sealPath = __DIR__ . '/asset/GambiaNationalSeal.png';
    if (file_exists($orgPath))  $mail->addEmbeddedImage($orgPath,  'org_logo',  'organizationLOGO.png',  'base64', 'image/png');
    if (file_exists($sealPath)) $mail->addEmbeddedImage($sealPath, 'nat_seal',  'GambiaNationalSeal.png', 'base64', 'image/png');
}

// ── Shared two-logo email header HTML (logos only, no text) ──────────────────
function email_header_html(): string {
    return "
  <tr>
    <td style='background:#f4f7fb;padding:18px 24px;border-bottom:3px solid #0a2540;'>
      <table width='100%' cellpadding='0' cellspacing='0'>
        <tr>
          <td width='110' style='vertical-align:middle;'>
            <img src='cid:org_logo' alt='Organization' width='110' height='31' style='width:110px;height:31px;display:block;'>
          </td>
          <td style='text-align:center;vertical-align:middle;padding:0 10px;'></td>
          <td width='64' style='vertical-align:middle;text-align:right;'>
            <img src='cid:nat_seal' alt='National Seal' width='52' height='52' style='width:52px;height:52px;display:block;margin-left:auto;'>
          </td>
        </tr>
      </table>
    </td>
  </tr>";
}

function send_confirmation_email(array $data): bool {
    if (empty(MAIL_USERNAME) || empty(MAIL_PASSWORD)) {
        return false; // credentials not configured yet
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure  = MAIL_ENCRYPTION;
        $mail->Port        = MAIL_PORT;
        $mail->Timeout     = 10;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $mail->CharSet     = 'UTF-8';
        $mail->Encoding    = 'base64';
        $mail->XMailer     = ' ';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($data['email'], $data['first_name'] . ' ' . $data['last_name']);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
        attach_email_logos($mail);

        $mail->isHTML(true);
        $mail->Subject = 'GAMBIA 2026 - Registration Received: ' . $data['first_name'] . ' ' . $data['last_name'];
        $mail->Body    = email_body_html($data);
        $mail->AltBody = email_body_plain($data);

        // Attach generated confirmation slip (requires TCPDF — skipped if not set up)
        $pdf = build_confirmation_pdf($data);
        if ($pdf !== '') {
            $ref = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);
            $mail->addStringAttachment($pdf, "Registration_Confirmation_{$ref}.pdf", 'base64', 'application/pdf');
        }

        $mail->send();
        return true;
    } catch (Exception) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

// ── Approval email ────────────────────────────────────────
// ── Rejection email ───────────────────────────────────────
function send_rejection_email(array $data, string $reason = ''): bool {
    if (empty(MAIL_USERNAME) || empty(MAIL_PASSWORD)) return false;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure  = MAIL_ENCRYPTION;
        $mail->Port        = MAIL_PORT;
        $mail->Timeout     = 10;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $mail->CharSet     = 'UTF-8';
        $mail->Encoding    = 'base64';
        $mail->XMailer     = ' ';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($data['email'], $data['first_name'] . ' ' . $data['last_name']);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
        attach_email_logos($mail);

        $mail->isHTML(true);
        $mail->Subject = 'GAMBIA 2026 - Registration Update: ' . $data['first_name'] . ' ' . $data['last_name'];
        $mail->Body    = rejection_email_html($data, $reason);
        $mail->AltBody = rejection_email_plain($data, $reason);

        $mail->send();
        return true;
    } catch (Exception) {
        error_log('Rejection mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

function rejection_email_html(array $data, string $reason): string {
    $name    = htmlspecialchars($data['first_name'] . ' ' . $data['last_name']);
    $ref     = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);
    $reasonHtml = $reason
        ? "<table width='100%' cellpadding='0' cellspacing='0' style='margin:20px 0;'>
            <tr>
              <td style='background:#fff3cd;border-left:4px solid #f59e0b;border-radius:0 8px 8px 0;padding:14px 18px;font-size:13px;color:#1a2332;line-height:1.6;'>
                <strong>Reason provided:</strong><br>" . nl2br(htmlspecialchars($reason)) . "
              </td>
            </tr>
           </table>"
        : '';

    return "
<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f5f5;padding:40px 16px;'>
<tr><td align='center'>
<table width='560' cellpadding='0' cellspacing='0' style='max-width:560px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e0e0e0;'>
  " . email_header_html() . "
  <tr>
    <td style='background:#fee2e2;border-bottom:1px solid #fecaca;padding:14px 36px;text-align:center;'>
      <span style='font-size:14px;font-weight:700;color:#991b1b;letter-spacing:.03em;'>REGISTRATION NOT APPROVED</span>
    </td>
  </tr>
  <tr>
    <td style='padding:36px 36px 28px;color:#222222;font-size:15px;line-height:1.8;'>
      <p style='margin:0 0 18px;'>Dear <strong>{$name}</strong>,</p>
      <p style='margin:0 0 18px;'>Thank you for your interest in the <strong>GAMBIA 2026 NGO Summit</strong>. After careful review, we regret to inform you that your registration (<strong>{$ref}</strong>) has not been approved at this time.</p>
      {$reasonHtml}
      <p style='margin:0 0 18px;'>If you believe this decision was made in error or wish to seek further clarification, please contact us directly.</p>
      <p style='margin:0 0 28px;'>We appreciate your interest and hope to engage with you in future initiatives.</p>
      <p style='margin:0;'>Best regards,<br><strong>GAMBIA 2026, Summit Registration Team</strong></p>
    </td>
  </tr>
  <tr>
    <td style='background:#f8f9fa;border-top:1px solid #e8e8e8;padding:18px 36px;text-align:center;'>
      <p style='margin:0;font-size:11px;color:#999999;line-height:1.6;'>
        For queries: <a href='mailto:secretariat@ngocsocd.org' style='color:#0a2540;text-decoration:none;'>secretariat@ngocsocd.org</a>
        &nbsp;&bull;&nbsp;<a href='https://ngocsocd.org' style='color:#0a2540;text-decoration:none;'>ngocsocd.org</a>
      </p>
    </td>
  </tr>
</table>
</td></tr>
</table>
</body></html>";
}

function rejection_email_plain(array $data, string $reason): string {
    $name = $data['first_name'] . ' ' . $data['last_name'];
    $ref  = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);
    $txt  = "GAMBIA 2026 - Registration Not Approved\n"
          . str_repeat('=', 42) . "\n\n"
          . "Dear {$name},\n\n"
          . "After careful review, your registration ({$ref}) has not been approved.\n\n";
    if ($reason) $txt .= "Reason: {$reason}\n\n";
    return $txt
        . "If you believe this is an error, contact: secretariat@ngocsocd.org\n\n"
        . "Best regards,\nGAMBIA 2026, Summit Registration Team\n\n"
        . str_repeat('-', 42) . "\n"
        . "12-16 October 2026 | Banjul, Republic of The Gambia";
}

function send_approval_email(array $data): bool {
    if (empty(MAIL_USERNAME) || empty(MAIL_PASSWORD)) return false;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure  = MAIL_ENCRYPTION;
        $mail->Port        = MAIL_PORT;
        $mail->Timeout     = 10;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $mail->CharSet     = 'UTF-8';
        $mail->Encoding    = 'base64';
        $mail->XMailer     = ' ';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($data['email'], $data['first_name'] . ' ' . $data['last_name']);
        $mail->addReplyTo(MAIL_FROM, MAIL_FROM_NAME);
        attach_email_logos($mail);

        $mail->isHTML(true);
        $mail->Subject = 'GAMBIA 2026 - Registration Approved: ' . $data['first_name'] . ' ' . $data['last_name'];
        $mail->Body    = approval_email_html($data, make_badge_url($data));
        $mail->AltBody = approval_email_plain($data);

        $nominationPdf = build_nomination_letter_pdf($data);
        if ($nominationPdf) {
            $ref = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);
            $mail->addStringAttachment($nominationPdf, "Award_Nomination_Letter_{$ref}.pdf", 'base64', 'application/pdf');
        }

        $mail->send();
        return true;
    } catch (Exception) {
        error_log('Approval mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

function approval_email_html(array $data, string $badgeUrl = ''): string {
    $name   = htmlspecialchars($data['first_name'] . ' ' . $data['last_name']);
    $ref    = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);
    $qrText = urlencode("GAMBIA 2026|{$ref}|" . $data['first_name'] . ' ' . $data['last_name'] . '|' . $data['organisation_name']);
    $qrUrl  = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&margin=6&color=0a2540&data={$qrText}";
    $safeBadgeUrl = htmlspecialchars($badgeUrl);

    return "
<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;'>

<table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f5f5;padding:40px 16px;'>
<tr><td align='center'>
<table width='560' cellpadding='0' cellspacing='0' style='max-width:560px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e0e0e0;'>

  " . email_header_html() . "

  <!-- Green approval banner -->
  <tr>
    <td style='background:#dcfce7;border-bottom:1px solid #bbf7d0;padding:14px 36px;text-align:center;'>
      <span style='font-size:14px;font-weight:700;color:#166534;letter-spacing:.03em;'>
        &#10003;&nbsp; REGISTRATION APPROVED
      </span>
    </td>
  </tr>

  <!-- Body -->
  <tr>
    <td style='padding:28px 36px 20px;color:#222222;font-size:14px;line-height:1.5;'>

      <p style='margin:0 0 10px;'>Dear {$name},</p>

      <p style='margin:0 0 10px;'>
        Congratulations! Your registration for the NGO restitution of the Second World Social Summit
        scheduled for 12&ndash;16 October 2026 has been approved.
        You have also been nominated for the Global Civil Society Unsung Heroes Earth Hour Award.
      </p>

      <p style='margin:0 0 10px;'>
        This award recognizes your community service, positive impact, and environmental leadership.
        It highlights how local leaders inspire others and the value of supporting frontline environmental defenders.
        The initiative helps measure and track carbon footprints and related environmental metrics.
      </p>

      <p style='margin:0 0 10px;'>
        By working together, we can amplify civil society&rsquo;s role in climate action and social development
        for the benefit of all.
      </p>

      <p style='margin:0 0 10px;'>
        For more information about the award visit
        <a href='https://www.earthhouraward.org' style='color:#0a2540;text-decoration:none;'>www.earthhouraward.org</a>
      </p>

      <p style='margin:0 0 18px;'>
        Please review the information carefully. We congratulate you again and look forward to your
        acceptance letter and to honoring you at the event.
      </p>

      <!-- Signature -->
      <p style='margin:0 0 4px;font-style:italic;color:#4a6080;'>(2026 Award Selection Committee)</p>
      <p style='margin:0 0 2px;font-size:13px;color:#4a6080;'>Name:</p>
      <p style='margin:0 0 20px;font-size:13px;color:#4a6080;'>Position: Team Lead</p>

      <!-- Reference -->
      <table width='100%' cellpadding='0' cellspacing='0' style='margin:0 0 16px;'>
        <tr>
          <td style='background:#f0f4f8;border-radius:8px;padding:12px 20px;text-align:center;'>
            <div style='font-size:11px;color:#7a8fa8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;'>Reference Number</div>
            <div style='font-size:20px;font-weight:700;color:#0a2540;letter-spacing:.04em;'>{$ref}</div>
          </td>
        </tr>
      </table>

      " . ($safeBadgeUrl ? "
      <!-- Badge download link -->
      <table width='100%' cellpadding='0' cellspacing='0' style='margin:0 0 8px;'>
        <tr>
          <td style='text-align:center;'>
            <a href='{$safeBadgeUrl}' style='display:inline-block;background:#059669;color:#fff;text-decoration:none;padding:11px 24px;border-radius:8px;font-size:14px;font-weight:700;'>
              &#127250; Download Your Accreditation Badge
            </a>
          </td>
        </tr>
      </table>
      " : "") . "

    </td>
  </tr>

  <!-- QR Code -->
  <tr>
    <td style='background:#f0f4f8;border-top:1px solid #e8e8e8;padding:22px 36px;text-align:center;'>
      <div style='font-size:11px;font-weight:700;color:#4a6080;text-transform:uppercase;letter-spacing:.08em;margin-bottom:10px;'>Delegate Accreditation QR</div>
      <img src='{$qrUrl}' alt='QR Code' width='130' height='130' style='border:4px solid #fff;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.1);'>
      <div style='font-size:11px;color:#9aaabf;margin-top:8px;'>Present this QR at the venue for check-in</div>
    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style='background:#f8f9fa;border-top:1px solid #e8e8e8;padding:18px 36px;text-align:center;'>
      <p style='margin:0;font-size:11px;color:#999999;line-height:1.6;'>
        This is an automated message &mdash; please do not reply directly to this email.<br>
        For queries: <a href='mailto:secretariat@ngocsocd.org' style='color:#0a2540;text-decoration:none;'>secretariat@ngocsocd.org</a>
        &nbsp;&bull;&nbsp;
        <a href='https://ngocsocd.org' style='color:#0a2540;text-decoration:none;'>ngocsocd.org</a>
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>";
}

function approval_email_plain(array $data): string {
    $name = $data['first_name'] . ' ' . $data['last_name'];
    $ref  = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);

    return "GAMBIA 2026 - Registration Approved\n"
        . str_repeat('=', 42) . "\n\n"
        . "Dear {$name},\n\n"
        . "Congratulations! Your registration for the NGO restitution of the Second World Social\n"
        . "Summit scheduled for 12-16 October 2026 has been approved.\n"
        . "You have also been nominated for the Global Civil Society Unsung Heroes Earth Hour Award.\n\n"
        . "Reference Number: {$ref}\n\n"
        . "This award recognizes your community service, positive impact, and environmental\n"
        . "leadership. It highlights how local leaders inspire others and the value of supporting\n"
        . "frontline environmental defenders. The initiative helps measure and track carbon\n"
        . "footprints and related environmental metrics.\n\n"
        . "By working together, we can amplify civil society's role in climate action and social\n"
        . "development for the benefit of all.\n\n"
        . "For more information about the award visit: www.earthhouraward.org\n\n"
        . "Please review the information carefully. We congratulate you again and look forward\n"
        . "to your acceptance letter and to honoring you at the event.\n\n"
        . "(2026 Award Selection Committee)\n"
        . "____________\n"
        . "Name:\n"
        . "Position: Team Lead\n\n"
        . str_repeat('-', 42) . "\n"
        . "secretariat@ngocsocd.org | ngocsocd.org";
}

// ── Email HTML body ────────────────────────────────────────
function email_body_html(array $data): string {
    $ref  = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);

    return "
<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;'>

<table width='100%' cellpadding='0' cellspacing='0' style='background:#f5f5f5;padding:40px 16px;'>
<tr><td align='center'>
<table width='560' cellpadding='0' cellspacing='0' style='max-width:560px;width:100%;background:#ffffff;border-radius:10px;overflow:hidden;border:1px solid #e0e0e0;'>

  " . email_header_html() . "

  <!-- Body -->
  <tr>
    <td style='padding:36px 36px 28px;color:#222222;font-size:15px;line-height:1.7;'>

      <p style='margin:0 0 18px;'>Dear Applicant,</p>

      <p style='margin:0 0 18px;'>
        Thank you for submitting your <strong>GAMBIA 2026 NGO Summit Registration</strong>.
      </p>

      <p style='margin:0 0 18px;'>
        We will review your submission and reply to you via email.
      </p>

      <p style='margin:0 0 18px;'>
        If you have any questions, you can reach out to us via our email address:<br>
        <a href='mailto:secretariat@ngocsocd.org' style='color:#0a2540;font-weight:600;text-decoration:none;'>secretariat@ngocsocd.org</a>
      </p>

      <p style='margin:0 0 28px;'>
        For further information regarding the summit bookings, please visit our website:<br>
        <a href='https://ngocsocd.org' style='color:#0a2540;font-weight:600;text-decoration:none;'>https://ngocsocd.org</a>
      </p>

      <!-- Reference pill — bottom of content -->
      <table width='100%' cellpadding='0' cellspacing='0' style='margin:0 0 28px;'>
        <tr>
          <td style='background:#f0f4f8;border-radius:8px;padding:14px 20px;text-align:center;'>
            <div style='font-size:11px;color:#7a8fa8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:4px;'>Your Reference Number</div>
            <div style='font-size:22px;font-weight:700;color:#0a2540;letter-spacing:.04em;'>{$ref}</div>
          </td>
        </tr>
      </table>

      <p style='margin:0;'>
        Best regards,<br>
        <strong>GAMBIA 2026, Summit Registration Team</strong>
      </p>

    </td>
  </tr>

  <!-- Footer -->
  <tr>
    <td style='background:#f8f9fa;border-top:1px solid #e8e8e8;padding:18px 36px;text-align:center;'>
      <p style='margin:0;font-size:11px;color:#999999;line-height:1.6;'>
        This is an automated message &mdash; please do not reply directly to this email.<br>
        &copy; 2026 GAMBIA 2026 NGO Summit. All rights reserved.
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>

</body>
</html>";
}

// ── Plain-text fallback ────────────────────────────────────
function email_body_plain(array $data): string {
    $ref = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);

    return "GAMBIA 2026 - NGO Summit Registration\n"
        . str_repeat('=', 42) . "\n\n"
        . "Dear Applicant,\n\n"
        . "Thank you for submitting your GAMBIA 2026 NGO Summit Registration.\n\n"
        . "Your Reference Number: {$ref}\n\n"
        . "We will review your submission and reply to you via email.\n\n"
        . "If you have any questions, you can reach out to us via our email address:\n"
        . "secretariat@ngocsocd.org\n\n"
        . "For further information regarding the summit bookings, please visit our website:\n"
        . "https://ngocsocd.org\n\n"
        . "Best regards,\n"
        . "GAMBIA 2026, Summit Registration Team\n\n"
        . str_repeat('-', 42) . "\n"
        . "12-16 October 2026 | Banjul, Republic of The Gambia";
}

// ── Badge URL helper ─────────────────────────────────────────────────────────

function make_badge_url(array $data): string {
    $ref   = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);
    $token = hash_hmac('sha256', $ref, BADGE_SECRET);
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'ngocsocd.org';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return $scheme . '://' . $host . $dir . '/badge.php?ref=' . urlencode($ref) . '&token=' . $token;
}

// ── Admin password reset ──────────────────────────────────────────────────────

function send_password_reset_email(string $toEmail, string $toName, string $resetUrl): bool {
    if (empty(MAIL_USERNAME) || empty(MAIL_PASSWORD)) return false;

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure  = MAIL_ENCRYPTION;
        $mail->Port        = MAIL_PORT;
        $mail->Timeout     = 10;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $mail->CharSet     = 'UTF-8';
        $mail->Encoding    = 'base64';
        $mail->XMailer     = ' ';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = 'GAMBIA 2026 Admin — Password Reset Request';
        $mail->Body    = password_reset_email_html($toName, $resetUrl);
        $mail->AltBody = password_reset_email_plain($toName, $resetUrl);
        $mail->send();
        return true;
    } catch (Exception) {
        return false;
    }
}

function password_reset_email_html(string $name, string $url): string {
    $safeName = htmlspecialchars($name);
    $safeUrl  = htmlspecialchars($url);
    return <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8">
<style>
  body{margin:0;padding:0;background:#f0f4f8;font-family:'Helvetica Neue',Arial,sans-serif;}
  .wrap{max-width:560px;margin:40px auto;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.1);}
  .header{background:#0a2540;padding:28px 32px;text-align:center;}
  .header h1{color:#fff;font-size:18px;margin:0;}
  .body{padding:32px;}
  .body p{color:#374151;font-size:15px;line-height:1.7;margin:0 0 16px;}
  .btn{display:inline-block;background:#0a2540;color:#fff;text-decoration:none;padding:13px 28px;border-radius:8px;font-size:15px;font-weight:700;margin:8px 0 20px;}
  .note{font-size:13px;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:16px;margin-top:8px;}
  .footer{background:#f9fafb;padding:18px 32px;text-align:center;font-size:12px;color:#9aaabf;}
</style></head><body>
<div class="wrap">
  <div class="header"><h1>GAMBIA 2026 - Admin Password Reset</h1></div>
  <div class="body">
    <p>Hello <strong>{$safeName}</strong>,</p>
    <p>We received a request to reset your admin account password. Click the button below to set a new password. This link is valid for <strong>1 hour</strong>.</p>
    <a href="{$safeUrl}" class="btn">Reset My Password →</a>
    <p class="note">If you didn't request this, you can safely ignore this email — your password won't change.<br>
    If the button doesn't work, copy and paste this URL into your browser:<br>
    <a href="{$safeUrl}" style="color:#0d6e8c;word-break:break-all;">{$safeUrl}</a></p>
  </div>
  <div class="footer">GAMBIA 2026 NGO Summit &mdash; Banjul, The Gambia &mdash; secretariat@ngocsocd.org</div>
</div>
</body></html>
HTML;
}

function password_reset_email_plain(string $name, string $url): string {
    return "Hello {$name},\n\n"
        . "We received a request to reset your GAMBIA 2026 admin account password.\n\n"
        . "Click or copy this link to reset your password (valid for 1 hour):\n"
        . "{$url}\n\n"
        . "If you didn't request this, ignore this email — your password is safe.\n\n"
        . "— GAMBIA 2026, Secretariat";
}
