<?php
require_once 'db.php';
require_once 'mail_config.php';

$ref   = trim($_GET['ref']   ?? '');
$token = trim($_GET['token'] ?? '');

if (!$ref || !$token || !hash_equals(hash_hmac('sha256', $ref, BADGE_SECRET), $token)) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:60px;text-align:center;color:#dc2626;"><h2>Invalid or expired badge link.</h2><p>Please use the link provided in your approval email.</p></body></html>';
    exit;
}

$id = (int)ltrim(substr($ref, 6), '0');
$stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? AND status = 'approved' LIMIT 1");
$stmt->execute([$id]);
$r = $stmt->fetch();

if (!$r) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:60px;text-align:center;color:#dc2626;"><h2>Badge not found.</h2><p>This reference is not approved or does not exist.</p></body></html>';
    exit;
}

$firstName = htmlspecialchars($r['first_name']);
$lastName  = htmlspecialchars($r['last_name']);
$fullName  = htmlspecialchars(($r['title'] ? $r['title'] . ' ' : '') . $r['first_name'] . ' ' . $r['last_name']);
$org       = htmlspecialchars($r['organisation_name']);
$type      = htmlspecialchars($r['representation_type']);
$refH      = htmlspecialchars($ref);
// Validate filename — prevent path traversal
$picFile   = $r['picture'] ?? '';
$picFile   = (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $picFile) && strpos($picFile, '..') === false) ? $picFile : '';
$photoPath = $picFile ? 'uploads/' . $picFile : '';
$photoExts = ['jpg','jpeg','png','gif','webp'];
$hasPhoto  = $photoPath && in_array(strtolower(pathinfo($picFile, PATHINFO_EXTENSION)), $photoExts) && file_exists($photoPath);

$qrData = urlencode($ref);
$qrUrl  = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=6&data={$qrData}";

