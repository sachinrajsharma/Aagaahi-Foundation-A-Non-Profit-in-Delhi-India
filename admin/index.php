<?php
/**
 * admin/index.php — the staff dashboard (front controller). Every page is /admin/?p=<name>.
 * Pages live in admin/pages/<name>.php; they print their content and the layout wraps it.
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/sanitize.php';
require_once __DIR__ . '/../includes/images.php';
require_once __DIR__ . '/lib.php';

header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
header('Cache-Control: no-store');

// page => permission needed (null = any signed-in staff member)
const ADMIN_PAGES = [
    'home' => null, 'account' => null, 'login' => '-', 'logout' => '-',
    'messages' => 'messages', 'volunteers' => 'messages', 'subscribers' => 'messages', 'donations' => 'donations', 'posts' => 'posts', 'post' => 'posts', 'gallery' => 'gallery', 'event' => 'gallery',
    'media' => 'media', 'staff' => 'staff', 'settings' => 'settings', 'audit' => 'audit',
];
$page = preg_replace('/[^a-z_]/', '', (string)($_GET['p'] ?? 'home'));
if (!array_key_exists($page, ADMIN_PAGES)) { http_response_code(404); $page = 'home'; }

if (!db_enabled() || db_try() === null) {
    http_response_code(503);
    exit('<p style="font-family:sans-serif;padding:2rem">The dashboard needs the database. Check <code>includes/config.local.php</code> and run <code>/install/</code> first.</p>');
}

// A POST bigger than the server's limit arrives EMPTY (PHP drops it). Say so clearly instead of "form expired".
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $lim = ini_get('post_max_size');
    $msg = 'That upload is larger than this server allows (' . $lim . '). Please use smaller or fewer photos at a time.';
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') json_out(['ok' => false, 'error' => $msg], 413);
    session_boot(); flash('err', $msg);
    redirect(staff() ? ($_SERVER['HTTP_REFERER'] ?? admin_url('home')) : admin_url('login'));
}

if ($page === 'login') { require __DIR__ . '/pages/login.php'; exit; }
if ($page === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_require(); staff_logout(); }
    redirect(admin_url('login'));
}

$me = staff();
if (!$me) redirect(admin_url('login'));
if ($me['must_change_password'] && $page !== 'account') { flash('err', 'Please choose a new password before you continue.'); redirect(admin_url('account')); }
$need = ADMIN_PAGES[$page];
if ($need !== null && !can($need, $me)) { http_response_code(403); $page = 'forbidden'; }

ob_start();
if ($page === 'forbidden') {
    echo '<div class="card"><h1>No access</h1><p>Your role does not include this page.</p></div>';
} else {
    require __DIR__ . '/pages/' . $page . '.php';
}
$content = (string)ob_get_clean();
require __DIR__ . '/layout.php';
