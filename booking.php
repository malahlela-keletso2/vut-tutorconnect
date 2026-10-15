<?php

date_default_timezone_set('Africa/Johannesburg');

require_once __DIR__ . '/smtp_mailer.php';

define('RECIPIENT_EMAIL', 'tutorconnect82@gmail.com');
define('BOOKINGS_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'bookings.csv');

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function clean(string $value): string {
    $value = trim($value);
    return preg_replace('/[^\P{C}\n\r\t]+/u', '', $value) ?? '';
}

function clean_line(string $value): string {
    return str_replace(["\r", "\n", "\t"], ' ', clean($value));
}

function csv_safe(string $value): string {
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('booking.html');
}

$required = ['student-name', 'student-number', 'email', 'faculty', 'year-level',
             'course-code', 'session-type', 'preferred-date', 'preferred-time'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
        redirect('booking.html?status=error');
    }
}

$name     = clean_line($_POST['student-name']);
$number   = clean_line($_POST['student-number']);
$email    = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
$phone    = clean_line($_POST['phone'] ?? '');
$faculty  = clean_line($_POST['faculty']);
$year     = clean_line($_POST['year-level']);
$code     = strtoupper(clean_line($_POST['course-code']));
$session  = clean_line($_POST['session-type']);
$date     = clean_line($_POST['preferred-date']);
$time     = clean_line($_POST['preferred-time']);
$notes    = clean($_POST['notes'] ?? '');

if ($email === false
    || !preg_match('/^\d{9}$/', $number)
    || !preg_match('/^[A-Z0-9]{3,10}$/', $code)
    || ($phone !== '' && !preg_match('/^[0-9+()\s-]{7,15}$/', $phone))
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
    || !preg_match('/^\d{2}:00$/', $time)) {
    redirect('booking.html?status=error');
}

$d = DateTime::createFromFormat('Y-m-d', $date);
if (!$d || $d->format('Y-m-d') !== $date || $d < new DateTime('today')) {
    redirect('booking.html?status=error');
}

$endHour  = sprintf('%02d:00', ((int) substr($time, 0, 2)) + 2);
$slot     = "{$time} - {$endHour}";
$received = date('Y-m-d H:i:s');

$saved = false;
$isNew = !file_exists(BOOKINGS_FILE);
if ($fh = @fopen(BOOKINGS_FILE, 'ab')) {
    if (flock($fh, LOCK_EX)) {
        if ($isNew) {
            fputcsv($fh, ['Received', 'Name', 'Student number', 'Email', 'Phone', 'Faculty',
                          'Year', 'Course code', 'Session type', 'Date', 'Time slot', 'Notes']);
        }
        $saved = fputcsv($fh, array_map('csv_safe', [$received, $name, $number, $email, $phone, $faculty,
                          $year, $code, $session, $date, $slot, $notes])) !== false;
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

$body = "New tutoring booking from the TutorConnect website\n"
      . "------------------------------------------------\n"
      . "Received      : {$received}\n"
      . "Name          : {$name}\n"
      . "Student number: {$number}\n"
      . "Email         : {$email}\n"
      . "Phone         : " . ($phone !== '' ? $phone : '-') . "\n"
      . "Faculty       : {$faculty}\n"
      . "Year level    : {$year}\n"
      . "Course code   : {$code}\n"
      . "Session type  : {$session}\n"
      . "Preferred date: {$date}\n"
      . "Preferred time: {$slot}\n\n"
      . "Notes:\n" . ($notes !== '' ? $notes : '-') . "\n";

$subject = "TutorConnect Booking: {$code} - {$name}";
$sent = smtp_send_mail(RECIPIENT_EMAIL, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $email);

if (!$sent) {
    $headers = "From: TutorConnect Website <no-reply@vut.ac.za>\r\n"
             . "Reply-To: {$email}\r\n"
             . "Content-Type: text/plain; charset=UTF-8";
    $sent = @mail(RECIPIENT_EMAIL, $subject, $body, $headers);
}

if (!$sent) {
    smtp_log('booking.php: team email for ' . $code . ' / ' . $number . ' was NOT delivered' . ($saved ? ' (booking saved in bookings.csv)' : ' and the booking could not be saved'));
}

if ($sent) {
    $confirm = "Hi {$name},\n\n"
             . "Thank you for booking with VUT TutorConnect. We received your request:\n\n"
             . "Course        : {$code}\n"
             . "Session type  : {$session}\n"
             . "Preferred date: {$date}\n"
             . "Preferred time: {$slot}\n\n"
             . "We will contact you within 24 hours to confirm your tutor.\n\n"
             . "VUT TutorConnect - Connecting you to success\n";
    $confSubject = 'Your TutorConnect booking request';
    if (!smtp_send_mail($email, '=?UTF-8?B?' . base64_encode($confSubject) . '?=', $confirm, RECIPIENT_EMAIL)) {
        $confHeaders = "From: TutorConnect Website <no-reply@vut.ac.za>\r\n"
                     . "Reply-To: " . RECIPIENT_EMAIL . "\r\n"
                     . "Content-Type: text/plain; charset=UTF-8";
        @mail($email, $confSubject, $confirm, $confHeaders);
    }
}

redirect(($sent || $saved) ? 'booking.html?status=success' : 'booking.html?status=error');
