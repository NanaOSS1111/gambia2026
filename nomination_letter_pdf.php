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

    $day    = (int)date('j');
    $dateStr = $day . ordinal_suffix($day) . ' ' . date('F Y');

    // Logos + signature as base64 for PDF (dompdf requires embedded images)
    $headPath = __DIR__ . '/asset/HeadLogo-01.png';
    $sigPath  = __DIR__ . '/asset/signature.png';
    $headSrc  = file_exists($headPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($headPath)) : '';
    $sigSrc   = file_exists($sigPath)  ? 'data:image/png;base64,' . base64_encode(file_get_contents($sigPath))  : '';

    $headImg = $headSrc ? "<img src='$headSrc' style='width:200px;height:auto;display:block;'>" : '';
    $sigImg  = $sigSrc  ? "<img src='$sigSrc'  style='height:52px;width:auto;display:block;margin-bottom:2px;'>" : '';

    // Address block rows
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
  body { font-family: Arial, Helvetica, sans-serif; font-size: 10pt; color: #1a1a1a; margin: 0; padding: 0; line-height: 1.35; }
  table { border-collapse: collapse; }
  .page { padding: 6mm 18mm 12mm 18mm; }
  .lh-table  { width: 100%; margin-bottom: 8px; }
  .lh-logo-l { width: 44%; vertical-align: middle; padding-right: 16px; }
  .lh-contact { vertical-align: middle; text-align: left; font-size: 8.5pt; color: #1a56db; line-height: 1.45; }
  .divider   { border: none; border-top: 2.5px solid #0a2540; margin: 6px 0 14px 0; }
  .top-table { width: 100%; margin-bottom: 14px; }
  .date-cell { vertical-align: top; font-size: 10pt; padding-top: 2px; width: 38%; }
  .addr-cell { vertical-align: top; text-align: right; font-size: 9.5pt; line-height: 1.35; width: 62%; padding-left: 30%; }
  a, .link   { color: #1a56db; text-decoration: underline; }
  .notif     { margin-bottom: 3px; font-size: 10pt; }
  .notif-red { color: #c0392b; font-weight: bold; }
  .ref-line  { margin-bottom: 10px; font-size: 10pt; }
  .salute    { margin-bottom: 6px; font-size: 10pt; }
  p          { margin: 0 0 6px 0; text-align: justify; font-size: 10pt; }
  .sign-area { margin-top: 14px; font-size: 10pt; }
  .part2     { page-break-before: always; border-top: 2px solid #0a2540; padding-top: 10px; margin-top: 0; }
  .part2-title { font-size: 12pt; font-weight: bold; color: #0a2540; margin-bottom: 6px; }
  .decl-title  { font-weight: bold; margin-bottom: 5px; font-size: 10.5pt; }
  .blank { border-bottom: 1px solid #333; display: inline-block; width: 200px; }
  .blank-sm { border-bottom: 1px solid #333; display: inline-block; width: 120px; }
  ol li { margin-bottom: 3px; font-size: 10pt; }
  .sign3-table { width: 100%; margin-top: 12px; font-size: 9.5pt; }
  .sign3-table td { vertical-align: top; padding-right: 12px; }
  .sign-blank { border-bottom: 1px solid #333; margin-bottom: 3px; height: 20px; }
  .footer-bar { margin-top: 14px; border-top: 1px solid #0a2540; padding-top: 6px; font-size: 7.5pt; color: #555; text-align: center; }
</style>
</head>
<body>
<div class='page'>

  <!-- Letterhead -->
  <div style='margin-bottom:6px;'>
    <div>$headImg</div>
    <div style='font-size:8.5pt;color:#1a56db;line-height:1.75;margin-top:4px;'>
      <div>211 E 43rd Street, 7th Floor New York, NY 10017, USA.</div>
      <div><a href='mailto:info@ngocsocd.org' style='color:#1a56db;'>info@ngocsocd.org</a>,&nbsp;&nbsp;<a href='https://www.ngocsocd.org' style='color:#1a56db;'>www.ngocsocd.org</a>&nbsp;&nbsp;+1 212-537-9303</div>
      <div>IRS 501 (C) 3 Exempt EIN 99-447-7990</div>
    </div>
  </div>

  <!-- Date + Address -->
  <table class='top-table'>
    <tr>
      <td class='date-cell' style='width:50%;'>$dateStr</td>
      <td class='addr-cell'>
        <table style='margin-left:auto;'>$addrRows</table>
      </td>
    </tr>
  </table>

  <!-- Reference lines -->
  <div class='notif'><span class='notif-red'>NOTIFICATION:</span> <b>$refCode</b></div>
  <div class='ref-line'><b>Ref:</b> <i>Nomination for the Conferment of the Unsung Heroes &ldquo;Earth Hour Award&rdquo;</i></div>

  <!-- Salutation -->
  <div class='salute'>$fullName,</div>

  <!-- Body -->
  <p><b>Congratulations.</b> We are pleased to inform you that you have been nominated for the Prestigious Global Civil Society Unsung Heroes <b>&ldquo;Earth Hour Award&rdquo;</b>.</p>

  <p>This honor is in recognition of your positive footprints, your community service, the meaningful social transformation and environmental stewardship. It also highlights the importance of acknowledging the contributions of your community leadership (unsung heroes) and how your work inspires others to take action.</p>

  <p>Please review the following information to proceed.</p>

  <p>The <b>&ldquo;Earth Hour Award&rdquo;</b> Selection Committee and partners of the Gambia 2026 organizing committee have identified you as a nominee for the Distinguished Service Award. The award ceremony will be held at <b>SDK Convention Center, Banjul, The Gambia, October 12&ndash;16, 2026</b>.</p>

  <p>The <b>One Billion &ldquo;Earth Hours Award&rdquo;</b> is a global civil society initiative designed to identify, recognize, and scale the work of 10 million grassroots changemakers, contributing one billion verified hours of climate action and social development by 2030. Verified hours refer to documented and independently confirmed hours of climate action performed by individuals or groups, as validated by accredited certifying organizations. This initiative addresses the gap by building a transparent, digital, and decentralized recognition and acceleration ecosystem.</p>

  <p>This 5-year project operates through a systematic, multi-layered process that begins with strategic planning and culminates in global recognition of 10 million people and institutions for their contributions to climate action and social development. Accredit 3,500+ certifying organizations, which are entities authorized to validate impact hours and ensure the integrity of documented climate actions. Mobilize 1 billion verified impact hours, which are hours of meaningful climate and social action validated using standardized criteria. Connect grassroots actors to funding, policy, and global platforms, enhancing their visibility and access to resources.</p>

  <p>The year 2026 marks an important milestone for the global civil society community focused on social development. It coincides with activities related to the 2025 United Nations Doha Political Declaration of the Second World Social Summit for Social Development and the launch of the civil society Earth Hour Award Gala in The Gambia. We invite you to participate as both an award nominee and a summit delegate. This provides an opportunity to join a growing network of climate ambassadors committed to collective action on today&rsquo;s social and environmental challenges. The award package includes a pin, certificate, trophy, and medal.</p>

  <p>You have demonstrated integrity, leadership, and a strong commitment to environmental stewardship and social progress. Your contributions reflect real community needs, and this nomination recognizes the lasting value of your service. Your work has created a meaningful legacy that benefits others and stands as an example of the impact we seek to promote globally.</p>

  <p>Your documented Earth Hour contributions have earned you this distinction and reflect your lasting impact on your community. Your nomination was reviewed and unanimously endorsed by a 24-member selection panel representing the Board of Trustees of PEP Africa, the Strategic Advisory Team of the NGO Coalition for Social Development, and partner organizations.</p>

  <p><b>About the NGO Coalition for Social Development.</b><br>
  The NGO Coalition for Social Development is registered in the United States and headquartered in New York. A significant portion of its members hold consultative status with UN ECOSOC, UNFCCC, UNCCD, CBD, INC and UNEP. It is an alliance of civil society organizations and stakeholders, including NGOs, Indigenous Peoples&rsquo; groups, organizations of persons with disabilities, youth representatives, youth-led organizations, community-based organizations, faith-based organizations, academic institutions, worker and employer representatives, and private-sector partners. The Coalition promotes inclusion, diversity, and collaboration in support of social development. Whoever you are and wherever you come from, you will find a welcoming place within the NGO Coalition for Social Development. www.ngocsocd.org</p>

  <p><b>Contribution to Climate Governance and Social Transformation.</b><br>
  By the end of 2030, the One Billion Earth Hours Award will have cultivated a global culture of excellence in community service. <i>&ldquo;We are not just counting hours; we are quantifying the collective will of humanity to save itself. One billion hours is the down payment for recovering our planet and a sustainable future.&rdquo;</i></p>

  <p>This initiative offers a unique opportunity for development partners and stakeholders to invest in a proven, transparent mechanism that empowers the frontline defenders of our environment by measuring, calculating, estimating, or quantifying the carbon footprints and tracking the carbon metrics emitted. If we come together, we will ensure that civil society&rsquo;s contribution to climate action and social development is acknowledged, amplified, and scaled for the benefit of all humanity. <a href='https://www.earthhouraward.org' style='color:#1a56db;'>www.earthhouraward.org</a></p>

  <p>We sincerely congratulate you on your achievements and look forward to honoring you at this event. Additional details will be shared after we receive <b>(Part 2)</b> your acceptance letter.</p>

  <!-- Sign-off -->
  <div class='sign-area'>
    <p style='margin-bottom:6px;'>Yours sincerely,</p>
    {$sigImg}
    <p style='margin-bottom:2px;'><b>Melvine Wajiri</b></p>
    <p style='margin-bottom:2px;'>The Chair, NGO Coalition for Social Development.</p>
    <p>GCO, The Earth Hour Award Committee</p>
    <p style='margin-top:10px;font-style:italic;font-size:9pt;'>&ldquo;One billion hours of action. Ten million leaders. One resilient planet.&rdquo;</p>
  </div>

  <!-- PART 2 -->
  <div class='part2'>
    <div class='part2-title'>PART 2 &mdash; Liabilities of the Signatory</div>
    <div class='decl-title'>Declaration by the signatory.</div>

    <p>I, <span class='blank' style='width:180px;'>&nbsp;$fullName&nbsp;</span>, &nbsp;NID Card/Passport No: <span class='blank-sm'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span></p>
    <p>Resident at <span class='blank' style='width:200px;'>&nbsp;</span> &nbsp;Country <span class='blank-sm'>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>, at this moment, acknowledge that I have read the following and declared that:</p>

    <ol>
      <li>I am the authorized representative of my organization to receive this Award.</li>
      <li>I will attend in-person for the Award ceremony.</li>
      <li>I am a nominee for the 2026 Earth Hour Award in The Gambia.</li>
      <li>I recognize that the organizer may support my participation with logistics if possible.</li>
      <li>I recognize that the organizer does not assume any responsibility for my dependents.</li>
      <li>I authorize the organizer and its partners to obtain and disclose any information concerning me, whether academic, professionally, personal or otherwise.</li>
    </ol>

    <div class='decl-title' style='margin-top:12px;'>We are undertaking by the signatory.</div>
    <ol>
      <li>About my participation, I undertake not to take any action to incur expenses for the Award.</li>
      <li>I undertake to diligently follow the program set out, abide by the regulations of the host organization, and comply with the terms and conditions detailed in the Selection Guide for the Management of Award Recipients.</li>
      <li>I undertake to submit to the organizer or the certifying organization/agency any requested report/record relating to my community service and other related work on climate change and social development.</li>
      <li>I intend to attend the award ceremony if my nomination is accepted and return to my country afterward.</li>
      <li>I undertake not to participate in any unlawful act in the host country during the period of the award.</li>
      <li>I undertake not to submit any request to Immigration, Refugees, and Citizenship for any purpose other than this Agreement.</li>
    </ol>

    <p style='margin-top:10px;'><b>Default:</b> Any false statement, misconduct, or breach of this Agreement for any reason on my part will constitute default. Failure to meet any of the obligations stated in this Agreement, including the regulations of the host organization and the terms and conditions detailed in the Award Selection Guide for the Management of nominees, will lead to my immediate termination. If so, I will have to return to the organizers all materials, gadgets, symbols, finance paid on my behalf in the context of this Agreement, and the necessary procedures will be instituted without further notice or delay.</p>

    <!-- Three signature blocks side by side -->
    <table style='width:100%;margin-top:12px;font-size:9pt;border-collapse:collapse;'>
      <tr>
        <td style='width:33%;padding-right:10px;vertical-align:top;'>
          <div style='border-bottom:1px solid #333;height:22px;margin-bottom:3px;'>&nbsp;</div>
          <div><b>Signature (Shortlisted Nominee)</b></div>
          <table style='width:100%;font-size:9pt;'><tr>
            <td style='white-space:nowrap;padding-right:4px;'>Name: $fullName</td>
          </tr><tr>
            <td style='text-align:right;white-space:nowrap;'>Position: 2026 Earth Hour Award Nominee</td>
          </tr></table>
        </td>
        <td style='width:33%;padding:0 5px;vertical-align:top;'>
          <div style='border-bottom:1px solid #333;height:22px;margin-bottom:3px;'>&nbsp;</div>
          <div><b>Signature (2026 Award Selection Committee)</b></div>
          <table style='width:100%;font-size:9pt;'><tr>
            <td style='white-space:nowrap;'>Name: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
          </tr><tr>
            <td style='text-align:right;white-space:nowrap;'>Position: Team Lead</td>
          </tr></table>
        </td>
        <td style='width:34%;padding-left:10px;vertical-align:top;'>
          <div style='border-bottom:1px solid #333;height:22px;margin-bottom:3px;'>&nbsp;</div>
          <div><b>Signature (NGO Coalition for Social Development)</b></div>
          <table style='width:100%;font-size:9pt;'><tr>
            <td style='white-space:nowrap;'>Name: &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
          </tr><tr>
            <td style='text-align:right;white-space:nowrap;'>Position: The Chair / GCO</td>
          </tr></table>
        </td>
      </tr>
    </table>
  </div>

  <div class='footer-bar'>
    NGO Coalition for Social Development (NGOCSOCD) &bull; 211 E 43rd Street, 7th Floor, New York, NY 10017, USA<br>
    <a href='mailto:info@ngocsocd.org' style='color:#1a56db;'>info@ngocsocd.org</a> &bull; <a href='https://www.ngocsocd.org' style='color:#1a56db;'>www.ngocsocd.org</a> &bull; +1 212-537-9303 &bull; IRS 501(C)3 Exempt &bull; EIN 99-447-799
  </div>

</div>
</body>
</html>";
}
