<?php
// Contact form handler. With a database configured every message is saved (so nothing is lost if email fails), spam is
// throttled per visitor, and the team is notified by email. Without a database it falls back to email + a log file.
require_once __DIR__ . '/db.php';

$back = $_POST['back'] ?? '/';
// Only redirect to a local path.
if (!preg_match('#^/[A-Za-z0-9_\-./]*$#', $back) || str_contains($back, '//')) {
    $back = '/';
}

const CONTACT_MAX_PER_HOUR = 5;   // messages one visitor can send per hour
$result = 0;                      // 0 = problem with the form, 1 = sent, 2 = too many messages

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['website'])) { // honeypot
    $name    = mb_substr(trim(strip_tags((string)($_POST['name'] ?? ''))), 0, 120);
    $email   = trim((string)($_POST['email'] ?? ''));
    $message = mb_substr(trim(strip_tags((string)($_POST['message'] ?? ''))), 0, 5000);

    if ($name !== '' && $message !== '' && strlen($email) <= 190 && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $saved = false; $limited = false;
        $pdo = db_try();
        if ($pdo) {
            try {
                $ip = ip_hash();
                $recent = $pdo->prepare('SELECT COUNT(*) FROM contact_messages WHERE ip_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
                $recent->execute([$ip]);
                if ((int)$recent->fetchColumn() >= CONTACT_MAX_PER_HOUR) {
                    $limited = true;
                } else {
                    $pdo->prepare('INSERT INTO contact_messages (name, email, message, ip_hash) VALUES (?,?,?,?)')->execute([$name, $email, $message, $ip]);
                    $saved = true;
                }
            } catch (Throwable $e) { error_log('[aagahi] contact save failed: ' . $e->getMessage()); }
        }
        if ($limited) {
            $result = 2;
        } else {
            $notify = (string)(setting('notify_email', '') ?: ($ngo['email'] ?? ''));
            $mailed = false;
            if ($notify !== '') {
                $subject = 'Website enquiry from ' . preg_replace('/[\r\n]+/', ' ', $name);
                $headers = 'From: ' . $notify . "\r\nReply-To: " . $email;
                $mailed = @mail($notify, $subject, "Name: $name\nEmail: $email\n\n$message\n", $headers);
            }
            if (!$saved && !$mailed) {   // last resort so the message is never lost
                @file_put_contents(__DIR__ . '/messages.log', date('c') . "\nName: $name\nEmail: $email\n\n$message\n---\n", FILE_APPEND | LOCK_EX);
            }
            $result = 1;
        }
    }
}

header('Location: ' . $back . '?sent=' . $result . '#contact');
exit;
