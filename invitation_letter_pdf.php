<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function build_invitation_letter_pdf(array $data): string {
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml(invitation_letter_html($data), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}

function ordinal_suffix_inv(int $n): string {
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 13) return 'th';
    return ['th','st','nd','rd'][$n % 10] ?? 'th';
}

function invitation_letter_html(array $data): string {
    $title     = trim($data['title'] ?? '');
    $firstName = htmlspecialchars($data['first_name']);
    $lastName  = htmlspecialchars($data['last_name']);
    $fullName  = trim("$title $firstName $lastName");
    $position  = htmlspecialchars($data['position'] ?? '');
    $org       = htmlspecialchars($data['organisation_name'] ?? '');
    $email     = htmlspecialchars($data['email']);
    $phone     = htmlspecialchars($data['contact_number'] ?? '');
    $address   = nl2br(htmlspecialchars(trim($data['home_address'] ?? $data['address_in_country'] ?? '')));
    $idNum     = str_pad($data['id'], 5, '0', STR_PAD_LEFT);
    $ref       = 'GAM26-' . $idNum;
    $refLine   = 'G26/INV/2026/Ref #' . $idNum;

    $day     = (int)date('j');
    $dateStr = $day . ordinal_suffix_inv($day) . ' ' . date('F Y');

    $headPath = __DIR__ . '/asset/HeadLogo-01.png';
    $sigPath  = __DIR__ . '/asset/signature.png';
    $headSrc  = file_exists($headPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($headPath)) : '';
    $sigSrc   = file_exists($sigPath)  ? 'data:image/png;base64,' . base64_encode(file_get_contents($sigPath))  : '';

    $headImg = $headSrc ? "<img src='$headSrc' style='width:200px;height:auto;display:block;'>" : '';
    $sigImg  = $sigSrc  ? "<img src='$sigSrc'  style='height:52px;width:auto;display:block;margin-bottom:2px;'>" : '';

    $addrRows  = "<tr><td><b>$fullName</b></td></tr>";
    if ($position) $addrRows .= "<tr><td>$position</td></tr>";
    if ($org)      $addrRows .= "<tr><td>$org</td></tr>";
    if ($address)  $addrRows .= "<tr><td>$address</td></tr>";
    $addrRows .= "<tr><td>$email</td></tr>";
    if ($phone)    $addrRows .= "<tr><td>$phone</td></tr>";

    return "<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'>
<style>
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #1a1a1a; margin: 0; padding: 0; line-height: 1.45; }
  table { border-collapse: collapse; }
  .page { padding: 6mm 18mm 12mm 18mm; }
  .divider { border: none; border-top: 2.5px solid #0a2540; margin: 6px 0 14px 0; }
  .top-table { width: 100%; margin-bottom: 14px; }
  .date-cell { vertical-align: top; font-size: 10pt; padding-top: 2px; width: 45%; }
  .addr-cell { vertical-align: top; text-align: right; font-size: 9.5pt; line-height: 1.35; padding-left: 20%; }
  a { color: #1a56db; text-decoration: underline; }
  .ref-block { background: #f0f4f8; border-left: 3px solid #0a2540; padding: 6px 12px; margin: 10px 0 14px 0; font-size: 9.5pt; }
  .subject   { font-weight: bold; font-size: 10.5pt; margin-bottom: 10px; text-decoration: underline; }
  p { margin: 0 0 7px 0; text-align: justify; font-size: 10pt; }
  .sign-area { margin-top: 16px; font-size: 10pt; }
  .footer-bar { margin-top: 16px; border-top: 1px solid #0a2540; padding-top: 6px; font-size: 7.5pt; color: #555; text-align: center; }
  .highlight { background: #fff9e6; border: 1px solid #f59e0b; border-radius: 4px; padding: 8px 12px; margin: 10px 0; font-size: 9.5pt; }
  .theme-block { background: #eef2ff; border-left: 3px solid #1a56db; padding: 8px 12px; margin: 10px 0; font-size: 9.5pt; }
</style>
</head>
<body>
<div class='page'>

  <!-- Letterhead -->
  <div style='margin-bottom:6px;'>
    <div>$headImg</div>
    <div style='font-size:8.5pt;color:#1a56db;line-height:1.75;margin-top:4px;'>
      <div>211 E 43rd Street, 7th Floor New York, NY 10017, USA.</div>
      <div><a href='mailto:info@ngocsocd.org' style='color:#1a56db;'>info@ngocsocd.org</a>&nbsp;&nbsp;<a href='https://www.ngocsocd.org' style='color:#1a56db;'>www.ngocsocd.org</a>&nbsp;&nbsp;+1 212-537-9303</div>
      <div>IRS 501 (C) 3 Exempt EIN 99-447-7990</div>
    </div>
  </div>
  <hr class='divider'>

  <!-- Date + addressee -->
  <table class='top-table'>
    <tr>
      <td class='date-cell'>$dateStr</td>
      <td class='addr-cell'>
        <table style='margin-left:auto;'>$addrRows</table>
      </td>
    </tr>
  </table>

  <!-- Reference block -->
  <div class='ref-block'>
    <b>Ref:</b> $refLine &nbsp;&nbsp;&bull;&nbsp;&nbsp; <b>Delegate Reference:</b> $ref
  </div>

  <!-- Subject -->
  <div class='subject'>RE: Official Invitation &mdash; GAMBIA 2026 NGO Summit on Social Development<br>
  <span style='font-weight:normal;font-size:10pt;text-decoration:none;'>SDK Conference Centre, Senegambia, The Gambia &bull; 12&ndash;16 October 2026</span></div>

  <!-- Salutation -->
  <p>Dear $firstName,</p>

  <!-- Body -->
  <p>We are honored to extend a formal invitation to you and your esteemed organization to attend <b>GAMBIA 2026: NGO Restitution on the Doha Political Declaration on Social Development</b>. This high-level gathering is convened under the auspices of the Government of The Gambia, NGO Affairs Branch, and will take place in person from <b>October 12&ndash;16, 2026</b>, from 9:00 a.m. to 5:00 p.m. at the <b>SDK Conference Centre in Banjul, The Gambia</b>.</p>

  <div class='theme-block'>
    <b>Summit Theme:</b><br>
    &ldquo;Pathways and Partnerships for the Future after 30 Years: Reinforcing the 2025 Doha Political Declaration in Times of Multiple Global Crises.&rdquo;<br><br>
    This timely theme highlights the interconnected challenges facing our global community and underscores the urgent need for social solidarity, resilience, and coherent, forward-looking strategies.
  </div>

  <p>The primary objective of GAMBIA 2026 is to develop a comprehensive civil society framework of action to accelerate the implementation of the 2025 Doha Declaration. This summit offers a pivotal opportunity to shift from passive observers of global policy to active architects of its execution. By participating, your organization will help co-create a critical framework for accountability and transformative action, while forging strategic partnerships and mobilizing grassroots commitments to measurable action.</p>

  <div class='highlight'>
    <b>Key Program Highlights:</b><br>
    &bull;&nbsp;<b>Civil Society Action Week:</b> Dynamic workshops and strategy sessions led by global advocates.<br>
    &bull;&nbsp;<b>Coalition Leadership:</b> The election of the new Coalition Executive Bureau.<br>
    &bull;&nbsp;<b>The Earth Hour Award:</b> The official launch of this landmark impact initiative.<br>
    &bull;&nbsp;<b>Cultural Excursion:</b> An optional, sponsored tour exploring key historical and cultural landmarks across The Gambia.
  </div>

  <p>To facilitate your visa application, please submit this official invitation along with your organization&rsquo;s nomination letter (clearly referencing your registration number) to the Gambian Department of Immigration via email at: <a href='mailto:info.registration@gambia.gov' style='color:#1a56db;'>info.registration@gambia.gov</a>. We strongly encourage you to contact the immigration department as soon as possible to ensure timely processing.</p>

  <p>We look forward to welcoming you to The Gambia this October as we collaborate to advance real-world solutions for global social development.</p>

  <!-- Sign-off -->
  <div class='sign-area'>
    <p style='margin-bottom:10px;'>Yours sincerely,</p>
    <table width='100%' style='border-collapse:collapse;'>
      <tr>
        <td style='width:50%;vertical-align:top;padding-right:12px;'>
          {$sigImg}
          <p style='margin-bottom:2px;'><b>Melvine Wajiri</b></p>
          <p style='margin-bottom:2px;font-size:9pt;'>Chair, NGO Coalition for Social Development</p>
          <p style='margin-bottom:4px;font-size:9pt;'>GCO, The Earth Hour Award Committee</p>
          <p style='margin-bottom:1px;font-size:8pt;color:#555;'>211 E 43rd Street, 7th Floor, New York, NY 10017, USA</p>
          <p style='margin-bottom:1px;font-size:8pt;color:#555;'><a href='mailto:m.wajiri@ngocsocd.org' style='color:#1a56db;'>m.wajiri@ngocsocd.org</a> &nbsp;|&nbsp; +19726840854</p>
          <p style='font-size:8pt;color:#555;'><a href='https://www.ngocsocd.org' style='color:#1a56db;'>www.ngocsocd.org</a> &nbsp;|&nbsp; EIN 99-447-7990</p>
        </td>
        <td style='width:50%;vertical-align:top;padding-left:12px;border-left:1px solid #dde0e6;'>
          <p style='margin-bottom:2px;'><b>Ebrima Jarbo</b></p>
          <p style='margin-bottom:2px;font-size:9pt;'>Director, NGO Affairs Agency</p>
          <p style='margin-bottom:4px;font-size:9pt;'>Ministry of Land, Regional Government<br>and Religious Association, The Gambia</p>
          <p style='margin-bottom:1px;font-size:8pt;color:#555;'><a href='mailto:ebrimajarbo@gmail.com' style='color:#1a56db;'>ebrimajarbo@gmail.com</a></p>
          <p style='font-size:8pt;color:#555;'>+2207533085</p>
        </td>
      </tr>
    </table>
  </div>

  <div class='footer-bar'>
    NGO Coalition for Social Development (NGOCSOCD) &bull; 211 E 43rd Street, 7th Floor, New York, NY 10017, USA<br>
    <a href='mailto:info@ngocsocd.org' style='color:#1a56db;'>info@ngocsocd.org</a> &bull;
    <a href='https://www.ngocsocd.org' style='color:#1a56db;'>www.ngocsocd.org</a> &bull;
    +1 212-537-9303 &bull; IRS 501(C)3 Exempt &bull; EIN 99-447-7990
  </div>

</div>
</body>
</html>";
}

/* ── Request handler (only when accessed directly) ───────── */
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    session_start();
    if (!isset($_SESSION['admin'])) { http_response_code(403); exit('Access denied.'); }

    require_once __DIR__ . '/db.php';

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); exit('Missing id.'); }

    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); exit('Registration not found.'); }

    $ref = 'GAM26-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
    $pdf = build_invitation_letter_pdf($row);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="InvitationLetter-' . $ref . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
}
