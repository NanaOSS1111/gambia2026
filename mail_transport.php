<?php
/**
 * Mail transport layer.
 *
 * This server's firewall redirects all outbound SMTP (25/465/587) to the local mail
 * server and refuses 2525 outright — verified 14 Aug 2026 by connectivity_test.php,
 * where a connection to smtp-relay.brevo.com:587 was answered by bhs108.truehost.cloud.
 * Mail therefore cannot leave via SMTP to any external provider, and the local server
 * has been discarding it under an outgoing mail hold.
 *
 * Outbound HTTPS on 443 is unrestricted, so delivery goes over Brevo's REST API instead.
 *
 * Set in .env on the server:
 *     MAIL_PROVIDER=brevo
 *     BREVO_API_KEY=xkeysib-...
 *
 * Leaving MAIL_PROVIDER unset (or 'smtp') keeps the old PHPMailer path, so this can be
 * rolled back without a deploy.
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!defined('MAIL_PROVIDER')) define('MAIL_PROVIDER', strtolower(trim((string) env('MAIL_PROVIDER', 'smtp'))));
if (!defined('BREVO_API_KEY')) define('BREVO_API_KEY', (string) env('BREVO_API_KEY', ''));
if (!defined('SITE_URL'))      define('SITE_URL', rtrim((string) env('SITE_URL', 'https://gambia2026.ngocsocd.org'), '/'));

const BREVO_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

/**
 * Source for a header logo.
 *
 * Brevo's REST API has no equivalent of a CID inline attachment, so under that provider
 * the logos are served over HTTPS from this site instead of embedded.
 */
function email_logo_src(string $which): string {
    $file = $which === 'seal' ? '/asset/GambiaNationalSeal.png' : '/asset/organizationLOGO.png';

    if (MAIL_PROVIDER === 'brevo') {
        return SITE_URL . $file;
    }
    return $which === 'seal' ? 'cid:nat_seal' : 'cid:org_logo';
}

/**
 * Send one message.
 *
 * @param array $m {
 *     @type string $to_email        Required.
 *     @type string $to_name         Display name for the recipient.
 *     @type string $subject         Required.
 *     @type string $html            Required.
 *     @type string $text            Plain-text alternative.
 *     @type array  $attachments     List of ['name' => string, 'data' => raw bytes].
 *     @type bool   $embed_logos     Embed the two logos as CID images (SMTP path only).
 *     @type string $reply_to_email  Overrides the default reply-to of MAIL_FROM.
 *     @type string $reply_to_name   Display name for that override.
 * }
 * @return bool True only if the provider accepted the message.
 */
function deliver(array $m): bool {
    $toEmail = trim((string) ($m['to_email'] ?? ''));
    if ($toEmail === '') {
        error_log('Mailer error: deliver() called with no recipient');
        return false;
    }

    return MAIL_PROVIDER === 'brevo'
        ? deliver_via_brevo($m, $toEmail)
        : deliver_via_smtp($m, $toEmail);
}

/** Deliver over Brevo's REST API on port 443. */
function deliver_via_brevo(array $m, string $toEmail): bool {
    if (BREVO_API_KEY === '') {
        error_log('Mailer error: MAIL_PROVIDER=brevo but BREVO_API_KEY is not set in .env');
        return false;
    }

    $recipient = ['email' => $toEmail];
    if (!empty($m['to_name'])) {
        $recipient['name'] = $m['to_name'];
    }

    $payload = [
        'sender'      => ['email' => MAIL_FROM, 'name' => MAIL_FROM_NAME],
        'replyTo'     => [
            'email' => $m['reply_to_email'] ?? MAIL_FROM,
            'name'  => $m['reply_to_name']  ?? MAIL_FROM_NAME,
        ],
        'to'          => [$recipient],
        'subject'     => (string) ($m['subject'] ?? ''),
        'htmlContent' => (string) ($m['html'] ?? ''),
    ];

    if (!empty($m['text'])) {
        $payload['textContent'] = $m['text'];
    }

    foreach ($m['attachments'] ?? [] as $att) {
        if (empty($att['data'])) {
            continue;
        }
        $payload['attachment'][] = [
            'name'    => $att['name'],
            'content' => base64_encode($att['data']),
        ];
    }

    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        error_log('Mailer error: could not encode Brevo payload — ' . json_last_error_msg());
        return false;
    }

    $ch = curl_init(BREVO_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'accept: application/json',
            'content-type: application/json',
            'api-key: ' . BREVO_API_KEY,
        ],
    ]);

    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    unset($ch);

    if ($response === false || $status === 0) {
        error_log('Mailer error: Brevo request failed for ' . $toEmail . ' — ' . ($curlErr ?: 'no response'));
        return false;
    }

    // Brevo answers 201 Created with a messageId when the message is accepted.
    if ($status < 200 || $status >= 300) {
        error_log('Mailer error: Brevo rejected message to ' . $toEmail . ' — HTTP ' . $status . ' ' . substr((string) $response, 0, 500));
        return false;
    }

    $messageId = json_decode((string) $response, true)['messageId'] ?? '(none)';
    error_log('Mailer sent to ' . $toEmail . ' via Brevo — messageId ' . $messageId);
    return true;
}

/**
 * Deliver over SMTP with PHPMailer — the original path, kept as a rollback.
 *
 * Note this cannot currently reach an external provider from this server; every SMTP
 * host resolves to the local mail server regardless of what is configured.
 */
function deliver_via_smtp(array $m, string $toEmail): bool {
    if (empty(MAIL_USERNAME) || empty(MAIL_PASSWORD)) {
        error_log('Mailer error: SMTP credentials not configured');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host        = MAIL_HOST;
        $mail->SMTPAuth    = true;
        $mail->Username    = MAIL_USERNAME;
        $mail->Password    = MAIL_PASSWORD;
        $mail->SMTPSecure  = MAIL_ENCRYPTION;
        $mail->Port        = MAIL_PORT;
        $mail->Timeout     = 15;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]];
        $mail->CharSet     = 'UTF-8';
        $mail->Encoding    = 'base64';
        $mail->XMailer     = ' ';

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, (string) ($m['to_name'] ?? ''));
        $mail->addReplyTo(
            $m['reply_to_email'] ?? MAIL_FROM,
            $m['reply_to_name']  ?? MAIL_FROM_NAME
        );

        if (!empty($m['embed_logos'])) {
            $org  = __DIR__ . '/asset/organizationLOGO.png';
            $seal = __DIR__ . '/asset/GambiaNationalSeal.png';
            if (file_exists($org))  $mail->addEmbeddedImage($org,  'org_logo', 'organizationLOGO.png',  'base64', 'image/png');
            if (file_exists($seal)) $mail->addEmbeddedImage($seal, 'nat_seal', 'GambiaNationalSeal.png', 'base64', 'image/png');
        }

        $mail->isHTML(true);
        $mail->Subject = (string) ($m['subject'] ?? '');
        $mail->Body    = (string) ($m['html'] ?? '');
        $mail->AltBody = (string) ($m['text'] ?? '');

        foreach ($m['attachments'] ?? [] as $att) {
            if (!empty($att['data'])) {
                $mail->addStringAttachment($att['data'], $att['name'], 'base64', 'application/pdf');
            }
        }

        $mail->send();
        error_log('Mailer sent to ' . $toEmail . ' via SMTP');
        return true;

    } catch (PHPMailerException $e) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    } catch (\Throwable $e) {
        error_log('Mailer unexpected error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        return false;
    }
}
