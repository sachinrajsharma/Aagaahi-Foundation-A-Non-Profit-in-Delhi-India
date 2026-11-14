<?php
/**
 * includes/auth.php — staff sign-in, sessions, roles, CSRF protection and flash messages for the dashboard (/admin/).
 *
 * Roles and what they can do:
 *   super_admin : everything, including other super admins
 *   admin       : staff (not super admins), settings, audit log, plus everything an editor can do
 *   editor      : posts, gallery, media and the messages inbox
 */
require_once __DIR__ . '/db.php';

const ROLE_PERMS = [
    'super_admin' => ['posts', 'gallery', 'media', 'messages', 'staff', 'settings', 'audit', 'donations'],
    'admin'       => ['posts', 'gallery', 'media', 'messages', 'staff', 'settings', 'audit', 'donations'],
    'editor'      => ['posts', 'gallery', 'media', 'messages'],
];
const LOGIN_MAX_FAILS_PER_ACCOUNT = 5;      // then the account is locked for LOGIN_LOCK_MINUTES
const LOGIN_MAX_FAILS_PER_IP      = 10;     // failures from one visitor in LOGIN_WINDOW_MINUTES
const LOGIN_LOCK_MINUTES          = 15;
const LOGIN_WINDOW_MINUTES        = 15;
const SESSION_IDLE_SECONDS        = 7200;   // signed out after 2 hours without activity
const SESSION_MAX_SECONDS         = 43200;  // and always after 12 hours

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function is_https(): bool { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }

function session_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('aagahi_staff');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

// ── CSRF ───────────────────────────────────────────────────────────────────
function csrf_token(): string
{
    session_boot();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function csrf_valid(): bool
{
    session_boot();
    $sent = (string)($_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    return $sent !== '' && !empty($_SESSION['csrf']) && hash_equals((string)$_SESSION['csrf'], $sent);
}
/** Stops the request with a plain message if the form token is missing or wrong. Call at the top of every POST handler. */
function csrf_require(): void
{
    if (!csrf_valid()) { http_response_code(400); exit('This form has expired. Please go back, reload the page and try again.'); }
}

// ── flash messages (shown once after a redirect) ──────────────────────────
function flash(string $kind, string $text, array $extra = []): void
{
    session_boot();
    $_SESSION['flash'] = array_merge(['kind' => $kind, 'text' => $text], $extra);
}
function take_flash(): ?array
{
    session_boot();
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ── who is signed in ───────────────────────────────────────────────────────
/** The signed-in staff member (fresh from the database on every request, so a disabled or demoted account loses access at once). */
function staff(): ?array
{
    static $cache = false;
    if ($cache !== false) return $cache;
    $cache = null;
    if (!isset($_COOKIE['aagahi_staff']) && session_status() !== PHP_SESSION_ACTIVE) return null;
    session_boot();
    $id = (int)($_SESSION['staff_id'] ?? 0);
    if ($id <= 0) return null;
    $now = time();
    if ($now - (int)($_SESSION['last_seen'] ?? 0) > SESSION_IDLE_SECONDS || $now - (int)($_SESSION['login_at'] ?? 0) > SESSION_MAX_SECONDS) {
        unset($_SESSION['staff_id']); return null;
    }
    $pdo = db_try();
    if (!$pdo) return null;
    $st = $pdo->prepare('SELECT id, name, email, role, is_active, must_change_password FROM staff_users WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row || !$row['is_active']) { unset($_SESSION['staff_id']); return null; }
    $_SESSION['last_seen'] = $now;
    return $cache = $row;
}

function can(string $perm, ?array $s = null): bool
{
    $s = $s ?? staff();
    return $s !== null && in_array($perm, ROLE_PERMS[$s['role']] ?? [], true);
}

// ── signing in ─────────────────────────────────────────────────────────────
/** @return array{ok: bool, error?: string} */
function staff_login(string $email, string $password): array
{
    $pdo = db_try();
    if (!$pdo) return ['ok' => false, 'error' => 'The database is not available right now. Please try again shortly.'];
    $email = strtolower(trim($email));
    $ip = ip_hash();
    $tooMany = 'Too many attempts. Please wait ' . LOGIN_LOCK_MINUTES . ' minutes and try again.';
    $recent = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND success = 0 AND created_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)');
    $recent->execute([$ip]);
    if ((int)$recent->fetchColumn() >= LOGIN_MAX_FAILS_PER_IP) return ['ok' => false, 'error' => $tooMany];

    $st = $pdo->prepare('SELECT * FROM staff_users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && $u['locked_until'] && strtotime($u['locked_until']) > time()) return ['ok' => false, 'error' => $tooMany];

    $good = $u && $u['is_active'] && password_verify($password, $u['password_hash']);
    $log = $pdo->prepare('INSERT INTO login_attempts (email, ip_hash, success) VALUES (?,?,?)');
    $log->execute([mb_substr($email, 0, 190), $ip, $good ? 1 : 0]);
    if (!$good) {
        if ($u) {
            $fails = (int)$u['failed_logins'] + 1;
            $lock = $fails >= LOGIN_MAX_FAILS_PER_ACCOUNT ? date('Y-m-d H:i:s', time() + LOGIN_LOCK_MINUTES * 60) : null;
            $pdo->prepare('UPDATE staff_users SET failed_logins = ?, locked_until = ? WHERE id = ?')->execute([$lock ? 0 : $fails, $lock, $u['id']]);
        } else {
            password_verify($password, '$2y$10$usesomesillystringforsaltuQ2zO6Q6o1j9o0r2Q8m3yq7mEkq0lW');   // keep timing similar for unknown emails
        }
        return ['ok' => false, 'error' => 'That email and password do not match.'];
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE staff_users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    $pdo->prepare('UPDATE staff_users SET failed_logins = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?')->execute([$u['id']]);
    session_boot();
    session_regenerate_id(true);
    $_SESSION = ['staff_id' => (int)$u['id'], 'login_at' => time(), 'last_seen' => time(), 'csrf' => bin2hex(random_bytes(32))];
    audit((int)$u['id'], 'login');
    return ['ok' => true];
}

function staff_logout(): void
{
    session_boot();
    $id = (int)($_SESSION['staff_id'] ?? 0);
    if ($id) audit($id, 'logout');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
}

// ── password / staff-management rules shared by the dashboard pages ──────
const PASSWORD_MIN_LENGTH = 10;

function temp_password(int $len = 12): string
{
    $set = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $o = '';
    for ($i = 0; $i < $len; $i++) $o .= $set[random_int(0, strlen($set) - 1)];
    return $o;
}

/** May $actor change/delete $target? Admins may manage everyone except super admins. */
function can_manage_staff(array $actor, array $target): bool
{
    return $actor['role'] === 'super_admin' || $target['role'] !== 'super_admin';
}
function assignable_roles(array $actor): array
{
    return $actor['role'] === 'super_admin' ? ['super_admin', 'admin', 'editor'] : ['admin', 'editor'];
}
