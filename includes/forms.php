<?php
/**
 * includes/forms.php — protection shared by the public forms (volunteer, newsletter, donate).
 *
 *  • a hidden "website" field that humans never see (bots fill it),
 *  • a signed time token (a form submitted in under 2 seconds, or after 2 hours, is not a person),
 *  • a limit per visitor per hour (counted in form_attempts, the visitor IP stored only as a keyed hash).
 */
require_once __DIR__ . '/db.php';

function form_token(): string
{
    $ts = (string)time();
    return $ts . '.' . substr(hash_hmac('sha256', 'form|' . $ts, app_secret()), 0, 32);
}

function form_token_ok(string $token, int $minSeconds = 2, int $maxSeconds = 7200): bool
{
    if (!preg_match('/^(\d{9,11})\.([a-f0-9]{32})$/', $token, $m)) return false;
    if (!hash_equals(substr(hash_hmac('sha256', 'form|' . $m[1], app_secret()), 0, 32), $m[2])) return false;
    $age = time() - (int)$m[1];
    return $age >= $minSeconds && $age <= $maxSeconds;
}

/** Hidden fields every public form must include: the honeypot and the time token. */
function form_hidden_fields(): string
{
    return '<input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">'
         . '<input type="hidden" name="ft" value="' . htmlspecialchars(form_token(), ENT_QUOTES) . '">';
}

/** True if the submission looks automated (honeypot filled, or token missing/forged/too fast/too old). */
function form_looks_automated(): bool
{
    return !empty($_POST['website']) || !form_token_ok((string)($_POST['ft'] ?? ''));
}

/** Records one attempt and returns false when this visitor has already sent $max in the last hour. */
function form_rate_ok(string $form, int $max, PDO $pdo): bool
{
    $ip = ip_hash();
    $c = $pdo->prepare('SELECT COUNT(*) FROM form_attempts WHERE form = ? AND ip_hash = ? AND created_at > (NOW() - INTERVAL 1 HOUR)');
    $c->execute([$form, $ip]);
    if ((int)$c->fetchColumn() >= $max) return false;
    $pdo->prepare('INSERT INTO form_attempts (form, ip_hash) VALUES (?, ?)')->execute([$form, $ip]);
    if (random_int(1, 50) === 1) $pdo->exec('DELETE FROM form_attempts WHERE created_at < (NOW() - INTERVAL 3 DAY)');   // housekeeping
    return true;
}

/** One line of plain text: tags and control characters removed, trimmed, cut to $max characters. */
function clean_line(string $s, int $max): string
{
    $s = strip_tags($s);
    $s = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s) ?? '';
    return mb_substr(trim(preg_replace('/\s+/u', ' ', $s) ?? ''), 0, $max);
}

/** Plain text that may have several lines. */
function clean_text(string $s, int $max): string
{
    $s = strip_tags(str_replace("\r\n", "\n", $s));
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $s) ?? '';
    return mb_substr(trim($s), 0, $max);
}

function valid_email_address(string $e): bool
{
    return strlen($e) <= 190 && filter_var($e, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_phone(string $p): bool
{
    return $p === '' || (bool)preg_match('/^[0-9+\-\s()]{7,30}$/', $p);
}

/** Unsubscribe link for a subscriber: derived from a secret, so any newsletter can include it without storing it. */
function unsubscribe_token(int $id): string
{
    return $id . '.' . substr(hash_hmac('sha256', 'unsub|' . $id, app_secret()), 0, 32);
}

function unsubscribe_id_from_token(string $t): ?int
{
    if (!preg_match('/^(\d{1,10})\.([a-f0-9]{32})$/', $t, $m)) return null;
    return hash_equals(substr(hash_hmac('sha256', 'unsub|' . $m[1], app_secret()), 0, 32), $m[2]) ? (int)$m[1] : null;
}
