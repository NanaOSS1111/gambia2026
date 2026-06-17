<?php
session_start();
require_once 'session_guard.php';
require_once 'db.php';
require_once 'logger.php';

if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }

/* ── Redirect to setup if table missing ──────────────────── */
try {
    $pdo->query("SELECT 1 FROM admin_users LIMIT 1");
} catch (PDOException) {
    header('Location: setup_admin.php'); exit;
}

$me = (int)($_SESSION['admin_id'] ?? 0);

/* ── Add user ─────────────────────────────────────────────── */
if (isset($_POST['add_user'])) {
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    if ($name && $email && $pass && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $chk = $pdo->prepare("SELECT id FROM admin_users WHERE email=?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'That email is already in use.'];
        } else {
            $pdo->prepare("INSERT INTO admin_users (name, email, password) VALUES (?,?,?)")
                ->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])]);
            log_action($pdo, 'add_admin', "Added admin user: $name ($email)");
            $_SESSION['flash'] = ['type' => 'success', 'msg' => htmlspecialchars($name) . ' added successfully.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'All fields are required and email must be valid.'];
    }
    header('Location: users.php'); exit;
}

/* ── Edit user ────────────────────────────────────────────── */
if (isset($_POST['edit_user'])) {
    $id    = (int)$_POST['id'];
    $name  = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    if ($name && $email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $chk = $pdo->prepare("SELECT id FROM admin_users WHERE email=? AND id != ?");
        $chk->execute([$email, $id]);
        if ($chk->fetch()) {
            $_SESSION['flash'] = ['type' => 'error', 'msg' => 'That email is already used by another account.'];
        } else {
            if ($pass) {
                $pdo->prepare("UPDATE admin_users SET name=?, email=?, password=? WHERE id=?")
                    ->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]), $id]);
            } else {
                $pdo->prepare("UPDATE admin_users SET name=?, email=? WHERE id=?")
                    ->execute([$name, $email, $id]);
            }
            if ($id === $me) {
                $_SESSION['admin_name']  = $name;
                $_SESSION['admin_email'] = $email;
            }
            log_action($pdo, 'edit_admin', "Edited admin user: $name ($email)");
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Account updated successfully.'];
        }
    } else {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'Name and a valid email are required.'];
    }
    header('Location: users.php'); exit;
}

/* ── Delete user ──────────────────────────────────────────── */
if (isset($_GET['delete'])) {
    $did = (int)$_GET['delete'];
    if ($did === $me) {
        $_SESSION['flash'] = ['type' => 'error', 'msg' => 'You cannot delete your own account.'];
    } else {
        $delStmt = $pdo->prepare("SELECT name, email FROM admin_users WHERE id=?");
        $delStmt->execute([$did]);
        $delRow = $delStmt->fetch();
        $pdo->prepare("DELETE FROM admin_users WHERE id=?")->execute([$did]);
        log_action($pdo, 'delete_admin', "Removed admin user: " . ($delRow ? $delRow['name'] . ' (' . $delRow['email'] . ')' : "ID $did"));
        $_SESSION['flash'] = ['type' => 'info', 'msg' => 'Admin user removed.'];
    }
    header('Location: users.php'); exit;
}

$users = $pdo->query("SELECT * FROM admin_users ORDER BY created_at ASC")->fetchAll();

