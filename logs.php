<?php
session_start();
require_once 'session_guard.php';
require_once 'db.php';
require_once 'logger.php';

if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }

ensure_logs_table($pdo);

/* ── Clear logs ───────────────────────────────────────────── */
if (isset($_POST['clear_logs'])) {
    if (
        empty($_POST['admin_csrf']) ||
        empty($_SESSION['admin_csrf']) ||
        !hash_equals($_SESSION['admin_csrf'], $_POST['admin_csrf'])
    ) {
        http_response_code(403);
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Invalid security token. Please try again.'];
        header('Location: logs.php'); exit;
    }
    $pdo->exec("DELETE FROM admin_logs");
    log_action($pdo, 'clear_logs', 'Cleared all activity logs');
    $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Activity logs cleared.'];
    header('Location: logs.php'); exit;
}

/* ── Filters ──────────────────────────────────────────────── */
$filter  = $_GET['filter']  ?? 'all';
$period  = $_GET['period']  ?? 'all';
$search  = trim($_GET['search'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;

$where  = [];
$params = [];

$actionGroups = [
    'auth'         => ['login', 'logout'],
    'registrations'=> ['approve', 'reject', 'delete', 'bulk_approve', 'bulk_reject', 'bulk_delete'],
    'users'        => ['add_admin', 'edit_admin', 'delete_admin', 'clear_logs'],
];
if ($filter !== 'all' && isset($actionGroups[$filter])) {
    $ph      = implode(',', array_fill(0, count($actionGroups[$filter]), '?'));
    $where[] = "action IN ($ph)";
    $params  = array_merge($params, $actionGroups[$filter]);
}

if ($period === 'today') {
    $where[] = "DATE(created_at) = CURDATE()";
} elseif ($period === 'week') {
    $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
}

if ($search) {
    $where[] = "(admin_name LIKE ? OR admin_email LIKE ? OR details LIKE ?)";
    $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = $pdo->prepare("SELECT COUNT(*) FROM admin_logs $whereSql");
$total->execute($params);
$totalRows = (int)$total->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT * FROM admin_logs $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$logs = $stmt->fetchAll();

/* ── Summary counts ───────────────────────────────────────── */
$todayCount  = (int)$pdo->query("SELECT COUNT(*) FROM admin_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$loginCount  = (int)$pdo->query("SELECT COUNT(*) FROM admin_logs WHERE action='login' AND DATE(created_at) = CURDATE()")->fetchColumn();
$actionCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_logs WHERE action NOT IN ('login','logout') AND DATE(created_at) = CURDATE()")->fetchColumn();
$totalAll    = (int)$pdo->query("SELECT COUNT(*) FROM admin_logs")->fetchColumn();

/* ── Flash ────────────────────────────────────────────────── */
$flash = null;
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }

/* ── Badge config ─────────────────────────────────────────── */
$badgeCfg = [
    'login'        => ['bg'=>'#dbeafe','color'=>'#1e40af','label'=>'Login'],
    'logout'       => ['bg'=>'#f1f5f9','color'=>'#475569','label'=>'Logout'],
    'approve'      => ['bg'=>'#dcfce7','color'=>'#166534','label'=>'Approve'],
    'reject'       => ['bg'=>'#fff3cd','color'=>'#92600a','label'=>'Reject'],
    'delete'       => ['bg'=>'#fee2e2','color'=>'#991b1b','label'=>'Delete'],
    'bulk_approve' => ['bg'=>'#bbf7d0','color'=>'#14532d','label'=>'Bulk Approve'],
    'bulk_reject'  => ['bg'=>'#fde68a','color'=>'#78350f','label'=>'Bulk Reject'],
    'bulk_delete'  => ['bg'=>'#fecaca','color'=>'#7f1d1d','label'=>'Bulk Delete'],
    'add_admin'    => ['bg'=>'#ede9fe','color'=>'#5b21b6','label'=>'Add Admin'],
    'edit_admin'   => ['bg'=>'#e0e7ff','color'=>'#3730a3','label'=>'Edit Admin'],
    'delete_admin' => ['bg'=>'#fce7f3','color'=>'#9d174d','label'=>'Remove Admin'],
    'clear_logs'   => ['bg'=>'#f1f5f9','color'=>'#475569','label'=>'Clear Logs'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Activity Logs — GAMBIA 2026</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1a2332;min-height:100vh;}

  /* Nav */
  .nav{background:#fff;border-bottom:1px solid #e8f0f8;height:64px;display:flex;align-items:center;padding:0 32px;gap:16px;position:sticky;top:0;z-index:200;box-shadow:0 1px 6px rgba(0,0,0,.06);}
  .nav-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
  .nav-brand-icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;}
  .nav-brand-text{font-size:15px;font-weight:700;color:#0a2540;line-height:1.2;}
  .nav-brand-text small{display:block;font-size:11px;font-weight:500;color:#9aaabf;}
  .nav-spacer{flex:1;}
  .nav-link{display:flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;color:#4a6080;text-decoration:none;background:#f0f4f8;transition:all .15s;}
  .nav-link:hover{background:#e2eaf4;}
  .nav-link.danger:hover{background:#fee2e2;color:#dc2626;}

  /* Main */
  .main{max-width:1200px;margin:0 auto;padding:32px 28px;}

  /* Stat cards */
  .stat-row{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:28px;}
  .stat-card{background:#fff;border-radius:12px;padding:20px 22px;box-shadow:0 2px 12px rgba(0,0,0,.06);border-left:4px solid #e8f0f8;}
  .stat-card.c-blue {border-color:#3b82f6;}
  .stat-card.c-green{border-color:#10b981;}
  .stat-card.c-amber{border-color:#f59e0b;}
  .stat-card.c-gray {border-color:#94a3b8;}
  .stat-val{font-size:28px;font-weight:700;color:#0a2540;line-height:1;}
  .stat-lbl{font-size:12px;color:#9aaabf;margin-top:4px;}

  /* Card */
  .card{background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.06);overflow:hidden;}
  .card-header{padding:18px 24px;border-bottom:1px solid #f0f4f8;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
  .card-title{font-size:16px;font-weight:700;color:#0a2540;}

  /* Toolbar */
  .toolbar{padding:14px 24px;border-bottom:1px solid #f0f4f8;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
  .filter-tabs{display:flex;gap:4px;}
  .filter-tabs a{padding:5px 14px;border-radius:20px;font-size:12px;font-weight:600;text-decoration:none;color:#4a6080;transition:all .15s;}
  .filter-tabs a:hover{background:#f0f4f8;}
  .filter-tabs a.active{background:#0a2540;color:#fff;}

  .period-sel{padding:7px 12px;border:1.5px solid #d1dce8;border-radius:8px;font-size:12px;font-family:inherit;color:#1a2332;background:#f8fbff;outline:none;cursor:pointer;}
  .search-box{position:relative;flex:1;max-width:280px;}
  .search-box svg{position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;stroke:#9aaabf;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;pointer-events:none;}
  .search-box input{width:100%;padding:7px 12px 7px 32px;border:1.5px solid #d1dce8;border-radius:8px;font-size:12px;font-family:inherit;background:#f8fbff;outline:none;}
  .search-box input:focus{border-color:#0d6e8c;}
  .btn{padding:8px 16px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:none;display:inline-flex;align-items:center;gap:5px;transition:all .15s;text-decoration:none;}
  .btn-dark{background:#0a2540;color:#fff;}
  .btn-dark:hover{background:#0d6e8c;}
  .btn-danger{background:#fee2e2;color:#991b1b;}
  .btn-danger:hover{background:#fecaca;}

  /* Table */
  .tbl-wrap{overflow-x:auto;}
  table{width:100%;border-collapse:collapse;}
  thead th{background:#f8fbff;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#7a8fa8;padding:11px 18px;text-align:left;border-bottom:1px solid #e8f0f8;white-space:nowrap;}
  tbody tr{border-bottom:1px solid #f5f7fa;transition:background .1s;}
  tbody tr:hover{background:#f8fbff;}
  tbody td{padding:12px 18px;font-size:13px;vertical-align:middle;}
  .td-admin{font-weight:600;color:#0a2540;line-height:1.3;}
  .td-email{font-size:11px;color:#9aaabf;margin-top:1px;}
  .td-details{font-size:12px;color:#4a6080;max-width:340px;}
  .td-ip{font-size:11px;color:#b8cfe0;font-family:monospace;}
  .td-time{font-size:12px;color:#9aaabf;white-space:nowrap;}

  .badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap;}

  /* Pagination */
  .pagination{display:flex;align-items:center;justify-content:center;gap:4px;padding:20px;}
  .pag-btn{padding:6px 12px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;color:#4a6080;background:#f0f4f8;transition:all .15s;}
  .pag-btn:hover{background:#e2eaf4;}
  .pag-btn.active{background:#0a2540;color:#fff;}
  .pag-btn.disabled{opacity:.4;pointer-events:none;}

  .empty-state{padding:56px 24px;text-align:center;color:#9aaabf;font-size:14px;}

  @media(max-width:900px){.stat-row{grid-template-columns:repeat(2,1fr);}}
  @media(max-width:600px){.main{padding:20px 14px;}.stat-row{grid-template-columns:1fr 1fr;}}
</style>
</head>
<body>

<nav class="nav">
  <a class="nav-brand" href="admin.php">
    <div class="nav-brand-icon"><img src="asset/organizationLOGO.png" alt="GAMBIA 2026" style="width:38px;height:38px;object-fit:contain;"></div>
    <div class="nav-brand-text">GAMBIA 2026 <small>Registration Admin</small></div>
  </a>
  <div class="nav-spacer"></div>
  <span style="font-size:13px;color:#9aaabf;"><?= htmlspecialchars($_SESSION['admin_name'] ?? '') ?></span>
  <a href="admin.php" class="nav-link">← Dashboard</a>
  <a href="#" onclick="confirmLogout();return false;" class="nav-link danger">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
    Sign Out
  </a>
</nav>

<div class="main">

  <!-- Stat cards -->
  <div class="stat-row">
    <div class="stat-card c-blue">
      <div class="stat-val"><?= $todayCount ?></div>
      <div class="stat-lbl">Actions Today</div>
    </div>
    <div class="stat-card c-green">
      <div class="stat-val"><?= $loginCount ?></div>
      <div class="stat-lbl">Logins Today</div>
    </div>
    <div class="stat-card c-amber">
      <div class="stat-val"><?= $actionCount ?></div>
      <div class="stat-lbl">Changes Today</div>
    </div>
    <div class="stat-card c-gray">
      <div class="stat-val"><?= $totalAll ?></div>
      <div class="stat-lbl">Total Log Entries</div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <span class="card-title">Activity Logs</span>
      <span style="font-size:13px;color:#9aaabf;background:#f0f4f8;padding:3px 10px;border-radius:20px;"><?= $totalRows ?> entries</span>
      <div style="flex:1;"></div>
      <form method="POST" id="clearForm">
        <input type="hidden" name="clear_logs" value="1">
        <input type="hidden" name="admin_csrf" value="<?= htmlspecialchars($_SESSION['admin_csrf'] ?? '') ?>">
        <button type="button" class="btn btn-danger" onclick="confirmClear()">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
          Clear All Logs
        </button>
      </form>
    </div>

    <!-- Filters -->
    <form method="GET" class="toolbar">
      <div class="filter-tabs">
        <?php
        $tabs = ['all'=>'All','auth'=>'Auth','registrations'=>'Registrations','users'=>'User Mgmt'];
        foreach ($tabs as $k => $v):
          $qs = http_build_query(['filter'=>$k,'period'=>$period,'search'=>$search]);
        ?>
        <a href="?<?= $qs ?>" class="<?= $filter===$k?'active':'' ?>"><?= $v ?></a>
        <?php endforeach; ?>
      </div>
      <select name="period" class="period-sel" onchange="this.form.submit()">
        <option value="all"  <?= $period==='all'  ?'selected':'' ?>>All time</option>
        <option value="today"<?= $period==='today'?'selected':'' ?>>Today</option>
        <option value="week" <?= $period==='week' ?'selected':'' ?>>Last 7 days</option>
      </select>
      <div class="search-box">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search admin or details…">
      </div>
      <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
      <button type="submit" class="btn btn-dark">Search</button>
      <?php if ($search): ?>
        <a href="?filter=<?= $filter ?>&period=<?= $period ?>" class="btn" style="background:#f0f4f8;color:#4a6080;">✕ Clear</a>
      <?php endif; ?>
    </form>

    <!-- Table -->
    <div class="tbl-wrap">
      <?php if (empty($logs)): ?>
      <div class="empty-state">No activity logs found.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Admin</th>
            <th>Action</th>
            <th>Details</th>
            <th>IP Address</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($logs as $log):
            $cfg = $badgeCfg[$log['action']] ?? ['bg'=>'#f0f4f8','color'=>'#4a6080','label'=>ucfirst($log['action'])];
          ?>
          <tr>
            <td class="td-time">
              <?= date('d M Y', strtotime($log['created_at'])) ?><br>
              <span style="font-size:11px;color:#b8cfe0;"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
            </td>
            <td>
              <div class="td-admin"><?= htmlspecialchars($log['admin_name']) ?></div>
              <div class="td-email"><?= htmlspecialchars($log['admin_email']) ?></div>
            </td>
            <td>
              <span class="badge" style="background:<?= $cfg['bg'] ?>;color:<?= $cfg['color'] ?>;">
                <?= $cfg['label'] ?>
              </span>
            </td>
            <td class="td-details"><?= htmlspecialchars($log['details']) ?></td>
            <td class="td-ip"><?= htmlspecialchars($log['ip_address'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php
      $baseQs = http_build_query(['filter'=>$filter,'period'=>$period,'search'=>$search]);
      ?>
      <a href="?<?= $baseQs ?>&page=<?= $page-1 ?>" class="pag-btn <?= $page<=1?'disabled':'' ?>">← Prev</a>
      <?php for ($p = max(1,$page-2); $p <= min($totalPages,$page+2); $p++): ?>
        <a href="?<?= $baseQs ?>&page=<?= $p ?>" class="pag-btn <?= $p===$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
      <a href="?<?= $baseQs ?>&page=<?= $page+1 ?>" class="pag-btn <?= $page>=$totalPages?'disabled':'' ?>">Next →</a>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
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
    if (r.isConfirmed) window.location.href = 'admin.php?logout=1';
  });
}

function confirmClear() {
  Swal.fire({
    title: 'Clear all logs?',
    text: 'This will permanently delete all activity history.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, clear all',
    cancelButtonText: 'Cancel',
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#6b7280',
    reverseButtons: true,
  }).then(function(r) {
    if (r.isConfirmed) document.getElementById('clearForm').submit();
  });
}

<?php if ($flash): ?>
document.addEventListener('DOMContentLoaded', function() {
  Swal.fire({
    toast: true, position: 'top-end',
    icon: '<?= $flash['type'] ?>',
    title: '<?= addslashes($flash['msg']) ?>',
    showConfirmButton: false,
    timer: 4000, timerProgressBar: true,
  });
});
<?php endif; ?>
</script>
</body>
</html>
