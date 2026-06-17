<?php
/*
 * ONE-TIME SETUP — creates the admin_users table and first account.
 * DELETE THIS FILE from the server immediately after use.
 */
require_once 'db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
    id         INT UNSIGNED   NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100)   NOT NULL,
    email      VARCHAR(255)   NOT NULL UNIQUE,
    password   VARCHAR(255)   NOT NULL,
    created_at TIMESTAMP      DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$done  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name']  ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $conf  = $_POST['confirm']   ?? '';

    if (!$name || !$email || !$pass) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $conf) {
        $error = 'Passwords do not match.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM admin_users WHERE email=?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'An admin with that email already exists.';
        } else {
            $pdo->prepare("INSERT INTO admin_users (name, email, password) VALUES (?,?,?)")
                ->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])]);
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Setup — GAMBIA 2026</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
  .card{background:#fff;border-radius:20px;padding:44px 40px;width:100%;max-width:420px;box-shadow:0 8px 40px rgba(0,0,0,.12);}
  .warn{background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:10px 14px;font-size:12px;color:#7a4f00;margin-bottom:24px;line-height:1.5;}
  h2{font-size:20px;font-weight:700;color:#0a2540;margin-bottom:6px;}
  .sub{font-size:13px;color:#9aaabf;margin-bottom:28px;}
  label{font-size:12px;font-weight:600;color:#4a6080;display:block;margin-bottom:5px;}
  input{width:100%;padding:11px 14px;border:1.5px solid #d1dce8;border-radius:8px;font-size:14px;font-family:inherit;outline:none;margin-bottom:16px;transition:border-color .15s;}
  input:focus{border-color:#0d6e8c;box-shadow:0 0 0 3px rgba(13,110,140,.1);}
  button{width:100%;background:#0a2540;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;transition:background .2s;}
  button:hover{background:#0d6e8c;}
  .err{background:#fee2e2;color:#991b1b;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:16px;}
  .success{background:#dcfce7;border:1px solid #86efac;border-radius:12px;padding:24px;text-align:center;}
  .success h3{color:#166534;font-size:18px;margin-bottom:8px;}
  .success p{color:#166534;font-size:13px;line-height:1.6;}
  .success a{display:inline-block;margin-top:16px;background:#0a2540;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;}
</style>
</head>
<body>
<div class="card">
  <div class="warn">
    &#9888; <strong>Security notice:</strong> Delete this file (<code>setup_admin.php</code>) from your server immediately after creating your account.
  </div>

  <?php if ($done): ?>
  <div class="success">
    <h3>&#10003; Account Created</h3>
    <p>Your admin account has been set up successfully.<br>
    <strong>Delete this file now</strong> before proceeding.</p>
    <a href="admin.php">Go to Login →</a>
  </div>

  <?php else: ?>
  <h2>First-Time Admin Setup</h2>
  <p class="sub">Create the first admin account for GAMBIA 2026.</p>

  <?php if ($error): ?>
    <div class="err"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <label>Full Name</label>
    <input type="text" name="name" placeholder="Your full name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
    <label>Email Address</label>
    <input type="email" name="email" placeholder="admin@ngocsocd.org" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
    <label>Password</label>
    <input type="password" name="password" placeholder="Minimum 8 characters" required>
    <label>Confirm Password</label>
    <input type="password" name="confirm" placeholder="Repeat password" required>
    <button type="submit">Create Account →</button>
  </form>
  <?php endif; ?>
</div>
</body>
</html>
