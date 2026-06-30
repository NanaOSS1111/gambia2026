<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

function build_nomination_letter_pdf(array $data): string {
    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml(nomination_letter_html($data), 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    return $dompdf->output();
}

function ordinal_suffix(int $n): string {
    $mod100 = $n % 100;
    if ($mod100 >= 11 && $mod100 <= 13) return 'th';
    return ['th','st','nd','rd'][$n % 10] ?? 'th';
}

function nomination_letter_html(array $data): string {
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
    $refCode   = 'G26/AMB/2026/ Ref #' . $idNum;

    $day     = (int)date('j');
    $dateStr = $day . ordinal_suffix($day) . ' ' . date('F Y');

    $enc = static function (string $p): string {
        return file_exists($p) ? 'data:image/png;base64,' . base64_encode(file_get_contents($p)) : '';
    };
    $headSrc = $enc(__DIR__ . '/asset/HeadLogo-01.png');
    $sigSrc  = $enc(__DIR__ . '/asset/signature.png');

    $headImg = $headSrc ? "<img src='$headSrc' style='width:220px;height:auto;display:block;'>" : '';
    $sigImg  = $sigSrc  ? "<img src='$sigSrc'  style='height:50px;width:auto;display:block;margin-bottom:2px;'>" : '';

    // DejaVu Sans is bundled with dompdf and supports the □ character (U+25A1)
    $cb = "<span style='font-family: DejaVu Sans;'>&#9633;</span>";

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
  @page { margin: 15mm 12.7mm 12.7mm 12.7mm; }
  * { box-sizing: border-box; }
  body  { font-family: Arial, Helvetica, sans-serif; font-size: 11pt; color: #1a1a1a; margin:0; padding:0; line-height:1.3; }
  table { border-collapse: collapse; }
  .page { padding: 0; }
  p     { margin: 0 0 9pt 0; text-align: justify; }
  .part2       { page-break-before: always; padding-top: 4px; font-size: 10pt; }
  .part2 p     { margin: 0 0 4pt 0; }
  .part2-title { font-size: 13pt; font-weight: bold; text-align: center; margin-bottom: 6px; }
  .decl-title  { font-weight: bold; margin-bottom: 2px; }
  .blank    { border-bottom: 1px solid #333; display: inline-block; min-width: 180px; }
  .blank-sm { border-bottom: 1px solid #333; display: inline-block; min-width: 110px; }
  .cb-item  { margin-bottom: 2px; text-align: justify; text-indent: -14px; padding-left: 14px; }
  .sign-blank { border-bottom: 1px solid #333; height: 16px; width: 200px; margin-bottom: 2px; }
</style>
</head>
<body>
<div class='page'>

  <!-- Letterhead -->
  <div style='margin-bottom:4px;'>$headImg</div>
  <div style='font-size:9pt;color:#1a56db;line-height:1.5;margin-bottom:14px;'>
    <div><b>211 E 43rd Street, 7th Floor New York, NY 10017, USA.</b></div>
    <div><b>info@ngocsocd.org &nbsp;&nbsp; www.ngocsocd.org &nbsp;&nbsp; +1 212-537-9303</b></div>
    <div><b>IRS 501 (C) 3 Exempt EIN 99-447-7990</b></div>
  </div>

  <!-- Date | Addressee -->
  <table style='width:100%;margin-bottom:14px;'>
    <tr>
      <td style='width:45%;vertical-align:top;'>$dateStr</td>
      <td style='width:55%;vertical-align:top;text-align:right;line-height:1.5;'>
        <table style='margin-left:auto;'>$addrRows</table>
      </td>
    </tr>
  </table>

  <!-- Notification / Ref -->
  <p style='margin-bottom:3px;padding-left:36px;'><span style='color:#c0392b;font-weight:bold;'>NOTIFICATION:</span> <b>$refCode</b></p>
  <p style='margin-bottom:10px;padding-left:36px;'><b><u>Ref:</u> <i>Nomination for the Conferment of the Unsung Heroes &ldquo;Earth Hour Award&rdquo;</i></b></p>

  <!-- Salutation -->
  <p style='margin-bottom:18pt;padding-left:36px;'>$fullName,</p>

  <!-- Body -->
  <p>Congratulations. We are pleased to inform you that you have been nominated for the Prestigious Global Civil Society Unsung Heroes <i>&ldquo;Earth Hour Award&rdquo;</i>.</p>

  <p>This honor is in recognition of your positive footprints, your community service, the meaningful social transformation and environmental stewardship. It also highlights the importance of acknowledging the contributions of your community leadership (unsung heroes) and how your work inspires others to take action.</p>

  <p>Please review the following information to proceed.</p>

  <p>The <i>&ldquo;Earth Hour Award&rdquo;</i> Selection Committee and partners of the Gambia 2026 organizing committee have identified you as a nominee for the Distinguished Service Award. The award ceremony will be held at <b>SDK Convention Center, Banjul, The Gambia, October 12&ndash;16, 2026</b>.</p>

  <p>The <b>One Billion</b> <i>&ldquo;Earth Hours Award&rdquo;</i> is a global civil society initiative designed to identify, recognize, and scale the work of 10 million grassroots changemakers, contributing one billion verified hours of climate action and social development by 2030. Verified hours refer to documented and independently confirmed hours of climate action performed by individuals or groups, as validated by accredited certifying organizations. This initiative addresses the gap by building a transparent, digital, and decentralized recognition and acceleration ecosystem.</p>

  <p>This 5-year project operates through a systematic, multi-layered process that begins with strategic planning and culminates in global recognition of 10 million people and institutions for their contributions to climate action and social development. Accredit 3,500+ certifying organizations, which are entities authorized to validate impact hours and ensure the integrity of documented climate actions. Mobilize 1 billion verified impact hours, which are hours of meaningful climate and social action validated using standardized criteria. Connect grassroots actors to funding, policy, and global platforms, enhancing their visibility and access to resources.</p>

  <p>The year 2026 marks an important milestone for the global civil society community focused on social development. It coincides with activities related to the 2025 United Nations Doha Political Declaration of the Second World Social Summit for Social Development and the launch of the civil society Earth Hour Award Gala in The Gambia. We invite you to participate as both an award nominee and a summit delegate. This provides an opportunity to join a growing network of climate ambassadors committed to collective action on today&rsquo;s social and environmental challenges. The award package includes a pin, certificate, trophy, and medal.</p>

  <p>You have demonstrated integrity, leadership, and a strong commitment to environmental stewardship and social progress. Your contributions reflect real community needs, and this nomination recognizes the lasting value of your service. Your work has created a meaningful legacy that benefits others and stands as an example of the impact we seek to promote globally.</p>

  <p>Your documented Earth Hour contributions have earned you this distinction and reflect your lasting impact on your community. Your nomination was reviewed and unanimously endorsed by a 24-member selection panel representing the Board of Trustees of PEP Africa, the Strategic Advisory Team of the NGO Coalition for Social Development, and partner organizations.</p>

  <p style='margin-bottom:0;'><b>About the NGO Coalition for Social Development.</b></p>
  <p>The NGO Coalition for Social Development is registered in the United States and headquartered in New York. A significant portion of its members hold consultative status with UN ECOSOC, UNFCCC, UNCCD, CBD, INC and UNEP. It is an alliance of civil society organizations and stakeholders, including NGOs, Indigenous Peoples&rsquo; groups, organizations of persons with disabilities, youth representatives, youth-led organizations, community-based organizations, faith-based organizations, academic institutions, worker and employer representatives, and private-sector partners. The Coalition promotes inclusion, diversity, and collaboration in support of social development. Whoever you are and wherever you come from, you will find a welcoming place within the NGO Coalition for Social Development. <a href='https://www.ngocsocd.org' style='color:#1a56db;text-decoration:none;'>www.ngocsocd.org</a></p>

  <p style='margin-bottom:0;'><b>Contribution to Climate Governance and Social Transformation.</b></p>
  <p>By the end of 2030, the One Billion Earth Hours Award will have cultivated a global culture of excellence in community service. <i>&ldquo;We are not just counting hours; we are quantifying the collective will of humanity to save itself. One billion hours is the down payment for recovering our planet and a sustainable future.&rdquo;</i></p>

  <p>This initiative offers a unique opportunity for development partners and stakeholders to invest in a proven, transparent mechanism that empowers the frontline defenders of our environment by measuring, calculating, estimating, or quantifying the carbon footprints and tracking the carbon metrics emitted. If we come together, we will ensure that civil society&rsquo;s contribution to climate action and social development is acknowledged, amplified, and scaled for the benefit of all humanity. <a href='https://www.earthhouraward.org' style='color:#1a56db;text-decoration:none;'>www.earthhouraward.org</a></p>

  <p>We sincerely congratulate you on your achievements and look forward to honoring you at this event. Additional details will be shared after we receive <b><i>(Part 2)</i></b> your acceptance letter.</p>

  <!-- Sign-off -->
  <div style='margin-top:30pt;'>
    <p style='margin-bottom:8px;'>Yours sincerely,</p>
    $sigImg
    <p style='margin-bottom:1px;'>Melvine Wajiri</p>
    <p style='margin-bottom:1px;'>The Chair, NGO Coalition for Social Development.</p>
    <p style='margin-bottom:14px;'>GCO, The Earth Hour Award Committee</p>
    <p style='text-align:center;'><b><i>&ldquo;One billion hours of action. Ten million leaders. One resilient planet.&rdquo;</i></b></p>
  </div>

  <!-- ── PART 2 (new page) ─────────────────────────────────── -->
  <div class='part2'>

    <!-- Logo only on Part 2 page — no address so content fits on one page -->
    <div style='margin-bottom:8px;'>$headImg</div>

    <p class='part2-title'>PART 2 &mdash; Liabilities of the Signatory</p>

    <p class='decl-title'>A. Declaration by the signatory.</p>
    <p>I, <span class='blank'>&nbsp;$fullName&nbsp;</span> &nbsp; NID Card/Passport No: <span class='blank-sm'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></p>
    <p style='margin-bottom:8px;'>Resident at <span class='blank'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span> &nbsp; Country <span class='blank-sm'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>, at this moment, acknowledge that I have read the following and declared that:</p>

    <p class='cb-item'>$cb I am the authorized representative of my organization to receive this Award.</p>
    <p class='cb-item'>$cb I will attend in-person for the Award ceremony.</p>
    <p class='cb-item'>$cb I am a nominee for the 2026 Earth Hour Award in The Gambia.</p>
    <p class='cb-item'>$cb I recognize that the organizer may support my participation with logistics if possible.</p>
    <p class='cb-item'>$cb I recognize that the organizer does not assume any responsibility for my dependents.</p>
    <p class='cb-item' style='margin-bottom:6px;'>$cb I authorize the organizer and its partners to obtain and disclose any information concerning me, whether academic, professionally, personal or otherwise.</p>

    <p class='decl-title'>B. We are undertaking by the signatory.</p>
    <p class='cb-item'>$cb About my participation, I undertake not to take any action to incur expenses for the Award.</p>
    <p class='cb-item'>$cb I undertake to diligently follow the program set out, abide by the regulations of the host organization, and comply with the terms and conditions detailed in the Selection Guide for the Management of Award Recipients.</p>
    <p class='cb-item'>$cb I undertake to submit to the organizer or the certifying organization/agency any requested report/record relating to my community service and other related work on climate change and social development.</p>
    <p class='cb-item'>$cb I intend to attend the award ceremony if my nomination is accepted and return to my country afterward.</p>
    <p class='cb-item'>$cb I undertake not to participate in any unlawful act in the host country during the period of the award.</p>
    <p class='cb-item' style='margin-bottom:6px;'>$cb I undertake not to submit any request to Immigration, Refugees, and Citizenship for any purpose other than this Agreement.</p>

    <p><b>C. Default:</b> Any false statement, misconduct, or breach of this Agreement for any reason on my part will constitute default.</p>
    <p style='margin-bottom:8px;'>Failure to meet any of the obligations stated in this Agreement, including the regulations of the host organization and the terms and conditions detailed in the Award Selection Guide for the Management of nominees, will lead to my immediate termination. If so, I will have to return to the organizers all materials, gadgets, symbols, finance paid on my behalf in the context of this Agreement, and the necessary procedures will be instituted without further notice or delay.</p>

    <!-- Three signature blocks (stacked vertically) -->
    <div style='margin-top:8px;'>
      <p style='margin-bottom:1px;'><b>Signature (Shortlisted Nominee).</b></p>
      <div class='sign-blank'>&nbsp;</div>
      <p style='margin-bottom:1px;'>Name: $fullName</p>
      <p style='margin-bottom:10px;'>Position: 2026 Earth Hour Award Nominee.</p>

      <p style='margin-bottom:1px;'><b>Signature (2026 Award Selection Committee)</b></p>
      <div class='sign-blank'>&nbsp;</div>
      <p style='margin-bottom:1px;'>Name:</p>
      <p style='margin-bottom:10px;'>Position: Team Lead</p>

      <p style='margin-bottom:1px;'><b>Signature: (NGO Coalition for Social Development)</b></p>
      <div class='sign-blank'>&nbsp;</div>
      <p style='margin-bottom:1px;'>Name:</p>
      <p>Position: The Chair / GCO</p>
    </div>

  </div>

</div>
</body>
</html>";
}

/* ── Request handler (only when accessed directly) ───────── */
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    session_start();
    if (!isset($_SESSION['admin'])) {
        http_response_code(403); exit('Access denied.');
    }

    require_once __DIR__ . '/db.php';

    $id = (int)($_GET['id'] ?? 0);
    if (!$id) { http_response_code(400); exit('Missing id.'); }

    $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); exit('Registration not found.'); }

    $ref = 'GAM26-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT);
    $pdf = build_nomination_letter_pdf($row);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="NominationLetter-' . $ref . '.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
}
