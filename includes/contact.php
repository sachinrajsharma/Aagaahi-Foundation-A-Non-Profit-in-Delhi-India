<?php
// Minimal, safe contact handler. Works with PHP mail(); swap for SMTP/PHPMailer if needed.
require_once __DIR__ . '/config.php';

$back = $_POST['back'] ?? '/';
// Only redirect to a local path.
if (!preg_match('#^/[A-Za-z0-9_\-./]*$#', $back) || str_contains($back, '//')) {
    $back = '/';
}

$ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['website'])) { // honeypot
    $name    = trim(strip_tags($_POST['name'] ?? ''));
    $email   = trim($_POST['email'] ?? '');
    $message = trim(strip_tags($_POST['message'] ?? ''));

    if ($name !== '' && $message !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $subject = 'Website enquiry from ' . preg_replace('/[\r\n]+/', ' ', $name);
        $body    = "Name: $name\nEmail: $email\n\n$message\n";
        $headers = 'From: ' . $ngo['email'] . "\r\nReply-To: " . $email;
        $ok = @mail($ngo['email'], $subject, $body, $headers);
        // On local machines mail() usually fails; log so you can still see submissions.
        if (!$ok) {
            @file_put_contents(__DIR__ . '/messages.log', date('c') . "\n$body\n---\n", FILE_APPEND | LOCK_EX);
            $ok = true;
        }
    }
}

header('Location: ' . $back . '?sent=' . ($ok ? '1' : '0') . '#contact');
exit;
