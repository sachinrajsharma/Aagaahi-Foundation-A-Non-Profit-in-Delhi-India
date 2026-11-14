<?php
/**
 * includes/db.php — the website's MySQL connection (PDO) and a few small helpers.
 *
 * The site keeps working without a database: nothing here runs unless db_enabled() is true, i.e. the
 * credentials are set. Put them in includes/config.local.php (git-ignored, never committed):
 *
 *   $ngo['db'] = ['host' => 'localhost', 'name' => 'u123456789_aagahi', 'user' => 'u123456789_aagahi', 'pass' => '...'];
 *
 * On Hostinger the database name and user are shown in hPanel > Databases > MySQL Databases (both start with u + digits).
 * You can also use the environment variables AAGAHI_DB_HOST, AAGAHI_DB_NAME, AAGAHI_DB_USER, AAGAHI_DB_PASS.
 */
require_once __DIR__ . '/config.php';

function db_config(): array
{
    global $ngo;
    $c = $ngo['db'] ?? [];
    // a value set in config wins; when it is empty, the AAGAHI_DB_* environment variable is used, then the default
    $pick = static function (string $key, string $env, $default) use ($c) {
        if (isset($c[$key]) && $c[$key] !== '') return $c[$key];
        $e = getenv($env);
        return ($e !== false && $e !== '') ? $e : $default;
    };
    return [
        'host'    => (string)$pick('host', 'AAGAHI_DB_HOST', 'localhost'),
        'port'    => (int)$pick('port', 'AAGAHI_DB_PORT', 3306),
        'name'    => (string)$pick('name', 'AAGAHI_DB_NAME', ''),
        'user'    => (string)$pick('user', 'AAGAHI_DB_USER', ''),
        'pass'    => (string)$pick('pass', 'AAGAHI_DB_PASS', ''),
        'charset' => 'utf8mb4',
    ];
}

function db_enabled(): bool
{
    $c = db_config();
    return $c['name'] !== '' && $c['user'] !== '';
}

/** Opens a connection. $emulate=true allows several statements in one call (used only by the installer). */
function db_connect(bool $emulate = false): PDO
{
    $c = db_config();
    if ($c['name'] === '' || $c['user'] === '') {
        throw new RuntimeException('Database is not configured. Set $ngo[\'db\'] in includes/config.local.php.');
    }
    $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
    return new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => $emulate,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'",
    ]);
}

/** The shared connection for normal requests. */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) $pdo = db_connect(false);
    return $pdo;
}

/** Like db() but returns null (and logs) instead of throwing, so a database problem never breaks a public page. */
function db_try(): ?PDO
{
    if (!db_enabled()) return null;
    try { return db(); } catch (Throwable $e) { error_log('[aagahi] database unavailable: ' . $e->getMessage()); return null; }
}

/** One value from the settings table (cached per request); $default when the table or key is missing. */
function setting(string $key, $default = null)
{
    static $all = null;
    if ($all === null) {
        $all = [];
        $pdo = db_try();
        if ($pdo) {
            try { foreach ($pdo->query('SELECT setting_key, setting_value FROM settings') as $r) $all[$r['setting_key']] = $r['setting_value']; }
            catch (Throwable $e) { /* not installed yet */ }
        }
    }
    return array_key_exists($key, $all) && $all[$key] !== null && $all[$key] !== '' ? $all[$key] : $default;
}

/** Secret used to fingerprint visitor IPs (so we never store the IP itself). Created by the installer. */
function app_secret(): string
{
    global $ngo;
    return (string)($ngo['app_secret'] ?? setting('app_secret', '')) ?: 'aagahi-fallback-secret';
}

function client_ip(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '');
}

function ip_hash(?string $ip = null): string
{
    return hash_hmac('sha256', $ip ?? client_ip(), app_secret());
}

/** Writes one line to the audit trail. Never pass passwords or message text in $detail. */
function audit(?int $staffId, string $action, ?string $entity = null, $entityId = null, ?string $detail = null): void
{
    $pdo = db_try();
    if (!$pdo) return;
    try {
        $pdo->prepare('INSERT INTO audit_log (staff_id, action, entity, entity_id, detail, ip_hash) VALUES (?,?,?,?,?,?)')
            ->execute([$staffId, $action, $entity, $entityId === null ? null : (string)$entityId, $detail === null ? null : mb_substr($detail, 0, 500), ip_hash()]);
    } catch (Throwable $e) { error_log('[aagahi] audit failed: ' . $e->getMessage()); }
}
