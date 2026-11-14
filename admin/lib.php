<?php
/** admin/lib.php — small helpers shared by the dashboard pages. */

function admin_url(string $page = 'home', array $params = []): string
{
    return '/admin/?' . http_build_query(array_merge(['p' => $page], $params));
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function post_str(string $key, int $max = 255): string
{
    return mb_substr(trim((string)($_POST[$key] ?? '')), 0, $max);
}

function is_post(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }

function fmt_dt(?string $dt): string
{
    if (!$dt) return '';
    $t = strtotime($dt);
    return $t ? date('j M Y, g:i a', $t) : '';
}

function status_badge(string $status): string
{
    $cls = ['published' => 'ok', 'new' => 'warn', 'scheduled' => 'info', 'replied' => 'ok', 'read' => 'info', 'draft' => 'muted', 'spam' => 'bad', 'archived' => 'muted'][$status] ?? 'muted';
    return '<span class="badge ' . $cls . '">' . h(ucfirst($status)) . '</span>';
}

/** Stores one audit line for the signed-in staff member. */
function log_action(string $action, ?string $entity = null, $id = null, ?string $detail = null): void
{
    $s = staff();
    audit($s ? (int)$s['id'] : null, $action, $entity, $id, $detail);
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** A path/URL for an image that staff may choose: our own uploads/assets, or an https address. */
function clean_image_ref(string $v): string
{
    $v = trim($v);
    if ($v === '') return '';
    if (preg_match('#^/(uploads|assets)/[A-Za-z0-9_\-./]+$#', $v) && !str_contains($v, '..')) return $v;
    if (preg_match('#^https://[^\s<>"\']+$#i', $v)) return mb_substr($v, 0, 400);
    return '';
}

function setting_labels(): array
{
    return [
        'site_name' => 'Organisation name', 'short_name' => 'Short name', 'tagline' => 'Tagline (home page headline)', 'mission' => 'Mission (one or two sentences)',
        'email' => 'Public email address', 'notify_email' => 'Email that receives contact-form messages', 'phone' => 'Phone', 'address' => 'Address',
        'donate_url' => 'Donate link (UPI / payment page)', 'reg_note' => 'Registration note (footer)', 'hero_image' => 'Home page photo (path or https link)',
    ];
}