/* ── Flash ────────────────────────────────────────────────── */
$flash = null;
if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Users — GAMBIA 2026</title>
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
  .nav-link:hover{background:#e2eaf4;color:#1a2332;}
  .nav-link.danger:hover{background:#fee2e2;color:#dc2626;}

  /* Main */
  .main{max-width:900px;margin:0 auto;padding:36px 28px;}
  .page-title{font-size:22px;font-weight:700;color:#0a2540;margin-bottom:6px;}
  .page-sub{font-size:13px;color:#9aaabf;margin-bottom:28px;}

  /* Cards */
  .card{background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.06);overflow:hidden;margin-bottom:28px;}
  .card-header{padding:20px 24px;border-bottom:1px solid #f0f4f8;display:flex;align-items:center;gap:12px;}
  .card-title{font-size:16px;font-weight:700;color:#0a2540;}
  .card-body{padding:24px;}

  /* Form grid */
  .form-grid{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;align-items:end;}
  .field label{font-size:12px;font-weight:600;color:#4a6080;display:block;margin-bottom:5px;}
  .field input{width:100%;padding:10px 14px;border:1.5px solid #d1dce8;border-radius:8px;font-size:13px;font-family:inherit;outline:none;transition:border-color .15s;}
  .field input:focus{border-color:#0d6e8c;box-shadow:0 0 0 3px rgba(13,110,140,.1);}
  .btn{padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:none;transition:all .15s;}
  .btn-dark{background:#0a2540;color:#fff;}
  .btn-dark:hover{background:#0d6e8c;}

  /* Table */
  .tbl-wrap{overflow-x:auto;}
  table{width:100%;border-collapse:collapse;}
  thead th{background:#f8fbff;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#7a8fa8;padding:12px 20px;text-align:left;border-bottom:1px solid #e8f0f8;}
  tbody tr{border-bottom:1px solid #f0f4f8;transition:background .1s;}
  tbody tr:hover{background:#f8fbff;}
  tbody td{padding:14px 20px;font-size:13px;vertical-align:middle;}
  .td-name{font-weight:600;color:#0a2540;}
  .td-email{font-size:12px;color:#9aaabf;margin-top:2px;}

  .badge-me{display:inline-block;background:#dbeafe;color:#1e40af;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:6px;vertical-align:middle;}

  .act-btn{padding:5px 14px;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;border:none;transition:opacity .15s;text-decoration:none;display:inline-block;}
  .act-btn:hover{opacity:.75;}
  .btn-edit{background:#dbeafe;color:#1e40af;}
  .btn-del {background:#fee2e2;color:#991b1b;}

  /* Modal */
  .modal-bg{display:none;position:fixed;inset:0;background:rgba(10,20,40,.55);z-index:900;align-items:center;justify-content:center;padding:20px;}
  .modal-bg.open{display:flex;}
  .modal{background:#fff;border-radius:16px;padding:32px;width:100%;max-width:460px;box-shadow:0 20px 60px rgba(0,0,0,.25);}
  .modal h3{font-size:17px;font-weight:700;color:#0a2540;margin-bottom:20px;}
  .modal .field{margin-bottom:14px;}
  .modal-foot{display:flex;gap:10px;justify-content:flex-end;margin-top:20px;}
  .btn-cancel{background:#f0f4f8;color:#4a6080;}
  .btn-cancel:hover{background:#e2eaf4;}
  .pass-hint{font-size:11px;color:#9aaabf;margin-top:4px;}
</style>
</head>
<body>

<!-- Nav -->
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
  <div class="page-title">Manage Admin Users</div>
  <p class="page-sub">Add, edit, or remove accounts that can access this admin portal.</p>

  <!-- Add user form -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Add New Admin</span>
    </div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="add_user" value="1">
        <div class="form-grid">
          <div class="field">
            <label>Full Name</label>
            <input type="text" name="name" placeholder="e.g. John Mendy" required>
          </div>
          <div class="field">
            <label>Email Address</label>
            <input type="email" name="email" placeholder="john@ngocsocd.org" required>
          </div>
          <div class="field">
            <label>Password</label>
            <input type="password" name="password" placeholder="Set a strong password" required>
          </div>
          <button type="submit" class="btn btn-dark">Add User</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Users table -->
  <div class="card">
    <div class="card-header">
      <span class="card-title">Admin Accounts</span>
      <span style="font-size:13px;color:#9aaabf;background:#f0f4f8;padding:3px 10px;border-radius:20px;"><?= count($users) ?></span>
    </div>
    <div class="tbl-wrap">
      <table>
        <thead>
          <tr>
            <th>Name / Email</th>
            <th>Added</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="td-name">
                <?= htmlspecialchars($u['name']) ?>
                <?php if ((int)$u['id'] === $me): ?>
                  <span class="badge-me">You</span>
                <?php endif; ?>
              </div>
              <div class="td-email"><?= htmlspecialchars($u['email']) ?></div>
            </td>
            <td style="color:#9aaabf;font-size:12px;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
            <td>
              <div style="display:flex;gap:6px;">
                <button class="act-btn btn-edit"
                  onclick="openEdit(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>', '<?= addslashes($u['email']) ?>')">
                  Edit
                </button>
                <?php if ((int)$u['id'] !== $me): ?>
                <button class="act-btn btn-del" onclick="confirmDelete(<?= $u['id'] ?>, '<?= addslashes($u['name']) ?>')">
                  Delete
                </button>
                <?php else: ?>
                <span style="font-size:12px;color:#c8d8e8;padding:5px 6px;">—</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Edit modal -->
<div class="modal-bg" id="editModal">
  <div class="modal">
    <h3>Edit Admin Account</h3>
    <form method="POST" id="editForm">
      <input type="hidden" name="edit_user" value="1">
      <input type="hidden" name="id" id="edit_id">
      <div class="field">
        <label>Full Name</label>
        <input type="text" name="name" id="edit_name" required>
      </div>
      <div class="field">
        <label>Email Address</label>
        <input type="email" name="email" id="edit_email" required>
      </div>
      <div class="field">
        <label>New Password</label>
        <input type="password" name="password" id="edit_pass" placeholder="Leave blank to keep current password">
        <p class="pass-hint">Only fill this if you want to change the password.</p>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-cancel" onclick="closeEdit()">Cancel</button>
        <button type="submit" class="btn btn-dark">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden delete form -->
<form id="deleteForm" method="GET" style="display:none">
  <input type="hidden" name="delete" id="del_id">
</form>

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

function openEdit(id, name, email) {
  document.getElementById('edit_id').value    = id;
  document.getElementById('edit_name').value  = name;
  document.getElementById('edit_email').value = email;
  document.getElementById('edit_pass').value  = '';
  document.getElementById('editModal').classList.add('open');
}
function closeEdit() {
  document.getElementById('editModal').classList.remove('open');
}
document.getElementById('editModal').addEventListener('click', function(e) {
  if (e.target === this) closeEdit();
});

function confirmDelete(id, name) {
  Swal.fire({
    title: 'Remove ' + name + '?',
    text:  'They will no longer be able to log in.',
    icon:  'warning',
    showCancelButton:   true,
    confirmButtonText:  'Yes, remove',
    cancelButtonText:   'Cancel',
    confirmButtonColor: '#ef4444',
    cancelButtonColor:  '#6b7280',
    reverseButtons: true,
  }).then(function(r) {
    if (r.isConfirmed) {
      document.getElementById('del_id').value = id;
      document.getElementById('deleteForm').submit();
    }
  });
}

<?php if ($flash): ?>
document.addEventListener('DOMContentLoaded', function() {
  Swal.fire({
    toast: true, position: 'top-end',
    icon:  '<?= $flash['type'] ?>',
    title: '<?= addslashes($flash['msg']) ?>',
    showConfirmButton: false,
    timer: 4000, timerProgressBar: true,
  });
});
<?php endif; ?>
</script>
</body>
</html>
