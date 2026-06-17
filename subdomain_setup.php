<?php
session_start();
require_once 'session_guard.php';
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Subdomain Setup — GAMBIA 2026 Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1a2332;padding:40px 20px;}
  .wrap{max-width:820px;margin:0 auto;}
  .back{display:inline-flex;align-items:center;gap:6px;color:#0d6e8c;text-decoration:none;font-size:13px;font-weight:600;margin-bottom:24px;}
  .back:hover{text-decoration:underline;}
  h1{font-size:22px;font-weight:700;color:#0a2540;margin-bottom:6px;}
  .sub{font-size:14px;color:#9aaabf;margin-bottom:32px;}
  .section{background:#fff;border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.06);padding:28px 32px;margin-bottom:24px;}
  h2{font-size:15px;font-weight:700;color:#0a2540;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid #f0f4f8;}
  p{font-size:14px;line-height:1.7;color:#374151;margin-bottom:12px;}
  p:last-child{margin-bottom:0;}
  code{background:#f0f4f8;color:#0a2540;padding:2px 7px;border-radius:5px;font-size:13px;font-family:'Courier New',monospace;}
  pre{background:#1a2332;color:#e2e8f0;padding:16px 20px;border-radius:10px;font-size:13px;font-family:'Courier New',monospace;line-height:1.7;overflow-x:auto;margin:12px 0;}
  .step{display:flex;gap:14px;margin-bottom:16px;align-items:flex-start;}
  .step-num{background:#0a2540;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;margin-top:2px;}
  .step-body{flex:1;font-size:14px;line-height:1.6;color:#374151;}
  .tip{background:#f0f9ff;border-left:4px solid #0d6e8c;padding:12px 16px;border-radius:0 8px 8px 0;font-size:13px;color:#0a2540;margin:12px 0;line-height:1.6;}
  .warn{background:#fef9c3;border-left:4px solid #fde047;padding:12px 16px;border-radius:0 8px 8px 0;font-size:13px;color:#713f12;margin:12px 0;line-height:1.6;}
</style>
</head>
<body>
<div class="wrap">
  <a href="admin.php" class="back">← Back to Admin</a>
  <h1>Subdomain Separation Setup</h1>
  <p class="sub">How to host the registration system on a dedicated subdomain (e.g., <code>register.ngocsocd.org</code>).</p>

  <div class="section">
    <h2>Why Use a Subdomain?</h2>
    <p>Hosting the registration system on its own subdomain (<code>register.ngocsocd.org</code>) provides several benefits:</p>
    <p>• <strong>Cleaner URLs</strong> — delegates see <code>register.ngocsocd.org</code> instead of <code>ngocsocd.org/registration</code></p>
    <p>• <strong>Isolated SSL</strong> — you can get a dedicated certificate for the subdomain</p>
    <p>• <strong>Separate PHP/DB settings</strong> — the registration app gets its own php.ini and DB user</p>
    <p>• <strong>Easier security hardening</strong> — no path-traversal risk from the main site</p>
  </div>

  <div class="section">
    <h2>Step 1 — Create the Subdomain in cPanel</h2>
    <div class="step"><div class="step-num">1</div><div class="step-body">Log in to cPanel → <strong>Domains → Subdomains</strong>.</div></div>
    <div class="step"><div class="step-num">2</div><div class="step-body">Subdomain: <code>register</code> / Domain: <code>ngocsocd.org</code>.<br>Document Root: <code>/home/username/register.ngocsocd.org</code> (cPanel fills this in automatically).</div></div>
    <div class="step"><div class="step-num">3</div><div class="step-body">Click <strong>Create</strong>. cPanel creates the DNS A record and the document root folder.</div></div>
    <div class="step"><div class="step-num">4</div><div class="step-body">Upload all registration files to that document root folder (the contents of this <code>registration/</code> directory).</div></div>
  </div>

  <div class="section">
    <h2>Step 2 — Update db.php (Database Connection)</h2>
    <p>On cPanel the database host is <code>localhost</code> (not <code>127.0.0.1:3307</code> which is for XAMPP). Edit <code>db.php</code>:</p>
    <pre>define('DB_HOST', 'localhost');   // cPanel MySQL
define('DB_PORT', '3306');
define('DB_NAME', 'youruser_event_registration');
define('DB_USER', 'youruser_dbuser');
define('DB_PASS', 'your_db_password');</pre>
    <p>Create the database and user in cPanel → <strong>MySQL Databases</strong>, then run the SQL schema from your XAMPP <code>phpMyAdmin</code> export.</p>
  </div>

  <div class="section">
    <h2>Step 3 — SSL Certificate</h2>
    <div class="step"><div class="step-num">1</div><div class="step-body">In cPanel → <strong>SSL/TLS → Let's Encrypt SSL</strong> (or "SSL/TLS Status").</div></div>
    <div class="step"><div class="step-num">2</div><div class="step-body">Issue a free Let's Encrypt certificate for <code>register.ngocsocd.org</code>. Tick "Force HTTPS redirect".</div></div>
    <div class="step"><div class="step-num">3</div><div class="step-body">Verify the certificate auto-renews (cPanel typically handles this via cron).</div></div>
    <div class="tip">&#128275; Once HTTPS is active, update the <code>base_url()</code> helper in <code>reset_password.php</code> and ensure <code>BADGE_SECRET</code> in <code>mail_config.php</code> is changed to a strong random string.</div>
  </div>

  <div class="section">
    <h2>Step 4 — .htaccess Adjustments</h2>
    <p>The existing <code>.htaccess</code> in this project already removes <code>.php</code> extensions and adds security headers. Ensure <code>mod_rewrite</code> is enabled on the server (it is on all standard cPanel hosts).</p>
    <p>Add this to the top of <code>.htaccess</code> to force HTTPS if not already done by cPanel:</p>
    <pre>RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]</pre>
    <div class="warn">&#9888; Do not deploy <code>setup_admin.php</code> to the live server. Delete it (or keep it locally only) once your admin account is created.</div>
  </div>

  <div class="section">
    <h2>Step 5 — Uploads Directory Permissions</h2>
    <p>Set the <code>uploads/</code> directory to be writable by the web server:</p>
    <pre>chmod 755 uploads/</pre>
    <p>The <code>uploads/.htaccess</code> already blocks PHP execution inside that directory. Verify it exists after upload.</p>
  </div>

  <div class="section">
    <h2>Step 6 — reCAPTCHA Keys</h2>
    <p>Register the subdomain (<code>register.ngocsocd.org</code>) in your Google reCAPTCHA v3 console and paste the new keys into <code>mail_config.php</code>:</p>
    <pre>define('RECAPTCHA_SITE_KEY',   'your_site_key_here');
define('RECAPTCHA_SECRET_KEY', 'your_secret_key_here');</pre>
  </div>

  <div class="section">
    <h2>Checklist Before Going Live</h2>
    <p>
      ☐ Database exported from XAMPP and imported on cPanel<br>
      ☐ <code>db.php</code> updated with cPanel credentials<br>
      ☐ <code>mail_config.php</code> SMTP settings verified (send test email)<br>
      ☐ <code>BADGE_SECRET</code> changed to a strong random string<br>
      ☐ reCAPTCHA keys updated for the subdomain<br>
      ☐ SSL certificate active and HTTPS forced<br>
      ☐ <code>uploads/</code> is writable and <code>uploads/.htaccess</code> present<br>
      ☐ <code>setup_admin.php</code> deleted from the server<br>
      ☐ Admin account tested (login, approve, reject, export)<br>
      ☐ Registration form tested end-to-end (submit → email received)
    </p>
  </div>
</div>
</body>
</html>
