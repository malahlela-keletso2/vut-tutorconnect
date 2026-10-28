<?php

define('RECIPIENT_EMAIL', 'coursecorrect40@gmail.com');

require_once __DIR__ . DIRECTORY_SEPARATOR . 'smtp_mailer.php';

$lang = (($_POST['lang'] ?? '') === 'af') ? 'af' : 'en';
$base = ($lang === 'af') ? 'af/contact.html' : 'contact.html';

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function clean(string $value): string {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('contact.html');
}

$required = ['contact-name', 'contact-email', 'subject', 'message'];
foreach ($required as $field) {
    if (!isset($_POST[$field]) || trim((string) $_POST[$field]) === '') {
        redirect($base . '?status=error');
    }
}

$name    = clean($_POST['contact-name']);
$email   = filter_var(trim($_POST['contact-email']), FILTER_VALIDATE_EMAIL);
$subject = clean($_POST['subject']);
$message = clean($_POST['message']);

if ($email === false) {
    redirect($base . '?status=error');
}

$subject = str_replace(["\r", "\n"], ' ', $subject);

$body = "New message from the CourseCorrect contact form\n"
      . "------------------------------------------------\n"
      . "Name    : {$name}\n"
      . "Email   : {$email}\n"
      . "Subject : {$subject}\n\n"
      . "Message:\n{$message}\n";

$headers = "From: CourseCorrect Website <no-reply@vut.ac.za>\r\n"
         . "Reply-To: {$email}\r\n"
         . "Content-Type: text/plain; charset=UTF-8";

$mailSubject = mb_encode_mimeheader("CourseCorrect Contact: {$subject}", 'UTF-8');

$mailSent = smtp_send_mail(RECIPIENT_EMAIL, $mailSubject, $body, $email);
if (!$mailSent) {
    $mailSent = @mail(RECIPIENT_EMAIL, "CourseCorrect Contact: {$subject}", $body, $headers);
}

$saved = false;
$file = __DIR__ . DIRECTORY_SEPARATOR . 'messages.csv';
$isNew = !file_exists($file);
$fh = @fopen($file, 'a');
if ($fh !== false) {
    if (flock($fh, LOCK_EX)) {
        if ($isNew) {
            fputcsv($fh, ['timestamp', 'name', 'email', 'subject', 'message']);
        }
        $safe = function ($v) { return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v; };
        $saved = fputcsv($fh, array_map($safe, [date('Y-m-d H:i:s'), $name, $email, $subject, $message])) !== false;
        flock($fh, LOCK_UN);
    }
    fclose($fh);
}

if (!$mailSent && !$saved) {
    redirect($base . '?status=error');
}

redirect($base . '?status=success');
