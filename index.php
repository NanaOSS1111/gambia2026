<?php
session_start();
require_once 'mail_config.php';
require_once 'db.php';
require_once 'settings.php';
$regStatus = is_registration_open($pdo);

// Delegate counter for hero
$counterRow = $pdo->query(
    "SELECT COUNT(*) AS total, COUNT(DISTINCT passport_nationality) AS countries FROM registrations WHERE status = 'approved'"
)->fetch();
$approvedCount  = (int)($counterRow['total']     ?? 0);
$countryCount   = (int)($counterRow['countries'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GAMBIA 2026 — Delegate Registration</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php if (!empty(RECAPTCHA_SITE_KEY)): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= RECAPTCHA_SITE_KEY ?>"></script>
<?php endif; ?>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Inter', sans-serif;
    background: #f0f4f8;
    color: #1a2332;
    min-height: 100vh;
  }

  /* HEADER — photo hero */
  .reg-hero {
    position: relative;
    width: 100%;
    background: url('asset/medicare.png-scaled.jpg') center 40% / cover no-repeat;
    background-color: #0a1e33;
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
  
  }
  .reg-hero-content {
    position: relative;
    z-index: 1;
    padding: 36px 24px 22px;
    text-align: center;
    color: #fff;
    display: flex;
    flex-direction: column;
    align-items: center;
  }
  .reg-hero-eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: #e0603a;
    margin-bottom: 10px;
  }
  .reg-hero-title {
    font-size: 64px;
    font-weight: 800;
    letter-spacing: -.03em;
    line-height: 1;
    color: #fff;
    margin-bottom: 10px;
  }
  .reg-hero-title .accent {
    color: #e0603a;
    font-style: italic;
    font-weight: 700;
  }
  .delegate-counter {
    display: inline-flex;
    align-items: center;
    gap: 16px;
    margin-top: 18px;
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.22);
    backdrop-filter: blur(6px);
    border-radius: 40px;
    padding: 8px 22px;
    color: #fff;
    font-size: 13px;
    font-weight: 500;
    letter-spacing: .01em;
  }
  .delegate-counter .dc-num {
    font-size: 17px;
    font-weight: 700;
    color: #e0603a;
  }
  .delegate-counter .dc-sep {
    width: 1px;
    height: 20px;
    background: rgba(255,255,255,.25);
  }
  .reg-hero-subtitle {
    font-family: Georgia, 'Times New Roman', serif;
    font-size: 17px;
    font-style: italic;
    color: rgba(255,255,255,.82);
    max-width: 600px;
    line-height: 1.5;
    margin-bottom: 22px;
  }
  .reg-hero-types {
    position: relative;
    z-index: 1;
    background: rgba(0,0,0,.38);
    border-top: 1px solid rgba(255,255,255,.1);
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    padding: 9px 24px;
    font-size: 11px;
    color: rgba(255,255,255,.65);
    text-align: center;
    line-height: 1.6;
    letter-spacing: .01em;
  }

  @media (max-width: 700px) {
    .reg-hero-title    { font-size: 44px; }
    .reg-hero-subtitle { font-size: 15px; }
    .reg-hero-content  { padding: 28px 16px 18px; }
    .delegate-counter  { font-size: 12px; gap: 10px; }
    .cd-block { min-width: 62px; padding: 10px 14px 8px; }
    .cd-num   { font-size: 30px; }
    .cd-sep   { font-size: 26px; padding-top: 12px; }
  }

  .reg-notice {
    background: #fff3cd;
    border-left: 4px solid #f0a500;
    padding: 12px 24px;
    font-size: 13px;
    font-weight: 600;
    color: #7a5100;
    text-align: center;
  }

  /* LAYOUT */
  .reg-layout {
    max-width: 1300px;
    margin: 36px auto;
    padding: 0 24px;
    display: grid;
    grid-template-columns: 210px 1fr 220px;
    gap: 28px;
    align-items: start;
  }

  /* SIDEBAR */
  .reg-sidebar {
    position: sticky;
    top: 24px;
  }
  .reg-sidebar-box {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    overflow: hidden;
  }
  .reg-sidebar-title {
    background: #0a2540;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: 12px 16px;
  }
  .reg-sidebar-nav a {
    display: block;
    padding: 11px 16px;
    font-size: 13px;
    color: #4a6080;
    text-decoration: none;
    border-bottom: 1px solid #f0f4f8;
    transition: all .15s;
  }
  .reg-sidebar-nav a:hover,
  .reg-sidebar-nav a.active { background: #eef6ff; color: #0d6e8c; font-weight: 600; }
  .reg-sidebar-contact {
    padding: 14px 16px;
    font-size: 12px;
    color: #7a8fa8;
    border-top: 1px solid #f0f4f8;
  }
  .reg-sidebar-contact strong { display: block; color: #1a2332; margin-bottom: 4px; }
  .reg-sidebar-contact a { color: #0d6e8c; text-decoration: none; }

  /* FORM */
  .reg-form { display: flex; flex-direction: column; gap: 24px; }

  .reg-info-box {
    background: #fff;
    border-radius: 12px;
    border: 1px solid #e2eaf4;
    padding: 18px 20px;
    font-size: 13px;
    color: #4a6080;
    line-height: 1.6;
  }
  .reg-info-box.warning {
    border-color: #f0a500;
    background: #fffbf0;
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }
  .reg-info-box.warning .icon { font-size: 20px; flex-shrink: 0; margin-top: 2px; }

  .reg-section {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    overflow: hidden;
  }
  .reg-section-header {
    padding: 18px 24px 16px;
    border-bottom: 1px solid #e8f0f8;
    display: flex;
    justify-content: space-between;
    align-items: baseline;
  }
  .reg-section-header h2 {
    font-size: 18px;
    font-weight: 700;
    color: #0d6e8c;
    letter-spacing: -.01em;
  }
  .reg-section-header span {
    font-size: 12px;
    color: #9aaabf;
  }
  .reg-section-desc {
    padding: 12px 24px 0;
    font-size: 13px;
    color: #7a8fa8;
    line-height: 1.6;
  }
  .reg-section-body { padding: 20px 24px 24px; display: flex; flex-direction: column; gap: 20px; }

  /* FIELDS */
  .field { display: flex; flex-direction: column; gap: 6px; }
  .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .field-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }

  label {
    font-size: 13px;
    font-weight: 600;
    color: #1a2332;
  }
  label .req { color: #e03e3e; margin-left: 2px; }
  .field-hint {
    font-size: 11px;
    color: #9aaabf;
    margin-top: -2px;
    font-style: italic;
  }

  input[type="text"],
  input[type="email"],
  input[type="date"],
  input[type="tel"],
  select,
  textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1.5px solid #d1dce8;
    border-radius: 8px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    color: #1a2332;
    background: #fff;
    transition: border-color .15s, box-shadow .15s;
    outline: none;
    appearance: none;
    -webkit-appearance: none;
  }
  input:focus, select:focus, textarea:focus {
    border-color: #0d6e8c;
    box-shadow: 0 0 0 3px rgba(13,110,140,.1);
  }
  select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%230d6e8c' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 14px center;
    padding-right: 36px;
    cursor: pointer;
  }
  textarea { resize: vertical; min-height: 90px; }

  /* FILE UPLOAD */
  .file-drop {
    border: 2px dashed #b8cfe0;
    border-radius: 10px;
    padding: 28px 20px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: #f8fbff;
    position: relative;
  }
  .file-drop:hover, .file-drop.dragover {
    border-color: #0d6e8c;
    background: #eef6ff;
  }
  .file-drop input[type="file"] {
    position: absolute;
    inset: 0;
    opacity: 0;
    cursor: pointer;
    width: 100%;
    height: 100%;
  }
  .file-drop-icon { font-size: 28px; margin-bottom: 8px; }
  .file-drop-text { font-size: 14px; font-weight: 600; color: #1a2332; }
  .file-drop-sub  { font-size: 12px; color: #9aaabf; margin-top: 4px; }
  .file-drop-name {
    margin-top: 10px;
    font-size: 12px;
    color: #0d6e8c;
    font-weight: 600;
    display: none;
  }

  /* RADIO / CHECKBOX */
  .radio-group, .check-group {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
  }
  .radio-group label, .check-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 400;
    cursor: pointer;
    font-size: 14px;
  }
  input[type="radio"], input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: #0d6e8c;
    cursor: pointer;
    flex-shrink: 0;
  }
  .checkbox-field {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 16px;
    background: #f8fbff;
    border-radius: 8px;
    border: 1.5px solid #d1dce8;
  }
  .checkbox-field input { margin-top: 2px; }
  .checkbox-field .cb-text { font-size: 13px; line-height: 1.6; color: #1a2332; }
  .checkbox-field .cb-text a { color: #0d6e8c; }

  /* PICTURE PREVIEW */
  .picture-upload-wrap {
    display: grid;
    grid-template-columns: 180px 1fr;
    gap: 16px;
    align-items: start;
  }
  .picture-preview {
    width: 180px;
    height: 220px;
    border-radius: 8px;
    border: 2px dashed #b8cfe0;
    background: #f8fbff;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    flex-shrink: 0;
  }
  .picture-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: none;
  }
  .picture-preview .ph { font-size: 48px; color: #b8cfe0; }
  .picture-reqs {
    background: #eef6ff;
    border-radius: 8px;
    padding: 14px 16px;
    font-size: 12px;
    color: #4a6080;
    line-height: 1.7;
    margin-top: 12px;
  }
  .picture-reqs ul { padding-left: 16px; }

  /* ACCORDION */
  details.accord {
    border: 1px solid #d1dce8;
    border-radius: 8px;
    overflow: hidden;
    margin-top: 14px;
  }
  details.accord summary {
    padding: 11px 16px;
    background: #f0f6ff;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #0d6e8c;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 8px;
    user-select: none;
    border-radius: 8px;
    transition: background .15s;
  }
  details.accord[open] summary { border-radius: 8px 8px 0 0; background: #e0efff; }
  details.accord summary::-webkit-details-marker { display: none; }
  details.accord summary .accord-arrow {
    font-size: 10px;
    transition: transform .2s;
    flex-shrink: 0;
  }
  details.accord[open] summary .accord-arrow { transform: rotate(90deg); }
  details.accord summary:hover { background: #daeeff; }
  .accord-body {
    padding: 18px 20px;
    font-size: 13px;
    color: #4a6080;
    line-height: 1.75;
    border-top: 1px solid #d1dce8;
    background: #fafcff;
  }
  .accord-body h4 {
    font-size: 13px;
    font-weight: 700;
    color: #0a2540;
    margin: 16px 0 5px;
  }
  .accord-body h4:first-child { margin-top: 0; }
  .accord-body p { margin-bottom: 8px; }
  .accord-body p:last-child { margin-bottom: 0; }

  /* TOAST */
  #toast-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 99999;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
  }
  .toast {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 6px 24px rgba(0,0,0,.14);
    padding: 13px 15px 13px 16px;
    display: flex;
    align-items: flex-start;
    gap: 11px;
    border-left: 4px solid #e03e3e;
    min-width: 290px;
    max-width: 360px;
    pointer-events: all;
    transform: translateX(calc(100% + 30px));
    transition: transform .32s cubic-bezier(.22,.61,.36,1), opacity .32s;
    opacity: 0;
  }
  .toast.toast-show { transform: translateX(0); opacity: 1; }
  .toast.toast-hide { transform: translateX(calc(100% + 30px)); opacity: 0; }
  .toast-icon { font-size: 17px; flex-shrink: 0; margin-top: 1px; }
  .toast-body { flex: 1; min-width: 0; }
  .toast-title { font-size: 13px; font-weight: 700; color: #1a2332; line-height: 1.3; }
  .toast-msg   { font-size: 12px; color: #7a8fa8; margin-top: 2px; }
  .toast-close { background: none; border: none; cursor: pointer; color: #b0bec8; font-size: 15px; padding: 0 0 0 4px; line-height: 1; flex-shrink: 0; }
  .toast-close:hover { color: #1a2332; }
  .toast-server { border-left-color: #d97706; }
  .toast-server .toast-icon { color: #d97706; }

  /* Field error highlight */
  input.f-err, select.f-err, textarea.f-err {
    border-color: #e03e3e !important;
    box-shadow: 0 0 0 3px rgba(224,62,62,.1) !important;
  }

  /* Inline age warning below birth date */
  .birth-age-warn {
    display: none;
    margin-top: 6px;
    padding: 8px 12px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 6px;
    font-size: 12.5px;
    color: #7a4f00;
    line-height: 1.5;
  }
  .birth-age-warn.visible { display: block; }

  /* SUBMIT */
  .reg-submit {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    padding: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
  }
  .reg-submit p { font-size: 13px; color: #7a8fa8; }
  .btn-submit {
    background: #0a2540;
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 14px 36px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    letter-spacing: .02em;
    transition: background .2s, transform .1s;
  }
  .btn-submit:hover  { background: #0d6e8c; }
  .btn-submit:active { transform: scale(.98); }

  .btn-clear {
    background: none;
    color: #6b7280;
    border: 1.5px solid #d1dce8;
    border-radius: 8px;
    padding: 14px 24px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    letter-spacing: .01em;
    transition: background .2s, color .2s, border-color .2s;
  }
  .btn-clear:hover { background: #fde8e8; color: #b91c1c; border-color: #fca5a5; }

  /* ALERT */
  #reg-alert {
    display: none;
    padding: 14px 18px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 8px;
  }
  #reg-alert.error   { background: #fde8e8; color: #b91c1c; border: 1px solid #fca5a5; }
  #reg-alert.success { background: #dcfce7; color: #166534; border: 1px solid #86efac; }

  /* Scroll to top */
  #stt-btn {
    position: fixed;
    bottom: 30px;
    right: 28px;
    z-index: 9998;
    background: none;
    border: none;
    padding: 0;
    cursor: pointer;
    width: 56px;
    height: 56px;
    opacity: 0;
    transform: translateY(16px) scale(.88);
    transition: opacity .3s ease, transform .3s ease;
    pointer-events: none;
  }
  #stt-btn.stt-visible {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: all;
  }
  #stt-btn:hover .stt-bg   { fill: #0a2540; }
  #stt-btn:hover .stt-ring { stroke: #5bb8d4; }
  #stt-btn:hover .stt-arr  { stroke: #fff; }
  #stt-btn:hover .stt-pct  { fill: #fff; }

  /* ── Countdown ──────────────────────────────────────── */
  .cd-wrap {
    background: linear-gradient(160deg, #060f1e 0%, #0a2540 55%, #0c3355 100%);
    padding: 32px 24px;
    text-align: center;
    position: relative;
    overflow: hidden;
  }
  .cd-wrap::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image: radial-gradient(circle, rgba(255,255,255,.07) 1px, transparent 1px);
    background-size: 30px 30px;
    pointer-events: none;
  }
  .cd-eyebrow {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .22em;
    text-transform: uppercase;
    color: #0d6e8c;
    margin-bottom: 5px;
    position: relative;
  }
  .cd-heading {
    font-size: 13px;
    color: rgba(255,255,255,.5);
    margin-bottom: 24px;
    position: relative;
  }
  .cd-units {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    gap: 10px;
    position: relative;
  }
  .cd-block {
    background: rgba(255,255,255,.06);
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 10px;
    padding: 12px 20px 10px;
    min-width: 80px;
  }
  .cd-num {
    font-size: 40px;
    font-weight: 700;
    color: #fff;
    line-height: 1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -.02em;
    display: flex;
    gap: 1px;
    overflow: hidden;
  }
  .cd-digit {
    display: inline-block;
    min-width: .6em;
    text-align: center;
  }
  .cd-digit.cd-flip, .cd-num.cd-flip {
    animation: cdFlip .15s ease-out both;
  }
  @keyframes cdFlip {
    0%   { opacity: .2; }
    100% { opacity: 1; }
  }
  .cd-lbl {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .14em;
    text-transform: uppercase;
    color: rgba(255,255,255,.38);
    margin-top: 8px;
    display: block;
  }
  .cd-sep {
    font-size: 34px;
    font-weight: 300;
    color: rgba(255,255,255,.2);
    padding-top: 12px;
    flex-shrink: 0;
    line-height: 1;
    user-select: none;
  }

  /* ── Form progress ring ──────────────────────────────── */
  .prog-box {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    overflow: hidden;
    margin-top: 16px;
  }
  .prog-box-title {
    background: #0a2540;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: 12px 16px;
  }
  .prog-body {
    padding: 20px 16px 18px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
  }
  .prog-ring-wrap {
    position: relative;
    width: 120px;
    height: 120px;
    flex-shrink: 0;
  }
  .prog-ring-wrap svg {
    width: 120px;
    height: 120px;
    transform: rotate(-90deg);
    display: block;
  }
  .prog-track { fill: none; stroke: #e8f0f8; stroke-width: 9; }
  .prog-fill  {
    fill: none;
    stroke: #0d6e8c;
    stroke-width: 9;
    stroke-linecap: round;
    stroke-dasharray: 301.6;
    stroke-dashoffset: 301.6;
    transition: stroke-dashoffset .45s cubic-bezier(.4,0,.2,1);
  }
  .prog-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
  }
  .prog-pct-num {
    font-size: 26px;
    font-weight: 700;
    color: #0a2540;
    line-height: 1;
  }
  .prog-pct-lbl {
    font-size: 10px;
    font-weight: 600;
    color: #9aaabf;
    margin-top: 3px;
    letter-spacing: .05em;
  }
  .prog-steps {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 5px;
  }
  .prog-step {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 11px;
    color: #b8cfe0;
    line-height: 1.2;
  }
  .prog-step.visited { color: #0a2540; }
  .prog-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    background: #d1dce8;
    flex-shrink: 0;
    transition: background .3s;
  }
  .prog-step.visited .prog-dot { background: #0d6e8c; }

  @media (max-width: 1000px) {
    .reg-layout { grid-template-columns: 1fr; }
    .reg-sidebar { position: static; }
    .field-row, .field-row-3 { grid-template-columns: 1fr; }
    .picture-upload-wrap { grid-template-columns: 1fr; }
    .picture-preview { width: 100%; height: 200px; }
    .cd-block { min-width: 70px; padding: 14px 16px 10px; }
    .cd-num   { font-size: 36px; }
    .cd-sep   { font-size: 32px; }
  }
</style>
</head>
<body>

<div id="submit-overlay" style="display:none;position:fixed;inset:0;background:rgba(10,37,64,.78);z-index:9999;align-items:center;justify-content:center;flex-direction:column;gap:18px;">
  <div style="width:52px;height:52px;border:4px solid rgba(255,255,255,.25);border-top-color:#fff;border-radius:50%;animation:reg-spin .8s linear infinite;"></div>
  <div style="color:#fff;font-size:16px;font-weight:600;letter-spacing:.02em;">Submitting registration…</div>
  <div style="color:rgba(255,255,255,.55);font-size:13px;">Please wait, do not close this page.</div>
</div>
<style>@keyframes reg-spin{to{transform:rotate(360deg)}}</style>

<div class="reg-hero">
  <div class="reg-hero-overlay"></div>
  <div class="reg-hero-content">
    <div class="reg-hero-eyebrow">October 12–16, 2026 &nbsp;&bull;&nbsp; Banjul, The Gambia</div>
    <h1 class="reg-hero-title">GAMBIA<span class="accent">26</span></h1>
    <p class="reg-hero-subtitle">Where Civil Society Shapes Global Social Development</p>
    <div class="cd-units">
      <div class="cd-block">
        <span class="cd-num" id="cd-days">121</span>
        <span class="cd-lbl">Days</span>
      </div>
      <span class="cd-sep">:</span>
      <div class="cd-block">
        <span class="cd-num"><span class="cd-digit" id="cd-hours-t">0</span><span class="cd-digit" id="cd-hours-u">0</span></span>
        <span class="cd-lbl">Hours</span>
      </div>
      <span class="cd-sep">:</span>
      <div class="cd-block">
        <span class="cd-num"><span class="cd-digit" id="cd-mins-t">0</span><span class="cd-digit" id="cd-mins-u">0</span></span>
        <span class="cd-lbl">Minutes</span>
      </div>
      <span class="cd-sep">:</span>
      <div class="cd-block">
        <span class="cd-num"><span class="cd-digit" id="cd-secs-t">0</span><span class="cd-digit" id="cd-secs-u">0</span></span>
        <span class="cd-lbl">Seconds</span>
      </div>
    </div>
    <?php if ($approvedCount > 0): ?>
    <div class="delegate-counter">
      <span>You and <span class="dc-num"><?= $approvedCount ?></span> delegate<?= $approvedCount !== 1 ? 's' : '' ?> are attending Gambia 2026</span>
      <?php if ($countryCount > 1): ?>
      <span class="dc-sep"></span>
      <span>from <span class="dc-num"><?= $countryCount ?></span> countr<?= $countryCount !== 1 ? 'ies' : 'y' ?></span>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if (!$regStatus['open']): ?>
<div style="max-width:680px;margin:60px auto;padding:0 24px;text-align:center;">
  <div style="background:#fff;border-radius:16px;box-shadow:0 4px 32px rgba(0,0,0,.08);padding:52px 40px;">
    <div style="width:72px;height:72px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 24px;">🔒</div>
    <h2 style="font-size:26px;font-weight:700;color:#0a2540;margin-bottom:12px;">Registration is Closed</h2>
    <?php if (($regStatus['reason'] ?? '') === 'deadline'): ?>
      <p style="font-size:15px;color:#4a6080;line-height:1.7;">The registration deadline of <strong><?= date('d F Y, H:i', strtotime($regStatus['deadline'])) ?></strong> has passed.</p>
    <?php else: ?>
      <p style="font-size:15px;color:#4a6080;line-height:1.7;">Delegate registration for GAMBIA 2026 is currently closed.</p>
    <?php endif; ?>
    <p style="font-size:14px;color:#9aaabf;margin-top:16px;">For inquiries, contact <a href="mailto:secretariat@ngocsocd.org" style="color:#0d6e8c;">secretariat@ngocsocd.org</a></p>
  </div>
</div>
<?php else: ?>

<div class="reg-notice">
  ⚠ Participation is by invitation only. Registration opens 30 September 2026. All registrations are moderated and require approval.
</div>

<div class="reg-layout">

  <!-- LEFT NAV -->
  <div class="reg-sidebar">
    <div class="reg-sidebar-box">
      <div class="reg-sidebar-title">Navigation</div>
      <nav class="reg-sidebar-nav">
        <a href="#sec-representation">Representation</a>
        <a href="#sec-personal">Personal Data</a>
        <a href="#sec-visa">Visa Information</a>
        <a href="#sec-docs">Mandatory Documents</a>
        <a href="#sec-accommodation">Accommodation</a>
        <a href="#sec-conduct">Framework Document</a>
        <a href="#sec-privacy">Data Privacy</a>
        <a href="#sec-insurance">Liabilities</a>
        <a href="#sec-confirmation">Confirmation</a>
      </nav>
    </div>
  </div>

  <!-- FORM COLUMN -->
  <div>
    <div id="reg-alert"></div>

    <form id="regForm" class="reg-form" enctype="multipart/form-data" novalidate>
      <?php if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); ?>
      <input type="hidden" name="csrf_token"      value="<?= $_SESSION['csrf_token'] ?>">
      <input type="hidden" name="recaptcha_token" id="recaptcha_token">

      <div class="reg-info-box" style="margin-bottom:0;">
        <strong>This registration form is ONLY for the head of delegates of duly Registered organizations (by a country).</strong><br>
        If you are an individual and want to participate, please contact <a href="mailto:secretariat@ngocsocd.org">secretariat@ngocsocd.org</a>.
      </div>

      <div class="reg-info-box warning">
        <span class="icon">⚠️</span>
        <span><strong>Participation in this event is moderated.</strong><br>Your registration will have to be approved.</span>
      </div>

      <!-- SECTION 1: REPRESENTATION -->
      <div class="reg-section" id="sec-representation">
        <div class="reg-section-header">
          <h2>Representation</h2>
          <span>For representatives of organizations</span>
        </div>
        <div class="reg-section-body">
          <div class="field">
            <label for="representation_type">Representation Type <span class="req">*</span></label>
            <select name="representation_type" id="representation_type" required>
              <option value="">— Select —</option>
              <option>Parliamentarian</option>
              <option>Government Ministry</option>
              <option>UN Agencies</option>
              <option>International Organization</option>
              <option>Civil Society Organization</option>
              <option>Non-Governmental Organization</option>
              <option>Foundation</option>
              <option>Philanthropic Organization</option>
              <option>Charity Organization</option>
              <option>Indigenous People</option>
              <option>Social Development</option>
              <option>First Nation Communities</option>
              <option>People with Disabilities</option>
              <option>People of African Descent</option>
              <option>Youth Lead</option>
              <option>Climate Change</option>
              <option>Women Lead</option>
              <option>Child Protection</option>
              <option>Academics</option>
              <option>Social Worker</option>
              <option>Researchers</option>
              <option>Faith Based Organization</option>
              <option>Community Head / Chief</option>
              <option>Trade Union</option>
              <option>Human Rights Defenders and Development Activists</option>
            </select>
          </div>
          <div class="field">
            <label for="organisation_name">Organisation Name <span class="req">*</span></label>
            <input type="text" name="organisation_name" id="organisation_name" placeholder="Enter your organisation name" required>
          </div>
        </div>
      </div>

      <!-- SECTION 2: PERSONAL DATA -->
      <div class="reg-section" id="sec-personal">
        <div class="reg-section-header">
          <h2>Personal Data</h2>
        </div>
        <div class="reg-section-desc">Please fill out the form below in <strong>Latin characters ONLY</strong>.</div>
        <div class="reg-section-body">

          <!-- Picture -->
          <div class="field">
            <label>Picture <span class="req">*</span></label>
            <div class="picture-upload-wrap">
              <div>
                <div class="picture-preview" id="picturePreview">
                  <span class="ph">👤</span>
                  <img id="pictureImg" src="" alt="Preview">
                </div>
              </div>
              <div>
                <div class="file-drop" id="pictureDrop">
                  <input type="file" name="picture" id="pictureInput" accept="image/*" required>
                  <div class="file-drop-icon">📷</div>
                  <div class="file-drop-text">Drag a picture here</div>
                  <div class="file-drop-sub">or click to choose from your computer</div>
                  <div class="file-drop-name" id="pictureFileName"></div>
                </div>
                <div class="picture-reqs">
                  <strong>Picture requirements:</strong>
                  <ul>
                    <li>Background must be plain and uniform</li>
                    <li>Face must be properly centred and directly facing camera</li>
                    <li>Neutral expression, high resolution</li>
                    <li>No manual edits or alterations</li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="title">Title</label>
              <select name="title" id="title">
                <option value="">— Select —</option>
                <option>Mr.</option>
                <option>Mrs.</option>
                <option>Ms.</option>
                <option>Dr.</option>
                <option>Prof.</option>
                <option>Mx.</option>
              </select>
            </div>
            <div class="field">
              <label for="gender">Gender <span class="req">*</span></label>
              <select name="gender" id="gender" required>
                <option value="">— Select —</option>
                <option>Male</option>
                <option>Female</option>
                <option>Non-binary</option>
                <option>Prefer not to say</option>
              </select>
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="first_name">First Name <span class="req">*</span></label>
              <input type="text" name="first_name" id="first_name" placeholder="As on your identification document" required>
              <span class="field-hint">Latin characters exactly as on your identification document.</span>
            </div>
            <div class="field">
              <label for="last_name">Last Name <span class="req">*</span></label>
              <input type="text" name="last_name" id="last_name" placeholder="As on your identification document" required>
              <span class="field-hint">Latin characters exactly as on your identification document.</span>
            </div>
          </div>

          <!-- Duplicate name warning -->
          <div id="dup-warn" style="display:none;background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:12px 14px;font-size:13px;color:#713f12;margin-bottom:8px;"></div>

          <div class="field-row">
            <div class="field">
              <label for="position">Position</label>
              <input type="text" name="position" id="position" placeholder="e.g. Executive Director">
            </div>
            <div class="field">
              <label for="institution">Institution / Organization</label>
              <input type="text" name="institution" id="institution" placeholder="Your institution or organization">
            </div>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="email">Email Address <span class="req">*</span></label>
              <input type="email" name="email" id="email" placeholder="your@email.com" required>
            </div>
            <div class="field">
              <label for="birth_date">Birth Date <span class="req">*</span></label>
              <input type="date" name="birth_date" id="birth_date" required>
              <p class="birth-age-warn" id="birth-age-warn">
                &#9888; You must be at least 18 years old by 12 October 2026 to participate.
              </p>
            </div>
          </div>

          <div class="field">
            <label for="home_address">Postal Address <span class="req">*</span></label>
            <textarea name="home_address" id="home_address" placeholder="e.g. P.O. Box 1234, City, Country" required></textarea>
          </div>

        </div>
      </div>

      <!-- SECTION 3: VISA INFORMATION -->
      <div class="reg-section" id="sec-visa">
        <div class="reg-section-header">
          <h2>Visa Information</h2>
        </div>
        <div class="reg-section-desc">
          Participants are responsible for arranging and acquiring their own visas. All participants who need a visa to enter The Gambia are strongly advised to urgently apply for one to ensure timely visa issuance.
          To know if you need a visa to enter The Gambia, please consult the <a href="#" style="color:#0d6e8c;">general visa information link</a>.
        </div>
        <div class="reg-section-body">

          <div class="field">
            <label for="passport_nationality">Passport Nationality <span class="req">*</span></label>
            <select name="passport_nationality" id="passport_nationality" required>
              <option value="">— Select Country —</option>
              <?php
              $countries = ["Afghanistan","Albania","Algeria","Andorra","Angola","Antigua and Barbuda","Argentina","Armenia","Australia","Austria","Azerbaijan","Bahamas","Bahrain","Bangladesh","Barbados","Belarus","Belgium","Belize","Benin","Bhutan","Bolivia","Bosnia and Herzegovina","Botswana","Brazil","Brunei","Bulgaria","Burkina Faso","Burundi","Cabo Verde","Cambodia","Cameroon","Canada","Central African Republic","Chad","Chile","China","Colombia","Comoros","Congo","Costa Rica","Croatia","Cuba","Cyprus","Czech Republic","Denmark","Djibouti","Dominica","Dominican Republic","Ecuador","Egypt","El Salvador","Equatorial Guinea","Eritrea","Estonia","Eswatini","Ethiopia","Fiji","Finland","France","Gabon","Gambia","Georgia","Germany","Ghana","Greece","Grenada","Guatemala","Guinea","Guinea-Bissau","Guyana","Haiti","Honduras","Hungary","Iceland","India","Indonesia","Iran","Iraq","Ireland","Israel","Italy","Jamaica","Japan","Jordan","Kazakhstan","Kenya","Kiribati","Kuwait","Kyrgyzstan","Laos","Latvia","Lebanon","Lesotho","Liberia","Libya","Liechtenstein","Lithuania","Luxembourg","Madagascar","Malawi","Malaysia","Maldives","Mali","Malta","Marshall Islands","Mauritania","Mauritius","Mexico","Micronesia","Moldova","Monaco","Mongolia","Montenegro","Morocco","Mozambique","Myanmar","Namibia","Nauru","Nepal","Netherlands","New Zealand","Nicaragua","Niger","Nigeria","North Korea","North Macedonia","Norway","Oman","Pakistan","Palau","Palestine","Panama","Papua New Guinea","Paraguay","Peru","Philippines","Poland","Portugal","Qatar","Romania","Russia","Rwanda","Saint Kitts and Nevis","Saint Lucia","Saint Vincent and the Grenadines","Samoa","San Marino","Sao Tome and Principe","Saudi Arabia","Senegal","Serbia","Seychelles","Sierra Leone","Singapore","Slovakia","Slovenia","Solomon Islands","Somalia","South Africa","South Korea","South Sudan","Spain","Sri Lanka","Sudan","Suriname","Sweden","Switzerland","Syria","Taiwan","Tajikistan","Tanzania","Thailand","Timor-Leste","Togo","Tonga","Trinidad and Tobago","Tunisia","Turkey","Turkmenistan","Tuvalu","Uganda","Ukraine","United Arab Emirates","United Kingdom","United States","Uruguay","Uzbekistan","Vanuatu","Vatican City","Venezuela","Vietnam","Yemen","Zambia","Zimbabwe"];
              foreach ($countries as $c) echo "<option>" . htmlspecialchars($c) . "</option>\n";
              ?>
            </select>
            <span class="field-hint">Please provide the nationality on the passport you will use for travel to The Gambia.</span>
          </div>

          <div class="field-row">
            <div class="field">
              <label for="passport_number">Passport Number <span class="req">*</span></label>
              <input type="text" name="passport_number" id="passport_number" placeholder="e.g. AA238854" required>
              <span class="field-hint">Passport you will use for travel.</span>
            </div>
            <div class="field">
              <label for="passport_expiration">Passport Expiration <span class="req">*</span></label>
              <input type="date" name="passport_expiration" id="passport_expiration" required>
              <span class="field-hint">Ensure at least 6 months validity before your trip.</span>
            </div>
          </div>

          <div class="field">
            <label>Passport Scan <span class="req">*</span></label>
            <div class="file-drop" id="passportDrop">
              <input type="file" name="passport_file" id="passportInput" accept=".pdf,.jpg,.jpeg,.png" required>
              <div class="file-drop-icon">📄</div>
              <div class="file-drop-text">Drag your passport scan here</div>
              <div class="file-drop-sub">PDF, JPG or PNG — max 2 MB</div>
              <div class="file-drop-name" id="passportFileName"></div>
              <div id="passportPreview" style="text-align:center;"></div>
            </div>
            <span class="field-hint">Scanned copy of the data page of the passport you will use for travel.</span>
          </div>

        </div>
      </div>

      <!-- SECTION 4: MANDATORY DOCUMENTS -->
      <div class="reg-section" id="sec-docs">
        <div class="reg-section-header">
          <h2>Mandatory Documents</h2>
        </div>
        <div class="reg-section-desc">
          Only duly nominated participants from Parties to the Convention and accredited Observer organizations may participate. Please submit the relevant document to support your accreditation. Each participant must register individually. Max file size: 2 MB.
        </div>
        <div class="reg-section-body">

          <div class="field">
            <label>Official Nomination Letter from an accredited Observer Organization <span class="req">*</span></label>
            <div class="file-drop">
              <input type="file" name="nomination_letter" id="nominationInput" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
              <div class="file-drop-icon">📋</div>
              <div class="file-drop-text">Drag your nomination letter here</div>
              <div class="file-drop-sub">PDF, DOC, JPG or PNG — max 2 MB</div>
              <div class="file-drop-name" id="nominationFileName"></div>
              <div id="nominationPreview" style="text-align:center;"></div>
            </div>
          </div>

          <div class="field">
            <label>Are you at least 18 years old at the time of your participation? <span class="req">*</span></label>
            <div class="radio-group">
              <label><input type="radio" name="is_18_or_older" value="1" required> Yes</label>
              <label><input type="radio" name="is_18_or_older" value="0"> No</label>
            </div>
            <span class="field-hint">Participants under 18 must submit the Minor Participation Waiver Form.</span>
          </div>

        </div>
      </div>

      <!-- SECTION 5: ACCOMMODATION -->
      <div class="reg-section" id="sec-accommodation">
        <div class="reg-section-header">
          <h2>Accommodation &amp; Emergency Information</h2>
        </div>
        <div class="reg-section-desc">
          Participants are responsible for arranging their own accommodation. For logistical and emergency purposes, please provide the information below.
        </div>
        <div class="reg-section-body">

          <div class="field-row">
            <div class="field">
              <label for="arrival_date">Arrival Date in The Gambia <span class="req">*</span></label>
              <input type="date" name="arrival_date" id="arrival_date" required>
              <span class="field-hint">If locally based, indicate the date you will start attending.</span>
            </div>
            <div class="field">
              <label for="departure_date">Departure Date from The Gambia <span class="req">*</span></label>
              <input type="date" name="departure_date" id="departure_date" required>
              <span class="field-hint">If locally based, indicate the last date you will attend.</span>
            </div>
          </div>

          <div class="field">
            <label for="address_in_country">Address in The Gambia <span class="req">*</span></label>
            <textarea name="address_in_country" id="address_in_country" placeholder="Name and address of hotel or other accommodation. If locally based, provide your home address." required></textarea>
          </div>

          <div class="field">
            <label for="contact_number">Contact Number in The Gambia <span class="req">*</span></label>
            <input type="tel" name="contact_number" id="contact_number" placeholder="e.g. +220 000 0000" required>
            <span class="field-hint">A number we can reach you at in case of emergency.</span>
          </div>

        </div>
      </div>

      <!-- SECTION 6: FRAMEWORK DOCUMENT -->
      <div class="reg-section" id="sec-conduct">
        <div class="reg-section-header">
          <h2>Framework Document Endorsement</h2>
        </div>
        <div class="reg-section-desc">
          On the development of the NGO Framework of Action adopted within the 1st Edition of the GAMBIA 2026 NGO Summit, 12–16 October 2026, Banjul, Republic of The Gambia.
        </div>
        <div class="reg-section-body">

          <details class="accord">
            <summary><span class="accord-arrow">▶</span> Read Full Framework Document</summary>
            <div class="accord-body">
              <p>This Framework Document is designed to enable meaningful civil society engagement in monitoring and reporting the implementation of the 2025 Doha Political Declaration on the SWSS for Social Development. The finalized Framework will be presented for adoption at the concluding session of the NGO Restitution Summit, reflecting key outcomes, consolidated perspectives, and regional consultations arising from the zero draft deliberations.</p>
              <p>The document is open for endorsement from 14 April 2026 and is circulated to international delegates in advance to secure broad-based support, foster shared ownership, and promote a cohesive approach to its finalization and implementation.</p>

              <h4>Principles and Governance</h4>
              <p>Guided by the principles of inclusivity, equitable representation, and institutional sustainability, stakeholders agree to further consolidate the NGO Coalition's governance structure along clear and transparent lines. This consolidation aims to clarify roles, responsibilities, and decision-making processes to ensure accountable and participatory governance.</p>

              <h4>Institutional Strengthening and Action</h4>
              <p>The implementation of this Framework will strengthen the Coalition's institutional capacity, enhance operational effectiveness, and reinforce its standing as a credible civil society mechanism within the broader social development architecture. Signatories commit to advancing measurable and time-bound actions rather than solely declaratory statements, underpinned by solidarity, mutual respect, and shared responsibility.</p>

              <h4>Collective Commitment</h4>
              <p>This Framework continues the trajectory established at the Ottawa pre-summit (2025) and the Doha SWSS for Social Development, and it informs preparations for the 2026 Gambia Restitution Summit. These processes reaffirm our collective commitment to constructive dialogue, strengthened cooperation, and a unified vision for inclusive and sustainable social development within the United Nations framework.</p>
            </div>
          </details>

          <div class="checkbox-field" style="margin-top:16px;">
            <input type="checkbox" name="code_of_conduct" id="code_of_conduct" value="1" required>
            <div class="cb-text">
              I confirm that I am the authorized representative of my organization to review and approve the attached Framework Document and, if acceptable, to provide formal endorsement. <span class="req">*</span>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 7: DATA PROTECTION -->
      <div class="reg-section" id="sec-privacy">
        <div class="reg-section-header">
          <h2>Data Protection &amp; Privacy Notice</h2>
        </div>
        <div class="reg-section-desc">
          Your privacy and the protection of personal data are important. By submitting this form, you accept the data protection and privacy policy of the organizers.
        </div>
        <div class="reg-section-body">
          <div class="checkbox-field">
            <input type="checkbox" name="data_privacy" id="data_privacy" value="1" required>
            <div class="cb-text">
              By registering, I authorize the organizer and its partners to obtain and disclose any information concerning me, whether academic, professional, personal or otherwise, as required for participation. <span class="req">*</span>
            </div>
          </div>
        </div>
      </div>

      <!-- SECTION 8: LIABILITIES OF THE SIGNATORY -->
      <div class="reg-section" id="sec-insurance">
        <div class="reg-section-header">
          <h2>Liabilities of the Signatory</h2>
          <span>Earth Hour Award — The Gambia, 16 October 2026</span>
        </div>
        <div class="reg-section-body">

          <p style="font-size:13px;color:#0a2540;font-weight:700;margin-bottom:8px;">A. Declaration by the signatory</p>
          <div class="checkbox-field" style="margin-bottom:16px;">
            <input type="checkbox" name="terms_conditions" id="terms_conditions" value="1" required>
            <div class="cb-text">
              I declare that: I am the authorized representative of my organization to receive this Award; I will attend in-person for the Award ceremony; I am a nominee for the 2026 Earth Hour Award in The Gambia; I recognize that the organizer may support my participation with logistics if possible; I recognize that the organizer does not assume any responsibility for my dependents; I authorize the organizer and its partners to obtain and disclose any information concerning me, whether academic, professional, personal or otherwise. <span class="req">*</span>
            </div>
          </div>

          <p style="font-size:13px;color:#0a2540;font-weight:700;margin-bottom:8px;">B. Undertakings by the signatory</p>
          <div class="checkbox-field" style="margin-bottom:16px;">
            <input type="checkbox" name="undertakings" id="undertakings" value="1" required>
            <div class="cb-text">
              I undertake to: diligently follow the program and abide by the regulations of the host organization and comply with the terms and conditions detailed in the Selection Guide for the Management of Award Recipients; submit to the organizer any requested report/record relating to my community service and other related work on climate change and social development; attend the award ceremony if my nomination is accepted and return to my country afterward; not participate in any unlawful act in the host country during the period of the award; not submit any request to Immigration, Refugees, and Citizenship for any purpose other than this Agreement. <span class="req">*</span>
            </div>
          </div>

          <details class="accord">
            <summary><span class="accord-arrow">▶</span> C. Default — Read Terms</summary>
            <div class="accord-body">
              <h4>C. Default</h4>
              <p><strong>Any false statement, misconduct, or breach of this Agreement for any reason on my part will constitute default.</strong></p>
              <p>Failure to meet any of the obligations stated in this Agreement, including the regulations of the host organization and the terms and conditions detailed in the Award Selection Guide for the Management of nominees, will lead to my immediate termination.</p>
              <p>If so, I will have to return to the organizers all materials, gadgets, symbols, and finance paid on my behalf in the context of this Agreement, and the necessary procedures will be instituted without further notice or delay.</p>
            </div>
          </details>

        </div>
      </div>

      <!-- SECTION 9: CONFIRMATION -->
      <div class="reg-section" id="sec-confirmation">
        <div class="reg-section-header">
          <h2>Confirmation &amp; Acknowledgement</h2>
        </div>
        <div class="reg-section-desc">
          GAMBIA 2026 represents a deliberate continuation of our collective efforts and a transition toward a more structured, results-oriented phase of collaboration with social development stakeholders.
          This endorsement signifies our commitment to solidarity, coherence, and principled engagement, which underpin the NGO Coalition and its evolving governance framework.
        </div>
        <div class="reg-section-body">
          <div class="checkbox-field">
            <input type="checkbox" name="final_confirmation" id="final_confirmation" value="1" required>
            <div class="cb-text">
              I confirm and sign — I am the authorized representative of my organization to review, approve, and formally endorse the attached Framework Document. <span class="req">*</span>
            </div>
          </div>
        </div>
      </div>

      <!-- SUBMIT -->
      <div class="reg-submit">
        <p>All fields marked <strong style="color:#e03e3e">*</strong> are required. Your registration will be reviewed before approval.</p>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <button type="button" class="btn-clear" id="clearBtn">Clear Fields</button>
          <button type="submit" class="btn-submit" id="submitBtn">Submit Registration →</button>
        </div>
      </div>

    </form>
  </div>

  <!-- RIGHT SIDEBAR: Quick Links -->
  <div class="reg-sidebar">
    <div class="reg-sidebar-box">
      <div class="reg-sidebar-title">Quick Links</div>
      <nav class="reg-sidebar-nav">
        <a href="#">Overview</a>
        <a href="#" class="active">Registration</a>
        <a href="https://ngocsocd.org" target="_blank">GAMBIA 2026 website</a>
        <a href="https://visitthegambia.com/">Host Country webpage</a>
        <a href="#">Visa Information</a>
        <a href="https://visitthegambia.com/?page_id=5726">Travel Information</a>
        <a href="#">E-Visa Portal</a>
        <a href="https://visitthegambia.com/?page_id=3275">Accommodations</a>
        <a href="#">Data Privacy Notice</a>
        <a href="#">Code of conduct</a>
        <a href="https://visitthegambia.com/">Gambia Tours</a>
      </nav>
      <div class="reg-sidebar-contact">
        <strong>Contact</strong>
        <a href="mailto:secretariat@ngocsocd.org">secretariat@ngocsocd.org</a>
      </div>
    </div>

    <!-- Form progress ring -->
    <div class="prog-box">
      <div class="prog-box-title">Form Progress</div>
      <div class="prog-body">
        <div class="prog-ring-wrap">
          <svg viewBox="0 0 120 120">
            <defs>
              <linearGradient id="progGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%"   stop-color="#0d6e8c"/>
                <stop offset="100%" stop-color="#0a2540"/>
              </linearGradient>
            </defs>
            <circle class="prog-track" cx="60" cy="60" r="48"/>
            <circle class="prog-fill"  cx="60" cy="60" r="48" id="progFill"
                    style="stroke:url(#progGrad);"/>
          </svg>
          <div class="prog-center">
            <span class="prog-pct-num" id="progNum">0%</span>
            <span class="prog-pct-lbl">Complete</span>
          </div>
        </div>
        <div class="prog-steps" id="progSteps">
          <div class="prog-step" id="ps-0"><span class="prog-dot"></span>Representation</div>
          <div class="prog-step" id="ps-1"><span class="prog-dot"></span>Personal Data</div>
          <div class="prog-step" id="ps-2"><span class="prog-dot"></span>Visa Info</div>
          <div class="prog-step" id="ps-3"><span class="prog-dot"></span>Documents</div>
          <div class="prog-step" id="ps-4"><span class="prog-dot"></span>Accommodation</div>
          <div class="prog-step" id="ps-5"><span class="prog-dot"></span>Framework</div>
          <div class="prog-step" id="ps-6"><span class="prog-dot"></span>Data Privacy</div>
          <div class="prog-step" id="ps-7"><span class="prog-dot"></span>Liabilities</div>
          <div class="prog-step" id="ps-8"><span class="prog-dot"></span>Confirmation</div>
        </div>
      </div>
    </div>

  </div>


</div><!-- end layout -->
<?php endif; ?>

<button id="stt-btn" aria-label="Scroll to top" title="Back to top">
  <svg viewBox="0 0 56 56" fill="none" xmlns="http://www.w3.org/2000/svg" width="56" height="56">
    <defs>
      <filter id="stt-shadow" x="-25%" y="-25%" width="150%" height="150%">
        <feDropShadow dx="0" dy="2" stdDeviation="5" flood-color="rgba(0,0,0,.18)"/>
      </filter>
    </defs>
    <circle class="stt-bg"   cx="28" cy="28" r="25" fill="#fff" filter="url(#stt-shadow)"/>
    <circle cx="28" cy="28" r="22" stroke="rgba(10,37,64,.1)" stroke-width="2.5"/>
    <circle class="stt-ring" cx="28" cy="28" r="22" stroke="#0d6e8c" stroke-width="2.5"
            stroke-linecap="round" stroke-dasharray="138.23" stroke-dashoffset="138.23"
            transform="rotate(-90 28 28)" style="transition:stroke-dashoffset .12s linear;"/>
    <path   class="stt-arr"  d="M21 30.5 L28 22.5 L35 30.5" stroke="#0a2540" stroke-width="2.2"
            stroke-linecap="round" stroke-linejoin="round"/>
    <text   class="stt-pct"  x="28" y="40" fill="#0a2540" font-size="9.5" font-weight="700"
            text-anchor="middle" font-family="Inter,sans-serif">0%</text>
  </svg>
</button>

<div id="toast-container"></div>

<script>
var RECAPTCHA_SITE_KEY = <?= json_encode(RECAPTCHA_SITE_KEY) ?>;
// File drop preview (with image thumbnail for image files)
function setupFileDrop(inputId, nameId, previewId) {
  var input = document.getElementById(inputId);
  if (!input) return;
  var nameEl    = document.getElementById(nameId);
  var previewEl = previewId ? document.getElementById(previewId) : null;
  input.addEventListener('change', function () {
    var file = this.files[0];
    if (!file) return;
    nameEl.textContent = '✓ ' + file.name;
    nameEl.style.display = 'block';
    if (previewEl) {
      if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function(e) {
          previewEl.innerHTML = '<img src="' + e.target.result + '" style="max-width:100%;max-height:100px;border-radius:6px;margin-top:8px;border:1px solid #d1dce8;">';
        };
        reader.readAsDataURL(file);
      } else {
        var icons = {'application/pdf':'📋','application/msword':'📝','application/vnd.openxmlformats-officedocument.wordprocessingml.document':'📝'};
        var icon = icons[file.type] || '📄';
        previewEl.innerHTML = '<div style="font-size:32px;margin-top:8px;">' + icon + '</div>';
      }
    }
  });
}
setupFileDrop('passportInput',   'passportFileName',   'passportPreview');
setupFileDrop('nominationInput', 'nominationFileName', 'nominationPreview');

// Picture preview
document.getElementById('pictureInput').addEventListener('change', function () {
  if (this.files[0]) {
    var reader = new FileReader();
    reader.onload = function (e) {
      var img = document.getElementById('pictureImg');
      var ph  = document.querySelector('.picture-preview .ph');
      img.src = e.target.result;
      img.style.display = 'block';
      if (ph) ph.style.display = 'none';
    };
    reader.readAsDataURL(this.files[0]);
    document.getElementById('pictureFileName').textContent = '✓ ' + this.files[0].name;
    document.getElementById('pictureFileName').style.display = 'block';
  }
});

// ── Toast ──────────────────────────────────────────────────
function showToast(title, sub, isServer) {
  var c = document.getElementById('toast-container');
  var t = document.createElement('div');
  t.className = 'toast' + (isServer ? ' toast-server' : '');
  t.innerHTML =
    '<span class="toast-icon">' + (isServer ? '⚠️' : '❗') + '</span>' +
    '<div class="toast-body"><div class="toast-title">' + title + '</div>' +
    (sub ? '<div class="toast-msg">' + sub + '</div>' : '') + '</div>' +
    '<button class="toast-close" aria-label="Close">✕</button>';
  t.querySelector('.toast-close').addEventListener('click', function () { dismissToast(t); });
  c.appendChild(t);
  requestAnimationFrame(function () {
    requestAnimationFrame(function () { t.classList.add('toast-show'); });
  });
  var timer = setTimeout(function () { dismissToast(t); }, 5000);
  t._timer = timer;
}
function dismissToast(t) {
  clearTimeout(t._timer);
  t.classList.remove('toast-show');
  t.classList.add('toast-hide');
  setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 350);
}

// ── Field labels for validation messages ───────────────────
var fieldLabels = {
  representation_type:  'Representation Type',
  organisation_name:    'Organisation Name',
  gender:               'Gender',
  first_name:           'First Name',
  last_name:            'Last Name',
  email:                'Email Address',
  birth_date:           'Date of Birth',
  passport_nationality: 'Passport Nationality',
  passport_number:      'Passport Number',
  passport_expiration:  'Passport Expiration Date',
  arrival_date:         'Arrival Date in The Gambia',
  departure_date:       'Departure Date from The Gambia',
  home_address:         'Postal Address',
  address_in_country:   'Address in The Gambia',
  contact_number:       'Contact Number',
  picture:              'Profile Picture',
  passport_file:        'Passport Scan',
  nomination_letter:    'Nomination Letter',
  is_18_or_older:       'Age Confirmation (18 or older)',
  code_of_conduct:      'Framework Document Endorsement',
  data_privacy:         'Data Privacy Agreement',
  terms_conditions:     'Declaration – Section A',
  undertakings:         'Undertakings – Section B',
  final_confirmation:   'Final Confirmation & Acknowledgement',
};

// ── Client-side validation ─────────────────────────────────
function validateForm(form) {
  var errors  = [];
  var dateErrs = [];  // descriptive date messages shown as toasts

  // Required text / select / textarea / date / email / tel
  ['representation_type','organisation_name','gender','first_name','last_name',
   'email','birth_date','home_address','passport_nationality','passport_number','passport_expiration',
   'arrival_date','departure_date','address_in_country','contact_number'].forEach(function (n) {
    var el = form.querySelector('[name="' + n + '"]');
    if (el && !el.value.trim()) errors.push(n);
  });

  // File inputs
  ['picture','passport_file','nomination_letter'].forEach(function (n) {
    var el = form.querySelector('[name="' + n + '"]');
    if (el && !el.files.length) errors.push(n);
  });

  // Radio
  if (!form.querySelector('[name="is_18_or_older"]:checked')) errors.push('is_18_or_older');

  // Checkboxes
  ['code_of_conduct','data_privacy','terms_conditions','undertakings','final_confirmation'].forEach(function (n) {
    var el = form.querySelector('[name="' + n + '"]');
    if (el && !el.checked) errors.push(n);
  });

  // Date logic (only when the fields are filled)
  var birthVal  = form.querySelector('[name="birth_date"]').value;
  var passExp   = form.querySelector('[name="passport_expiration"]').value;
  var arrival   = form.querySelector('[name="arrival_date"]').value;
  var departure = form.querySelector('[name="departure_date"]').value;
  var eventStart = new Date('2026-10-12');
  var eventEnd   = new Date('2026-10-16');
  var minPassExp = new Date(eventEnd);
  minPassExp.setMonth(minPassExp.getMonth() + 6);

  if (birthVal) {
    var birth = new Date(birthVal);
    var age   = eventStart.getFullYear() - birth.getFullYear()
                - (eventStart < new Date(eventStart.getFullYear(), birth.getMonth(), birth.getDate()) ? 1 : 0);
    if (age < 18) {
      errors.push('birth_date');
      dateErrs.push('You must be at least 18 years old by 12 October 2026.');
    }
  }

  if (passExp) {
    if (new Date(passExp) < minPassExp) {
      errors.push('passport_expiration');
      dateErrs.push('Passport must be valid for at least 6 months after the event (until ' +
        minPassExp.toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'}) + ' or later).');
    }
  }

  if (arrival && departure && new Date(departure) < new Date(arrival)) {
    errors.push('departure_date');
    dateErrs.push('Departure Date cannot be before your Arrival Date.');
  }

  errors._dateErrs = dateErrs;
  return errors;
}

function markErrors(form, errors) {
  // Clear previous
  form.querySelectorAll('.f-err').forEach(function (el) { el.classList.remove('f-err'); });
  errors.forEach(function (n) {
    var el = form.querySelector('[name="' + n + '"]');
    if (el) {
      el.classList.add('f-err');
      el.addEventListener('input',  function () { el.classList.remove('f-err'); }, { once: true });
      el.addEventListener('change', function () { el.classList.remove('f-err'); }, { once: true });
    }
  });
}

// ── Birth date real-time age check ────────────────────────
(function () {
  var bd   = document.getElementById('birth_date');
  var warn = document.getElementById('birth-age-warn');
  if (!bd || !warn) return;
  var eventStart = new Date('2026-10-12');

  function checkAge() {
    var val = bd.value;
    if (!val) { warn.classList.remove('visible'); return; }
    var birth = new Date(val);
    var age   = eventStart.getFullYear() - birth.getFullYear()
                - (eventStart < new Date(eventStart.getFullYear(), birth.getMonth(), birth.getDate()) ? 1 : 0);
    if (age < 18) {
      warn.classList.add('visible');
      bd.classList.add('f-err');
    } else {
      warn.classList.remove('visible');
      bd.classList.remove('f-err');
    }
  }

  bd.addEventListener('change', checkAge);
  bd.addEventListener('input',  checkAge);
})();

// ── Duplicate name detection ──────────────────────────────
(function() {
  var firstEl = document.getElementById('first_name');
  var lastEl  = document.getElementById('last_name');
  var warn    = document.getElementById('dup-warn');
  var timer;

  function checkDuplicate() {
    clearTimeout(timer);
    var first = (firstEl.value || '').trim();
    var last  = (lastEl.value  || '').trim();
    if (first.length < 2 || last.length < 2) { warn.style.display = 'none'; return; }
    timer = setTimeout(function() {
      fetch('check_duplicate.php?first=' + encodeURIComponent(first) + '&last=' + encodeURIComponent(last))
        .then(function(r){ return r.json(); })
        .then(function(data) {
          if (data.matches && data.matches > 0) {
            warn.innerHTML = '&#9888; A registration with a similar name already exists. If this is a different person, you may continue.';
            warn.style.display = 'block';
          } else {
            warn.style.display = 'none';
          }
        }).catch(function(){ warn.style.display = 'none'; });
    }, 800);
  }

  firstEl.addEventListener('blur', checkDuplicate);
  lastEl.addEventListener('blur',  checkDuplicate);
})();

// ── Form submit ────────────────────────────────────────────
document.getElementById('regForm').addEventListener('submit', function (e) {
  e.preventDefault();
  var form = this;
  var btn  = document.getElementById('submitBtn');

  var errors = validateForm(form);
  if (errors.length > 0) {
    markErrors(form, errors);

    // Scroll to first invalid field
    var firstEl = form.querySelector('[name="' + errors[0] + '"]');
    if (firstEl) firstEl.scrollIntoView({ behavior: 'smooth', block: 'center' });

    // Show date-logic errors first, then missing-field toasts
    var dateErrs = errors._dateErrs || [];
    dateErrs.forEach(function (msg, i) {
      setTimeout(function () { showToast('Invalid date', msg, true); }, i * 130);
    });
    var offset  = dateErrs.length;
    var regular = errors.filter(function (n) { return typeof n === 'string'; });
    var show    = regular.slice(0, Math.max(0, 5 - offset));
    show.forEach(function (n, i) {
      setTimeout(function () {
        showToast(fieldLabels[n] || n, 'This field is required.');
      }, (offset + i) * 130);
    });
    var remaining = regular.length - show.length;
    if (remaining > 0) {
      setTimeout(function () {
        showToast(remaining + ' more field(s) required',
          'Please scroll through the form to complete all fields.');
      }, (offset + show.length) * 130);
    }
    return;
  }

  // All valid — show confirm dialog
  Swal.fire({
    title: 'Confirm Your Details',
    html:  'Please make sure all the information you have provided is <strong>accurate and complete</strong> before submitting.',
    icon:  'question',
    showCancelButton:  true,
    confirmButtonText: 'Yes, Submit',
    cancelButtonText:  'Review Again',
    confirmButtonColor: '#0a2540',
    cancelButtonColor:  '#6b7280',
    reverseButtons: true,
  }).then(function (result) {
    if (!result.isConfirmed) return;

    btn.disabled    = true;
    btn.textContent = 'Submitting…';

    var overlay = document.getElementById('submit-overlay');
    var hideOverlay = function() { overlay.style.display = 'none'; btn.disabled = false; btn.textContent = 'Submit Registration →'; };

    var doSubmit = function() {
      overlay.style.display = 'flex';
      fetch('process.php', { method: 'POST', body: new FormData(form) })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.success) {
          var ref = 'GAM26-' + String(data.id).padStart(5, '0');
          overlay.style.display = 'none';
          Swal.fire({
            title: 'Registration Submitted!',
            html:  'Your registration has been received and is <strong>pending review</strong>.<br><br>Reference: <strong>' + ref + '</strong>',
            icon:  'success',
            confirmButtonText:  'OK',
            confirmButtonColor: '#0a2540',
            allowOutsideClick:  false,
          }).then(function () {
            localStorage.removeItem(DRAFT_KEY);
            window.location.href = 'thankyou.php?ref=' + encodeURIComponent(ref);
          });
        } else {
          hideOverlay();
          showToast(data.message, 'Please correct this and try again.', true);
        }
      })
      .catch(function () {
        hideOverlay();
        showToast('Something went wrong', 'Please check your connection and try again.', true);
      });
    };

    // reCAPTCHA v3: get token then submit; fallback to direct submit if not loaded
    if (typeof grecaptcha !== 'undefined' && document.getElementById('recaptcha_token')) {
      grecaptcha.ready(function() {
        grecaptcha.execute(RECAPTCHA_SITE_KEY, {action: 'register'}).then(function(token) {
          document.getElementById('recaptcha_token').value = token;
          doSubmit();
        });
      });
    } else {
      doSubmit();
    }
  });
});

// ── Draft persistence ─────────────────────────────────────
var DRAFT_KEY = 'ngo_reg_draft';
var _saveTimer;

function saveDraft() {
  clearTimeout(_saveTimer);
  _saveTimer = setTimeout(function () {
    var form = document.getElementById('regForm');
    var data = {};
    form.querySelectorAll('input:not([type="file"]):not([type="radio"]):not([type="checkbox"]), select, textarea').forEach(function (el) {
      if (el.name && el.name !== 'csrf_token') data[el.name] = el.value;
    });
    form.querySelectorAll('input[type="radio"]:checked').forEach(function (el) {
      if (el.name) data[el.name] = el.value;
    });
    form.querySelectorAll('input[type="checkbox"]').forEach(function (el) {
      if (el.name) data[el.name] = el.checked;
    });
    form.querySelectorAll('input[type="file"]').forEach(function (el) {
      if (el.name && el.files[0]) data['_fn_' + el.name] = el.files[0].name;
    });
    localStorage.setItem(DRAFT_KEY, JSON.stringify(data));
  }, 300);
}

function restoreDraft() {
  var raw = localStorage.getItem(DRAFT_KEY);
  if (!raw) return;
  var data;
  try { data = JSON.parse(raw); } catch (e) { return; }
  var form = document.getElementById('regForm');
  Object.keys(data).forEach(function (key) {
    var value = data[key];
    if (key.indexOf('_fn_') === 0) {
      var fieldName = key.slice(4);
      var inputEl = form.querySelector('[name="' + fieldName + '"]');
      if (inputEl) {
        var dropEl = inputEl.closest('.file-drop');
        if (dropEl) {
          var nameDiv = dropEl.querySelector('.file-drop-name');
          if (nameDiv) {
            nameDiv.textContent = '📎 Previously selected: ' + value + ' — please re-select';
            nameDiv.style.display = 'block';
            nameDiv.style.color   = '#d97706';
          }
        }
      }
      return;
    }
    var el = form.querySelector('[name="' + key + '"]');
    if (!el) return;
    if (el.type === 'checkbox') {
      el.checked = !!value;
    } else if (el.type === 'radio') {
      var radio = form.querySelector('[name="' + key + '"][value="' + CSS.escape(String(value)) + '"]');
      if (radio) radio.checked = true;
    } else {
      el.value = value;
    }
  });
}

function clearDraft() {
  localStorage.removeItem(DRAFT_KEY);
  var form = document.getElementById('regForm');
  form.reset();
  var img = document.getElementById('pictureImg');
  var ph  = document.querySelector('.picture-preview .ph');
  if (img) { img.src = ''; img.style.display = 'none'; }
  if (ph)  ph.style.display = '';
  document.querySelectorAll('.file-drop-name').forEach(function (el) {
    el.style.display = 'none';
    el.textContent   = '';
  });
}

document.getElementById('regForm').addEventListener('input',  saveDraft);
document.getElementById('regForm').addEventListener('change', saveDraft);

document.getElementById('clearBtn').addEventListener('click', function () {
  Swal.fire({
    title: 'Clear all fields?',
    text:  'This will erase all your saved progress.',
    icon:  'warning',
    showCancelButton:   true,
    confirmButtonText:  'Yes, clear',
    cancelButtonText:   'Cancel',
    confirmButtonColor: '#e03e3e',
    cancelButtonColor:  '#6b7280',
    reverseButtons: true,
  }).then(function (result) {
    if (result.isConfirmed) clearDraft();
  });
});

restoreDraft();

// ── Countdown ─────────────────────────────────────────────
(function () {
  var TARGET = new Date('2026-10-12T00:00:00').getTime();
  var prevDay = null;
  var prevH = ['',''], prevM = ['',''], prevS = ['',''];

  function setDigit(id, ch) {
    var el = document.getElementById(id);
    if (!el || el.textContent === ch) return;
    el.textContent = ch;
    el.classList.remove('cd-flip');
    void el.offsetWidth;
    el.classList.add('cd-flip');
  }

  function setWhole(id, str) {
    var el = document.getElementById(id);
    if (!el || el.textContent === str) return;
    el.textContent = str;
    el.classList.remove('cd-flip');
    void el.offsetWidth;
    el.classList.add('cd-flip');
  }

  function tick() {
    var diff = TARGET - Date.now();
    if (diff <= 0) {
      setWhole('cd-days', '0');
      setDigit('cd-hours-t','0'); setDigit('cd-hours-u','0');
      setDigit('cd-mins-t','0');  setDigit('cd-mins-u','0');
      setDigit('cd-secs-t','0');  setDigit('cd-secs-u','0');
      return;
    }
    var d = Math.floor(diff / 86400000);
    var h = Math.floor((diff % 86400000) / 3600000);
    var m = Math.floor((diff % 3600000)  / 60000);
    var s = Math.floor((diff % 60000)    / 1000);

    // Days — full number, no padding needed
    var dStr = String(d);
    if (dStr !== prevDay) { setWhole('cd-days', dStr); prevDay = dStr; }

    // Hours, minutes, seconds — always 2 digits, animate per digit
    var hStr = String(h).padStart(2,'0');
    var mStr = String(m).padStart(2,'0');
    var sStr = String(s).padStart(2,'0');
    if (hStr[0] !== prevH[0]) { setDigit('cd-hours-t', hStr[0]); prevH[0] = hStr[0]; }
    if (hStr[1] !== prevH[1]) { setDigit('cd-hours-u', hStr[1]); prevH[1] = hStr[1]; }
    if (mStr[0] !== prevM[0]) { setDigit('cd-mins-t',  mStr[0]); prevM[0] = mStr[0]; }
    if (mStr[1] !== prevM[1]) { setDigit('cd-mins-u',  mStr[1]); prevM[1] = mStr[1]; }
    if (sStr[0] !== prevS[0]) { setDigit('cd-secs-t',  sStr[0]); prevS[0] = sStr[0]; }
    if (sStr[1] !== prevS[1]) { setDigit('cd-secs-u',  sStr[1]); prevS[1] = sStr[1]; }
  }
  tick();
  setInterval(tick, 1000);
}());

// ── Form progress ──────────────────────────────────────────
(function () {
  var SECTION_IDS = [
    'sec-representation','sec-personal','sec-visa','sec-docs',
    'sec-accommodation','sec-conduct','sec-privacy','sec-insurance','sec-confirmation'
  ];
  var CIRC = 301.6; // 2π × 48

  function updateProgress() {
    var mid = window.scrollY + window.innerHeight * 0.65;
    var done = 0;
    SECTION_IDS.forEach(function (id, i) {
      var sec = document.getElementById(id);
      var step = document.getElementById('ps-' + i);
      if (!sec) return;
      var passed = mid >= sec.offsetTop;
      if (passed) done++;
      if (step) step.classList.toggle('visited', passed);
    });
    var pct  = Math.round((done / SECTION_IDS.length) * 100);
    var fill = document.getElementById('progFill');
    var num  = document.getElementById('progNum');
    if (fill) fill.style.strokeDashoffset = CIRC * (1 - done / SECTION_IDS.length);
    if (num)  num.textContent = pct + '%';
  }

  window.addEventListener('scroll', updateProgress, { passive: true });
  updateProgress();
}());

// ── Scroll to top ─────────────────────────────────────────
(function () {
  var btn  = document.getElementById('stt-btn');
  var ring = btn.querySelector('.stt-ring');
  var pct  = btn.querySelector('.stt-pct');
  var CIRC = 138.23; // 2π × r22

  function updateStt() {
    var scrolled = window.scrollY || document.documentElement.scrollTop;
    var total    = document.documentElement.scrollHeight - window.innerHeight;
    var ratio    = total > 0 ? Math.min(scrolled / total, 1) : 0;
    var p        = Math.round(ratio * 100);
    ring.style.strokeDashoffset = CIRC * (1 - ratio);
    pct.textContent = p + '%';
    btn.classList.toggle('stt-visible', scrolled > 120);
  }

  window.addEventListener('scroll', updateStt, { passive: true });
  btn.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: 'smooth' });
  });
  updateStt();
}());

// Highlight active left nav on scroll
var sections = document.querySelectorAll('.reg-section');
var leftNavLinks = document.querySelectorAll('.reg-sidebar:first-child .reg-sidebar-nav a');
window.addEventListener('scroll', function () {
  var pos = window.scrollY + 120;
  sections.forEach(function (sec) {
    var top = sec.offsetTop, bottom = top + sec.offsetHeight;
    var id  = sec.id;
    if (pos >= top && pos < bottom) {
      leftNavLinks.forEach(function (a) {
        a.classList.toggle('active', a.getAttribute('href') === '#' + id);
      });
    }
  });
});
</script>
</body>
</html>
