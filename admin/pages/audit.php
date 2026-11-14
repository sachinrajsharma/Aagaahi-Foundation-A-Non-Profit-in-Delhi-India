<?php
/** Activity log: who did what, newest first. */
$rows = db()->query('SELECT a.created_at, a.action, a.entity, a.entity_id, a.detail, s.name AS who FROM audit_log a LEFT JOIN staff_users s ON s.id = a.staff_id ORDER BY a.id DESC LIMIT 300')->fetchAll();
?>
<h1>Activity log</h1>
<p class="muted">The latest 300 actions. Passwords and message text are never recorded here.</p>
<div class="card"><table class="tbl"><thead><tr><th>When</th><th>Who</th><th>What</th><th>Item</th><th>Detail</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?>
  <tr><td><?= h(fmt_dt($r['created_at'])) ?></td><td><?= h($r['who'] ?? 'someone') ?></td><td><?= h($r['action']) ?></td><td><?= h(trim(($r['entity'] ?? '') . ' ' . ($r['entity_id'] ?? ''))) ?></td><td><?= h($r['detail'] ?? '') ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="5" class="muted">Nothing yet.</td></tr><?php endif; ?>
</tbody></table></div>
