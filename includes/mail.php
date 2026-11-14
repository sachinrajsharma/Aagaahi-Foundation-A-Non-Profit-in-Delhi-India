<?php
/**
 * includes/mail.php — sends email (confirmations, receipts, notifications).
 *
 * Uses PHP's mail(), which works on Hostinger. The From address is no-reply@<your domain> (an address on your own domain
 * is needed or messages land in spam); replies go to the organisation's email. For local testing set
 *   $ngo['mail_transport'] = 'file';   // writes each message to includes/mail.log instead of sending
 */
require_once __DIR__ . '/db.php';

function mail_clean_header(string $s): string
{
    return trim(preg_replace('/[\r\n\0]+/', ' ', $s) ?? '');
}

function mail_from_address(): string
{
    global $ngo;
    if (!empty($ngo['mail_from'])) return mail_clean_header((string)$ngo['mail_from']);
    $host = parse_url((string)($ngo['site_url'] ?? ''), PHP_URL_HOST) ?: preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return 'no-reply@' . preg_replace('/^www\./', '', (string)$host);
}

/** Address that replies go to, and that receives notifications. */
function org_email(): string
{
    global $ngo;
    return (string)(setting('notify_email', '') ?: ($ngo['email'] ?? ''));
}

function send_mail(string $to, string $subject, string $text, ?string $html = null, ?string $replyTo = null): bool
{
    global $ngo;
    $to = mail_clean_header($to);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $subject = mail_clean_header($subject);
    $replyTo = $replyTo !== null ? mail_clean_header($replyTo) : org_email();
    $from = mail_from_address();
    $fromName = mail_clean_header((string)($ngo['name'] ?? ''));

    if (($ngo['mail_transport'] ?? '') === 'file') {
        $line = json_encode(['at' => date('c'), 'to' => $to, 'subject' => $subject, 'text' => $text, 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        return @file_put_contents($ngo['mail_log'] ?? (__DIR__ . '/mail.log'), $line, FILE_APPEND | LOCK_EX) !== false;
    }

    $headers = ['From: ' . ($fromName !== '' ? mb_encode_mimeheader($fromName, 'UTF-8') . " <$from>" : $from), 'MIME-Version: 1.0', 'X-Mailer: Aagahi'];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $headers[] = 'Reply-To: ' . $replyTo;
    if ($html !== null) {
        $b = 'b' . bin2hex(random_bytes(8));
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$b\"";
        $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$text\r\n"
              . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$html\r\n--$b--";
    } else {
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $body = $text;
    }
    $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, implode("\r\n", $headers));
    if (!$ok) error_log('[aagahi] mail() failed for a message with subject: ' . $subject);
    return $ok;
}

/** Simple branded HTML wrapper for emails. */
function mail_html(string $title, string $bodyHtml): string
{
    global $ngo;
    $n = htmlspecialchars((string)($ngo['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    return '<!doctype html><html><body style="margin:0;background:#f4f7f4;font-family:Arial,Helvetica,sans-serif;color:#1c2b21"><div style="max-width:560px;margin:0 auto;padding:24px">'
        . '<div style="font-size:20px;font-weight:bold;color:#14532d;padding:6px 0 14px">' . $n . '</div>'
        . '<div style="background:#fff;border-radius:14px;padding:26px"><h1 style="font-size:20px;margin:0 0 12px;color:#14532d">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>'
        . $bodyHtml . '</div><p style="font-size:12px;color:#64748b;text-align:center;padding:14px 8px">' . $n . '</p></div></body></html>';
}
