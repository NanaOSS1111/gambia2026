<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
session_start();
ob_start();
header('Content-Type: application/json');
require_once 'db.php';
require_once 'mailer.php';
require_once 'rate_limit.php';
require_once 'settings.php';

function err($msg) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => $msg, 'csrf_token' => $_SESSION['csrf_token'] ?? '']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Invalid request.');

$regStatus = is_registration_open($pdo);
if (!$regStatus['open']) err('Registration is currently closed. Please check back later.');

// Rate limit identifiers — recorded only after a successful insert below
$rl_email = strtolower(trim($_POST['email'] ?? ''));
$rl_phone = trim($_POST['contact_number'] ?? '');

// ── CSRF ──────────────────────────────────────────────────
if (
    empty($_POST['csrf_token']) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    err('Security token mismatch. Please refresh the page and try again.');
}
// Rotate token after use
$_SESSION['csrf_token'] = bin2hex(random_bytes(16));

// ── reCAPTCHA v3 ─────────────────────────────────────────────
if (!empty(RECAPTCHA_SECRET_KEY)) {
    $recaptchaToken = trim($_POST['recaptcha_token'] ?? '');
    if (empty($recaptchaToken)) err('reCAPTCHA verification failed. Please try again.');
    $rc = @file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . RECAPTCHA_SECRET_KEY . '&response=' . urlencode($recaptchaToken) . '&remoteip=' . urlencode($_SERVER['REMOTE_ADDR'] ?? ''));
    if ($rc === false || !isset(json_decode($rc, true)['success']) || !json_decode($rc, true)['success'] || (json_decode($rc, true)['score'] ?? 0) < 0.5) {
        err('reCAPTCHA score too low. Please try again or contact us if the problem persists.');
    }
}

// ── Required text fields ──────────────────────────────────
$required = [
    'representation_type', 'organisation_name', 'gender',
    'first_name', 'last_name', 'email', 'birth_date', 'home_address',
    'passport_nationality', 'passport_number', 'passport_expiration',
    'arrival_date', 'departure_date', 'address_in_country', 'contact_number',
];
foreach ($required as $f) {
    if (empty(trim($_POST[$f] ?? ''))) err("Field '$f' is required.");
}

// ── Checkboxes & radio ────────────────────────────────────
if (empty($_POST['is_18_or_older']))     err('Please confirm you are 18 or older.');
if (empty($_POST['code_of_conduct']))    err('You must confirm the Framework Document endorsement.');
if (empty($_POST['data_privacy']))       err('You must agree to the Data Privacy Notice.');
if (empty($_POST['terms_conditions']))   err('You must complete the Declaration (Section A).');
if (empty($_POST['undertakings']))       err('You must complete the Undertakings (Section B).');
if (empty($_POST['final_confirmation'])) err('You must provide your final confirmation.');

// ── Email format ──────────────────────────────────────────
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    err('Please enter a valid email address.');
}

// ── Date validations ──────────────────────────────────────
$today      = new DateTimeImmutable('today');
$eventStart = new DateTimeImmutable('2026-10-12');
$eventEnd   = new DateTimeImmutable('2026-10-16');

// Birth date — must be a real date and person must be 18+ by event start
$birthRaw = trim($_POST['birth_date']);
$birth    = DateTimeImmutable::createFromFormat('Y-m-d', $birthRaw);
if (!$birth || $birth->format('Y-m-d') !== $birthRaw) {
    err('Birth Date is not a valid date.');
}
$ageAtEvent = $birth->diff($eventStart)->y;
if ($ageAtEvent < 18) {
    err('You must be at least 18 years old by 12 October 2026 to participate.');
}

// Passport expiration — must be at least 6 months after the event ends
$passExpRaw = trim($_POST['passport_expiration']);
$passExp    = DateTimeImmutable::createFromFormat('Y-m-d', $passExpRaw);
if (!$passExp || $passExp->format('Y-m-d') !== $passExpRaw) {
    err('Passport Expiration is not a valid date.');
}
$minPassExp = $eventEnd->modify('+6 months');
if ($passExp < $minPassExp) {
    err('Your passport must be valid for at least 6 months after the event (valid until ' . $minPassExp->format('d M Y') . ' or later).');
}

// Arrival & departure — must be real dates; departure must be on or after arrival
$arrivalRaw    = trim($_POST['arrival_date']);
$departureRaw  = trim($_POST['departure_date']);
$arrival       = DateTimeImmutable::createFromFormat('Y-m-d', $arrivalRaw);
$departure     = DateTimeImmutable::createFromFormat('Y-m-d', $departureRaw);

if (!$arrival || $arrival->format('Y-m-d') !== $arrivalRaw) {
    err('Arrival Date is not a valid date.');
}
if (!$departure || $departure->format('Y-m-d') !== $departureRaw) {
    err('Departure Date is not a valid date.');
}
if ($departure < $arrival) {
    err('Departure Date cannot be before your Arrival Date.');
}

// ── Duplicate check — email, phone, name+company, passport ──
$email   = strtolower(trim($_POST['email']));
$phone   = trim($_POST['contact_number']);

$dupEmail = $pdo->prepare("SELECT id FROM registrations WHERE LOWER(email) = ?");
$dupEmail->execute([$email]);
if ($dupEmail->fetch()) {
    err('This email address is already registered. Each delegate may only register once.');
}

$dupPhone = $pdo->prepare("SELECT id FROM registrations WHERE contact_number = ?");
$dupPhone->execute([$phone]);
if ($dupPhone->fetch()) {
    err('This phone number is already registered. Each delegate may only register once.');
}

