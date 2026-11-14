<?php
/** admin/layout.php — page frame. Expects $me, $page, $content. */
$flash = take_flash();
$nav = [
    ['home', 'Dashboard', null], ['messages', 'Messages', 'messages'], ['volunteers', 'Volunteers', 'messages'], ['subscribers', 'Newsletter', 'messages'], ['donations', 'Donations', 'donations'], ['posts', 'Blog posts', 'posts'], ['gallery', 'Gallery', 'gallery'],
    ['media', 'Images', 'media'], ['staff', 'Staff & access', 'staff'], ['settings', 'Settings', 'settings'], ['audit', 'Activity log', 'audit'],
];
$unread = 0;
try { if (can('messages', $me)) $unread = (int)db()->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'new'")->fetchColumn(); } catch (Throwable $e) { /* ignore */ }
?><!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= h(ucfirst($page)) ?> · Dashboard · <?= h($GLOBALS['ngo']['name'] ?? '') ?></title>
<link rel="stylesheet" href="/admin/assets/admin.css">
</head><body>
<aside class="side">
  <div class="brand"><?= h($GLOBALS['ngo']['logo'] ?? '🌱') ?> <?= h($GLOBALS['ngo']['short'] ?? 'Dashboard') ?></div>
  <nav>
    <?php foreach ($nav as [$p, $label, $perm]): if ($perm !== null && !can($perm, $me)) continue; ?>
      <a href="<?= h(admin_url($p)) ?>" class="<?= $page === $p || ($p === 'posts' && $page === 'post') || ($p === 'gallery' && $page === 'event') ? 'on' : '' ?>"><?= h($label) ?><?= $p === 'messages' && $unread ? ' <span class="pill">' . $unread . '</span>' : '' ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="side-foot">
    <a href="/" target="_blank" rel="noopener">View website ↗</a>
    <a href="<?= h(admin_url('account')) ?>"><?= h($me['name']) ?> <small>(<?= h(str_replace('_', ' ', $me['role'])) ?>)</small></a>
    <form method="post" action="<?= h(admin_url('logout')) ?>"><?= csrf_field() ?><button class="linklike" type="submit">Sign out</button></form>
  </div>
</aside>
<main class="main">
  <?php if (is_dir(dirname(__DIR__) . '/install') && ($me['role'] === 'super_admin' || $me['role'] === 'admin')): ?>
    <div class="flash err"><b>Security reminder:</b> the <code>install/</code> folder is still on the server. Delete it now (hPanel &gt; File Manager) so nobody else can run the installer.</div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="flash <?= h($flash['kind']) ?>"><?= h($flash['text']) ?>
      <?php if (!empty($flash['temp'])): ?><div class="temp">Temporary password for <b><?= h($flash['tempFor'] ?? '') ?></b> (shown only once):<br><code><?= h($flash['temp']) ?></code></div><?php endif; ?>
    </div>
  <?php endif; ?>
  <?= $content ?>
</main>
<script src="/admin/assets/admin.js"></script>
<?php if ($page === 'post'): ?><script src="/admin/assets/seo-assistant.js"></script><script src="/admin/assets/editor.js"></script><?php endif; ?>
</body></html>
