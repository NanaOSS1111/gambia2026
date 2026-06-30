<?php
// GitHub webhook receiver — triggers git pull + file sync on push to main.
// Validates HMAC-SHA256 signature so only GitHub can trigger this.
require_once __DIR__ . '/mail_config.php';

if (!defined('DEPLOY_WEBHOOK_SECRET') || DEPLOY_WEBHOOK_SECRET === '') {
    http_response_code(500);
    die('Webhook secret not configured.');
}

define('REPO_PATH',   '/home/zvtadugw/gambia2026_repo');
define('DEPLOY_PATH', '/home/zvtadugw/gambia2026.ngocsocd.org');

$payload = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
$expected = 'sha256=' . hash_hmac('sha256', $payload, DEPLOY_WEBHOOK_SECRET);

if (!hash_equals($expected, $sig)) {
    http_response_code(403);
    die('Forbidden: invalid signature.');
}

$data = json_decode($payload, true);
if (($data['ref'] ?? '') !== 'refs/heads/main') {
    http_response_code(200);
    die('Not main branch — skipped.');
}

if (!function_exists('exec')) {
    http_response_code(500);
    die('exec() is disabled on this server. Cannot auto-deploy.');
}

// Pull latest code into the repo clone
$pull = [];
exec('cd ' . escapeshellarg(REPO_PATH) . ' && git pull origin main 2>&1', $pull, $pullCode);

if ($pullCode !== 0) {
    http_response_code(500);
    die("git pull failed:\n" . implode("\n", $pull));
}

// Sync repo to live document root (skip .git and sensitive files)
$sync = [];
exec(
    'rsync -a --delete'
    . ' --exclude=".git"'
    . ' --exclude=".github"'
    . ' --exclude="db.php"'
    . ' --exclude="mail_config.php"'
    . ' --exclude="_deploy.php"'
    . ' ' . escapeshellarg(REPO_PATH . '/')
    . ' ' . escapeshellarg(DEPLOY_PATH . '/')
    . ' 2>&1',
    $sync, $syncCode
);

// Fall back to cp if rsync not available
if ($syncCode !== 0) {
    $sync = [];
    exec(
        'cp -R ' . escapeshellarg(REPO_PATH) . '/. ' . escapeshellarg(DEPLOY_PATH) . '/ 2>&1',
        $sync, $syncCode
    );
}

if ($syncCode !== 0) {
    http_response_code(500);
    die("Sync failed:\n" . implode("\n", $sync));
}

http_response_code(200);
echo "Deployed successfully at " . date('Y-m-d H:i:s') . "\n";
echo implode("\n", $pull);
