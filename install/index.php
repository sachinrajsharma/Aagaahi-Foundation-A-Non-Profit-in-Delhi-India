<?php
/**
 * install/index.php — one-time web installer: creates the tables, imports the starting content and creates the first
 * super admin. DELETE THE install/ FOLDER AS SOON AS YOU HAVE FINISHED.
 *
 * Before opening it, in includes/config.local.php set:
 *   $ngo['db']            = ['host' => 'localhost', 'name' => '...', 'user' => '...', 'pass' => '...'];
 *   $ngo['install_token'] = 'a-long-random-phrase-you-invent';
 * Then open https://your-domain/install/ and type the same phrase into the form.
 */
declare(strict_types=1);
require_once __DIR__ . '/../database/migrate.php';
require_once __DIR__ . '/../database/seed.php';

session_start();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');

$lockFile = __DIR__ . '/../includes/install.lock';
$h = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));

$token = (string)($ngo['install_token'] ?? '');
$locked = is_file($lockFile);
$checks = [];
$checks[] = ['PHP 8.0 or newer', PHP_VERSION_ID >= 80000, PHP_VERSION];
$checks[] = ['PDO MySQL driver', extension_loaded('pdo_mysql'), extension_loaded('pdo_mysql') ? 'available' : 'missing: enable pdo_mysql in hPanel > PHP Configuration'];
$checks[] = ['mbstring extension', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'available' : 'missing'];
$checks[] = ['Database details set', db_enabled(), db_enabled() ? db_config()['name'] : 'add $ngo[\'db\'] to includes/config.local.php'];
$checks[] = ['Install phrase set', $token !== '', $token !== '' ? 'set' : 'add $ngo[\'install_token\'] to includes/config.local.php'];
$conn = null; $connError = '';
if (db_enabled() && extension_loaded('pdo_mysql')) {
    try { $conn = db_connect(false); } catch (Throwable $e) { $connError = $e->getMessage(); }
}
$checks[] = ['Can connect to the database', $conn !== null, $conn ? 'connected' : ($connError ?: 'not tried yet')];
$ready = !$locked && !in_array(false, array_column($checks, 1), true);
$hasAdmin = false;
if ($conn) { try { $hasAdmin = (int)$conn->query("SELECT COUNT(*) FROM staff_users WHERE role = 'super_admin'")->fetchColumn() > 0; } catch (Throwable $e) { /* tables not created yet */ } }

