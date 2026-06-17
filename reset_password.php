<?php
session_start();
require_once 'db.php';
require_once 'mailer.php';

// Auto-create password_resets table
$pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id         INT UNSIGNED  NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(255)  NOT NULL,
    token      VARCHAR(64)   NOT NULL,
    expires_at DATETIME      NOT NULL,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Derive base URL for reset link
function base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir    = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
    return $scheme . '://' . $host . $dir;
}

$token    = trim($_GET['token'] ?? '');
$step     = $token ? 'reset' : 'request';
$success  = false;
$error    = '';

// ── Step 1: request (POST email) ─────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'request') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM admin_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            // Invalidate old tokens for this email
            $pdo->prepare("DELETE FROM password_resets WHERE email = ?")->execute([$email]);

            $resetToken = bin2hex(random_bytes(32));
            $expires    = date('Y-m-d H:i:s', time() + 3600);
            $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)")
                ->execute([$email, $resetToken, $expires]);

            $resetUrl = base_url() . '/reset_password?token=' . $resetToken;
            send_password_reset_email($email, $user['name'], $resetUrl);
        }
        // Always show success (avoid email enumeration)
        $success = true;
    }
}

// ── Step 2: reset (GET with token — validate) ────────────────
$tokenRow = null;
if ($step === 'reset') {
    $stmt = $pdo->prepare(
        "SELECT pr.*, au.name FROM password_resets pr
         JOIN admin_users au ON au.email = pr.email
         WHERE pr.token = ? AND pr.expires_at > NOW() LIMIT 1"
    );
    $stmt->execute([$token]);
    $tokenRow = $stmt->fetch();
    if (!$tokenRow) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
        $step  = 'expired';
    }
}

// ── Step 2: reset (POST new password) ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'reset') {
    $pass  = $_POST['password']         ?? '';
    $conf  = $_POST['password_confirm'] ?? '';

    if (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $conf) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("UPDATE admin_users SET password = ? WHERE email = ?")
            ->execute([$hash, $tokenRow['email']]);
        $pdo->prepare("DELETE FROM password_resets WHERE email = ?")
            ->execute([$tokenRow['email']]);
        $success = true;
        $step    = 'done';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reset Password — GAMBIA 2026 Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:url('asset/the-gambia-bloHpsZyi90-unsplash.jpg') center/cover no-repeat fixed;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
  body::before{content:'';position:fixed;inset:0;background:rgba(5,20,40,.62);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);}
  .card{background:#fff;border-radius:20px;padding:48px 40px;width:100%;max-width:400px;box-shadow:0 24px 64px rgba(0,0,0,.45);position:relative;z-index:1;}
  .logo{width:72px;height:72px;margin:0 auto 24px;display:flex;align-items:center;justify-content:center;}
  .logo img{width:72px;height:72px;object-fit:contain;}
  h2{font-size:20px;font-weight:700;color:#0a2540;text-align:center;margin-bottom:6px;}
  .sub{font-size:13px;color:#9aaabf;text-align:center;margin-bottom:28px;line-height:1.5;}
  label{font-size:12px;font-weight:600;color:#4a6080;display:block;margin-bottom:6px;}
  input[type=email],input[type=password]{width:100%;padding:12px 16px;border:1.5px solid #d1dce8;border-radius:10px;font-size:14px;font-family:inherit;outline:none;transition:border-color .15s,box-shadow .15s;margin-bottom:18px;}
  input:focus{border-color:#0d6e8c;box-shadow:0 0 0 3px rgba(13,110,140,.12);}
  button{width:100%;background:#0a2540;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;transition:background .2s;margin-top:4px;}
  button:hover{background:#0d6e8c;}
  .err{background:#fee2e2;color:#991b1b;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:18px;text-align:center;}
  .ok{background:#dcfce7;border:1px solid #86efac;border-radius:12px;padding:24px;text-align:center;}
  .ok h3{color:#166534;font-size:18px;margin-bottom:8px;}
  .ok p{color:#166534;font-size:13px;line-height:1.6;}
  .ok a{display:inline-block;margin-top:14px;background:#0a2540;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;}
  .back{display:block;text-align:center;margin-top:18px;font-size:13px;color:#0d6e8c;text-decoration:none;}
  .back:hover{text-decoration:underline;}
</style>
</head>
<body>
<div class="card">
  <div class="logo"><img src="asset/organizationLOGO.png" alt="Logo"></div>

  <?php if ($step === 'request' && !$success): ?>
    <h2>Reset Password</h2>
    <p class="sub">Enter the email address on your admin account and we'll send you a reset link.</p>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <label>Email Address</label>
      <input type="email" name="email" placeholder="admin@ngocsocd.org" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
      <button type="submit">Send Reset Link →</button>
    </form>
    <a href="admin.php" class="back">← Back to Login</a>

  <?php elseif ($step === 'request' && $success): ?>
    <div class="ok">
      <h3>&#9993; Check your inbox</h3>
      <p>If that email matches an admin account, you'll receive a reset link shortly. Check your spam folder if it doesn't arrive.</p>
      <a href="admin.php">Back to Login →</a>
    </div>

  <?php elseif ($step === 'reset'): ?>
    <h2>Set New Password</h2>
    <p class="sub">Hello <strong><?= htmlspecialchars($tokenRow['name']) ?></strong>, choose a new secure password.</p>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="token" value=""><!-- token stays in GET -->
      <label>New Password</label>
      <input type="password" name="password" placeholder="Minimum 8 characters" required autofocus>
      <label>Confirm Password</label>
      <input type="password" name="password_confirm" placeholder="Repeat new password" required>
      <button type="submit">Save New Password →</button>
    </form>

  <?php elseif ($step === 'done'): ?>
    <div class="ok">
      <h3>&#10003; Password Updated</h3>
      <p>Your password has been changed successfully. You can now sign in with your new password.</p>
      <a href="admin.php">Go to Login →</a>
    </div>

  <?php elseif ($step === 'expired'): ?>
    <div class="err"><?= htmlspecialchars($error) ?></div>
    <a href="reset_password.php" class="back">Request a new reset link →</a>
  <?php endif; ?>
</div>
</body>
</html>
