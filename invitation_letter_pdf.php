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
    $fullName  = trim(($title ? "$title " : '') . "$firstName $lastName");
    $org       = htmlspecialchars($data['organisation_name'] ?? '');
    $address   = htmlspecialchars(trim($data['home_address'] ?? $data['address_in_country'] ?? ''));
    $country   = htmlspecialchars($data['country'] ?? '');

    $day     = (int)date('j');
    $dateStr = $day . ordinal_suffix_inv($day) . ' ' . date('F Y');

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

    return "<!DOCTYPE html>
<html lang='en'>
<head><meta charset='UTF-8'>
<style>
  body    { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #1a1a1a; margin: 0; padding: 0; line-height: 1.4; }
  table   { border-collapse: collapse; }
  .page   { padding: 5mm 15mm 22mm 15mm; }
  hr.div  { border: none; border-top: 1.5px solid #0a2540; margin: 5px 0 8px 0; }
  .subj   { text-align: center; font-weight: bold; font-size: 10pt; text-decoration: underline; margin: 8px 0 7px 0; }
  p       { margin: 0 0 5px 0; text-align: justify; font-size: 9pt; }
  a       { color: #1a56db; text-decoration: underline; }
  .footer { position: fixed; bottom: 4mm; left: 15mm; right: 15mm; }
</style>
</head>
<body>
<div class='page'>

  <!-- Header logos -->
  <table width='100%'>
    <tr>
      <td style='vertical-align:top; width:65%;'>$b1Img</td>
      <td style='vertical-align:top; text-align:right; width:35%;'>$b2Img</td>
    </tr>
  </table>

  <!-- Date + addressee -->
  <table width='100%' style='margin-bottom:7px;'>
    <tr>
      <td style='vertical-align:top; width:45%; font-size:9pt;'>$dateStr</td>
      <td style='vertical-align:top; text-align:right; font-size:9pt; line-height:1.5;'>$addrBlock</td>
    </tr>
  </table>

  <!-- Subject -->
  <div class='subj'>Official Invitation Letter:</div>

  <!-- Salutation -->
  <p>Dear $firstName,</p>

  <!-- Body paragraphs — exact official text -->
  <p>We are honored to extend a formal invitation to you and your esteemed organization to attend GAMBIA 2026: NGO Restitution on the Doha Political Declaration on Social Development. This high-level gathering is convened under the auspices of the Government of The Gambia, NGO Affairs Branch, and will take place in person from October 12&ndash;16, 2026, from 9:00 a.m. to 5:00 p.m. at the SDK Conference Centre in Banjul, The Gambia.</p>

  <p>Summit Theme <em>&ldquo;Pathways and Partnerships for the Future after 30 Years: Reinforcing the 2025 Doha Political Declaration in Times of Multiple Global Crises.&rdquo;</em> This timely theme highlights the interconnected challenges facing our global community and underscores the urgent need for social solidarity, resilience, and coherent, forward-looking strategies.</p>

  <p>The primary objective of GAMBIA 2026 is to develop a comprehensive civil society framework of action to accelerate the implementation of the 2025 Doha Declaration. This summit offers a pivotal opportunity to shift from passive observers of global policy to active architects of its execution. By participating, your organization will help co-create a critical framework for accountability and transformative action, while forging strategic partnerships and mobilizing grassroots commitments to measurable action.</p>

  <p><b>Key Program Highlights:</b> Civil Society Action Week: Dynamic workshops and strategy sessions led by global advocates. Coalition Leadership: The election of the new Coalition Executive Bureau. The Earth Hour Award: The official launch of this landmark impact initiative. Cultural Excursion: An optional, sponsored tour exploring key historical and cultural landmarks across The Gambia.</p>

  <p>To facilitate your visa application, please submit this official invitation along with your organization&rsquo;s nomination letter (clearly referencing your registration number) to the Gambian Department of Immigration via email at: <a href='mailto:info.registration@gambia.gov' style='color:#1a56db;'>info.registration@gambia.gov</a>.</p>

  <p>We strongly encourage you to contact the immigration department as soon as possible to ensure timely processing.</p>

  <p>We look forward to welcoming you to The Gambia this October as we collaborate to advance real-world solutions for global social development.</p>

  <!-- Signatures -->
  <table width='100%' style='margin-top:20px;'>
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

  <!-- Footer — pinned to page bottom -->
  <table width='100%' class='footer'>
    <tr>
      <td style='vertical-align:middle; width:30%;'>$b3Img</td>
      <td style='vertical-align:middle; text-align:center; padding-right:20px;'>
        <span style='font-size:8.5pt; font-weight:bold; color:#c0392b; letter-spacing:0.03em;'>&ldquo;MOBILIZING CIVIL SOCIETY FOR BOLD SOCIAL DEVELOPMENT&rdquo;</span>
      </td>
    </tr>
  </table>

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
