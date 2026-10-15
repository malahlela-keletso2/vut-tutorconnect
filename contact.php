<?php

date_default_timezone_set('Africa/Johannesburg');

require_once __DIR__ . '/smtp_mailer.php';

define('RECIPIENT_EMAIL', 'tutorconnect82@gmail.com');

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function clean(string $value): string {
    $value = trim($value);
    return preg_replace('/[^\P{C}\n\r\t]+/u', '', $value) ?? '';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('contact.html');
}

$required = ['contact-name', 'contact-email', 'subject', 'message'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
        redirect('contact.html?status=error');
    }
}

$name    = clean($_POST['contact-name']);
$email   = filter_var(trim($_POST['contact-email']), FILTER_VALIDATE_EMAIL);
$subject = clean($_POST['subject']);
$message = clean($_POST['message']);

if ($email === false) {
    redirect('contact.html?status=error');
}

$name    = str_replace(["\r", "\n"], ' ', $name);
$subject = str_replace(["\r", "\n"], ' ', $subject);

$body = "New message from the TutorConnect contact form\n"
      . "------------------------------------------------\n"
      . "Name    : {$name}\n"
      . "Email   : {$email}\n"
      . "Subject : {$subject}\n\n"
      . "Message:\n{$message}\n";

$fullSubject = "TutorConnect Contact: {$subject}";
$encodedSubject = '=?UTF-8?B?' . base64_encode($fullSubject) . '?=';

$sent = smtp_send_mail(RECIPIENT_EMAIL, $encodedSubject, $body, $email);

if (!$sent) {
    $headers = "From: TutorConnect Website <no-reply@vut.ac.za>\r\n"
             . "Reply-To: {$email}\r\n"
             . "Content-Type: text/plain; charset=UTF-8";
    $sent = @mail(RECIPIENT_EMAIL, $fullSubject, $body, $headers);
}

if (!$sent) {
    smtp_log('contact.php: message from ' . $email . ' was NOT delivered');
}

redirect($sent ? 'contact.html?status=success' : 'contact.html?status=error');
