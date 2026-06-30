<?php
// Copy this file to mail_config.php and fill in your real credentials.
// NEVER commit mail_config.php — it is listed in .gitignore.

// ── SMTP Configuration ────────────────────────────────────────────────────────
// Common setups:
//   cPanel / Webmail  →  mail.yourdomain.com,  port 465, ssl
//   Google Workspace  →  smtp.gmail.com,        port 587, tls  (needs App Password)
//   Outlook / M365    →  smtp.office365.com,    port 587, tls

define('MAIL_HOST',       'mail.yourdomain.com');
define('MAIL_PORT',       465);
define('MAIL_ENCRYPTION', 'ssl');
define('MAIL_USERNAME',   'noreply@yourdomain.com');
define('MAIL_PASSWORD',   'your_smtp_password');
define('MAIL_FROM',       'noreply@yourdomain.com');
define('MAIL_FROM_NAME',  'GAMBIA 2026 Secretariat');

// ── Badge HMAC secret (signs badge URLs — use a long random string) ───────────
define('BADGE_SECRET', 'replace-with-a-long-random-secret');

// ── Google reCAPTCHA v3 ───────────────────────────────────────────────────────
// Get keys at: https://www.google.com/recaptcha/admin/create
define('RECAPTCHA_SITE_KEY',   '');
define('RECAPTCHA_SECRET_KEY', '');

// ── GitHub webhook auto-deploy (ngocsocd.org only) ───────────────────────────
// DEPLOY_WEBHOOK_SECRET must match the "Secret" set in GitHub → Webhooks.
// CPANEL_USER / CPANEL_PASS are the cPanel login credentials for this server.
define('DEPLOY_WEBHOOK_SECRET', '');
define('CPANEL_USER', '');
define('CPANEL_PASS', '');
