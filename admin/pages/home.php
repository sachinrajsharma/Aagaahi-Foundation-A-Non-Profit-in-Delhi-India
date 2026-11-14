<?php
/** Dashboard home: numbers at a glance and the latest activity. */
$pdo = db();
$c = fn(string $sql): int => (int)$pdo->query($sql)->fetchColumn();
$stats = [
    ['New messages', $c("SELECT COUNT(*) FROM contact_messages WHERE status='new'"), 'messages', 'messages'],
    ['New volunteers', $c("SELECT COUNT(*) FROM volunteer_applications WHERE status='new'"), 'volunteers', 'messages'],
    ['Newsletter subscribers', $c("SELECT COUNT(*) FROM newsletter_subscribers WHERE status='confirmed'"), 'subscribers', 'messages'],
    ['Donations (paid)', $c("SELECT COUNT(*) FROM donations WHERE status='paid'"), 'donations', 'donations'],
    ['Published posts', $c("SELECT COUNT(*) FROM posts WHERE status='published'"), 'posts', 'posts'],
    ['Drafts', $c("SELECT COUNT(*) FROM posts WHERE status<>'published'"), 'posts', 'posts'],
    ['Gallery events', $c('SELECT COUNT(*) FROM events'), 'gallery', 'gallery'],
    ['Photos', $c('SELECT COUNT(*) FROM event_photos'), 'gallery', 'gallery'],
    ['Staff', $c('SELECT COUNT(*) FROM staff_users WHERE is_active=1'), 'staff', 'staff'],
];
$msgs = can('messages', $me) ? $pdo->query("SELECT id, name, email, status, created_at, LEFT(message, 90) AS snippet FROM contact_messages WHERE status IN ('new','read') ORDER BY created_at DESC LIMIT 6")->fetchAll() : [];
$posts = can('posts', $me) ? $pdo->query('SELECT id, title, status, updated_at FROM posts ORDER BY updated_at DESC LIMIT 6')->fetchAll() : [];
?>
<h1>Welcome, <?= h(explode(' ', $me['name'])[0]) ?></h1>
<div class="stats">
  <?php foreach ($stats as [$label, $n, $target, $perm]): if (!can($perm, $me)) continue; ?>
    <a class="stat" href="<?= h(admin_url($target)) ?>"><b><?= $n ?></b><span><?= h($label) ?></span></a>
  <?php endforeach; ?>
</div>
<div class="cols">
  <?php if (can('messages', $me)): ?>
  <section class="card"><h2>Latest messages</h2>
    <?php if (!$msgs): ?><p class="muted">No messages waiting.</p><?php endif; ?>
    <?php foreach ($msgs as $m): ?>
      <a class="row-link" href="<?= h(admin_url('messages', ['id' => $m['id']])) ?>"><span><b><?= h($m['name']) ?></b> <?= status_badge($m['status']) ?><br><small class="muted"><?= h($m['snippet']) ?>…</small></span><small><?= h(fmt_dt($m['created_at'])) ?></small></a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>
  <?php if (can('posts', $me)): ?>
  <section class="card"><h2>Recent posts</h2>
    <?php foreach ($posts as $p): ?>
      <a class="row-link" href="<?= h(admin_url('post', ['id' => $p['id']])) ?>"><span><?= h($p['title']) ?> <?= status_badge($p['status']) ?></span><small><?= h(fmt_dt($p['updated_at'])) ?></small></a>
    <?php endforeach; ?>
    <p><a class="btn sm" href="<?= h(admin_url('post')) ?>">Write a new post</a></p>
  </section>
  <?php endif; ?>
</div>
