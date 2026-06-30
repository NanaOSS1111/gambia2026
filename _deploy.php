<?php
// GitHub webhook receiver — triggers cPanel Git Version Control deployment.
// Uses cPanel internal API (localhost:2082) so exec() is not required.
require_once __DIR__ . '/mail_config.php';

if (!defined('DEPLOY_WEBHOOK_SECRET') || DEPLOY_WEBHOOK_SECRET === '') {
    http_response_code(500); die('Webhook secret not configured.');
}
if (!defined('CPANEL_USER') || !defined('CPANEL_PASS')) {
    http_response_code(500); die('cPanel credentials not configured.');
}

// Validate GitHub HMAC-SHA256 signature
$payload  = file_get_contents('php://input');
$sig      = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $payload, DEPLOY_WEBHOOK_SECRET);

if (!hash_equals($expected, $sig)) {
    http_response_code(403); die('Forbidden: invalid signature.');
}

// Only act on pushes to main
$data = json_decode($payload, true);
if (($data['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(200); die('Not main branch — skipped.');
}

// Call cPanel UAPI internally to trigger Git Version Control deployment
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'http://localhost:2082/execute/VersionControlDeployment/create',
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['repository_root' => '/home/zvtadugw/gambia2026_repo']),
    CURLOPT_USERPWD        => CPANEL_USER . ':' . CPANEL_PASS,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(500); die('cURL error: ' . $curlErr);
}

$result = json_decode($response, true);
if ($httpCode !== 200 || !empty($result['errors'])) {
    http_response_code(500);
    die('cPanel API error (' . $httpCode . '): ' . $response);
}

http_response_code(200);
echo 'Deployment triggered at ' . date('Y-m-d H:i:s');
