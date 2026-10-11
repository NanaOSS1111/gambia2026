<?php
/**
 * Badge Rendering Template for Batch and Bulk Operations
 * Variables passed in from bulk_badges.php:
 *  - $delegates (array of DB rows)
 *  - $totalMatching (int)
 *  - $layout ('badge' or 'a4')
 *  - $typesList (array of unique representation types)
 *  - $typeFilter (string)
 *  - $batchLimit (int)
 *  - $batchOffset (int)
 *  - $isExport (bool)
 */
$loadedCount = count($delegates);

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'gambia2026.ngocsocd.org');
$imgBase = !empty($isExport) ? ($baseUrl . '/') : '';

$orgLogoSrc = $imgBase . 'asset/organizationLOGO.png';
$natSealSrc = $imgBase . 'asset/GambiaNationalSeal.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>GAMBIA 2026 — Bulk Accreditation Badges (<?= $loadedCount ?> delegates)</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}

  body {
    font-family:'Inter',sans-serif;
    background: #0a2540;
    color: #1a2332;
    min-height: 100vh;
  }

  /* ── Sticky Toolbar (Screen only) ────────────────────────── */
  .bulk-top-bar {
    position: sticky;
    top: 0;
    z-index: 1000;
    background: #ffffff;
    border-bottom: 2px solid #0d6e8c;
    box-shadow: 0 4px 20px rgba(0,0,0,.25);
    padding: 12px 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
  }
  .bt-left {
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .bt-back {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f0f4f8;
    color: #0a2540;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: background .15s;
  }
  .bt-back:hover { background: #e2eaf4; }
  .bt-title {
    font-size: 15px;
    font-weight: 800;
    color: #0a2540;
  }
  .bt-badge-count {
    background: #dcfce7;
    color: #15803d;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
  }
  .bt-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  .bt-select {
    padding: 8px 12px;
    border: 1.5px solid #d1dce8;
    border-radius: 8px;
    font-size: 13px;
    font-family: inherit;
    background: #fff;
    color: #1a2332;
    cursor: pointer;
  }
  .btn-print-all {
    background: #c9a84c;
    color: #0a2540;
    border: none;
    border-radius: 8px;
    padding: 10px 24px;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
    font-family: inherit;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 2px 10px rgba(201,168,76,.35);
    transition: all .2s;
  }
  .btn-print-all:hover {
    background: #e0be6a;
    transform: translateY(-1px);
    box-shadow: 0 4px 14px rgba(201,168,76,.5);
  }
  .btn-zip {
    background: #0d6e8c;
    color: #ffffff;
    border: none;
    border-radius: 8px;
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: background .15s;
  }
  .btn-zip:hover { background: #0a5269; }

  /* ── Canvas Display Area ─────────────────────────────────── */
  .badge-container {
    padding: 40px 20px 80px;
    display: flex;
    flex-wrap: wrap;
    gap: 30px;
    justify-content: center;
    align-items: flex-start;
  }

  /* ── Single Badge Card (86mm × 118mm) ────────────────────── */
  .badge {
    width: 86mm;
    height: 118mm;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 16px 40px rgba(0,0,0,.45);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    position: relative;
    flex-shrink: 0;
    page-break-inside: avoid;
    break-inside: avoid;
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

  /* ── Header ─────────────────────────────────────────────── */
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
  .badge-header-logo-org {
    width: 27mm;
    height: 13.5mm;
    object-fit: contain;
    object-position: left center;
    flex-shrink: 0;
  }
  .badge-header-logo-seal {
    width: 12.5mm;
    height: 12.5mm;
    object-fit: contain;
    object-position: right center;
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

  /* ── Photo — 28mm ───────────────────────────────────────── */
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

  /* ── Name band — 18mm ───────────────────────────────────── */
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
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .badge-name-last {
    font-size: 13.5pt;
    font-weight: 900;
    color: #fff;
    letter-spacing: .02em;
    line-height: 1.1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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

  /* ── Details ────────────────────────────────────────────── */
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
  .badge-detail-text {
    font-size: 6.5pt;
    color: #374151;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* ── QR footer — 17mm ───────────────────────────────────── */
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
    padding: 1.2mm;
    flex-shrink: 0;
    width: 13.5mm;
    height: 13.5mm;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .badge-qr-wrap img, .badge-qr-wrap canvas {
    width: 100% !important;
    height: 100% !important;
    display: block;
  }
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

  /* ── A4 Sheet Mode ──────────────────────────────────────── */
  .a4-page {
    width: 210mm;
    min-height: 297mm;
    background: #fff;
    margin: 0 auto 30px;
    padding: 18mm 12mm;
    box-shadow: 0 10px 40px rgba(0,0,0,.35);
    display: grid;
    grid-template-columns: repeat(2, 86mm);
    grid-template-rows: repeat(2, 118mm);
    gap: 12mm 14mm;
    justify-content: center;
    align-content: center;
    page-break-inside: avoid;
    break-inside: avoid;
    position: relative;
  }
  .a4-page .badge {
    box-shadow: none;
    border: 1px dashed #d1dce8;
  }

  /* ── Print Media Query ──────────────────────────────────── */
  @media print {
    *, *::before, *::after {
      -webkit-print-color-adjust: exact !important;
      print-color-adjust: exact !important;
    }
    .bulk-top-bar { display: none !important; }
    body { background: #fff !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }

    <?php if ($layout === 'a4'): ?>
      @page {
        size: A4 portrait;
        margin: 0;
      }
      .badge-container { padding: 0 !important; gap: 0 !important; }
      .a4-page {
        margin: 0 !important;
        box-shadow: none !important;
        page-break-after: always !important;
        break-after: page !important;
        height: 297mm !important;
        max-height: 297mm !important;
      }
      .a4-page:last-child {
        page-break-after: avoid !important;
        break-after: avoid !important;
      }
    <?php else: ?>
      @page {
        size: 86mm 118mm;
        margin: 0;
      }
      html, body {
        width: 86mm !important;
        height: 118mm !important;
        padding: 0 !important;
        margin: 0 !important;
      }
      .badge-container {
        padding: 0 !important;
        margin: 0 !important;
        display: block !important;
      }
      .badge {
        width: 86mm !important;
        height: 118mm !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        page-break-inside: avoid !important;
        break-inside: avoid !important;
        page-break-after: always !important;
        break-after: page !important;
      }
      .badge:last-child {
        page-break-after: avoid !important;
        break-after: avoid !important;
      }
    <?php endif; ?>
  }
</style>
</head>
<body>

<?php if (!$isExport): ?>
<!-- ── Top Controls Bar ───────────────────────────────────── -->
<div class="bulk-top-bar">
  <div class="bt-left">
    <a href="admin.php?status=approved" class="bt-back">← Back to Admin</a>
    <div class="bt-title">
      Bulk Badges
      <span class="bt-badge-count"><?= $loadedCount ?> of <?= $totalMatching ?> Loaded</span>
    </div>
  </div>

  <div class="bt-controls">
    <!-- Layout Switcher -->
    <select class="bt-select" onchange="changeParam('layout', this.value)">
      <option value="badge" <?= $layout === 'badge' ? 'selected' : '' ?>>🪪 1 Card Per Page (86×118mm)</option>
      <option value="a4" <?= $layout === 'a4' ? 'selected' : '' ?>>📄 4 Cards Per A4 Sheet</option>
    </select>

    <!-- Representation Type Filter -->
    <select class="bt-select" onchange="changeParam('type', this.value)">
      <option value="">All Categories (<?= $totalMatching ?>)</option>
      <?php foreach ($typesList as $t): ?>
        <option value="<?= htmlspecialchars($t) ?>" <?= $typeFilter === $t ? 'selected' : '' ?>>
          <?= htmlspecialchars($t) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <!-- Batch Selection for large datasets -->
    <?php if ($totalMatching > 100): ?>
      <select class="bt-select" onchange="changeBatch(this.value)">
        <option value="all" <?= $batchLimit === 0 ? 'selected' : '' ?>>All <?= $totalMatching ?> Badges</option>
        <?php
        $batchStep = 100;
        for ($i = 0; $i < $totalMatching; $i += $batchStep):
          $end = min($i + $batchStep, $totalMatching);
          $sel = ($batchLimit === $batchStep && $batchOffset === $i) ? 'selected' : '';
        ?>
          <option value="<?= $i ?>_<?= $batchStep ?>" <?= $sel ?>>
            Batch: <?= ($i + 1) ?>–<?= $end ?>
          </option>
        <?php endfor; ?>
      </select>
    <?php endif; ?>

    <!-- ZIP Download -->
    <a href="?<?= http_build_query(array_merge($_GET, ['action' => 'zip'])) ?>" class="btn-zip">
      📦 Download ZIP
    </a>

    <!-- Print / Save as PDF Button -->
    <button class="btn-print-all" onclick="window.print()">
      🖨️ Print / Save All as PDF
    </button>
  </div>
</div>
<?php endif; ?>

<!-- ── Badges Render Container ────────────────────────────── -->
<div class="badge-container">
<?php
// Function to render a single badge HTML block
function render_single_badge(array $r, string $orgLogoSrc, string $natSealSrc, string $imgBase = ''): void {
    $rid       = (int)$r['id'];
    $firstName = htmlspecialchars($r['first_name']);
    $lastName  = htmlspecialchars($r['last_name']);
    $org       = htmlspecialchars($r['organisation_name'] ?? '');
    $type      = htmlspecialchars($r['representation_type'] ?? '');
    $ref       = 'GAM26-' . str_pad($rid, 5, '0', STR_PAD_LEFT);
    $initials  = strtoupper(substr($r['first_name'], 0, 1) . substr($r['last_name'], 0, 1));

    $picFile   = $r['picture'] ?? '';
    $picFile   = (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $picFile) && strpos($picFile, '..') === false) ? $picFile : '';
    $photoPath = $picFile ? 'uploads/' . $picFile : '';
    $hasPhoto  = $photoPath && file_exists(__DIR__ . '/' . $photoPath);
    $photoSrc  = $hasPhoto ? ($imgBase . $photoPath) : '';
    $qrUrl     = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&margin=6&data=" . urlencode($ref);
    ?>
    <div class="badge" data-ref="<?= $ref ?>">
      <!-- Header -->
      <div class="badge-header">
        <div class="badge-header-row">
          <img src="<?= $orgLogoSrc ?>" class="badge-header-logo-org" alt="Organization Logo">
          <div class="badge-header-center">
            <div class="badge-org-label">OFFICIAL DELEGATE BADGE</div>
            <div class="badge-event-name">GAMBIA 2026</div>
            <div class="badge-event-sub">NGO SUMMIT &bull; BANJUL, THE GAMBIA</div>
          </div>
          <img src="<?= $natSealSrc ?>" class="badge-header-logo-seal" alt="Gambia National Seal">
        </div>
        <div class="badge-gold-bar"></div>
      </div>

      <!-- Photo -->
      <div class="badge-photo-wrap">
        <?php if ($hasPhoto): ?>
          <img src="<?= $photoSrc ?>" alt="Photo" class="badge-photo">
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
        <div class="badge-ref"><?= $ref ?></div>

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
        <div class="badge-qr-wrap" id="qr-<?= $rid ?>">
          <img src="<?= $qrUrl ?>" alt="QR Code">
        </div>
        <div class="badge-footer-text">
          <div class="badge-footer-event">GAMBIA 2026</div>
          <div class="badge-footer-date">Banjul, Republic of<br>The Gambia</div>
          <div class="badge-footer-scan">Scan to verify accreditation</div>
        </div>
      </div>
    </div>
    <?php
}

if ($layout === 'a4') {
    // Group into chunks of 4 for A4 pages
    $chunks = array_chunk($delegates, 4);
    foreach ($chunks as $chunk) {
        echo '<div class="a4-page">';
        foreach ($chunk as $d) {
            render_single_badge($d, $orgLogoSrc, $natSealSrc, $imgBase);
        }
        echo '</div>';
    }
} else {
    // 1 card per page
    foreach ($delegates as $d) {
        render_single_badge($d, $orgLogoSrc, $natSealSrc, $imgBase);
    }
}
?>
</div>

<script>
// Fast Client-side QR generation using QRCode.js if loaded
document.addEventListener('DOMContentLoaded', function() {
  if (typeof QRCode !== 'undefined') {
    document.querySelectorAll('.badge').forEach(function(badge) {
      var ref = badge.getAttribute('data-ref');
      var wrap = badge.querySelector('.badge-qr-wrap');
      if (ref && wrap) {
        wrap.innerHTML = '';
        new QRCode(wrap, {
          text: ref,
          width: 64,
          height: 64,
          colorDark: '#000000',
          colorLight: '#ffffff',
          correctLevel: QRCode.CorrectLevel.M
        });
      }
    });
  }
});

// Helper functions for parameter changes
function changeParam(key, val) {
  var url = new URL(window.location.href);
  if (val) {
    url.searchParams.set(key, val);
  } else {
    url.searchParams.delete(key);
  }
  window.location.href = url.toString();
}

function changeBatch(val) {
  var url = new URL(window.location.href);
  if (val === 'all') {
    url.searchParams.delete('limit');
    url.searchParams.delete('offset');
  } else {
    var parts = val.split('_');
    url.searchParams.set('offset', parts[0]);
    url.searchParams.set('limit', parts[1]);
  }
  window.location.href = url.toString();
}
</script>

</body>
</html>
