<?php
/** My account: change my name and password. */
$pdo = db();
if (is_post()) {
    csrf_require();
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'profile') {
        $name = post_str('name', 120);
        if ($name === '') flash('err', 'Please enter your name.');
        else { $pdo->prepare('UPDATE staff_users SET name = ? WHERE id = ?')->execute([$name, $me['id']]); log_action('account.profile', 'staff', $me['id']); flash('ok', 'Name updated.'); }
    } elseif ($act === 'password') {
        $row = $pdo->prepare('SELECT password_hash FROM staff_users WHERE id = ?'); $row->execute([$me['id']]); $hash = (string)$row->fetchColumn();
        $new = (string)($_POST['next'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $hash)) flash('err', 'Your current password is not right.');
        elseif (mb_strlen($new) < PASSWORD_MIN_LENGTH) flash('err', 'The new password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.');
        elseif ($new !== (string)($_POST['again'] ?? '')) flash('err', 'The two new passwords do not match.');
        elseif (password_verify($new, $hash)) flash('err', 'Please choose a password you have not used just now.');
        else {
            $pdo->prepare('UPDATE staff_users SET password_hash = ?, must_change_password = 0 WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            log_action('account.password', 'staff', $me['id']);
            flash('ok', 'Password changed.');
            redirect(admin_url('home'));
        }
    }
    redirect(admin_url('account'));
}
?>
<h1>My account</h1>
<div class="cols">
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h2>Profile</h2>
    <p class="muted"><?= h($me['email']) ?> · <?= h(str_replace('_', ' ', $me['role'])) ?></p>
    <label for="name">Name</label><input id="name" name="name" value="<?= h($me['name']) ?>" maxlength="120" required>
    <button class="btn sm">Save</button>
  </form>
  <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="password" autocomplete="off">
    <h2>Change password</h2>
    <label for="current">Current password</label><input id="current" name="current" type="password" required autocomplete="current-password">
    <label for="next">New password <span class="muted">(at least <?= PASSWORD_MIN_LENGTH ?> characters)</span></label><input id="next" name="next" type="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
    <label for="again">Repeat new password</label><input id="again" name="again" type="password" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
    <button class="btn sm">Change password</button>
  </form>
</div>