// Same full name + same organisation = same person re-registering
$dupName = $pdo->prepare(
    "SELECT id FROM registrations
     WHERE LOWER(first_name) = LOWER(?) AND LOWER(last_name) = LOWER(?) AND LOWER(organisation_name) = LOWER(?)"
);
$dupName->execute([
    trim($_POST['first_name']),
    trim($_POST['last_name']),
    trim($_POST['organisation_name']),
]);
if ($dupName->fetch()) {
    err('A registration already exists for this name and organisation. If you believe this is an error please contact the Secretariat.');
}

// Same passport number = same person regardless of name/email
$dupPassport = $pdo->prepare("SELECT id FROM registrations WHERE passport_number = ?");
$dupPassport->execute([trim($_POST['passport_number'])]);
if ($dupPassport->fetch()) {
    err('This passport number is already registered. Each delegate may only register once.');
}

// ── File uploads ──────────────────────────────────────────
function handleUpload($field, $required = true) {
    static $mimeMap = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    if (empty($_FILES[$field]['name'])) {
        if ($required) err("Please upload the required file: $field.");
        return null;
    }
    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) err("Upload error for $field (code {$file['error']}).");
    if ($file['size'] > MAX_FILE_SIZE)    err("File '$field' exceeds the 2 MB size limit.");
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $mimeMap)) err("File type '.$ext' is not allowed for $field.");

    // MIME type validation — file content must match declared extension
    $finfo        = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);
    $expectedMime = $mimeMap[$ext];
    // jpeg files may also be reported as image/jpg
    $ok = ($detectedMime === $expectedMime)
       || ($ext === 'jpg' && $detectedMime === 'image/jpg')
       || ($ext === 'docx' && $detectedMime === 'application/zip'); // docx is a zip
    if (!$ok) err("File '$field' content does not match its extension. Please upload a valid file.");

    $newName = uniqid() . '_' . preg_replace('/[^a-z0-9._-]/i', '_', basename($file['name']));
    $dest    = UPLOAD_DIR . $newName;
    if (!move_uploaded_file($file['tmp_name'], $dest)) err("Failed to save file $field. Please try again.");
    return $newName;
}

$picture           = handleUpload('picture',          true);
$passport_file     = handleUpload('passport_file',    true);
$nomination_letter = handleUpload('nomination_letter', true);

// ── Sanitise ──────────────────────────────────────────────
$s = fn($v) => htmlspecialchars(trim($v ?? ''), ENT_QUOTES, 'UTF-8');

// ── Insert ────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("
        INSERT INTO registrations
          (representation_type, organisation_name, picture, title, gender,
           first_name, last_name, position, institution, email, birth_date,
           home_address, passport_nationality, passport_number, passport_expiration,
           passport_file, nomination_letter, is_18_or_older,
           arrival_date, departure_date, address_in_country, contact_number,
           scholarship,
           code_of_conduct, data_privacy, terms_conditions, undertakings,
           final_confirmation, ip_address)
        VALUES
          (:representation_type, :organisation_name, :picture, :title, :gender,
           :first_name, :last_name, :position, :institution, :email, :birth_date,
           :home_address, :passport_nationality, :passport_number, :passport_expiration,
           :passport_file, :nomination_letter, :is_18_or_older,
           :arrival_date, :departure_date, :address_in_country, :contact_number,
           :scholarship,
           1, 1, 1, 1, 1, :ip_address)
    ");

    $stmt->execute([
        ':representation_type'  => $s($_POST['representation_type']),
        ':organisation_name'    => $s($_POST['organisation_name']),
        ':picture'              => $picture,
        ':title'                => $s($_POST['title'] ?? ''),
        ':gender'               => $s($_POST['gender']),
        ':first_name'           => $s($_POST['first_name']),
        ':last_name'            => $s($_POST['last_name']),
        ':position'             => $s($_POST['position'] ?? ''),
        ':institution'          => $s($_POST['institution'] ?? ''),
        ':email'                => strtolower(trim($_POST['email'])),
        ':birth_date'           => $birthRaw,
        ':home_address'         => $s($_POST['home_address']),
        ':passport_nationality' => $s($_POST['passport_nationality']),
        ':passport_number'      => $s($_POST['passport_number']),
        ':passport_expiration'  => $passExpRaw,
        ':passport_file'        => $passport_file,
        ':nomination_letter'    => $nomination_letter,
        ':is_18_or_older'       => 1,
        ':arrival_date'         => $arrivalRaw,
        ':departure_date'       => $departureRaw,
        ':address_in_country'   => $s($_POST['address_in_country']),
        ':contact_number'       => $phone,
        ':scholarship'          => in_array($_POST['scholarship'] ?? '', ['Accommodation', 'Airfare']) ? $_POST['scholarship'] : null,
        ':ip_address'           => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $newId    = (int)$pdo->lastInsertId();
    $emailData = array_merge($_POST, ['id' => $newId]);

    // Send JSON response to browser first, then send email in background
    ob_clean();
    echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Registration submitted successfully.', 'csrf_token' => $_SESSION['csrf_token'] ?? '']);
    header('Connection: close');
    header('Content-Length: ' . ob_get_length());
    ob_end_flush();
    flush();
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    ignore_user_abort(true);
    set_time_limit(120);

    send_confirmation_email($emailData);
    exit;

} catch (PDOException $e) {
    error_log('Registration DB error: ' . $e->getMessage());
    err('Database error. Please try again later.');
}
