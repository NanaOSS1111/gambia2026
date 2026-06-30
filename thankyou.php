<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Registration Submitted — GAMBIA 2026</title>
<link rel="preload" href="asset/gambia-river.jpg" as="image">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: 'Inter', sans-serif;
    background: #f0f4f8;
    color: #1a2332;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
  }

  /* ── Hero banner ───────────────────────────────────────── */
  .reg-hero {
    position: relative;
    width: 100%;
    overflow: hidden;
    background-color: #2d3a1e;
  }
  .reg-hero-bg {
    position: absolute;
    inset: 0;
    background: url('asset/gambia-river.jpg') center center / cover no-repeat;
  }
  .reg-hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(
      to bottom,
      rgba(5,12,25,.08) 0%,
      rgba(5,12,25,.18) 55%,
      rgba(5,12,25,.30) 100%
    );
    z-index: 1;
  }
  .reg-hero-content {
    position: relative;
    z-index: 2;
    padding: 240px 24px 80px;
    text-align: center;
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .reg-hero-title {
    font-size: clamp(22px, 3.2vw, 42px);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .04em;
    line-height: 1.15;
    color: #fff;
    margin-bottom: 14px;
    max-width: 820px;
    text-shadow: 0 2px 16px rgba(0,0,0,.35);
  }
  .reg-hero-subtitle {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 16px;
    font-style: italic;
    color: rgba(255,255,255,.88);
    max-width: 720px;
    line-height: 1.6;
    margin-bottom: 0;
  }
  .reg-hero-location {
    font-size: 16px;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #e0a060;
    margin-top: 14px;
  }

  /* ── Countdown bar below hero ──────────────────────────── */
  .cd-bar {
    background: #0a2540;
    padding: 18px 24px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
  }
  .cd-units {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .cd-block {
    display: flex;
    flex-direction: column;
    align-items: center;
    background: rgba(255,255,255,.1);
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 6px;
    min-width: 46px;
    padding: 5px 10px 5px;
    backdrop-filter: blur(4px);
  }
  .cd-num {
    font-size: 20px;
    font-weight: 800;
    line-height: 1;
    color: #fff;
    font-variant-numeric: tabular-nums;
  }
  .cd-lbl {
    font-size: 8px;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: rgba(255,255,255,.55);
    margin-top: 3px;
  }
  .cd-sep {
    font-size: 16px;
    font-weight: 700;
    color: rgba(255,255,255,.4);
    line-height: 1;
    padding-top: 4px;
    user-select: none;
  }

  @media (max-width: 700px) {
    .reg-hero-subtitle { font-size: 14px; }
    .reg-hero-location { font-size: 14px; }
    .reg-hero-content  { padding: 160px 16px 48px; }
    .cd-block { min-width: 40px; padding: 4px 8px 4px; }
    .cd-num   { font-size: 18px; }
    .cd-sep   { font-size: 14px; padding-top: 3px; }
  }

  /* ── Page body ──────────────────────────────────────────── */
  .page-body {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 24px;
  }

  .card {
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 4px 32px rgba(0,0,0,.08);
    padding: 52px 48px;
    max-width: 560px;
    width: 100%;
    text-align: center;
  }

  .check-circle {
    width: 80px; height: 80px;
    background: #dcfce7;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 36px;
    margin: 0 auto 28px;
  }

  .card h2 {
    font-size: 26px;
    font-weight: 700;
    color: #0a2540;
    margin-bottom: 12px;
  }

  .card p {
    font-size: 15px;
    color: #4a6080;
    line-height: 1.7;
    margin-bottom: 8px;
  }

  .ref-box {
    background: #f0f4f8;
    border-radius: 10px;
    padding: 16px 24px;
    margin: 24px 0;
    display: inline-block;
    width: 100%;
  }
  .ref-box .ref-label { font-size: 11px; color: #9aaabf; font-weight: 600; letter-spacing: .08em; text-transform: uppercase; }
  .ref-box .ref-num   { font-size: 24px; font-weight: 700; color: #0d6e8c; margin-top: 4px; }

  .info-list {
    background: #f8fbff;
    border-radius: 10px;
    border: 1px solid #e2eaf4;
    padding: 20px 24px;
    text-align: left;
    margin: 20px 0 28px;
    font-size: 13px;
    color: #4a6080;
    line-height: 1.8;
  }
  .info-list li { list-style: none; padding-left: 20px; position: relative; }
  .info-list li::before { content: '✓'; position: absolute; left: 0; color: #16a34a; font-weight: 700; }

  .btn-home {
    display: inline-block;
    background: #0a2540;
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    padding: 14px 36px;
    font-size: 15px;
    font-weight: 700;
    letter-spacing: .02em;
    transition: background .2s;
  }
  .btn-home:hover { background: #0d6e8c; }

  .contact {
    margin-top: 28px;
    font-size: 12px;
    color: #9aaabf;
  }
  .contact a { color: #0d6e8c; text-decoration: none; }

  @media (max-width: 560px) {
    .card { padding: 36px 24px; }
    .card h2 { font-size: 22px; }
  }
</style>
</head>
<body>
<?php include __DIR__ . '/_spinner.php'; ?>

<!-- ── Hero banner ───────────────────────────────────────── -->
<div class="reg-hero">
  <div class="reg-hero-bg"></div>
  <div class="reg-hero-overlay"></div>
  <div class="reg-hero-content">
    <h1 class="reg-hero-title">&ldquo;Mobilizing Civil Society for Bold Social Development&rdquo;</h1>
    <p class="reg-hero-subtitle">Restitution of the Second World Social Summit (SWSS) on Social Development Outcome</p>
    <p class="reg-hero-location">The SDK Conference Centre, Senegambia, The Gambia &nbsp;&bull;&nbsp; October 12–16, 2026</p>
  </div>
</div>

<!-- ── Countdown bar below hero ──────────────────────────── -->
<div class="cd-bar">
  <div class="cd-units">
    <div class="cd-block">
      <span class="cd-num" id="cd-days">00</span>
      <span class="cd-lbl">Days</span>
    </div>
    <span class="cd-sep">:</span>
    <div class="cd-block">
      <span class="cd-num" id="cd-hours">00</span>
      <span class="cd-lbl">Hours</span>
    </div>
    <span class="cd-sep">:</span>
    <div class="cd-block">
      <span class="cd-num" id="cd-mins">00</span>
      <span class="cd-lbl">Minutes</span>
    </div>
    <span class="cd-sep">:</span>
    <div class="cd-block">
      <span class="cd-num" id="cd-secs">00</span>
      <span class="cd-lbl">Seconds</span>
    </div>
  </div>
</div>

<!-- ── Thank you card ────────────────────────────────────── -->
<div class="page-body">
  <div class="card">

    <div class="check-circle">✓</div>

    <h2>Registration Submitted!</h2>
    <p>Thank you for registering for the <strong>GAMBIA 2026 NGO Summit</strong>. Your application has been received and is now <strong>pending review</strong> by the Secretariat.</p>

    <?php
    $ref = htmlspecialchars(strip_tags($_GET['ref'] ?? ''));
    if ($ref):
    ?>
    <div class="ref-box">
      <div class="ref-label">Your Reference Number</div>
      <div class="ref-num"><?= $ref ?></div>
    </div>
    <?php endif; ?>

    <ul class="info-list">
      <li>Your registration documents have been received</li>
      <li>A confirmation email has been sent to your address</li>
      <li>You will be notified once your registration is approved</li>
      <li>Please keep your reference number for your records</li>
    </ul>

    <p>If you have any questions, please contact the Secretariat.</p>

    <br>
    <a href="index.php" class="btn-home">Back to Homepage</a>

    <div class="contact">
      <strong>Contact:</strong>
      <a href="mailto:secretariat@ngocsocd.org">secretariat@ngocsocd.org</a>
      &nbsp;&bull;&nbsp;
      <a href="https://ngocsocd.org" target="_blank">ngocsocd.org</a>
    </div>

  </div>
</div>

<script>
(function () {
  var target = new Date('2026-10-12T00:00:00');
  var days  = document.getElementById('cd-days');
  var hours = document.getElementById('cd-hours');
  var mins  = document.getElementById('cd-mins');
  var secs  = document.getElementById('cd-secs');
  function pad(n) { return String(n).padStart(2, '0'); }
  function tick() {
    var diff = target - Date.now();
    if (diff <= 0) { days.textContent = hours.textContent = mins.textContent = secs.textContent = '00'; return; }
    var s = Math.floor(diff / 1000);
    days.textContent  = pad(Math.floor(s / 86400));
    hours.textContent = pad(Math.floor((s % 86400) / 3600));
    mins.textContent  = pad(Math.floor((s % 3600) / 60));
    secs.textContent  = pad(s % 60);
  }
  tick();
  setInterval(tick, 1000);
})();
</script>

</body>
</html>
