<?php
session_start();
require_once 'session_guard.php';
if (!isset($_SESSION['admin'])) { header('Location: admin.php'); exit; }

require_once 'db.php';
require_once 'mail_config.php';
require_once 'mailer.php';
require_once 'logger.php';

// Ensure admin_notes column exists (added on first use)
try { $pdo->query("SELECT admin_notes FROM registrations LIMIT 1"); }
catch (PDOException) { $pdo->exec("ALTER TABLE registrations ADD COLUMN admin_notes TEXT NULL AFTER final_confirmation"); }

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: admin.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ?");
$stmt->execute([$id]);
$r = $stmt->fetch();
if (!$r) { header('Location: admin.php'); exit; }

// Flush redirect to browser immediately so admin doesn't wait for SMTP
function flush_and_continue(string $url): void {
    session_write_close();
    header('Location: ' . $url);
    header('Connection: close');
    header('Content-Length: 0');
    if (ob_get_level() > 0) ob_end_flush();
    flush();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    ignore_user_abort(true);
    set_time_limit(120);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $newStatus = $_POST['status'];
    $reason    = trim($_POST['reject_reason'] ?? '');
    $pdo->prepare("UPDATE registrations SET status=? WHERE id=?")->execute([$newStatus, $id]);
    $fresh = $pdo->prepare("SELECT * FROM registrations WHERE id=?");
    $fresh->execute([$id]);
    $row      = $fresh->fetch();
    $fullName = $row['first_name'] . ' ' . $row['last_name'];
    if ($newStatus === 'approved') {
        log_action($pdo, 'approve', "Approved registration for $fullName (ID: $id)");
        $_SESSION['flash'] = ['type' => 'success', 'msg' => htmlspecialchars($fullName) . ' approved. Confirmation email sent.'];
        flush_and_continue("view.php?id=$id");
        send_approval_email($row);
    } elseif ($newStatus === 'rejected') {
        log_action($pdo, 'reject', "Rejected registration for $fullName (ID: $id)");
        $_SESSION['flash'] = ['type' => 'info', 'msg' => htmlspecialchars($fullName) . ' rejected. Notification email sent.'];
        flush_and_continue("view.php?id=$id");
        send_rejection_email($row, $reason);
    } else {
        header("Location: view.php?id=$id");
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_notes'])) {
    $notes = trim($_POST['admin_notes'] ?? '');
    $pdo->prepare("UPDATE registrations SET admin_notes=? WHERE id=?")->execute([$notes ?: null, $id]);
    log_action($pdo, 'edit_notes', "Updated notes on registration #{$id}");
    header("Location: view.php?id=$id&notes_saved=1"); exit;
}

$ref    = 'GAM26-' . str_pad($r['id'], 5, '0', STR_PAD_LEFT);
$name   = htmlspecialchars(($r['title'] ? $r['title'] . ' ' : '') . $r['first_name'] . ' ' . $r['last_name']);
$status = $r['status'];

function val($v, $fallback = '—') {
    $v = trim($v ?? '');
    return $v !== '' ? htmlspecialchars($v) : "<span style='color:#c0ccd8;font-style:italic;'>{$fallback}</span>";
}
function yn($v) { return $v ? '<span style="color:#059669;font-weight:600;">Yes</span>' : '<span style="color:#dc2626;font-weight:600;">No</span>'; }
function docext($f) { return strtolower(pathinfo($f ?? '', PATHINFO_EXTENSION)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $ref ?> — <?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Inter',sans-serif;background:#f0f4f8;color:#1a2332;min-height:100vh;}

  /* ── Nav (matches admin.php) ────────────────── */
  .nav{
    background:#fff;border-bottom:1px solid #e8f0f8;
    height:64px;display:flex;align-items:center;padding:0 32px;gap:16px;
    position:sticky;top:0;z-index:200;box-shadow:0 1px 6px rgba(0,0,0,.06);
  }
  .nav-brand{display:flex;align-items:center;gap:12px;text-decoration:none;}
  .nav-brand-icon{
    width:38px;height:38px;
    border-radius:10px;display:flex;align-items:center;justify-content:center;
    flex-shrink:0;overflow:hidden;
  }
  .nav-brand-text{font-size:15px;font-weight:700;color:#0a2540;line-height:1.2;}
  .nav-brand-text small{display:block;font-size:11px;font-weight:500;color:#9aaabf;}
  .nav-sep{width:1px;height:28px;background:#e8f0f8;}
  .nav-ref{font-size:13px;font-weight:600;color:#0d6e8c;background:#eef6ff;padding:5px 12px;border-radius:20px;}
  .nav-spacer{flex:1;}
  .nav-btn{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;
    text-decoration:none;transition:all .15s;cursor:pointer;border:none;font-family:inherit;
  }
  .nav-btn-back{background:#f0f4f8;color:#0a2540;}
  .nav-btn-back:hover{background:#e2eaf4;}
  .nav-btn-logout{background:none;color:#9aaabf;}
  .nav-btn-logout:hover{background:#fee2e2;color:#dc2626;}

  /* ── Main ──────────────────────────────────── */
  .main{max-width:1000px;margin:0 auto;padding:32px 24px;display:flex;flex-direction:column;gap:24px;}

  /* ── Hero card ─────────────────────────────── */
  .hero{
    background:#fff;border-radius:16px;
    box-shadow:0 2px 16px rgba(0,0,0,.07);
    overflow:hidden;
  }
  .hero-body{padding:28px;display:flex;gap:28px;align-items:flex-start;}
  .hero-avatar{
    width:160px;height:200px;
    border-radius:12px;
    border:3px solid #e8f0f8;
    box-shadow:0 4px 16px rgba(0,0,0,.1);
    object-fit:cover;background:#e8f0f8;
    flex-shrink:0;
  }
  .hero-avatar-ph{
    width:160px;height:200px;border-radius:12px;
    border:3px solid #e8f0f8;box-shadow:0 4px 16px rgba(0,0,0,.1);
    background:linear-gradient(135deg,#d1dce8,#e8f0f8);
    display:flex;align-items:center;justify-content:center;
    font-size:56px;flex-shrink:0;color:#b8cfe0;
  }
  .hero-info{flex:1;padding-top:4px;min-width:0;}
  .hero-name{font-size:24px;font-weight:700;color:#0a2540;line-height:1.2;margin-bottom:4px;}
  .hero-org{font-size:14px;color:#4a6080;margin-bottom:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
  .hero-meta{display:flex;gap:10px;flex-wrap:wrap;align-items:center;}
  .hero-ref{font-size:12px;font-weight:700;color:#0d6e8c;background:#eef6ff;padding:4px 12px;border-radius:20px;}
  .hero-date{font-size:12px;color:#9aaabf;}

  .hero-actions{padding:0 28px 24px;border-top:1px solid #f0f4f8;margin-top:20px;padding-top:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
  .badge{
    display:inline-flex;align-items:center;gap:6px;
    padding:6px 14px;border-radius:20px;font-size:12px;font-weight:700;letter-spacing:.03em;
  }
  .badge::before{content:'';width:7px;height:7px;border-radius:50%;background:currentColor;opacity:.55;flex-shrink:0;}
  .badge.pending {background:#fff3cd;color:#92600a;font-size:13px;padding:7px 16px;}
  .badge.approved{background:#dcfce7;color:#166534;font-size:13px;padding:7px 16px;}
  .badge.rejected{background:#fee2e2;color:#991b1b;font-size:13px;padding:7px 16px;}
  .btn-action{
    padding:9px 20px;border-radius:8px;font-size:13px;font-weight:600;
    cursor:pointer;border:1.5px solid transparent;font-family:inherit;
    display:inline-flex;align-items:center;gap:7px;transition:all .15s;
  }
  .btn-approve{background:#dcfce7;color:#166534;border-color:#bbf7d0;}
  .btn-approve:hover{background:#bbf7d0;}
  .btn-reject {background:#fee2e2;color:#991b1b;border-color:#fecaca;}
  .btn-reject:hover{background:#fecaca;}

  /* ── Section card ──────────────────────────── */
  .section{background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.06);overflow:hidden;}
  .section-hd{
    padding:16px 24px;border-bottom:1px solid #f0f4f8;
    display:flex;align-items:center;gap:10px;
  }
  .section-hd-icon{
    width:30px;height:30px;
    display:flex;align-items:center;justify-content:center;font-size:17px;
    flex-shrink:0;
  }
  .section-hd h3{font-size:14px;font-weight:700;color:#0a2540;}
  .section-body{padding:8px 24px 20px;}

  /* ── Field rows ────────────────────────────── */
  .fields{display:grid;grid-template-columns:1fr 1fr;gap:0;}
  .fields.single{grid-template-columns:1fr;}
  .f{padding:12px 0;border-bottom:1px solid #f0f4f8;display:flex;flex-direction:column;gap:3px;}
  .f:nth-last-child(-n+2):not(:nth-child(odd)+*){border-bottom:none;}
  .fields.single .f:last-child{border-bottom:none;}
  .f-lbl{font-size:11px;font-weight:600;color:#9aaabf;text-transform:uppercase;letter-spacing:.07em;}
  .f-val{font-size:14px;color:#1a2332;font-weight:500;line-height:1.4;}

  /* ── Documents ─────────────────────────────── */
  .doc-grid{padding:20px 24px;display:grid;grid-template-columns:1fr 1fr;gap:16px;}
  .doc-card{border:1.5px solid #e2eaf4;border-radius:12px;overflow:hidden;background:#fafcff;}
  .doc-card-hd{
    padding:12px 16px;background:#f8fbff;border-bottom:1px solid #e8f0f8;
    display:flex;align-items:center;gap:8px;
  }
  .doc-card-hd svg{width:16px;height:16px;stroke:#0d6e8c;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;flex-shrink:0;}
  .doc-card-hd span{font-size:12px;font-weight:700;color:#0a2540;}
  .doc-card-body{padding:14px;}
  .doc-img{width:100%;max-height:200px;object-fit:contain;border-radius:8px;background:#f0f4f8;display:block;margin-bottom:10px;}
  .doc-pdf{
    display:flex;align-items:center;gap:10px;
    padding:12px 14px;background:#f0f4f8;border-radius:8px;margin-bottom:10px;
  }
  .doc-pdf-icon{font-size:28px;flex-shrink:0;}
  .doc-pdf-name{font-size:12px;font-weight:600;color:#0a2540;word-break:break-all;}
  .doc-pdf-sub{font-size:11px;color:#9aaabf;}
  .doc-dl{
    display:inline-flex;align-items:center;gap:6px;
    padding:8px 14px;background:#0a2540;color:#fff;
    border-radius:8px;font-size:12px;font-weight:600;text-decoration:none;
    transition:background .15s;
  }
  .doc-dl:hover{background:#0d6e8c;}
  .doc-dl svg{width:13px;height:13px;stroke:#fff;fill:none;stroke-width:2.5;stroke-linecap:round;stroke-linejoin:round;}
  .doc-missing{
    padding:20px;text-align:center;
    color:#c0ccd8;font-size:13px;font-style:italic;
  }

  @media(max-width:700px){
    .main{padding:16px 12px;}
    .fields{grid-template-columns:1fr;}
    .doc-grid{grid-template-columns:1fr;}
    .hero-body{flex-direction:column;align-items:center;text-align:center;}
    .hero-meta{justify-content:center;}
    .hero-actions{justify-content:center;}
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

<!-- Hidden status form -->
<form id="statusForm" method="POST" style="display:none">
  <input type="hidden" name="status"         id="sf_status">
  <input type="hidden" name="reject_reason"  id="sf_reason">
  <input type="hidden" name="update_status"  value="1">
</form>

<!-- ── Nav ────────────────────────────────────────────── -->
<nav class="nav">
  <a class="nav-brand" href="admin.php">
    <div class="nav-brand-icon"><img src="asset/organizationLOGO.png" alt="GAMBIA 2026" style="width:38px;height:38px;object-fit:contain;"></div>
    <div class="nav-brand-text">
      GAMBIA 2026
      <small>Registration Admin</small>
    </div>
  </a>
  <div class="nav-sep"></div>
  <span class="nav-ref"><?= $ref ?></span>
  <div class="nav-spacer"></div>
  <a href="admin.php" class="nav-btn nav-btn-back">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    Back to list
  </a>
  <a href="#" onclick="confirmLogout();return false;" class="nav-btn nav-btn-logout">Sign out</a>
</nav>

<!-- ── Main ───────────────────────────────────────────── -->
<div class="main">

  <!-- ── Hero ─────────────────────────────────────────── -->
  <div class="hero">
    <div class="hero-body">
      <?php if ($r['picture']): ?>
        <img class="hero-avatar" src="uploads/<?= htmlspecialchars($r['picture']) ?>" alt="Photo">
      <?php else: ?>
        <div class="hero-avatar-ph">👤</div>
      <?php endif; ?>
      <div class="hero-info">
        <div class="hero-name"><?= $name ?></div>
        <div class="hero-org"><?= htmlspecialchars($r['organisation_name']) ?></div>
        <div class="hero-meta">
          <span class="hero-ref"><?= $ref ?></span>
          <span class="hero-date">Submitted <?= date('d M Y, H:i', strtotime($r['submitted_at'])) ?></span>
        </div>
      </div>
    </div>
    <div class="hero-actions">
      <span class="badge <?= $status ?>"><?= ucfirst($status) ?></span>
      <?php if ($status !== 'approved'): ?>
        <button type="button" class="btn-action btn-approve" onclick="changeStatus('approved','Approve')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
          Approve
        </button>
      <?php endif; ?>
      <?php if ($status !== 'rejected'): ?>
        <button type="button" class="btn-action btn-reject" onclick="changeStatus('rejected','Reject')">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
          Reject
        </button>
      <?php endif; ?>
      <?php if ($status === 'approved'): ?>
        <?php $badgeToken = hash_hmac('sha256', $ref, BADGE_SECRET); ?>
        <a href="badge.php?ref=<?= urlencode($ref) ?>&token=<?= urlencode($badgeToken) ?>" target="_blank" class="btn-action" style="background:#eef6ff;color:#0d6e8c;border-color:#b3d9f0;text-decoration:none;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 0 0-4 0v2"/><line x1="12" y1="12" x2="12" y2="16"/><line x1="10" y1="14" x2="14" y2="14"/></svg>
          View Badge
        </a>
        <a href="nomination_letter_pdf.php?id=<?= $id ?>" target="_blank" class="btn-action" style="background:#f0fdf4;color:#166534;border-color:#bbf7d0;text-decoration:none;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          Nomination PDF
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Personal Data ─────────────────────────────────── -->
  <div class="section">
    <div class="section-hd">
      <div class="section-hd-icon">👤</div>
      <h3>Personal Data</h3>
    </div>
    <div class="section-body">
      <div class="fields">
        <div class="f"><span class="f-lbl">First Name</span><span class="f-val"><?= val($r['first_name']) ?></span></div>
        <div class="f"><span class="f-lbl">Last Name</span><span class="f-val"><?= val($r['last_name']) ?></span></div>
        <div class="f"><span class="f-lbl">Title</span><span class="f-val"><?= val($r['title']) ?></span></div>
        <div class="f"><span class="f-lbl">Gender</span><span class="f-val"><?= val($r['gender']) ?></span></div>
        <div class="f"><span class="f-lbl">Email Address</span><span class="f-val"><a href="mailto:<?= htmlspecialchars($r['email']) ?>" style="color:#0d6e8c;text-decoration:none;"><?= val($r['email']) ?></a></span></div>
        <div class="f"><span class="f-lbl">Date of Birth</span><span class="f-val"><?= val($r['birth_date']) ?></span></div>
        <div class="f"><span class="f-lbl">Position</span><span class="f-val"><?= val($r['position']) ?></span></div>
        <div class="f"><span class="f-lbl">Institution</span><span class="f-val"><?= val($r['institution']) ?></span></div>
        <div class="f"><span class="f-lbl">Postal Address</span><span class="f-val"><?= val($r['home_address']) ?></span></div>
      </div>
    </div>
  </div>

  <!-- ── Representation ────────────────────────────────── -->
  <div class="section">
    <div class="section-hd">
      <div class="section-hd-icon">🏛</div>
      <h3>Representation</h3>
    </div>
    <div class="section-body">
      <div class="fields">
        <div class="f"><span class="f-lbl">Representation Type</span><span class="f-val"><?= val($r['representation_type']) ?></span></div>
        <div class="f"><span class="f-lbl">Organisation Name</span><span class="f-val"><?= val($r['organisation_name']) ?></span></div>
      </div>
    </div>
  </div>

  <!-- ── Visa & Passport ───────────────────────────────── -->
  <div class="section">
    <div class="section-hd">
      <div class="section-hd-icon">📄</div>
      <h3>Visa &amp; Passport Information</h3>
    </div>
    <div class="section-body">
      <div class="fields">
        <div class="f"><span class="f-lbl">Passport Nationality</span><span class="f-val"><?= val($r['passport_nationality']) ?></span></div>
        <div class="f"><span class="f-lbl">Passport Number</span><span class="f-val" style="font-family:monospace;letter-spacing:.05em;"><?= val($r['passport_number']) ?></span></div>
        <div class="f"><span class="f-lbl">Passport Expiration</span><span class="f-val"><?= val($r['passport_expiration']) ?></span></div>
      </div>
    </div>
  </div>

  <!-- ── Accommodation & Travel ────────────────────────── -->
  <div class="section">
    <div class="section-hd">
      <div class="section-hd-icon">🏨</div>
      <h3>Accommodation &amp; Travel</h3>
    </div>
    <div class="section-body">
      <div class="fields">
        <div class="f"><span class="f-lbl">Arrival Date</span><span class="f-val"><?= val($r['arrival_date']) ?></span></div>
        <div class="f"><span class="f-lbl">Departure Date</span><span class="f-val"><?= val($r['departure_date']) ?></span></div>
        <div class="f"><span class="f-lbl">Contact Number</span><span class="f-val"><?= val($r['contact_number']) ?></span></div>
      </div>
      <div class="fields single" style="margin-top:0;">
        <div class="f"><span class="f-lbl">Address in The Gambia</span><span class="f-val"><?= val($r['address_in_country']) ?></span></div>
      </div>
    </div>
  </div>

  <!-- ── Declarations ──────────────────────────────────── -->
  <div class="section">
    <div class="section-hd">
      <div class="section-hd-icon">✅</div>
      <h3>Declarations &amp; Consent</h3>
    </div>
    <div class="section-body">
      <div class="fields">
        <div class="f"><span class="f-lbl">Age 18 or older</span><span class="f-val"><?= yn($r['is_18_or_older']) ?></span></div>
        <div class="f"><span class="f-lbl">Framework Document Endorsement</span><span class="f-val"><?= yn($r['code_of_conduct']) ?></span></div>
        <div class="f"><span class="f-lbl">Data Privacy Agreement</span><span class="f-val"><?= yn($r['data_privacy']) ?></span></div>
        <div class="f"><span class="f-lbl">Declaration — Section A</span><span class="f-val"><?= yn($r['terms_conditions']) ?></span></div>
        <div class="f"><span class="f-lbl">Undertakings — Section B</span><span class="f-val"><?= yn($r['undertakings'] ?? 0) ?></span></div>
        <div class="f"><span class="f-lbl">Final Confirmation</span><span class="f-val"><?= yn($r['final_confirmation'] ?? 0) ?></span></div>
        <div class="f"><span class="f-lbl">IP Address</span><span class="f-val" style="font-family:monospace;font-size:13px;"><?= val($r['ip_address']) ?></span></div>
        <div class="f"><span class="f-lbl">Submitted At</span><span class="f-val"><?= val(date('d M Y, H:i', strtotime($r['submitted_at']))) ?></span></div>
      </div>
    </div>
  </div>

  <!-- ── Uploaded Documents ────────────────────────────── -->
  <div class="section">
    <div class="section-hd">
      <div class="section-hd-icon">📁</div>
      <h3>Uploaded Documents</h3>
    </div>
    <div class="doc-grid">

      <!-- Passport scan -->
      <div class="doc-card">
        <div class="doc-card-hd">
          <svg viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="2" ry="2"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
          <span>Passport Scan</span>
        </div>
        <div class="doc-card-body">
          <?php if ($r['passport_file']): ?>
            <?php $ext = docext($r['passport_file']); ?>
            <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
              <img class="doc-img" src="uploads/<?= htmlspecialchars($r['passport_file']) ?>" alt="Passport">
            <?php else: ?>
              <div class="doc-pdf">
                <span class="doc-pdf-icon">📄</span>
                <div>
                  <div class="doc-pdf-name"><?= htmlspecialchars(basename($r['passport_file'])) ?></div>
                  <div class="doc-pdf-sub"><?= strtoupper($ext) ?> document</div>
                </div>
              </div>
            <?php endif; ?>
            <a class="doc-dl" href="uploads/<?= htmlspecialchars($r['passport_file']) ?>" target="_blank">
              <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Download
            </a>
          <?php else: ?>
            <div class="doc-missing">No file uploaded.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Nomination Letter -->
      <div class="doc-card">
        <div class="doc-card-hd">
          <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span>Nomination Letter</span>
        </div>
        <div class="doc-card-body">
          <?php if ($r['nomination_letter']): ?>
            <?php $ext = docext($r['nomination_letter']); ?>
            <?php if (in_array($ext, ['jpg','jpeg','png'])): ?>
              <img class="doc-img" src="uploads/<?= htmlspecialchars($r['nomination_letter']) ?>" alt="Nomination Letter">
            <?php else: ?>
              <div class="doc-pdf">
                <span class="doc-pdf-icon"><?= in_array($ext, ['doc','docx']) ? '📝' : '📋' ?></span>
                <div>
                  <div class="doc-pdf-name"><?= htmlspecialchars(basename($r['nomination_letter'])) ?></div>
                  <div class="doc-pdf-sub"><?= strtoupper($ext) ?> document</div>
                </div>
              </div>
            <?php endif; ?>
            <a class="doc-dl" href="uploads/<?= htmlspecialchars($r['nomination_letter']) ?>" target="_blank">
              <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              Download
            </a>
          <?php else: ?>
            <div class="doc-missing">No file uploaded.</div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>

  <!-- Admin Notes -->
  <div style="background:#fff;border-radius:16px;box-shadow:0 2px 16px rgba(0,0,0,.06);padding:28px 32px;margin-top:28px;">
    <h3 style="font-size:15px;font-weight:700;color:#0a2540;margin-bottom:14px;display:flex;align-items:center;gap:8px;">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Admin Notes
    </h3>
    <?php if (isset($_GET['notes_saved'])): ?>
      <div style="background:#dcfce7;border:1px solid #86efac;border-radius:8px;padding:10px 14px;font-size:13px;color:#166534;margin-bottom:14px;">Notes saved successfully.</div>
    <?php endif; ?>
    <form method="POST">
      <textarea name="admin_notes" rows="5" style="width:100%;padding:12px 14px;border:1.5px solid #d1dce8;border-radius:10px;font-family:inherit;font-size:14px;resize:vertical;outline:none;line-height:1.6;color:#1a2332;" placeholder="Internal notes (not visible to the applicant)…"><?= htmlspecialchars($r['admin_notes'] ?? '') ?></textarea>
      <div style="margin-top:10px;display:flex;justify-content:flex-end;">
        <button type="submit" name="save_notes" value="1" style="background:#0a2540;color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:14px;font-weight:600;cursor:pointer;transition:background .2s;" onmouseover="this.style.background='#0d6e8c'" onmouseout="this.style.background='#0a2540'">Save Notes</button>
      </div>
    </form>
  </div>

</div><!-- /.main -->

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

<?php if (!empty($_SESSION['flash'])):
  $flash = $_SESSION['flash'];
  unset($_SESSION['flash']);
?>
document.addEventListener('DOMContentLoaded', function() {
  Swal.fire({
    toast:             true,
    position:          'top-end',
    icon:              '<?= $flash['type'] ?>',
    title:             '<?= addslashes($flash['msg']) ?>',
    showConfirmButton: false,
    timer:             4500,
    timerProgressBar:  true,
  });
});
<?php endif; ?>

function changeStatus(status, label) {
  var isReject = status === 'rejected';
  Swal.fire({
    title: label + ' this registration?',
    html: 'Delegate: <strong><?= addslashes(htmlspecialchars($r['first_name'] . ' ' . $r['last_name'])) ?></strong>'
        + (isReject ? '<br><br><textarea id="swal-reason" placeholder="Reason for rejection (sent to applicant)" rows="3" style="width:100%;margin-top:8px;padding:8px;border:1.5px solid #d1dce8;border-radius:6px;font-size:13px;font-family:inherit;resize:vertical;"></textarea>' : ''),
    icon: isReject ? 'warning' : 'question',
    showCancelButton:   true,
    confirmButtonText:  'Yes, ' + label.toLowerCase(),
    cancelButtonText:   'Cancel',
    confirmButtonColor: isReject ? '#ef4444' : '#059669',
    cancelButtonColor:  '#6b7280',
    reverseButtons: true,
    preConfirm: function() {
      if (isReject) {
        var reason = (document.getElementById('swal-reason').value || '').trim();
        if (!reason) { Swal.showValidationMessage('Please enter a reason for rejection.'); return false; }
        return reason;
      }
      return '';
    }
  }).then(function(result) {
    if (!result.isConfirmed) return;
    document.getElementById('sf_status').value = status;
    document.getElementById('sf_reason').value = result.value || '';
    document.querySelectorAll('.btn-action').forEach(function(b) { b.disabled = true; });

    document.getElementById('proc-msg').textContent = isReject ? 'Rejecting registration…' : 'Approving registration…';
    document.getElementById('proc-overlay').style.display = 'flex';
    requestAnimationFrame(function() {
      requestAnimationFrame(function() {
        document.getElementById('statusForm').submit();
      });
    });
  });
}
</script>
</body>
</html>
