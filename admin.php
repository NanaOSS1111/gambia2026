<?php
session_start();
require_once 'session_guard.php';
require_once 'db.php';
require_once 'mailer.php';
require_once 'logger.php';
require_once 'settings.php';

/* ── Redirect to setup if table missing ──────────────────── */
try {
    $pdo->query("SELECT 1 FROM admin_users LIMIT 1");
} catch (PDOException) {
    header('Location: setup_admin.php'); exit;
}

/* ── Login ───────────────────────────────────────────────── */
if (isset($_POST['do_login'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $stmt  = $pdo->prepare("SELECT * FROM admin_users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user  = $stmt->fetch();
    if ($user && password_verify($pass, $user['password'])) {
        $_SESSION['admin']         = true;
        $_SESSION['admin_id']      = $user['id'];
        $_SESSION['admin_name']    = $user['name'];
        $_SESSION['admin_email']   = $user['email'];
        $_SESSION['last_activity'] = time();
        log_action($pdo, 'login', 'Logged in successfully');
        header('Location: admin.php'); exit;
    }
    $loginError = 'Invalid email or password.';
}
if (isset($_POST['do_logout'])) {
    log_action($pdo, 'logout', 'Logged out');
    session_destroy();
    header('Location: admin.php'); exit;
}

/* ── Login page ───────────────────────────────────────────── */
if (!isset($_SESSION['admin'])): ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login — GAMBIA 2026</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:url('asset/the-gambia-bloHpsZyi90-unsplash.jpg') center/cover no-repeat fixed;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;}
  body::before{content:'';position:fixed;inset:0;background:rgba(5,20,40,.62);backdrop-filter:blur(2px);-webkit-backdrop-filter:blur(2px);}
  .card{background:#fff;border-radius:20px;padding:48px 40px;width:100%;max-width:400px;box-shadow:0 24px 64px rgba(0,0,0,.45);position:relative;z-index:1;}
  .logo{width:80px;height:80px;margin:0 auto 24px;display:flex;align-items:center;justify-content:center;}
  .logo img{width:80px;height:80px;object-fit:contain;}
  h2{font-size:22px;font-weight:700;color:#0a2540;text-align:center;margin-bottom:6px;}
  .sub{font-size:13px;color:#9aaabf;text-align:center;margin-bottom:32px;}
  label{font-size:12px;font-weight:600;color:#4a6080;display:block;margin-bottom:6px;}
  input[type=email],input[type=password]{width:100%;padding:12px 16px;border:1.5px solid #d1dce8;border-radius:10px;font-size:14px;font-family:inherit;outline:none;transition:border-color .15s,box-shadow .15s;margin-bottom:18px;}
  input[type=email]:focus,input[type=password]:focus{border-color:#0d6e8c;box-shadow:0 0 0 3px rgba(13,110,140,.12);}
  button{width:100%;background:#0a2540;color:#fff;border:none;border-radius:10px;padding:13px;font-size:15px;font-weight:700;cursor:pointer;transition:background .2s;}
  button:hover{background:#0d6e8c;}
  .err{background:#fee2e2;color:#991b1b;border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:18px;text-align:center;}
</style>
</head>
<body>
<div class="card">
  <div class="logo"><img src="asset/organizationLOGO.png" alt="GAMBIA 2026"></div>
  <h2>Admin Access</h2>
  <p class="sub">GAMBIA 2026 — Delegate Registration Portal</p>
  <?php if (!empty($loginError)): ?>
    <div class="err"><?= htmlspecialchars($loginError) ?></div>
  <?php endif; ?>
  <form method="POST">
    <input type="hidden" name="do_login" value="1">
    <label for="em">Email Address</label>
    <input type="email" name="email" id="em" placeholder="admin@ngocsocd.org" autofocus required>
    <label for="pw">Password</label>
    <input type="password" name="password" id="pw" placeholder="Enter your password" required>
    <button type="submit">Sign In →</button>
  </form>
  <a href="reset_password.php" style="display:block;text-align:center;margin-top:18px;font-size:13px;color:#0d6e8c;text-decoration:none;">Forgot your password?</a>
</div>
</body>
</html>
<?php exit; endif;

// Flush redirect to browser immediately so admin doesn't wait for SMTP.
// Sends a real HTML body with Content-Length so reverse proxies (LiteSpeed/nginx)
// forward the response without buffering the full PHP execution.
function flush_and_continue(string $url): void {
    ignore_user_abort(true);
    set_time_limit(120);
    session_write_close();

    // Drain any existing output buffers cleanly
    while (ob_get_level() > 0) ob_end_clean();

    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $body = '<!DOCTYPE html><html><head>'
          . '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">'
          . '<script>location.replace(' . json_encode($url) . ');</script>'
          . '</head><body></body></html>';

    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Length: ' . strlen($body));
    header('Connection: close');
    header('Cache-Control: no-store');

    echo $body;
    flush();

    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
}

/* ── Bulk actions ─────────────────────────────────────────── */
if (isset($_POST['bulk_action']) && !empty($_POST['selected_ids'])) {
    $ids = array_map('intval', (array)$_POST['selected_ids']);
    if (!empty($ids)) {
        $ph      = implode(',', array_fill(0, count($ids), '?'));
        $retUrl  = 'admin.php?' . http_build_query(['status' => $_POST['ret_status'] ?? 'all', 'search' => $_POST['ret_search'] ?? '']);
        if ($_POST['bulk_action'] === 'approve') {
            $pdo->prepare("UPDATE registrations SET status='approved' WHERE id IN ($ph)")->execute($ids);
            $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id IN ($ph)");
            $stmt->execute($ids);
            $toEmail = $stmt->fetchAll();
            $n = count($toEmail);
            log_action($pdo, 'bulk_approve', "Bulk approved $n registration(s). IDs: " . implode(', ', $ids));
            $_SESSION['flash'] = ['type' => 'success', 'msg' => $n . ' registration' . ($n > 1 ? 's' : '') . ' approved. Confirmation email' . ($n > 1 ? 's' : '') . ' sent.'];
            flush_and_continue($retUrl);
            foreach ($toEmail as $row) send_approval_email($row);
        } elseif ($_POST['bulk_action'] === 'reject') {
            $reason = trim($_POST['reject_reason'] ?? '');
            $pdo->prepare("UPDATE registrations SET status='rejected' WHERE id IN ($ph)")->execute($ids);
            $stmt2 = $pdo->prepare("SELECT * FROM registrations WHERE id IN ($ph)");
            $stmt2->execute($ids);
            $toEmail = $stmt2->fetchAll();
            $n = count($ids);
            log_action($pdo, 'bulk_reject', "Bulk rejected $n registration(s). IDs: " . implode(', ', $ids));
            $_SESSION['flash'] = ['type' => 'info', 'msg' => $n . ' registration' . ($n > 1 ? 's' : '') . ' rejected. Notification email' . ($n > 1 ? 's' : '') . ' sent.'];
            flush_and_continue($retUrl);
            foreach ($toEmail as $rrow) send_rejection_email($rrow, $reason);
        } elseif ($_POST['bulk_action'] === 'delete') {
            $pdo->prepare("DELETE FROM registrations WHERE id IN ($ph)")->execute($ids);
            $n = count($ids);
            log_action($pdo, 'bulk_delete', "Bulk deleted $n registration(s). IDs: " . implode(', ', $ids));
            $_SESSION['flash'] = ['type' => 'info', 'msg' => $n . ' registration' . ($n > 1 ? 's' : '') . ' deleted.'];
            header('Location: ' . $retUrl);
        }
    }
    exit;
}

/* ── Single status update ─────────────────────────────────── */
if (isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    $rid       = (int)$_POST['id'];
    $retUrl    = 'admin.php?' . http_build_query(['status' => $_POST['ret_status'] ?? 'all', 'search' => $_POST['ret_search'] ?? '']);
    $pdo->prepare("UPDATE registrations SET status=? WHERE id=?")->execute([$newStatus, $rid]);
    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id=?");
    $stmt->execute([$rid]);
    $row = $stmt->fetch();
    if ($row) {
        $fullName = $row['first_name'] . ' ' . $row['last_name'];
        if ($newStatus === 'approved') {
            log_action($pdo, 'approve', "Approved registration for $fullName (ID: $rid)");
            $_SESSION['flash'] = ['type' => 'success', 'msg' => htmlspecialchars($fullName) . ' approved. Confirmation email sent.'];
            flush_and_continue($retUrl);
            send_approval_email($row);
        } elseif ($newStatus === 'rejected') {
            $reason = trim($_POST['reject_reason'] ?? '');
            log_action($pdo, 'reject', "Rejected registration for $fullName (ID: $rid)");
            $_SESSION['flash'] = ['type' => 'info', 'msg' => htmlspecialchars($fullName) . ' rejected. Notification email sent.'];
            flush_and_continue($retUrl);
            send_rejection_email($row, $reason);
        } else {
            header('Location: ' . $retUrl);
        }
    } else {
        header('Location: ' . $retUrl);
    }
    exit;
}

/* ── Registration settings ────────────────────────────────── */
if (isset($_POST['save_reg_settings'])) {
    $manualOpen = isset($_POST['registration_open']) ? '1' : '0';
    $deadline   = trim($_POST['registration_deadline'] ?? '');
    // Validate deadline format if provided
    if ($deadline !== '' && !strtotime($deadline)) $deadline = '';
    set_setting($pdo, 'registration_open',     $manualOpen);
    set_setting($pdo, 'registration_deadline', $deadline);
    log_action($pdo, 'settings', 'Updated registration status: open=' . $manualOpen . ', deadline=' . ($deadline ?: 'none'));
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Registration settings saved.'];
    header('Location: admin.php'); exit;
}

/* ── Single delete ────────────────────────────────────────── */
if (isset($_GET['delete'])) {
    $did  = (int)$_GET['delete'];
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM registrations WHERE id=?");
    $stmt->execute([$did]);
    $row  = $stmt->fetch();
    $fullName = $row ? $row['first_name'] . ' ' . $row['last_name'] : "ID $did";
    $pdo->prepare("DELETE FROM registrations WHERE id=?")->execute([$did]);
    log_action($pdo, 'delete', "Deleted registration for $fullName (ID: $did)");
    header('Location: admin.php'); exit;
}

/* ── Filter & search ──────────────────────────────────────── */
$status   = $_GET['status'] ?? 'all';
$search   = trim($_GET['search'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 50;
$where    = [];
$params   = [];
if ($status !== 'all') { $where[] = 'status = ?'; $params[] = $status; }
if ($search !== '') {
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR organisation_name LIKE ?)';
    $params  = array_merge($params, array_fill(0, 4, "%$search%"));
}
$whereClause = $where ? ' WHERE ' . implode(' AND ', $where) : '';

// Count for pagination
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations" . $whereClause);
$countStmt->execute($params);
$filteredTotal = (int)$countStmt->fetchColumn();
$totalPages    = max(1, (int)ceil($filteredTotal / $perPage));
$page          = min($page, $totalPages);
$offset        = ($page - 1) * $perPage;

$sql  = "SELECT * FROM registrations{$whereClause} ORDER BY submitted_at DESC LIMIT {$perPage} OFFSET {$offset}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

/* ── Counts ───────────────────────────────────────────────── */
$counts   = $pdo->query("SELECT status, COUNT(*) as n FROM registrations GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$total    = array_sum($counts);
$pending  = (int)($counts['pending']  ?? 0);
$approved = (int)($counts['approved'] ?? 0);
$rejected = (int)($counts['rejected'] ?? 0);

/* ── Chart data ───────────────────────────────────────────── */
// Registrations per day — last 30 days
$dailyRows = $pdo->query(
    "SELECT DATE(submitted_at) AS day, COUNT(*) AS n
     FROM registrations
     WHERE submitted_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
     GROUP BY day ORDER BY day ASC"
)->fetchAll(PDO::FETCH_KEY_PAIR);

// Fill gaps so every day in the 30-day window appears
$chartDays = []; $chartCounts = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $chartDays[]   = date('d M', strtotime($d));
    $chartCounts[] = (int)($dailyRows[$d] ?? 0);
}

// By representation type
$typeRows = $pdo->query(
    "SELECT representation_type, COUNT(*) AS n FROM registrations GROUP BY representation_type ORDER BY n DESC"
)->fetchAll(PDO::FETCH_ASSOC);
$typeLabels = array_column($typeRows, 'representation_type');
$typeCounts = array_map('intval', array_column($typeRows, 'n'));

// By gender
$genderRows = $pdo->query(
    "SELECT COALESCE(NULLIF(TRIM(gender),''), 'Not specified') AS g, COUNT(*) AS n FROM registrations GROUP BY g ORDER BY n DESC"
)->fetchAll(PDO::FETCH_ASSOC);
$genderLabels = array_column($genderRows, 'g');
$genderCounts = array_map('intval', array_column($genderRows, 'n'));

// By country — top 15 approved delegates
$countryRows = $pdo->query(
    "SELECT COALESCE(NULLIF(TRIM(passport_nationality),''), 'Not specified') AS c, COUNT(*) AS n
     FROM registrations WHERE status = 'approved'
     GROUP BY c ORDER BY n DESC LIMIT 15"
)->fetchAll(PDO::FETCH_ASSOC);
$countryLabels = array_column($countryRows, 'c');
$countryCounts = array_map('intval', array_column($countryRows, 'n'));

/* ── Current registration status ─────────────────────────── */
$regNowOpen    = get_setting($pdo, 'registration_open', '1') === '1';
$regDeadline   = get_setting($pdo, 'registration_deadline', '');
$regStatusInfo = is_registration_open($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — GAMBIA 2026</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1a2332;min-height:100vh;}

  /* ── Top nav ─────────────────────────────────── */
  .nav{
    background:#fff;border-bottom:1px solid #e8f0f8;height:64px;
    display:flex;align-items:center;padding:0 32px;gap:16px;
    position:sticky;top:0;z-index:300;box-shadow:0 1px 6px rgba(0,0,0,.06);
  }
  .nav-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
  .nav-brand-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;}
  .nav-brand-text{font-size:15px;font-weight:700;color:#0a2540;line-height:1.2;}
  .nav-brand-text small{display:block;font-size:11px;font-weight:500;color:#9aaabf;}
  .nav-spacer{flex:1;}

  /* Registration status pill in nav */
  .nav-reg-pill{display:inline-flex;align-items:center;gap:6px;padding:5px 13px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:.03em;flex-shrink:0;}
  .nav-reg-pill.open{background:#dcfce7;color:#15803d;}
  .nav-reg-pill.closed{background:#fee2e2;color:#991b1b;}
  .nav-reg-pill .dot{width:7px;height:7px;border-radius:50%;}
  .nav-reg-pill.open .dot{background:#16a34a;}
  .nav-reg-pill.closed .dot{background:#dc2626;}

  /* Profile dropdown */
  .nav-profile{position:relative;}
  .nav-avatar{
    width:38px;height:38px;border-radius:50%;border:none;cursor:pointer;
    background:linear-gradient(135deg,#0a2540,#1e4d78);color:#fff;
    font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;
    transition:opacity .15s;
  }
  .nav-avatar:hover{opacity:.85;}
  .nav-dropdown{
    display:none;position:absolute;right:0;top:calc(100% + 10px);
    background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.14);
    min-width:220px;overflow:hidden;z-index:400;
    border:1px solid #e8f0f8;
  }
  .nav-dropdown.open{display:block;}
  .nav-dd-header{padding:14px 16px 12px;background:#f8fafc;border-bottom:1px solid #f0f4f8;}
  .nav-dd-name{font-size:13px;font-weight:700;color:#0a2540;}
  .nav-dd-email{font-size:11px;color:#9aaabf;margin-top:2px;}
  .nav-dd-divider{height:1px;background:#f0f4f8;margin:4px 0;}
  .nav-dd-item{
    display:flex;align-items:center;gap:10px;
    width:100%;padding:10px 16px;border:none;background:none;cursor:pointer;
    font-size:13px;font-weight:500;color:#374151;font-family:inherit;
    text-decoration:none;text-align:left;transition:background .12s;
  }
  .nav-dd-item:hover{background:#f0f4f8;color:#0a2540;}
  .nav-dd-item.danger{color:#dc2626;}
  .nav-dd-item.danger:hover{background:#fee2e2;}
  .nav-dd-item svg{width:15px;height:15px;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;opacity:.7;}

  /* ── Main ────────────────────────────────────── */
  .main{max-width:1360px;margin:0 auto;padding:32px 28px;}

  /* ── Stat cards — compact horizontal ─────────── */
  .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
  .stat-card{
    border-radius:12px;padding:14px 18px;color:#fff;
    display:flex;align-items:center;gap:14px;
    position:relative;overflow:hidden;
    box-shadow:0 3px 14px rgba(0,0,0,.12);
  }
  .stat-card::after{
    content:'';position:absolute;right:-14px;bottom:-14px;
    width:70px;height:70px;border-radius:50%;background:rgba(255,255,255,.09);
  }
  .stat-card.c-total    {background:linear-gradient(135deg,#0a2540 0%,#1e4d78 100%);}
  .stat-card.c-pending  {background:linear-gradient(135deg,#c2610a 0%,#f59e0b 100%);}
  .stat-card.c-approved {background:linear-gradient(135deg,#065f46 0%,#10b981 100%);}
  .stat-card.c-rejected {background:linear-gradient(135deg,#991b1b 0%,#ef4444 100%);}
  .stat-icon{
    width:38px;height:38px;flex-shrink:0;
    background:rgba(255,255,255,.18);border-radius:10px;
    display:flex;align-items:center;justify-content:center;
  }
  .stat-icon svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;}
  .stat-text{flex:1;min-width:0;}
  .stat-val{font-size:28px;font-weight:700;line-height:1;margin-bottom:3px;}
  .stat-lbl{font-size:11px;opacity:.82;font-weight:500;}

  /* ── Registration control modal ──────────────── */
  .reg-modal-overlay{position:fixed;inset:0;background:rgba(10,37,64,.55);z-index:500;display:flex;align-items:center;justify-content:center;padding:20px;}
  .reg-modal-box{background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(0,0,0,.22);width:100%;max-width:520px;overflow:hidden;}
  .reg-modal-header{display:flex;align-items:center;padding:20px 24px 16px;border-bottom:1px solid #f0f4f8;}
  .reg-modal-title{font-size:16px;font-weight:700;color:#0a2540;flex:1;}
  .reg-modal-close{background:none;border:none;cursor:pointer;font-size:22px;color:#9aaabf;line-height:1;padding:0 4px;transition:color .12s;}
  .reg-modal-close:hover{color:#dc2626;}
  .reg-modal-body{padding:22px 24px 24px;}
  .reg-modal-status{display:flex;align-items:center;gap:10px;margin-bottom:20px;padding:10px 14px;border-radius:10px;}
  .reg-modal-status.open{background:#dcfce7;}
  .reg-modal-status.closed{background:#fee2e2;}
  .reg-modal-status-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
  .reg-modal-status.open .reg-modal-status-dot{background:#16a34a;}
  .reg-modal-status.closed .reg-modal-status-dot{background:#dc2626;}
  .reg-modal-status-text{font-size:13px;font-weight:700;}
  .reg-modal-status.open .reg-modal-status-text{color:#15803d;}
  .reg-modal-status.closed .reg-modal-status-text{color:#991b1b;}
  .reg-modal-row{display:flex;align-items:flex-end;gap:20px;flex-wrap:wrap;}
  .reg-modal-group{display:flex;flex-direction:column;gap:6px;flex:1;min-width:160px;}
  .reg-modal-group label{font-size:12px;font-weight:600;color:#4a6080;}
  .reg-modal-group input[type=datetime-local]{padding:9px 12px;border:1.5px solid #d1dce8;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;width:100%;}
  .reg-modal-group input[type=datetime-local]:focus{border-color:#0d6e8c;}
  .reg-toggle-wrap{display:flex;align-items:center;gap:10px;padding:9px 0;}
  .reg-toggle{position:relative;width:44px;height:24px;flex-shrink:0;}
  .reg-toggle input{opacity:0;width:0;height:0;position:absolute;}
  .reg-toggle-slider{position:absolute;inset:0;background:#d1dce8;border-radius:12px;cursor:pointer;transition:background .2s;}
  .reg-toggle-slider::before{content:'';position:absolute;width:18px;height:18px;background:#fff;border-radius:50%;top:3px;left:3px;transition:transform .2s;box-shadow:0 1px 4px rgba(0,0,0,.2);}
  .reg-toggle input:checked + .reg-toggle-slider{background:#16a34a;}
  .reg-toggle input:checked + .reg-toggle-slider::before{transform:translateX(20px);}
  .reg-modal-hint{font-size:11px;color:#9aaabf;margin-top:3px;}
  .reg-modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;border-top:1px solid #f0f4f8;}
  .btn-modal-cancel{padding:9px 20px;border-radius:8px;border:1.5px solid #d1dce8;background:#fff;color:#4a6080;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;}
  .btn-modal-cancel:hover{background:#f0f4f8;}
  .btn-modal-save{padding:9px 22px;border-radius:8px;border:none;background:#0a2540;color:#fff;font-size:13px;font-weight:700;cursor:pointer;font-family:inherit;transition:background .2s;}
  .btn-modal-save:hover{background:#0d6e8c;}

  /* ── Scroll to top ──────────────────────────── */
  #scroll-top{
    position:fixed;bottom:90px;right:24px;z-index:300;
    width:42px;height:42px;border-radius:50%;
    background:#0a2540;color:#fff;border:none;cursor:pointer;
    display:none;align-items:center;justify-content:center;
    box-shadow:0 4px 16px rgba(0,0,0,.2);transition:background .2s,opacity .2s;
    font-size:18px;
  }
  #scroll-top:hover{background:#0d6e8c;}
  #scroll-top.visible{display:flex;}

  /* ── Pagination ─────────────────────────────── */
  .pager{display:flex;align-items:center;justify-content:center;gap:6px;padding:16px 24px;border-top:1px solid #f0f4f8;}
  .pager a,.pager span{padding:7px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;transition:all .15s;}
  .pager a{color:#4a6080;background:#f0f4f8;}
  .pager a:hover{background:#e2eaf4;color:#0a2540;}
  .pager span.current{background:#0a2540;color:#fff;}
  .pager span.dots{color:#9aaabf;background:transparent;}

  /* ── Chart row ──────────────────────────────── */
  .chart-grid{display:grid;grid-template-columns:2fr 1fr 1fr;gap:20px;margin-bottom:28px;}
  .chart-card{background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.06);padding:22px 24px;}
  .chart-title{font-size:14px;font-weight:700;color:#0a2540;margin-bottom:16px;}
  .chart-wrap{position:relative;height:200px;}
  @media(max-width:900px){.chart-grid{grid-template-columns:1fr;}
  @media(max-width:1200px) and (min-width:901px){.chart-grid{grid-template-columns:2fr 1fr;}}}

  /* ── Content card ────────────────────────────── */
  .card{background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.06);overflow:hidden;}
  .card-header{
    padding:20px 24px;
    border-bottom:1px solid #f0f4f8;
    display:flex;align-items:center;gap:14px;flex-wrap:wrap;
  }
  .card-title{font-size:17px;font-weight:700;color:#0a2540;}
  .card-count{font-size:13px;color:#9aaabf;background:#f0f4f8;padding:3px 10px;border-radius:20px;}

  /* ── Filter tabs ─────────────────────────────── */
  .filter-tabs{display:flex;gap:4px;}
  .filter-tabs a{
    padding:6px 16px;border-radius:20px;
    font-size:13px;font-weight:500;
    text-decoration:none;color:#4a6080;
    transition:all .15s;display:flex;align-items:center;gap:6px;
  }
  .filter-tabs a:hover{background:#f0f4f8;}
  .filter-tabs a.active{background:#0a2540;color:#fff;}
  .filter-tabs a .tc{
    font-size:11px;padding:1px 7px;border-radius:10px;
    background:rgba(0,0,0,.08);font-weight:700;
  }
  .filter-tabs a.active .tc{background:rgba(255,255,255,.2);}

  /* ── Toolbar ─────────────────────────────────── */
  .toolbar{padding:16px 24px;border-bottom:1px solid #f0f4f8;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
  .search-box{flex:1;min-width:220px;max-width:360px;position:relative;}
  .search-box svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);width:16px;height:16px;stroke:#9aaabf;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none;}
  .search-box input{
    width:100%;padding:9px 14px 9px 38px;
    border:1.5px solid #d1dce8;border-radius:8px;
    font-size:13px;font-family:inherit;background:#f8fbff;
    color:#1a2332;outline:none;transition:border-color .15s;
  }
  .search-box input:focus{border-color:#0d6e8c;background:#fff;}
  .btn{
    padding:9px 18px;border-radius:8px;font-size:13px;font-weight:600;
    cursor:pointer;border:none;text-decoration:none;
    display:inline-flex;align-items:center;gap:6px;transition:all .15s;
  }
  .btn-dark{background:#0a2540;color:#fff;}
  .btn-dark:hover{background:#0d6e8c;}
  .btn-green{background:#059669;color:#fff;}
  .btn-green:hover{background:#047857;}
  .btn-ghost{background:#f0f4f8;color:#4a6080;}
  .btn-ghost:hover{background:#e2eaf4;color:#1a2332;}

  /* ── Table ───────────────────────────────────── */
  .tbl-wrap{overflow-x:auto;}
  table{width:100%;border-collapse:collapse;}
  thead th{
    background:#f8fbff;
    font-size:11px;font-weight:700;letter-spacing:.08em;
    text-transform:uppercase;color:#7a8fa8;
    padding:13px 16px;text-align:left;
    border-bottom:1px solid #e8f0f8;white-space:nowrap;
  }
  thead th:first-child,tbody td:first-child{padding-left:20px;}
  tbody tr{border-bottom:1px solid #f0f4f8;transition:background .1s;}
  tbody tr:hover{background:#f8fbff;}
  tbody tr.row-selected{background:#eef6ff!important;}
  tbody td{padding:12px 16px;font-size:13px;vertical-align:middle;}

  input[type="checkbox"]{
    width:16px;height:16px;
    accent-color:#0d6e8c;cursor:pointer;border-radius:4px;
  }

  .avatar{width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid #e8f0f8;}
  .avatar-ph{
    width:40px;height:40px;border-radius:50%;
    background:linear-gradient(135deg,#d1dce8,#e8f0f8);
    display:flex;align-items:center;justify-content:center;font-size:17px;
    border:2px solid #e8f0f8;
  }

  .td-name{font-weight:600;color:#0a2540;line-height:1.3;}
  .td-email{font-size:12px;color:#9aaabf;margin-top:2px;}

  .badge{
    display:inline-flex;align-items:center;gap:5px;
    padding:4px 11px;border-radius:20px;
    font-size:11px;font-weight:700;letter-spacing:.03em;
  }
  .badge::before{content:'';width:6px;height:6px;border-radius:50%;background:currentColor;opacity:.55;flex-shrink:0;}
  .badge.pending {background:#fff3cd;color:#92600a;}
  .badge.approved{background:#dcfce7;color:#166534;}
  .badge.rejected{background:#fee2e2;color:#991b1b;}

  .row-actions{display:flex;gap:5px;align-items:center;flex-wrap:nowrap;}
  .bxs{
    padding:5px 12px;border-radius:6px;font-size:12px;font-weight:600;
    cursor:pointer;border:none;text-decoration:none;
    display:inline-block;transition:opacity .15s;white-space:nowrap;
  }
  .bxs:hover{opacity:.75;}
  .bxs-view   {background:#e0f2fe;color:#0369a1;}
  .bxs-approve{background:#dcfce7;color:#166534;}
  .bxs-reject {background:#fee2e2;color:#991b1b;}
  .bxs-delete {background:#f1f5f9;color:#64748b;}

  .empty-state{padding:64px 24px;text-align:center;}
  .empty-state svg{width:52px;height:52px;stroke:#d1dce8;fill:none;margin:0 auto 16px;display:block;stroke-width:1.5;}
  .empty-state p{color:#9aaabf;font-size:15px;}

  /* ── Floating bulk bar ───────────────────────── */
  #bulk-bar{
    position:fixed;bottom:28px;left:50%;
    transform:translateX(-50%) translateY(90px);
    background:#0a2540;color:#fff;
    border-radius:14px;padding:14px 20px;
    display:flex;align-items:center;gap:12px;flex-wrap:nowrap;
    box-shadow:0 10px 40px rgba(0,0,0,.28);
    z-index:999;
    transition:transform .32s cubic-bezier(.22,.61,.36,1),opacity .32s;
    opacity:0;pointer-events:none;
    white-space:nowrap;
  }
  #bulk-bar.open{transform:translateX(-50%) translateY(0);opacity:1;pointer-events:all;}
  .bulk-selected{font-size:14px;font-weight:700;}
  .bulk-sep{width:1px;height:26px;background:rgba(255,255,255,.18);flex-shrink:0;}
  .bbtn{
    padding:8px 16px;border-radius:8px;
    font-size:13px;font-weight:600;
    cursor:pointer;border:none;transition:opacity .15s;
  }
  .bbtn:hover{opacity:.82;}
  .bbtn-approve{background:#10b981;color:#fff;}
  .bbtn-reject {background:#ef4444;color:#fff;}
  .bbtn-delete {background:rgba(255,255,255,.12);color:#fff;}
  .bbtn-cancel {background:none;color:rgba(255,255,255,.55);padding:8px 10px;font-size:13px;cursor:pointer;border:none;}
  .bbtn-cancel:hover{color:#fff;}

  @media(max-width:1100px){.stat-grid{grid-template-columns:repeat(2,1fr);}}
  @media(max-width:700px){
    .main{padding:20px 14px;}
    .stat-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
    .stat-val{font-size:28px;}
    .nav{padding:0 16px;}
  }

</style>
</head>
<body>

<!-- Processing overlay -->
<div id="proc-overlay" style="display:none;position:fixed;inset:0;background:rgba(10,37,64,.72);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:18px;">
  <div style="width:48px;height:48px;border:4px solid rgba(255,255,255,.25);border-top-color:#fff;border-radius:50%;animation:spin .8s linear infinite;"></div>
  <div id="proc-msg" style="color:#fff;font-size:16px;font-weight:600;letter-spacing:.02em;"></div>
  <div style="color:rgba(255,255,255,.55);font-size:13px;">Sending notification email…</div>
</div>
<style>@keyframes spin{to{transform:rotate(360deg)}}</style>

<!-- ── Hidden single-action form (avoids nested forms) ──── -->
<form id="singleForm" method="POST" style="display:none">
  <input type="hidden" name="id"             id="sf_id">
  <input type="hidden" name="status"         id="sf_status">
  <input type="hidden" name="reject_reason"  id="sf_reject_reason">
  <input type="hidden" name="ret_status"     value="<?= htmlspecialchars($status) ?>">
  <input type="hidden" name="ret_search"     value="<?= htmlspecialchars($search) ?>">
  <input type="hidden" name="update_status"  value="1">
</form>

<!-- ── Nav ─────────────────────────────────────────────── -->
<nav class="nav">
  <a class="nav-brand" href="admin.php">
    <div class="nav-brand-icon"><img src="asset/organizationLOGO.png" alt="GAMBIA 2026" style="width:38px;height:38px;object-fit:contain;"></div>
    <div class="nav-brand-text">GAMBIA 2026<small>Registration Admin</small></div>
  </a>
  <div class="nav-spacer"></div>

  <!-- Registration status pill -->
  <span class="nav-reg-pill <?= $regStatusInfo['open'] ? 'open' : 'closed' ?>">
    <span class="dot"></span>
    <?= $regStatusInfo['open'] ? 'Registration Open' : 'Registration Closed' ?>
  </span>

  <!-- Profile dropdown -->
  <div class="nav-profile" id="navProfile">
    <button class="nav-avatar" onclick="toggleProfileMenu(event)" aria-label="Profile menu">
      <?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?>
    </button>
    <div class="nav-dropdown" id="navDropdown">
      <div class="nav-dd-header">
        <div class="nav-dd-name"><?= htmlspecialchars($_SESSION['admin_name'] ?? '') ?></div>
        <div class="nav-dd-email"><?= htmlspecialchars($_SESSION['admin_email'] ?? '') ?></div>
      </div>
      <div class="nav-dd-divider"></div>
      <a href="logs.php" class="nav-dd-item">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Activity Logs
      </a>
      <a href="users.php" class="nav-dd-item">
        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Manage Users
      </a>
      <a href="subdomain_setup.php" class="nav-dd-item">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
        Deploy Guide
      </a>
      <a href="gdpr_cleanup.php" class="nav-dd-item" onclick="return confirm('Run GDPR cleanup now? This will anonymize rejected records older than 90 days.')">
        <svg viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
        GDPR Cleanup
      </a>
      <a href="index.php" target="_blank" class="nav-dd-item">
        <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Visit Registration Portal
      </a>
      <div class="nav-dd-divider"></div>
      <button class="nav-dd-item" onclick="openRegModal()">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
        Registration Control
      </button>
      <div class="nav-dd-divider"></div>
      <button class="nav-dd-item danger" onclick="confirmLogout()">
        <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sign Out
      </button>
    </div>
  </div>
</nav>

<!-- ── Dashboard ───────────────────────────────────────── -->
<div class="main">

  <!-- Stat cards -->
  <div class="stat-grid">
    <div class="stat-card c-total">
      <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></div>
      <div class="stat-text"><div class="stat-val"><?= $total ?></div><div class="stat-lbl">Total Delegates</div></div>
    </div>
    <div class="stat-card c-pending">
      <div class="stat-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
      <div class="stat-text"><div class="stat-val"><?= $pending ?></div><div class="stat-lbl">Pending Review</div></div>
    </div>
    <div class="stat-card c-approved">
      <div class="stat-icon"><svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
      <div class="stat-text"><div class="stat-val"><?= $approved ?></div><div class="stat-lbl">Approved</div></div>
    </div>
    <div class="stat-card c-rejected">
      <div class="stat-icon"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
      <div class="stat-text"><div class="stat-val"><?= $rejected ?></div><div class="stat-lbl">Rejected</div></div>
    </div>
  </div>

  <!-- Charts -->
  <div class="chart-grid">
    <div class="chart-card">
      <div class="chart-title">Registrations — Last 30 Days</div>
      <div class="chart-wrap"><canvas id="chartDaily"></canvas></div>
    </div>
    <div class="chart-card">
      <div class="chart-title">By Representation Type</div>
      <div class="chart-wrap"><canvas id="chartType"></canvas></div>
    </div>
    <div class="chart-card">
      <div class="chart-title">By Gender</div>
      <div class="chart-wrap"><canvas id="chartGender"></canvas></div>
    </div>
  </div>
  <?php if (!empty($countryLabels)): ?>
  <div class="chart-card" style="margin-bottom:28px;">
    <div class="chart-title">Approved Delegates by Country</div>
    <div class="chart-wrap" style="height:<?= max(160, count($countryLabels) * 28) ?>px;">
      <canvas id="chartCountry"></canvas>
    </div>
  </div>
  <?php endif; ?>

  <!-- Main table card -->
  <div class="card">

    <!-- Header: title + filter tabs -->
    <div class="card-header">
      <span class="card-title">Delegate Registrations</span>
      <span class="card-count"><?= $filteredTotal ?> total<?= $totalPages > 1 ? ', page ' . $page . ' of ' . $totalPages : '' ?></span>
      <div style="flex:1;"></div>
      <div class="filter-tabs">
        <?php
        $tabs = ['all'=>'All','pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'];
        $tc   = ['all'=>$total,'pending'=>$pending,'approved'=>$approved,'rejected'=>$rejected];
        foreach ($tabs as $k => $v):
          $qs = http_build_query(['status'=>$k] + ($search ? ['search'=>$search] : []));
        ?>
        <a href="?<?= $qs ?>" class="<?= $status===$k?'active':'' ?>">
          <?= $v ?> <span class="tc"><?= $tc[$k] ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Toolbar: search + export -->
    <div class="toolbar">
      <form method="GET" style="display:contents">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <div class="search-box">
          <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, email, organisation…">
        </div>
        <button type="submit" class="btn btn-dark">Search</button>
        <?php if ($search): ?>
          <a href="?status=<?= htmlspecialchars($status) ?>" class="btn btn-ghost">✕ Clear</a>
        <?php endif; ?>
      </form>
      <div style="flex:1;"></div>
      <form method="POST" action="export_csv" style="display:inline;">
        <input type="hidden" name="export_all" value="1">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-green">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export All CSV
        </button>
      </form>
    </div>

    <!-- Table (wraps bulk form) -->
    <form id="bulkForm" method="POST">
      <input type="hidden" name="ret_status" value="<?= htmlspecialchars($status) ?>">
      <input type="hidden" name="ret_search" value="<?= htmlspecialchars($search) ?>">

      <!-- Hidden submit triggers for bulk bar -->
      <input type="hidden" name="reject_reason" id="bulkRejectReason">
      <button type="submit" name="bulk_action" value="approve" id="doBulkApprove" style="display:none"></button>
      <button type="submit" name="bulk_action" value="reject"  id="doBulkReject"  style="display:none"></button>
      <button type="submit" name="bulk_action" value="delete"  id="doBulkDelete"  style="display:none"></button>

      <div class="tbl-wrap">
        <?php if (empty($rows)): ?>
        <div class="empty-state">
          <svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
          <p>No registrations found.</p>
        </div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th><input type="checkbox" id="selectAll" title="Select all"></th>
              <th>Photo</th>
              <th>Delegate</th>
              <th>Organisation</th>
              <th>Type</th>
              <th>Submitted</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r):
              $rid  = (int)$r['id'];
              $name = htmlspecialchars($r['first_name'] . ' ' . $r['last_name']);
              $ref  = 'GAM26-' . str_pad($rid, 5, '0', STR_PAD_LEFT);
            ?>
            <tr id="row-<?= $rid ?>">
              <td><input type="checkbox" name="selected_ids[]" value="<?= $rid ?>" class="row-check"></td>
              <td>
                <?php if ($r['picture']): ?>
                  <img class="avatar" src="uploads/<?= htmlspecialchars($r['picture']) ?>" alt="">
                <?php else: ?>
                  <div class="avatar-ph">👤</div>
                <?php endif; ?>
              </td>
              <td>
                <div class="td-name"><?= $name ?></div>
                <div class="td-email"><?= htmlspecialchars($r['email']) ?></div>
                <div style="font-size:11px;color:#b8cfe0;margin-top:1px;"><?= $ref ?></div>
              </td>
              <td style="max-width:200px;">
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($r['organisation_name']) ?>">
                  <?= htmlspecialchars($r['organisation_name']) ?>
                </div>
              </td>
              <td style="font-size:12px;color:#7a8fa8;max-width:160px;">
                <div style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= htmlspecialchars($r['representation_type']) ?>">
                  <?= htmlspecialchars($r['representation_type']) ?>
                </div>
              </td>
              <td style="font-size:12px;color:#9aaabf;white-space:nowrap;"><?= date('d M Y', strtotime($r['submitted_at'])) ?></td>
              <td><span class="badge <?= $r['status'] ?>"><?= ucfirst($r['status']) ?></span></td>
              <td>
                <div class="row-actions">
                  <a href="view.php?id=<?= $rid ?>" class="bxs bxs-view">View</a>
                  <?php if ($r['status'] !== 'approved'): ?>
                  <button type="button" class="bxs bxs-approve" onclick="singleAction(<?= $rid ?>,'approved','Approve')">Approve</button>
                  <?php endif; ?>
                  <?php if ($r['status'] !== 'rejected'): ?>
                  <button type="button" class="bxs bxs-reject"  onclick="singleAction(<?= $rid ?>,'rejected','Reject')">Reject</button>
                  <?php endif; ?>
                  <button type="button" class="bxs bxs-delete"  onclick="singleDelete(<?= $rid ?>)">Delete</button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($totalPages > 1): ?>
    <div class="pager">
      <?php
      $q = http_build_query(array_filter(['status'=>$status!=='all'?$status:'', 'search'=>$search]));
      $qSep = $q ? '&' : '';
      if ($page > 1): ?>
        <a href="?<?= $q . $qSep ?>page=<?= $page - 1 ?>">← Prev</a>
      <?php endif;
      // Show window of page numbers
      $range = 2;
      for ($i = 1; $i <= $totalPages; $i++):
        if ($i === 1 || $i === $totalPages || abs($i - $page) <= $range):
          if ($i === $page): ?>
            <span class="current"><?= $i ?></span>
          <?php else: ?>
            <a href="?<?= $q . $qSep ?>page=<?= $i ?>"><?= $i ?></a>
          <?php endif;
        elseif (abs($i - $page) === $range + 1): ?>
          <span class="dots">…</span>
        <?php endif;
      endfor;
      if ($page < $totalPages): ?>
        <a href="?<?= $q . $qSep ?>page=<?= $page + 1 ?>">Next →</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /.card -->
</div><!-- /.main -->

<!-- ── Scroll-to-top button ───────────────────────────────── -->
<button id="scroll-top" title="Back to top" aria-label="Back to top">&#8679;</button>

<!-- ── Floating bulk action bar ──────────────────────────── -->
<div id="bulk-bar">
  <span class="bulk-selected"><span id="bulkNum">0</span> selected</span>
  <div class="bulk-sep"></div>
  <button class="bbtn bbtn-approve" onclick="triggerBulk('Approve')">✓ Approve</button>
  <button class="bbtn bbtn-reject"  onclick="triggerBulk('Reject')">✕ Reject</button>
  <button class="bbtn bbtn-delete"  onclick="triggerBulk('Delete')">🗑 Delete</button>
  <button class="bbtn" style="background:#059669;" onclick="exportSelected()">⬇ Export CSV</button>
  <button class="bbtn-cancel"       onclick="clearSelection()">Cancel</button>
</div>

<script>
// ── Session timeout: warn at 25 min, auto-logout at 30 min ──
(function() {
  var TIMEOUT_MS = 30 * 60 * 1000;
  var WARN_MS    = 25 * 60 * 1000;
  var warnTimer, logoutTimer, warned = false;

  function resetTimers() {
    clearTimeout(warnTimer);
    clearTimeout(logoutTimer);
    warned = false;
    warnTimer   = setTimeout(showWarning, WARN_MS);
    logoutTimer = setTimeout(doLogout,    TIMEOUT_MS);
  }

  function showWarning() {
    if (warned) return;
    warned = true;
    Swal.fire({
      title: 'Still there?',
      text: 'You will be signed out automatically in 5 minutes due to inactivity.',
      icon: 'warning',
      confirmButtonText: 'Keep me signed in',
      confirmButtonColor: '#0a2540',
      allowOutsideClick: false,
    }).then(resetTimers);
  }

  function doLogout() {
    var f = document.createElement('form');
    f.method = 'POST'; f.action = 'admin.php';
    var i = document.createElement('input');
    i.type = 'hidden'; i.name = 'do_logout'; i.value = '1';
    f.appendChild(i); document.body.appendChild(f); f.submit();
  }

  ['mousemove','keydown','click','scroll'].forEach(function(ev) {
    document.addEventListener(ev, resetTimers, { passive: true });
  });

  resetTimers();
})();

function confirmLogout() {
  Swal.fire({
    title: 'Sign out?',
    text: 'You will be returned to the login page.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Yes, sign out',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#0a2540',
    cancelButtonColor: '#6b7280',
    reverseButtons: true,
  }).then(function(r) {
    if (r.isConfirmed) doLogout();
  });
}

var selectAll = document.getElementById('selectAll');
var bulkBar   = document.getElementById('bulk-bar');
var bulkNum   = document.getElementById('bulkNum');
var checks    = function() { return document.querySelectorAll('.row-check'); };

function refreshBulkUI() {
  var all     = checks();
  var checked = document.querySelectorAll('.row-check:checked');
  var n       = checked.length;
  bulkNum.textContent = n;
  bulkBar.classList.toggle('open', n > 0);
  selectAll.indeterminate = n > 0 && n < all.length;
  selectAll.checked       = n > 0 && n === all.length;
  all.forEach(function(cb) {
    cb.closest('tr').classList.toggle('row-selected', cb.checked);
  });
}

if (selectAll) {
  selectAll.addEventListener('change', function() {
    checks().forEach(function(cb) { cb.checked = selectAll.checked; });
    refreshBulkUI();
  });
}

document.querySelectorAll('.row-check').forEach(function(cb) {
  cb.addEventListener('change', refreshBulkUI);
});

// ── Scroll-to-top ──────────────────────────────────────────
(function() {
  var btn = document.getElementById('scroll-top');
  window.addEventListener('scroll', function() {
    btn.classList.toggle('visible', window.scrollY > 400);
  }, { passive: true });
  btn.addEventListener('click', function() {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
})();

function clearSelection() {
  checks().forEach(function(cb) { cb.checked = false; });
  refreshBulkUI();
}

function exportSelected() {
  var ids = Array.from(document.querySelectorAll('.row-check:checked')).map(function(cb){ return cb.value; });
  if (!ids.length) return;
  var form = document.createElement('form');
  form.method = 'POST';
  form.action = 'export_csv';
  ids.forEach(function(id) {
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
    form.appendChild(inp);
  });
  document.body.appendChild(form);
  form.submit();
  document.body.removeChild(form);
}

function triggerBulk(action) {
  var n = document.querySelectorAll('.row-check:checked').length;
  if (!n) return;
  if (action === 'Reject') {
    Swal.fire({
      title: 'Reject ' + n + ' registration(s)?',
      html: '<textarea id="swal-reason" placeholder="Reason for rejection (sent to applicants)" style="width:100%;margin-top:10px;padding:10px;border:1.5px solid #d1dce8;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;min-height:80px;"></textarea>',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Yes, reject',
      cancelButtonText: 'Cancel',
      confirmButtonColor: '#ef4444',
      cancelButtonColor: '#6b7280',
      reverseButtons: true,
      preConfirm: function() {
        var reason = document.getElementById('swal-reason').value.trim();
        if (!reason) { Swal.showValidationMessage('Please enter a reason for rejection.'); return false; }
        return reason;
      }
    }).then(function(r) {
      if (!r.isConfirmed) return;
      document.getElementById('bulkRejectReason').value = r.value;
      document.getElementById('doBulkReject').click();
    });
    return;
  }
  var cfg = {
    Approve: { icon:'question', color:'#059669', msg:'Approve ' + n + ' registration(s)?' },
    Delete:  { icon:'warning',  color:'#ef4444', msg:'Permanently delete ' + n + ' registration(s)?' },
  };
  var c = cfg[action];
  Swal.fire({
    title: c.msg,
    icon:  c.icon,
    showCancelButton: true,
    confirmButtonText: 'Yes, ' + action.toLowerCase(),
    cancelButtonText: 'Cancel',
    confirmButtonColor: c.color,
    cancelButtonColor: '#6b7280',
    reverseButtons: true,
  }).then(function(r) {
    if (!r.isConfirmed) return;
    document.getElementById('doBulk' + action).click();
  });
}

function showOverlay(msg) {
  document.getElementById('proc-msg').textContent = msg;
  document.getElementById('proc-overlay').style.display = 'flex';
}

function submitWithOverlay(msg, formId) {
  showOverlay(msg);
  requestAnimationFrame(function() {
    requestAnimationFrame(function() {
      document.getElementById(formId).submit();
    });
  });
}

function singleAction(id, status, label) {
  var isReject = status === 'rejected';
  if (isReject) {
    Swal.fire({
      title: 'Reject this registration?',
      html: '<textarea id="swal-reason" placeholder="Reason for rejection (sent to applicant)" style="width:100%;margin-top:10px;padding:10px;border:1.5px solid #d1dce8;border-radius:8px;font-size:13px;font-family:inherit;resize:vertical;min-height:80px;"></textarea>',
      icon: 'warning',
      showCancelButton:   true,
      confirmButtonText:  'Yes, reject',
      cancelButtonText:   'Cancel',
      confirmButtonColor: '#ef4444',
      cancelButtonColor:  '#6b7280',
      reverseButtons: true,
      preConfirm: function() {
        var reason = document.getElementById('swal-reason').value.trim();
        if (!reason) { Swal.showValidationMessage('Please enter a reason for rejection.'); return false; }
        return reason;
      }
    }).then(function(r) {
      if (!r.isConfirmed) return;
      document.getElementById('sf_id').value            = id;
      document.getElementById('sf_status').value        = status;
      document.getElementById('sf_reject_reason').value = r.value;
      submitWithOverlay('Rejecting registration…', 'singleForm');
    });
    return;
  }
  Swal.fire({
    title: 'Approve this registration?',
    icon:  'question',
    showCancelButton:   true,
    confirmButtonText:  'Yes, approve',
    cancelButtonText:   'Cancel',
    confirmButtonColor: '#059669',
    cancelButtonColor:  '#6b7280',
    reverseButtons: true,
  }).then(function(r) {
    if (!r.isConfirmed) return;
    document.getElementById('sf_id').value     = id;
    document.getElementById('sf_status').value = status;
    submitWithOverlay('Approving registration…', 'singleForm');
  });
}

function singleDelete(id) {
  Swal.fire({
    title: 'Delete this registration?',
    text:  'This action cannot be undone.',
    icon:  'warning',
    showCancelButton:   true,
    confirmButtonText:  'Yes, delete',
    cancelButtonText:   'Cancel',
    confirmButtonColor: '#ef4444',
    cancelButtonColor:  '#6b7280',
    reverseButtons: true,
  }).then(function(r) {
    if (r.isConfirmed) window.location.href = 'admin.php?delete=' + id;
  });
}

<?php if (!empty($_SESSION['flash'])):
  $flash = $_SESSION['flash'];
  unset($_SESSION['flash']);
?>
document.addEventListener('DOMContentLoaded', function() {
  Swal.fire({
    toast:            true,
    position:         'top-end',
    icon:             '<?= $flash['type'] ?>',
    title:            '<?= addslashes($flash['msg']) ?>',
    showConfirmButton: false,
    timer:            4500,
    timerProgressBar: true,
  });
});
<?php endif; ?>

// ── Charts ──────────────────────────────────────────────────
(function() {
  var dailyLabels  = <?= json_encode($chartDays) ?>;
  var dailyCounts  = <?= json_encode($chartCounts) ?>;
  var typeLabels   = <?= json_encode($typeLabels) ?>;
  var typeCounts   = <?= json_encode($typeCounts) ?>;
  var genderLabels = <?= json_encode($genderLabels) ?>;
  var genderCounts = <?= json_encode($genderCounts) ?>;

  var palette = ['#0a2540','#0d6e8c','#059669','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6'];
  var genderPalette = { 'Male':'#0a2540', 'Female':'#c9a84c', 'Non-binary':'#059669', 'Prefer not to say':'#9aaabf', 'Not specified':'#d1dce8' };
  var genderColors = genderLabels.map(function(l) { return genderPalette[l] || '#0d6e8c'; });

  new Chart(document.getElementById('chartDaily'), {
    type: 'line',
    data: {
      labels: dailyLabels,
      datasets: [{
        label: 'Registrations',
        data: dailyCounts,
        borderColor: '#0d6e8c',
        backgroundColor: 'rgba(13,110,140,.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.35,
        pointRadius: 3,
        pointHoverRadius: 5,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 }, maxTicksLimit: 10 } },
        y: { beginAtZero: true, ticks: { font: { size: 11 }, stepSize: 1 } }
      }
    }
  });

  if (typeLabels.length) {
    new Chart(document.getElementById('chartType'), {
      type: 'doughnut',
      data: {
        labels: typeLabels,
        datasets: [{ data: typeCounts, backgroundColor: palette, borderWidth: 2 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } }
        }
      }
    });
  }

  if (genderLabels.length) {
    new Chart(document.getElementById('chartGender'), {
      type: 'doughnut',
      data: {
        labels: genderLabels,
        datasets: [{ data: genderCounts, backgroundColor: genderColors, borderWidth: 2 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } },
          tooltip: {
            callbacks: {
              label: function(ctx) {
                var total = ctx.dataset.data.reduce(function(a,b){ return a+b; }, 0);
                var pct = total ? Math.round(ctx.parsed / total * 100) : 0;
                return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
              }
            }
          }
        }
      }
    });
  }

  var countryEl = document.getElementById('chartCountry');
  if (countryEl) {
    var countryLabels = <?= json_encode($countryLabels) ?>;
    var countryCounts = <?= json_encode($countryCounts) ?>;
    new Chart(countryEl, {
      type: 'bar',
      data: {
        labels: countryLabels,
        datasets: [{
          label: 'Delegates',
          data: countryCounts,
          backgroundColor: 'rgba(13,110,140,.75)',
          borderColor: '#0d6e8c',
          borderWidth: 1,
          borderRadius: 4,
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, ticks: { font: { size: 11 }, stepSize: 1 }, grid: { color: 'rgba(0,0,0,.05)' } },
          y: { ticks: { font: { size: 11 } }, grid: { display: false } }
        }
      }
    });
  }
})();

// ── Profile dropdown ──────────────────────────────────────
function toggleProfileMenu(e) {
  e.stopPropagation();
  document.getElementById('navDropdown').classList.toggle('open');
}
document.addEventListener('click', function() {
  document.getElementById('navDropdown').classList.remove('open');
});

// ── Registration control modal ────────────────────────────
function openRegModal() {
  document.getElementById('navDropdown').classList.remove('open');
  document.getElementById('regModal').style.display = 'flex';
}
function closeRegModal(e) {
  if (!e || e.target === document.getElementById('regModal')) {
    document.getElementById('regModal').style.display = 'none';
  }
}
function updateToggleLabel(cb) {
  var lbl = document.getElementById('toggle-label');
  lbl.textContent = cb.checked ? 'Open' : 'Closed';
  lbl.style.color = cb.checked ? '#15803d' : '#dc2626';
}
</script>

<!-- ── Registration Control Modal ──────────────────────── -->
<div id="regModal" class="reg-modal-overlay" style="display:none;" onclick="closeRegModal(event)">
  <div class="reg-modal-box">
    <div class="reg-modal-header">
      <span class="reg-modal-title">Registration Control</span>
      <button class="reg-modal-close" onclick="closeRegModal()">&times;</button>
    </div>
    <div class="reg-modal-body">
      <div class="reg-modal-status <?= $regStatusInfo['open'] ? 'open' : 'closed' ?>">
        <span class="reg-modal-status-dot"></span>
        <span class="reg-modal-status-text">
          <?php if ($regStatusInfo['open']): ?>
            Registration is currently Open
          <?php elseif (($regStatusInfo['reason'] ?? '') === 'deadline'): ?>
            Closed — deadline of <?= date('d M Y, H:i', strtotime($regStatusInfo['deadline'])) ?> passed
          <?php else: ?>
            Closed manually
          <?php endif; ?>
        </span>
      </div>
      <form method="POST" action="admin.php">
        <input type="hidden" name="save_reg_settings" value="1">
        <div class="reg-modal-row">
          <div class="reg-modal-group">
            <label>Manual Toggle</label>
            <div class="reg-toggle-wrap">
              <label class="reg-toggle">
                <input type="checkbox" name="registration_open" <?= $regNowOpen ? 'checked' : '' ?> onchange="updateToggleLabel(this)">
                <span class="reg-toggle-slider"></span>
              </label>
              <span id="toggle-label" style="font-size:13px;font-weight:600;color:<?= $regNowOpen ? '#15803d' : '#dc2626' ?>;">
                <?= $regNowOpen ? 'Open' : 'Closed' ?>
              </span>
            </div>
          </div>
          <div class="reg-modal-group">
            <label>Auto-Close Deadline <span style="font-weight:400;color:#9aaabf;">(optional)</span></label>
            <input type="datetime-local" name="registration_deadline"
              value="<?= $regDeadline ? date('Y-m-d\TH:i', strtotime($regDeadline)) : '' ?>">
            <div class="reg-modal-hint">Closes automatically at this date &amp; time. Leave blank for no deadline.</div>
          </div>
        </div>
        <div class="reg-modal-footer">
          <button type="button" class="btn-modal-cancel" onclick="closeRegModal()">Cancel</button>
          <button type="submit" class="btn-modal-save">Save Settings</button>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>