$messages = []; $errors = []; $done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$ready) { $errors[] = $locked ? 'The installer has already been used.' : 'Fix the items marked in red first.'; }
    elseif (!hash_equals((string)$_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $errors[] = 'The form expired. Please reload the page and try again.'; }
    elseif (!hash_equals($token, (string)($_POST['token'] ?? ''))) { $errors[] = 'The install phrase is not the same as in includes/config.local.php.'; }
    else {
        try {
            if (!$hasAdmin) {
                $pw = (string)($_POST['password'] ?? '');
                if ($pw !== (string)($_POST['password2'] ?? '')) throw new InvalidArgumentException('The two passwords do not match.');
                if (trim((string)($_POST['name'] ?? '')) === '' || mb_strlen((string)$_POST['name']) > 120) throw new InvalidArgumentException('Please enter a name (up to 120 characters).');
                if (!filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Please enter a valid email address.');
                if (mb_strlen($pw) < 10) throw new InvalidArgumentException('The password must be at least 10 characters.');
            }
            $pdo = db_connect(false);
            $m = run_migrations($pdo);
            $messages[] = 'Tables: ' . ($m['applied'] ? 'created (' . implode(', ', $m['applied']) . ')' : 'already up to date') . '.';
            if (!empty($_POST['seed'])) {
                $s = seed_all($pdo, $ngo);
                $messages[] = "Starting content added: {$s['settings']} settings, {$s['categories']} categories, {$s['posts']} posts, {$s['events']} events with {$s['photos']} photos.";
            }
            $admins = (int)$pdo->query("SELECT COUNT(*) FROM staff_users WHERE role = 'super_admin'")->fetchColumn();
            if ($admins === 0) {
                create_staff($pdo, (string)($_POST['name'] ?? ''), (string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''), 'super_admin');
                $messages[] = 'First super admin created: ' . strtolower(trim((string)$_POST['email'])) . '.';
            } else {
                $messages[] = 'A super admin already exists, so no new account was created.';
            }
            if (@file_put_contents($lockFile, date('c') . "\n") === false) $messages[] = 'Could not write includes/install.lock. Please delete the install/ folder now.';
            $done = true;
        } catch (InvalidArgumentException $e) { $errors[] = $e->getMessage(); }
        catch (Throwable $e) { error_log('[aagahi installer] ' . $e->getMessage()); $errors[] = 'Something went wrong while installing: ' . $e->getMessage(); }
    }
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Install: <?= $h($ngo['name'] ?? 'Website') ?></title>
<style>
body{font-family:system-ui,Segoe UI,Roboto,sans-serif;background:#f5f7f4;color:#1c2b21;margin:0;padding:1.5rem}
.card{max-width:640px;margin:0 auto;background:#fff;border-radius:18px;padding:1.6rem;box-shadow:0 6px 30px #14532d14}
h1{margin:.2rem 0 .3rem;color:#14532d;font-size:1.5rem}.muted{color:#64748b;font-size:.92rem}
.row{display:flex;justify-content:space-between;gap:1rem;padding:.45rem 0;border-bottom:1px solid #eef2ee;font-size:.95rem}
.ok{color:#15803d;font-weight:700}.bad{color:#b91c1c;font-weight:700}
label{display:block;font-weight:700;margin:.9rem 0 .25rem;font-size:.9rem}
input[type=text],input[type=email],input[type=password]{width:100%;box-sizing:border-box;padding:.65rem .8rem;border:1.5px solid #cbd5d0;border-radius:10px;font:inherit}
button{margin-top:1.2rem;background:#14532d;color:#fff;border:0;border-radius:99px;padding:.8rem 1.6rem;font:inherit;font-weight:800;cursor:pointer}
.msg{background:#ecfdf5;border:1px solid #6ee7b7;border-radius:10px;padding:.6rem .9rem;margin:.5rem 0}.err{background:#fef2f2;border:1px solid #fca5a5;border-radius:10px;padding:.6rem .9rem;margin:.5rem 0;color:#991b1b}
</style></head><body><div class="card">
<h1>Set up the database</h1>
<p class="muted">One-time installer for <?= $h($ngo['name'] ?? '') ?>. It creates the tables, adds the starting content and creates your first administrator.</p>

<?php foreach ($messages as $m): ?><div class="msg"><?= $h($m) ?></div><?php endforeach; ?>
<?php foreach ($errors as $e): ?><div class="err"><?= $h($e) ?></div><?php endforeach; ?>

<?php if ($done): ?>
  <div class="msg"><b>Finished.</b> Now <b>delete the <code>install/</code> folder</b> from your hosting (hPanel &gt; File Manager) so nobody else can run it.</div>
<?php elseif ($locked): ?>
  <div class="err">The installer has already been used. Delete the <code>install/</code> folder. To run it again on purpose, delete <code>includes/install.lock</code> first.</div>
<?php else: ?>
  <h3>Checks</h3>
  <?php foreach ($checks as [$label, $okk, $detail]): ?>
    <div class="row"><span><?= $h($label) ?></span><span class="<?= $okk ? 'ok' : 'bad' ?>"><?= $okk ? '✓ ' : '✗ ' ?><?= $h($detail) ?></span></div>
  <?php endforeach; ?>
  <?php if ($ready): ?>
  <form method="post" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= $h($_SESSION['csrf']) ?>">
    <label for="token">Install phrase <span class="muted">(the one you put in config.local.php)</span></label>
    <input type="password" id="token" name="token" required>
    <?php if (!$hasAdmin): ?>
      <h3 style="margin-top:1.4rem">First administrator</h3>
      <label for="name">Full name</label><input type="text" id="name" name="name" required maxlength="120">
      <label for="email">Email</label><input type="email" id="email" name="email" required>
      <label for="password">Password <span class="muted">(at least 10 characters)</span></label><input type="password" id="password" name="password" required minlength="10">
      <label for="password2">Repeat password</label><input type="password" id="password2" name="password2" required minlength="10">
    <?php else: ?><p class="muted">An administrator already exists.</p><?php endif; ?>
    <label style="font-weight:500;margin-top:1rem"><input type="checkbox" name="seed" value="1" checked> Add the starting content (settings, the 4 blog posts and the photo gallery)</label>
    <button type="submit">Install</button>
  </form>
  <?php endif; ?>
<?php endif; ?>
</div></body></html>
