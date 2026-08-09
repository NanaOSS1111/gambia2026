<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function build_invitation_letter_pdf_v2(array $data): string {
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml(invitation_letter_html_v2($data), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}

function ordinal_suffix_v2(int $n): string {
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 13) return 'th';
    return ['th','st','nd','rd'][$n % 10] ?? 'th';
}

function invitation_letter_html_v2(array $data): string {
    $title     = trim($data['title'] ?? '');
    $firstName = htmlspecialchars($data['first_name']);
    $lastName  = htmlspecialchars($data['last_name']);
    $fullName  = trim(($title ? "$title " : '') . "$firstName $lastName");
    $org       = htmlspecialchars($data['organisation_name'] ?? '');
    $address   = htmlspecialchars(trim($data['home_address'] ?? $data['address_in_country'] ?? ''));
    $country   = htmlspecialchars($data['passport_nationality'] ?? $data['country'] ?? '');

    $day     = (int)date('j');
    $dateStr = $day . ordinal_suffix_v2($day) . ' ' . date('F Y');

    $enc = static function (string $p): string {
        return file_exists($p) ? 'data:image/png;base64,' . base64_encode(file_get_contents($p)) : '';
    };

    $b1Src   = $enc(__DIR__ . '/asset/b1.png');
    $b2Src   = $enc(__DIR__ . '/asset/b2.png');
    $b3Src   = $enc(__DIR__ . '/asset/b3.png');
    $sig1Src = $enc(__DIR__ . '/asset/signature.png');
    $sig2Src = $enc(__DIR__ . '/asset/sign1.png');

    $b1Img   = $b1Src   ? "<img src='$b1Src'   style='height:56px;width:auto;display:block;'>" : '';
    $b2Img   = $b2Src   ? "<img src='$b2Src'   style='height:56px;width:auto;display:block;'>" : '';
    $b3Img   = $b3Src   ? "<img src='$b3Src'   style='height:40px;width:auto;display:block;'>" : '';
    $sig1Img = $sig1Src ? "<img src='$sig1Src' style='height:42px;width:auto;display:block;margin-bottom:2px;'>" : '';
    $sig2Img = $sig2Src ? "<img src='$sig2Src' style='height:42px;width:auto;display:block;margin-bottom:2px;'>" : '';

    $addrBlock = "<b>$fullName,</b>";
    if ($org)     $addrBlock .= "<br>$org,";
    if ($address) $addrBlock .= "<br>$address";
    if ($country) $addrBlock .= "<br>$country";

    $header = "
    <table width='100%'>
      <tr>
        <td style='vertical-align:top; width:65%;'>$b1Img</td>
        <td style='vertical-align:top; text-align:right; width:35%;'>$b2Img</td>
      </tr>
    </table>";

    return "<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'>
<style>
  body    { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #1a1a1a; margin: 0; padding: 0; line-height: 1.5; }
  table   { border-collapse: collapse; }
  .page   { padding: 5mm 15mm 24mm 15mm; }
  p       { margin: 0 0 5px 0; text-align: justify; font-size: 9pt; }
  a       { color: #1a56db; text-decoration: underline; }
  ol      { margin: 3px 0 6px 0; padding-left: 22px; }
  ol li   { margin-bottom: 2px; }
  .footer { position: fixed; bottom: 4mm; left: 15mm; right: 15mm; }
  .pg2    { page-break-before: always; }
</style>
</head>
<body>

<!-- ══ PAGE 1: Personalised VISA & Invitation letter ══ -->
<div class='page'>
  $header

  <!-- Date (left) + Addressee (right) -->
  <table width='100%' style='margin-top:10mm; margin-bottom:14px;'>
    <tr>
      <td style='vertical-align:top; width:45%; font-size:9pt;'>$dateStr</td>
      <td style='vertical-align:top; text-align:right; font-size:9pt; line-height:1.6;'>$addrBlock</td>
    </tr>
  </table>

  <!-- Subject -->
  <p style='text-align:center; font-weight:bold; font-size:10pt; text-decoration:underline; margin:10px 0 8px 0;'>VISA Letter and Official Invitation:</p>

  <p>Dear $firstName,</p>

  <p>We are honored to extend a formal invitation to you and your esteemed organization to attend GAMBIA 2026: NGO Restitution on the Doha Political Declaration on Social Development. This high-level gathering is convened under the auspices of the Government of The Gambia, NGO Affairs Branch, and will take place in person from October 12&ndash;16, 2026, from 9:00 a.m. to 5:00 p.m. at the SDK Conference Centre in Banjul, The Gambia.</p>

  <p>Summit Theme: <em>&ldquo;Pathways and Partnerships for the Future after 30 Years: Reinforcing the 2025 Doha Political Declaration in Times of Multiple Global Crises.&rdquo;</em> This timely theme highlights the interconnected challenges facing our global community and underscores the urgent need for social solidarity, resilience, and coherent, forward-looking strategies.</p>

  <p>The primary objective of GAMBIA 2026 is to co-create a comprehensive civil society framework of action to accelerate the implementation of the 2025 Doha Declaration and the Launching of the &ldquo;Earth Hour Award&rdquo;. This summit offers a pivotal opportunity to shift from passive observers of global policy to active architects of its execution. For the summit program visit <a href='https://www.ngocsocd.org'>www.ngocsocd.org</a></p>

  <p style='font-weight:bold; margin-top:8px;'>VISA Application Instructions:</p>
  <p>For those who need visas, please submit this official invitation and your passport copy to;</p>
  <table style='margin:3px 0 5px 18px;'>
    <tr><td style='padding-bottom:1px;'><b>Name:</b>&nbsp;<em>BUBA BADJIE, Police Superintendent, Gambia Immigration Department (GID)</em></td></tr>
    <tr><td style='padding-bottom:1px;'><b>Email:</b>&nbsp;<a href='mailto:badjiebazzen@gmail.com'>badjiebazzen@gmail.com</a></td></tr>
    <tr><td><b>Subject:</b>&nbsp;GAMBIA2026 VISA WAIVER REQUEST..</td></tr>
  </table>
  <p>Visas to this summit are free of charge. We strongly encourage you to contact the immigration department as soon as possible to ensure timely processing.</p>
  <p>We look forward to welcoming you to Africa&rsquo;s smiling coast, the Gambia this October 2026.</p>

  <!-- Signatures -->
  <table width='100%' style='margin-top:16px;'>
    <tr>
      <td style='width:50%; vertical-align:top; padding-right:12px;'>
        <div style='font-size:9pt; margin-bottom:2px;'>Sign:</div>
        $sig1Img
        <p style='margin-bottom:0;'><b>Melvine Wajiri</b></p>
        <p style='margin-bottom:0; font-size:8.5pt;'>Chair, NGO Coalition for Social Development</p>
        <p style='margin-bottom:2px; font-size:8.5pt;'>GCO, The Earth Hour Award Committee</p>
        <p style='margin-bottom:0; font-size:8pt;'>211 E 43rd Street, 7th Floor New York, NY 10017, USA.</p>
        <p style='margin-bottom:0; font-size:8pt;'><a href='mailto:m.wajiri@ngocsocd.org' style='color:#1a56db;'>m.wajiri@ngocsocd.org</a> | +19726840854</p>
        <p style='font-size:8pt;'><a href='https://www.ngocsocd.org' style='color:#1a56db;'>www.ngocsocd.org</a>&nbsp;&nbsp;EIN 99-447-7990</p>
      </td>
      <td style='width:50%; vertical-align:top; padding-left:12px;'>
        <div style='font-size:9pt; margin-bottom:2px;'>Sign:</div>
        $sig2Img
        <p style='margin-bottom:0;'><b>Ebrima Jarbo</b></p>
        <p style='margin-bottom:0; font-size:8.5pt;'>Director, NGO Affairs Agency</p>
        <p style='margin-bottom:2px; font-size:8.5pt;'>Ministry of Land, Regional Government and Religious Association, The Gambia.</p>
        <p style='font-size:8pt;'><a href='mailto:ebrimajarbo@gmail.com' style='color:#1a56db;'>ebrimajarbo@gmail.com</a> | +2207533085</p>
      </td>
    </tr>
  </table>
</div>

<!-- ══ PAGE 2: Visa information (same for all delegates) ══ -->
<div class='page pg2'>
  $header

  <!-- Coloured banner -->
  <p style='text-align:center; font-weight:bold; margin:10px 0 3px 0;'>&ldquo;MOBILIZING CIVIL SOCIETY FOR BOLD SOCIAL DEVELOPMENT&rdquo;</p>
  <p style='text-align:center; font-weight:bold; text-decoration:underline; background-color:#FFD700; padding:2px 4px; margin-bottom:3px;'>Restitution of the Second World Social Summit (SWSS) on Social Development Outcome</p>
  <p style='text-align:center; font-weight:bold; color:#cc0000; margin-bottom:10px;'>The SDK Conference Center, Banjul, The Gambia, October 12-16, 2026.</p>

  <!-- Visa requirements -->
  <p><u><b>Visa requirements for participants:</b></u></p>
  <p>Participants are advised to check whether they require a visa to enter The Gambia through the following link: <a href='http://www.gid.gov.gm/visa/'>www.gid.gov.gm/visa/</a></p>

  <!-- Exemptions -->
  <p style='margin-top:7px;'><u><b>Exemptions:</b></u></p>
  <p>Kindly note: According to the official <a href='http://gid.gov.gm'>Gambia Immigration Department visa page</a>, <b>ECOWAS member states</b> allowed free entry are <b>Benin, Cabo Verde, Ivory Coast, Guinea-Bissau, Guinea Conakry, Ghana, Liberia, Nigeria, Senegal, Sierra Leone, and Togo</b>. The same official page also notes that citizens of <b>Mauritania, Mali, Niger, and Burkina Faso</b> enjoy entry privileges &ldquo;as accorded.&rdquo; [gid.gov.gm]</p>
  <p>The <a href='http://gambia.gov.gm'>Government of The Gambia immigration and visas page</a> also states that citizens of the <b>United Kingdom</b>, full members of the <b>European Union</b>, the <b>Commonwealth</b>, <b>ECOWAS</b>, and other countries with reciprocal visa exemption agreements generally do not require a visa for visits not exceeding 90 days. [gambia.gov.gm]</p>

  <!-- Visa facilitation -->
  <p style='margin-top:7px;'><b>Visa facilitation:</b></p>
  <p>For participants who are nationals of countries requiring a visa, The Gambia has put in place a contact point to simplify the process for participants including: Only the following documents are required to be attached to obtain an electronic visa (E-Visa) in advance, with a validity of 90 days from date of entry in The Gambia:</p>
  <ol>
    <li>Passport copy</li>
    <li>Visa letter and official Invitation</li>
  </ol>

  <!-- Important -->
  <p><b>Important:</b></p>
  <p>please submit the documents to;</p>
  <table style='margin:3px 0 8px 18px;'>
    <tr><td style='padding-bottom:1px;'><b>Name:</b>&nbsp;<em>BUBA BADJIE, Police Superintendent, Gambia Immigration Department (GID)</em></td></tr>
    <tr><td style='padding-bottom:1px;'><b>Email:</b>&nbsp;<a href='mailto:badjiebazzen@gmail.com' style='color:#1a56db;'>badjiebazzen@gmail.com</a></td></tr>
    <tr><td><b>Subject:</b>&nbsp;GAMBIA2026 VISA WAIVER REQUEST..</td></tr>
  </table>

  <p><b>Note 1:</b> The Organizing committee and host country government disclaim all responsibility for medical, accident and travel insurance, for compensation for death or disability compensation, for loss of or damage to personal property and for any other loss that may be incurred during travel time or the period of participation. In this context, it is strongly recommended to secure international medical, accident and travel insurance for the period of participation prior to departure.</p>

  <p style='margin-top:6px;'><b>Note 2:</b> To facilitate process at the airport in Banjul and any port of entry in the Gambia, please ensure to also bring a hard copy of the E-Visa attached to your passport.</p>
</div>

<!-- Footer pinned to bottom of every page -->
<table width='100%' class='footer'>
  <tr>
    <td style='vertical-align:middle; width:35%;'>$b3Img</td>
    <td style='vertical-align:middle; text-align:right; padding-right:5px;'>
      <span style='font-size:8.5pt; font-weight:bold; color:#c0392b; letter-spacing:0.02em;'>&ldquo;MOBILIZING CIVIL SOCIETY FOR BOLD SOCIAL DEVELOPMENT&rdquo;</span>
    </td>
  </tr>
</table>

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
    $pdf = build_invitation_letter_pdf_v2($row);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="OfficialInvitation-' . $ref . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
}
