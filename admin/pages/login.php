<?php
/** Sign-in page. */
if (staff()) redirect(admin_url('home'));
csrf_token();          // start the session BEFORE any output, so the browser receives its session cookie
$error = '';
if (is_post()) {
    csrf_require();
    $r = staff_login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($r['ok']) redirect(admin_url('home'));
    $error = $r['error'] ?? 'Could not sign in.';
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Sign in · <?= h($GLOBALS['ngo']['name'] ?? '') ?></title><link rel="stylesheet" href="/admin/assets/admin.css"></head>
<body class="login-page"><form class="login-card" method="post" action="<?= h(admin_url('login')) ?>" autocomplete="on">
  <div class="brand big"><?= h($GLOBALS['ngo']['logo'] ?? '🌱') ?> <?= h($GLOBALS['ngo']['name'] ?? 'Dashboard') ?></div>
  <p class="muted">Staff sign-in</p>
  <?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label for="email">Email</label><input id="email" name="email" type="email" required autofocus autocomplete="username" value="<?= h($_POST['email'] ?? '') ?>">
  <label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password">
  <button class="btn" type="submit">Sign in</button>
</form></body></html>
