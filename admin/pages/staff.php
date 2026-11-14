<?php
/** Staff & access: add team members, change roles, reset passwords, deactivate or delete accounts. */
require_once __DIR__ . '/../../database/seed.php';        // create_staff()
$pdo = db();
$roles = assignable_roles($me);

function active_super_admins(PDO $pdo): int { return (int)$pdo->query("SELECT COUNT(*) FROM staff_users WHERE role='super_admin' AND is_active=1")->fetchColumn(); }
function load_staff(PDO $pdo, int $id): ?array { $s = $pdo->prepare('SELECT * FROM staff_users WHERE id = ?'); $s->execute([$id]); return $s->fetch() ?: null; }

if (is_post()) {
    csrf_require();
    $act = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    try {
        if ($act === 'create') {
            $role = in_array($_POST['role'] ?? '', $roles, true) ? $_POST['role'] : 'editor';
            $plain = (string)($_POST['password'] ?? '');
            $generated = $plain === '';
            if ($generated) $plain = temp_password();
            $newId = create_staff($pdo, post_str('name', 120), post_str('email', 190), $plain, $role);
            $pdo->prepare('UPDATE staff_users SET must_change_password = 1 WHERE id = ?')->execute([$newId]);   // they choose their own on first sign-in
            log_action('staff.create', 'staff', $newId, $role);
            flash('ok', 'Account created for ' . strtolower(post_str('email', 190)) . '.', $generated ? ['temp' => $plain, 'tempFor' => strtolower(post_str('email', 190))] : []);
        } else {
            $t = load_staff($pdo, $id);
            if (!$t || !can_manage_staff($me, $t)) throw new InvalidArgumentException('You cannot change that account.');
            if ($act === 'update') {
                $role = in_array($_POST['role'] ?? '', $roles, true) ? $_POST['role'] : $t['role'];
                $active = !empty($_POST['is_active']) ? 1 : 0;
                if ($id === (int)$me['id'] && ($role !== $t['role'] || !$active)) throw new InvalidArgumentException('You cannot change your own role or deactivate yourself.');
                if ($t['role'] === 'super_admin' && ($role !== 'super_admin' || !$active) && active_super_admins($pdo) <= 1) throw new InvalidArgumentException('There must always be at least one active super admin.');
                $pdo->prepare('UPDATE staff_users SET role = ?, is_active = ? WHERE id = ?')->execute([$role, $active, $id]);
                log_action('staff.update', 'staff', $id, $role . ($active ? '' : ' (disabled)'));
                flash('ok', 'Saved ' . $t['email'] . '.');
            } elseif ($act === 'reset') {
                $plain = temp_password();
                $pdo->prepare('UPDATE staff_users SET password_hash = ?, must_change_password = 1, failed_logins = 0, locked_until = NULL WHERE id = ?')->execute([password_hash($plain, PASSWORD_DEFAULT), $id]);
                log_action('staff.reset_password', 'staff', $id);
                flash('ok', 'New temporary password set for ' . $t['email'] . '. Share it privately.', ['temp' => $plain, 'tempFor' => $t['email']]);
            } elseif ($act === 'delete') {
                if ($id === (int)$me['id']) throw new InvalidArgumentException('You cannot delete your own account.');
                if ($t['role'] === 'super_admin' && $t['is_active'] && active_super_admins($pdo) <= 1) throw new InvalidArgumentException('There must always be at least one active super admin.');
                $pdo->prepare('DELETE FROM staff_users WHERE id = ?')->execute([$id]);
                log_action('staff.delete', 'staff', $id, $t['email']);
                flash('ok', 'Deleted ' . $t['email'] . '.');
            }
        }
    } catch (InvalidArgumentException $e) { flash('err', $e->getMessage()); }
    redirect(admin_url('staff'));
}

$users = $pdo->query('SELECT id, name, email, role, is_active, last_login_at, created_at FROM staff_users ORDER BY created_at')->fetchAll();
?>
<h1>Staff &amp; access</h1>
<p class="muted"><b>Editors</b> write posts and manage the gallery, images and messages. <b>Admins</b> can also manage staff and settings. <b>Super admins</b> can do everything.</p>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="create">
  <h2>Add a team member</h2>
  <div class="grid3">
    <div><label for="n">Full name</label><input id="n" name="name" required maxlength="120"></div>
    <div><label for="e">Email</label><input id="e" name="email" type="email" required maxlength="190"></div>
    <div><label for="r">Role</label><select id="r" name="role"><?php foreach ($roles as $r): ?><option value="<?= h($r) ?>" <?= $r === 'editor' ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $r)) ?></option><?php endforeach; ?></select></div>
  </div>
  <label for="p">Password <span class="muted">(leave empty to generate one; they must change it when they first sign in)</span></label>
  <input id="p" name="password" type="text" autocomplete="off" placeholder="at least <?= PASSWORD_MIN_LENGTH ?> characters, or empty">
  <button class="btn">Create account</button>
</form>

<?php foreach ($users as $u): $manage = can_manage_staff($me, $u); $mine = (int)$u['id'] === (int)$me['id']; ?>
<div class="card">
  <div class="head"><h3><?= h($u['name']) ?> <span class="badge <?= $u['is_active'] ? 'ok' : 'bad' ?>"><?= $u['is_active'] ? 'active' : 'disabled' ?></span> <span class="badge muted"><?= h(str_replace('_', ' ', $u['role'])) ?></span><?= $mine ? ' <small class="muted">(you)</small>' : '' ?></h3><span class="muted"><?= h($u['email']) ?></span></div>
  <p class="muted">Last sign-in: <?= $u['last_login_at'] ? h(fmt_dt($u['last_login_at'])) : 'never' ?></p>
  <?php if ($manage): ?>
  <details><summary>Change access</summary>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
      <label>Role <select name="role" <?= $mine ? 'disabled' : '' ?>><?php foreach ($roles as $r): ?><option value="<?= h($r) ?>" <?= $r === $u['role'] ? 'selected' : '' ?>><?= h(str_replace('_', ' ', $r)) ?></option><?php endforeach; ?></select></label>
      <label class="check"><input type="checkbox" name="is_active" value="1" <?= $u['is_active'] ? 'checked' : '' ?> <?= $mine ? 'disabled' : '' ?>> Active (can sign in)</label>
      <?php if ($mine): ?><input type="hidden" name="role" value="<?= h($u['role']) ?>"><input type="hidden" name="is_active" value="1"><?php endif; ?>
      <button class="btn sm">Save</button>
    </form>
    <div class="inline-form">
      <form method="post" data-confirm="Set a new temporary password for <?= h($u['email']) ?>? Their old password stops working."><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn sm ghost">Reset password</button></form>
      <?php if (!$mine): ?><form method="post" data-confirm="Delete <?= h($u['email']) ?> for ever? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><button class="btn sm red">Delete account</button></form><?php endif; ?>
    </div>
  </details>
  <?php endif; ?>
</div>
<?php endforeach; ?>
