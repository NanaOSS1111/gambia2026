<?php
/**
 * Confirmation PDF generator.
 *
 * SETUP (one time):
 *   1. Download TCPDF: https://github.com/tecnickcom/TCPDF/releases
 *   2. Extract the zip and place the folder at: vendor/tcpdf/
 *   3. Uncomment the lines marked UNCOMMENT below.
 */

function build_confirmation_pdf(array $data): string {
    $html = confirmation_pdf_html($data);

    // ── UNCOMMENT once vendor/tcpdf/ is in place ───────────
    // require_once __DIR__ . '/vendor/tcpdf/tcpdf.php';
    // $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    // $pdf->SetCreator('GAMBIA 2026 Registration System');
    // $pdf->SetAuthor('GAMBIA 2026 Secretariat');
    // $pdf->SetTitle('GAMBIA 2026 Registration Confirmation');
    // $pdf->SetPrintHeader(false);
    // $pdf->SetPrintFooter(false);
    // $pdf->SetMargins(20, 20, 20);
    // $pdf->AddPage();
    // $pdf->writeHTML($html, true, false, true, false, '');
    // return $pdf->Output('', 'S'); // returns PDF as string
    // ───────────────────────────────────────────────────────

    return ''; // remove this line once TCPDF is set up
}

/**
 * ── PDF CONTENT TEMPLATE ──────────────────────────────────
 * Edit this function to match your final PDF design.
 * $data contains all registration fields from the DB row.
 */
function confirmation_pdf_html(array $data): string {
    $ref  = 'GAM26-' . str_pad($data['id'], 5, '0', STR_PAD_LEFT);
    $name = htmlspecialchars($data['first_name'] . ' ' . $data['last_name']);
    $org  = htmlspecialchars($data['organisation_name']);
    $type = htmlspecialchars($data['representation_type']);
    $date = date('d F Y');

    return "
<!DOCTYPE html>
<html>
<head>
<style>
  body        { font-family: Arial, sans-serif; color: #1a2332; font-size: 13px; }
  .header     { background: #0a2540; color: #fff; padding: 24px; margin-bottom: 24px; }
  .header h1  { font-size: 20px; margin: 0 0 4px; }
  .header p   { margin: 0; font-size: 12px; opacity: .8; }
  .ref        { font-size: 16px; font-weight: bold; color: #0d6e8c; margin-bottom: 20px; }
  table       { width: 100%; border-collapse: collapse; margin: 16px 0; }
  td          { padding: 8px 10px; border-bottom: 1px solid #e8f0f8; }
  td:first-child { font-weight: bold; width: 40%; color: #4a6080; }
  .footer     { margin-top: 32px; font-size: 11px; color: #9aaabf; border-top: 1px solid #e8f0f8; padding-top: 12px; }
  .status     { display: inline-block; background: #fff3cd; color: #92600a; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; }
</style>
</head>
<body>

<div class='header'>
  <h1>GAMBIA 2026 — Registration Confirmation</h1>
  <p>NGO Summit &bull; 12–16 October 2026, Banjul, Republic of The Gambia</p>
</div>

<div class='ref'>Reference: {$ref}</div>
<span class='status'>Pending Review</span>

<p style='margin-top:16px;'>Dear <strong>{$name}</strong>,</p>
<p>Thank you for submitting your registration for the GAMBIA 2026 NGO Summit. Please keep this document for your records.
Your registration is currently pending review and you will be notified of the outcome by email.</p>

<table>
  <tr><td>Full Name</td><td>{$name}</td></tr>
  <tr><td>Organisation</td><td>{$org}</td></tr>
  <tr><td>Representation Type</td><td>{$type}</td></tr>
  <tr><td>Email</td><td>" . htmlspecialchars($data['email']) . "</td></tr>
  <tr><td>Date Submitted</td><td>{$date}</td></tr>
</table>

<div class='footer'>
  GAMBIA 2026 Registration System &bull; NGO Summit Secretariat<br>
  For queries: secretariat@ngocsocd.org
</div>

</body>
</html>";
}