// Initials fallback if no photo
$initials = strtoupper(substr($r['first_name'], 0, 1) . substr($r['last_name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Accreditation Badge — <?= $fullName ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

  body {
    font-family:'Inter',sans-serif;
    background: linear-gradient(135deg,#0a2540 0%,#1a4a6e 50%,#0a2540 100%);
    min-height:100vh;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:40px 20px;
    gap:28px;
  }

  /* ── Screen controls ───────────────────────────── */
  .controls {
    text-align:center;
  }
  .controls p {
    color:rgba(255,255,255,.7);
    font-size:13px;
    margin-bottom:14px;
  }
  .btn-print {
    background:#c9a84c;
    color:#0a2540;
    border:none;
    border-radius:10px;
    padding:12px 32px;
    font-size:14px;
    font-weight:700;
    cursor:pointer;
    font-family:inherit;
    letter-spacing:.02em;
    transition:background .2s;
  }
  .btn-print:hover { background:#e0be6a; }

  /* ── Badge card — fixed 86×118mm, never grows ─── */
  .badge {
    width: 86mm;
    height: 118mm;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0,0,0,.4);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    flex-shrink: 0;
  }

  /* Lanyard hole */
  .badge::before {
    content:'';
    position:absolute;
    top: -1px; left: 50%;
    transform: translateX(-50%);
    width: 11mm; height: 5mm;
    background: linear-gradient(135deg,#0a2540 0%,#1a4a6e 100%);
    border-radius: 0 0 7px 7px;
    z-index: 10;
  }
  .badge::after {
    content:'';
    position:absolute;
    top: 3mm; left: 50%;
    transform: translateX(-50%);
    width: 4.5mm; height: 4.5mm;
    border-radius: 50%;
    background: #fff;
    box-shadow: inset 0 1px 3px rgba(0,0,0,.3);
    z-index: 11;
  }

  /* ── Header ───────────────────────────────────── */
  .badge-header {
    background: #ffffff;
    border-bottom: 2.5px solid #0a2540;
    padding: 9mm 3mm 3mm;
    position: relative;
    flex-shrink: 0;
  }
  .badge-header-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2mm;
  }
  .badge-header-logo {
    width: 11mm;
    height: 11mm;
    object-fit: contain;
    flex-shrink: 0;
  }
  .badge-header-center {
    flex: 1;
    text-align: center;
  }
  .badge-org-label {
    font-size: 5pt;
    font-weight: 600;
    color: #6b7280;
    letter-spacing: .12em;
    text-transform: uppercase;
    margin-bottom: 1mm;
  }
  .badge-event-name {
    font-size: 9pt;
    font-weight: 800;
    color: #0a2540;
    letter-spacing: .04em;
    line-height: 1.1;
  }
  .badge-event-sub {
    font-size: 5pt;
    color: #6b7280;
    margin-top: 1mm;
    letter-spacing: .05em;
  }
  .badge-gold-bar {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 2.5px;
    background: linear-gradient(90deg, transparent, #c9a84c, transparent);
  }

  /* ── Photo — 28mm ────────────────────────────── */
  .badge-photo-wrap {
    background: linear-gradient(180deg,#0f2f50 0%,#1a2332 100%);
    height: 28mm;
    flex-shrink: 0;
    display: flex;
    align-items: flex-end;
    justify-content: center;
    padding: 3mm 4mm 0;
    position: relative;
    overflow: hidden;
  }
  .badge-photo-wrap::before {
    content:'';
    position:absolute;
    bottom: 0; left: 0;
    width: 15mm; height: 15mm;
    background: #c9a84c;
    clip-path: polygon(0 100%, 100% 100%, 0 0);
    opacity: .65;
  }
  .badge-photo {
    width: 22mm;
    height: 26mm;
    object-fit: cover;
    border-radius: 6px 6px 0 0;
    border: 2px solid rgba(255,255,255,.2);
    position: relative;
    z-index: 2;
    display: block;
    flex-shrink: 0;
  }
  .badge-initials {
    width: 22mm;
    height: 26mm;
    background: linear-gradient(135deg,#1e4d78,#0a2540);
    border-radius: 6px 6px 0 0;
    border: 2px solid rgba(201,168,76,.35);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18pt;
    font-weight: 800;
    color: #c9a84c;
    position: relative;
    z-index: 2;
    flex-shrink: 0;
  }

  /* ── Name band — 18mm ────────────────────────── */
  .badge-name-band {
    background: linear-gradient(135deg,#0a2540 0%,#1e4d78 100%);
    padding: 2.5mm 4mm 2mm;
    text-align: center;
    flex-shrink: 0;
  }
  .badge-name-first {
    font-size: 9.5pt;
    font-weight: 700;
    color: rgba(255,255,255,.82);
    letter-spacing: .02em;
    line-height: 1.15;
  }
  .badge-name-last {
    font-size: 14pt;
    font-weight: 900;
    color: #fff;
    letter-spacing: .02em;
    line-height: 1.1;
  }
  .badge-type-pill {
    display: inline-block;
    background: #c9a84c;
    color: #0a2540;
    font-size: 5.5pt;
    font-weight: 800;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: 1.5px 7px;
    border-radius: 20px;
    margin-top: 1.5mm;
  }

  /* ── Details — flex:1 fills remaining space ──── */
  .badge-body {
    flex: 1;
    padding: 2.5mm 4mm 2mm;
    background: #f7f4ee;
    position: relative;
    overflow: hidden;
  }
  .badge-watermark {
    position: absolute;
    bottom: 1mm; right: 2mm;
    font-size: 22pt;
    font-weight: 900;
    color: rgba(10,37,64,.06);
    letter-spacing: .05em;
    pointer-events: none;
    user-select: none;
    line-height: 1;
  }
  .badge-ref-label {
    font-size: 5.5pt;
    font-weight: 700;
    color: #9aaabf;
    letter-spacing: .12em;
    text-transform: uppercase;
    margin-bottom: 0.8mm;
  }
  .badge-ref {
    font-size: 9pt;
    font-weight: 800;
    color: #0a2540;
    letter-spacing: .06em;
    margin-bottom: 2.5mm;
  }
  .badge-detail-row {
    display: flex;
    gap: 1.5mm;
    margin-bottom: 1.5mm;
    align-items: flex-start;
  }
  .badge-detail-row svg { flex-shrink: 0; margin-top: 1px; color: #c9a84c; }
  .badge-detail-text { font-size: 6.5pt; color: #374151; line-height: 1.35; }

  /* ── QR footer — 17mm ────────────────────────── */
  .badge-footer {
    background: linear-gradient(135deg,#0a2540 0%,#1e4d78 100%);
    padding: 2.5mm 4mm 2.5mm;
    display: flex;
    align-items: center;
    gap: 3mm;
    flex-shrink: 0;
  }
  .badge-qr-wrap {
    background: #fff;
    border-radius: 5px;
    padding: 1.5mm;
    flex-shrink: 0;
  }
  .badge-qr-wrap img { width: 13mm; height: 13mm; display: block; }
  .badge-footer-text { flex: 1; }
  .badge-footer-event {
    font-size: 6pt; font-weight: 700;
    color: #c9a84c; letter-spacing: .08em; text-transform: uppercase;
  }
  .badge-footer-date {
    font-size: 5.5pt; color: rgba(255,255,255,.6);
    margin-top: 0.8mm; line-height: 1.4;
  }
  .badge-footer-scan {
    font-size: 5pt; color: rgba(255,255,255,.35); margin-top: 0.8mm;
  }

  /* ── Print — locks to exactly one page ──────── */
  @media print {
    @page {
      size: 86mm 118mm;
      margin: 0;
    }
    html, body {
      width: 86mm;
      height: 118mm;
      overflow: hidden;
      background: #fff;
      padding: 0;
      margin: 0;
    }
    .controls { display: none !important; }
    .badge {
      width: 86mm;
      height: 118mm;
      border-radius: 0;
      box-shadow: none;
      page-break-inside: avoid;
      break-inside: avoid;
    }
  }
</style>
</head>
<body>

<div class="controls">
  <p>Your GAMBIA 2026 accreditation badge is ready.</p>
  <button class="btn-print" onclick="window.print()">&#128438;&nbsp; Print / Save as PDF</button>
</div>

<div class="badge">

  <!-- Header -->
  <div class="badge-header">
    <div class="badge-header-row">
      <img src="asset/organizationLOGO.png" class="badge-header-logo" alt="Organization Logo">
      <div class="badge-header-center">
        <div class="badge-org-label">OFFICIAL DELEGATE BADGE</div>
        <div class="badge-event-name">GAMBIA 2026</div>
        <div class="badge-event-sub">NGO SUMMIT &bull; BANJUL, THE GAMBIA</div>
      </div>
      <img src="asset/GambiaNationalSeal.png" class="badge-header-logo" alt="Gambia National Seal">
    </div>
    <div class="badge-gold-bar"></div>
  </div>

  <!-- Photo -->
  <div class="badge-photo-wrap">
    <?php if ($hasPhoto): ?>
      <img src="<?= htmlspecialchars($photoPath) ?>" alt="Photo" class="badge-photo">
    <?php else: ?>
      <div class="badge-initials"><?= $initials ?></div>
    <?php endif; ?>
  </div>

  <!-- Name band -->
  <div class="badge-name-band">
    <div class="badge-name-first"><?= $firstName ?></div>
    <div class="badge-name-last"><?= $lastName ?></div>
    <div><span class="badge-type-pill"><?= $type ?></span></div>
  </div>

  <!-- Details -->
  <div class="badge-body">
    <div class="badge-ref-label">Reference No.</div>
    <div class="badge-ref"><?= $refH ?></div>

    <div class="badge-detail-row">
      <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
      <div class="badge-detail-text"><?= $org ?></div>
    </div>

    <div class="badge-detail-row">
      <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      <div class="badge-detail-text">12–16 October 2026</div>
    </div>

    <div class="badge-watermark">GAM26</div>
  </div>

  <!-- QR footer -->
  <div class="badge-footer">
    <div class="badge-qr-wrap">
      <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code">
    </div>
    <div class="badge-footer-text">
      <div class="badge-footer-event">GAMBIA 2026</div>
      <div class="badge-footer-date">Banjul, Republic of<br>The Gambia</div>
      <div class="badge-footer-scan">Scan to verify accreditation</div>
    </div>
  </div>

</div>

</body>
</html>
