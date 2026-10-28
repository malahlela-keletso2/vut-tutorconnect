<?php

date_default_timezone_set('Africa/Johannesburg');

define('RECIPIENT_EMAIL', 'coursecorrect40@gmail.com');
define('TEAM_INBOX', RECIPIENT_EMAIL);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'smtp_mailer.php';
define('BOOKINGS_CSV', __DIR__ . DIRECTORY_SEPARATOR . 'bookings.csv');

$lang = (($_POST['lang'] ?? '') === 'af') ? 'af' : 'en';
$base = ($lang === 'af') ? 'af/booking.html' : 'booking.html';

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function clean_line($value, int $max = 150): string {
    $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $value);
    return mb_substr(trim($value), 0, $max);
}

function clean_text($value, int $max = 2000): string {
    $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    return mb_substr(trim($value), 0, $max);
}

function csv_safe(string $value): string {
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('booking.html');
}

$required = [
    'student-name', 'student-number', 'email', 'faculty',
    'year-level', 'course-code', 'session-type', 'preferred-date', 'preferred-time'
];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
        redirect($base . '?status=error');
    }
}

$name          = clean_line($_POST['student-name']);
$studentNumber = clean_line($_POST['student-number'], 20);
$email         = filter_var(trim((string) $_POST['email']), FILTER_VALIDATE_EMAIL);
$phone         = clean_line($_POST['phone'] ?? '', 20);
$faculty       = clean_line($_POST['faculty']);
$yearLevel     = clean_line($_POST['year-level']);
$courseCode    = strtoupper(clean_line($_POST['course-code'], 10));
$sessionType   = clean_line($_POST['session-type']);
$preferredDate = clean_line($_POST['preferred-date'], 10);
$preferredTime = clean_line($_POST['preferred-time'], 5);
$notes         = clean_text($_POST['notes'] ?? '');

if ($email === false) {
    redirect($base . '?status=error');
}

if (!preg_match('/^\d{9}$/', $studentNumber)) {
    redirect($base . '?status=error');
}

if (!preg_match('/^[A-Z0-9]{3,10}$/', $courseCode)) {
    redirect($base . '?status=error');
}

$dateObj = DateTime::createFromFormat('Y-m-d', $preferredDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $preferredDate || $preferredDate < date('Y-m-d')) {
    redirect($base . '?status=error');
}

$allowedSlots = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
if (!in_array($preferredTime, $allowedSlots, true)) {
    redirect($base . '?status=error');
}

$subject = mb_encode_mimeheader("New Tutoring Booking: {$courseCode} - {$name}", 'UTF-8');
$body = "New tutoring session booking received via the CourseCorrect website\n"
      . "------------------------------------------------------------------\n"
      . "Student Name : {$name}\n"
      . "Student No.  : {$studentNumber}\n"
      . "Email        : {$email}\n"
      . "Phone        : " . ($phone !== '' ? $phone : 'Not provided') . "\n"
      . "Faculty      : {$faculty}\n"
      . "Year Level   : {$yearLevel}\n"
      . "Course Code  : {$courseCode}\n"
      . "Session Type : {$sessionType}\n"
      . "Preferred    : {$preferredDate} at {$preferredTime}\n"
      . "Notes        : " . ($notes !== '' ? $notes : 'None') . "\n";

$headers = "From: CourseCorrect Website <no-reply@vut.ac.za>\r\n"
         . "Reply-To: {$email}\r\n"
         . "Content-Type: text/plain; charset=UTF-8";

$smtpOk = smtp_send_mail(TEAM_INBOX, $subject, $body, $email);
$mailSent = $smtpOk;
if (!$smtpOk) {
    $mailSent = @mail(RECIPIENT_EMAIL, $subject, $body, $headers);
}

$csvSaved = false;
$isNewFile = !file_exists(BOOKINGS_CSV);
$csv = @fopen(BOOKINGS_CSV, 'a');
if ($csv !== false) {
    if (flock($csv, LOCK_EX)) {
        if ($isNewFile) {
            fputcsv($csv, ['timestamp', 'name', 'student_number', 'email', 'phone', 'faculty',
                           'year_level', 'course_code', 'session_type', 'preferred_date',
                           'preferred_time', 'notes']);
        }
        $csvSaved = fputcsv($csv, array_map('csv_safe', [
            date('Y-m-d H:i:s'), $name, $studentNumber, $email, $phone,
            $faculty, $yearLevel, $courseCode, $sessionType,
            $preferredDate, $preferredTime, $notes
        ])) !== false;
        flock($csv, LOCK_UN);
    }
    fclose($csv);
}

if (!$mailSent && !$csvSaved) {
    redirect($base . '?status=error');
}

$slotLabels = [
    '08:00' => '08:00 - 10:00', '10:00' => '10:00 - 12:00', '12:00' => '12:00 - 14:00',
    '14:00' => '14:00 - 16:00', '16:00' => '16:00 - 18:00', '18:00' => '18:00 - 20:00'
];
$niceDate  = $dateObj->format('l, j F Y');
$niceTime  = $slotLabels[$preferredTime];
$firstName = explode(' ', $name)[0];

$studentSubject = mb_encode_mimeheader("Your CourseCorrect tutoring request ({$courseCode})", 'UTF-8');
$studentBody = "Dear {$firstName},\n\n"
    . "Thank you for booking a tutoring session with VUT CourseCorrect. "
    . "We have received your request and a member of our team will contact you "
    . "within 24 hours to confirm your tutor and the final session time.\n\n"
    . "YOUR BOOKING DETAILS\n"
    . "--------------------------------------\n"
    . "Name         : {$name}\n"
    . "Student No.  : {$studentNumber}\n"
    . "Faculty      : {$faculty}\n"
    . "Year Level   : {$yearLevel}\n"
    . "Course Code  : {$courseCode}\n"
    . "Session Type : {$sessionType}\n"
    . "Preferred    : {$niceDate}, {$niceTime}\n"
    . "Notes        : " . ($notes !== '' ? $notes : 'None') . "\n"
    . "--------------------------------------\n\n"
    . "Please note that this is an acknowledgement of your request, and the time "
    . "above is your preferred slot until we confirm it with you.\n\n"
    . "If any of these details are wrong, or you need to change or cancel your "
    . "booking, reply to this email or write to " . RECIPIENT_EMAIL . ".\n\n"
    . "Kind regards,\n"
    . "VUT CourseCorrect\n"
    . "Vaal University of Technology\n"
    . "Andries Potgieter Boulevard, Vanderbijlpark\n";

$studentHeaders = "From: VUT CourseCorrect <no-reply@vut.ac.za>\r\n"
                . "Reply-To: " . RECIPIENT_EMAIL . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8";

if (!$smtpOk || !smtp_send_mail($email, $studentSubject, $studentBody, RECIPIENT_EMAIL)) {
    @mail($email, $studentSubject, $studentBody, $studentHeaders);
}

redirect($base . '?status=success');
