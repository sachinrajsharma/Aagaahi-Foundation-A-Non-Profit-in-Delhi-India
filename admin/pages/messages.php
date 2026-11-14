<?php
/** Messages inbox (contact form). */
$pdo = db();
$statuses = ['new', 'read', 'replied', 'spam', 'archived'];

if (is_post()) {
    csrf_require();
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'status' && in_array($_POST['status'] ?? '', $statuses, true)) {
        $pdo->prepare('UPDATE contact_messages SET status = ?, handled_by = ?, replied_at = IF(? = \'replied\', NOW(), replied_at) WHERE id = ?')
            ->execute([$_POST['status'], $me['id'], $_POST['status'], $id]);
        log_action('message.status', 'message', $id, (string)$_POST['status']);
        flash('ok', 'Marked as ' . $_POST['status'] . '.');
    } elseif ($act === 'notes') {
        $pdo->prepare('UPDATE contact_messages SET notes = ?, handled_by = ? WHERE id = ?')->execute([post_str('notes', 2000), $me['id'], $id]);
        log_action('message.notes', 'message', $id);
        flash('ok', 'Note saved.');
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM contact_messages WHERE id = ?')->execute([$id]);
        log_action('message.delete', 'message', $id);
        flash('ok', 'Message deleted.');
        redirect(admin_url('messages'));
    }
    redirect(admin_url('messages', ['id' => $id]));
}

if (isset($_GET['export'])) {                                    // CSV download of everything in the current filter
    $rows = $pdo->query('SELECT id, created_at, name, email, status, message, notes FROM contact_messages ORDER BY created_at DESC')->fetchAll();
    log_action('message.export', 'message', null, count($rows) . ' rows');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="messages-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['ID', 'Received', 'Name', 'Email', 'Status', 'Message', 'Notes']);
    foreach ($rows as $r) {
        foreach (['name', 'email', 'message', 'notes'] as $k) if (preg_match('/^[=+\-@\t\r]/', (string)$r[$k])) $r[$k] = "'" . $r[$k];   // spreadsheet formula safety
        fputcsv($o, $r);
    }
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $st = $pdo->prepare('SELECT * FROM contact_messages WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) { echo '<div class="card"><p>That message does not exist. <a href="' . h(admin_url('messages')) . '">Back to the inbox</a></p></div>'; return; }
    if ($m['status'] === 'new') { $pdo->prepare("UPDATE contact_messages SET status='read', handled_by=? WHERE id=?")->execute([$me['id'], $id]); $m['status'] = 'read'; }
    ?>
    <p><a href="<?= h(admin_url('messages')) ?>">← Inbox</a></p>
    <div class="card">
      <h1><?= h($m['name']) ?> <?= status_badge($m['status']) ?></h1>
      <p class="muted"><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a> · <?= h(fmt_dt($m['created_at'])) ?></p>
      <div class="msg-body"><?= nl2br(h($m['message'])) ?></div>
      <p><a class="btn" href="mailto:<?= h($m['email']) ?>?subject=<?= h(rawurlencode('Re: your message to ' . ($GLOBALS['ngo']['name'] ?? ''))) ?>">Reply by email</a></p>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="status">
        <?php foreach ($statuses as $s): if ($s === $m['status']) continue; ?><button class="btn sm ghost" name="status" value="<?= h($s) ?>">Mark <?= h($s) ?></button> <?php endforeach; ?>
      </form>
    </div>
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="notes">
      <label for="notes">Internal notes (visitors never see these)</label><textarea id="notes" name="notes" rows="3" maxlength="2000"><?= h($m['notes'] ?? '') ?></textarea>
      <button class="btn sm">Save note</button>
    </form>
    <form method="post" class="card danger-zone" data-confirm="Delete this message for ever?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete">
      <button class="btn sm red">Delete message</button> <span class="muted">Delete messages you no longer need: they contain personal details.</span>
    </form>
    <?php
    return;
}

$filter = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$q = trim((string)($_GET['q'] ?? ''));
$page_n = max(1, (int)($_GET['n'] ?? 1)); $per = 25;
$where = []; $args = [];
if ($filter !== '') { $where[] = 'status = ?'; $args[] = $filter; }
if ($q !== '') { $where[] = '(name LIKE ? OR email LIKE ? OR message LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($args, $like, $like, $like); }
$wsql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$tot = $pdo->prepare('SELECT COUNT(*) FROM contact_messages' . $wsql); $tot->execute($args); $total = (int)$tot->fetchColumn();
$st = $pdo->prepare('SELECT id, name, email, status, created_at, LEFT(message, 110) AS snippet FROM contact_messages' . $wsql . ' ORDER BY created_at DESC LIMIT ' . $per . ' OFFSET ' . (($page_n - 1) * $per));
$st->execute($args);
$rows = $st->fetchAll();
?>
<div class="head"><h1>Messages</h1><a class="btn sm ghost" href="<?= h(admin_url('messages', ['export' => 1])) ?>">Download all as CSV</a></div>
<form method="get" class="filters"><input type="hidden" name="p" value="messages">
  <select name="status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option value="<?= h($s) ?>" <?= $filter === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?></select>
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search name, email or text">
  <button class="btn sm">Filter</button>
</form>
<div class="card list">
  <?php if (!$rows): ?><p class="muted">No messages found.</p><?php endif; ?>
  <?php foreach ($rows as $m): ?>
    <a class="row-link" href="<?= h(admin_url('messages', ['id' => $m['id']])) ?>"><span><b><?= h($m['name']) ?></b> <small class="muted"><?= h($m['email']) ?></small> <?= status_badge($m['status']) ?><br><small class="muted"><?= h($m['snippet']) ?></small></span><small><?= h(fmt_dt($m['created_at'])) ?></small></a>
  <?php endforeach; ?>
</div>
<?php if ($total > $per): $pages = (int)ceil($total / $per); ?>
  <p class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page_n ? 'on' : '' ?>" href="<?= h(admin_url('messages', ['status' => $filter, 'q' => $q, 'n' => $i])) ?>"><?= $i ?></a> <?php endfor; ?></p>
<?php endif; ?>
