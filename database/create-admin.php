<?php
/**
 * database/create-admin.php — creates a staff account from a terminal (for hosts with SSH access).
 *
 *   php database/create-admin.php "Full Name" you@example.org [super_admin|admin|editor]
 *
 * The password is read from the ADMIN_PASSWORD environment variable if set; otherwise a strong one is generated and
 * printed once. Share it privately and change it after the first sign-in.
 */
require_once __DIR__ . '/seed.php';

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$args = array_slice($argv, 1);
if (count($args) < 2) { fwrite(STDERR, "Usage: php database/create-admin.php \"Full Name\" email [super_admin|admin|editor]\n"); exit(1); }
[$name, $email] = $args;
$role = $args[2] ?? 'super_admin';
$password = getenv('ADMIN_PASSWORD') ?: '';
$generated = $password === '';
if ($generated) {
    $set = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    for ($i = 0; $i < 14; $i++) $password .= $set[random_int(0, strlen($set) - 1)];
}
try {
    $id = create_staff(db_connect(false), $name, $email, $password, $role);
    echo "Created $role account #$id for " . strtolower(trim($email)) . ".\n";
    if ($generated) echo "Temporary password (shown once): $password\n";
} catch (InvalidArgumentException $e) { fwrite(STDERR, $e->getMessage() . "\n"); exit(1); }
catch (Throwable $e) { fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n"); exit(1); }
