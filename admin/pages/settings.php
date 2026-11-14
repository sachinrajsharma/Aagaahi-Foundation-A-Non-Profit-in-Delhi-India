<?php
/** Site settings: the details shown across the public website. */
$pdo = db();
$labels = setting_labels();
$social = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'x' => 'X (Twitter)'];

if (is_post()) {
    csrf_require();
    $up = $pdo->prepare('INSERT INTO settings (setting_key, setting_value, updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)');
    $errors = [];
    foreach ($labels as $k => $label) {
        $v = post_str($k, $k === 'mission' ? 600 : 300);
        if (in_array($k, ['email', 'notify_email'], true) && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $errors[] = "$label is not a valid email address."; continue; }
        if ($k === 'donate_url' && $v !== '' && !preg_match('#^(https://|/|\#)#', $v)) { $errors[] = 'The donate link must start with https:// (or / or #).'; continue; }
        if ($k === 'hero_image') { $v = clean_image_ref($v); }
        $up->execute([$k, $v, $me['id']]);
    }
    $links = [];
    foreach ($social as $k => $label) {
        $v = post_str('social_' . $k, 300);
        if ($v === '') continue;
        if (!preg_match('#^https://[^\s<>"\']+$#i', $v)) { $errors[] = "$label link must start with https://"; continue; }
        $links[$label] = $v;
    }
    $up->execute(['social', json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $me['id']]);
    log_action('settings.update', 'settings');
    flash($errors ? 'err' : 'ok', $errors ? implode(' ', $errors) . ' Everything else was saved.' : 'Settings saved. The website shows them straight away.');
    redirect(admin_url('settings'));
}

$vals = [];
foreach ($pdo->query('SELECT setting_key, setting_value FROM settings') as $r) $vals[$r['setting_key']] = (string)$r['setting_value'];
$soc = json_decode($vals['social'] ?? '{}', true) ?: [];
$socByKey = [];
foreach ($social as $k => $label) $socByKey[$k] = $soc[$label] ?? '';
?>
<h1>Settings</h1>
<p class="muted">These details appear across the public website. Changes show immediately.</p>
<form method="post" class="card"><?= csrf_field() ?>
  <?php foreach ($labels as $k => $label): ?>
    <label for="s_<?= h($k) ?>"><?= h($label) ?></label>
    <?php if ($k === 'mission'): ?><textarea id="s_<?= h($k) ?>" name="<?= h($k) ?>" rows="3" maxlength="600"><?= h($vals[$k] ?? '') ?></textarea>
    <?php else: ?><input id="s_<?= h($k) ?>" name="<?= h($k) ?>" value="<?= h($vals[$k] ?? '') ?>" maxlength="300"><?php endif; ?>
  <?php endforeach; ?>
  <h2>Social media</h2>
  <?php foreach ($social as $k => $label): ?>
    <label for="so_<?= h($k) ?>"><?= h($label) ?> link</label><input id="so_<?= h($k) ?>" name="social_<?= h($k) ?>" value="<?= h($socByKey[$k]) ?>" placeholder="https://" maxlength="300">
  <?php endforeach; ?>
  <button class="btn">Save settings</button>
</form>
