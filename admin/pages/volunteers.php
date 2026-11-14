<?php
/** Volunteer sign-ups from /volunteer/. */
$pdo = db();
$statuses = ['new', 'contacted', 'active', 'declined'];

if (is_post()) {
    csrf_require();
    $id = (int)($_POST['id'] ?? 0);
    $act = (string)($_POST['action'] ?? '');
    if ($act === 'status' && in_array($_POST['status'] ?? '', $statuses, true)) {
        $pdo->prepare('UPDATE volunteer_applications SET status = ? WHERE id = ?')->execute([$_POST['status'], $id]);
        log_action('volunteer.status', 'volunteer', $id, (string)$_POST['status']);
        flash('ok', 'Marked as ' . $_POST['status'] . '.');
    } elseif ($act === 'notes') {
        $pdo->prepare('UPDATE volunteer_applications SET notes = ? WHERE id = ?')->execute([post_str('notes', 2000), $id]);
        log_action('volunteer.notes', 'volunteer', $id);
        flash('ok', 'Note saved.');
    } elseif ($act === 'delete') {
        $pdo->prepare('DELETE FROM volunteer_applications WHERE id = ?')->execute([$id]);
        log_action('volunteer.delete', 'volunteer', $id);
        flash('ok', 'Volunteer record deleted.');
        redirect(admin_url('volunteers'));
    }
    redirect(admin_url('volunteers', ['id' => $id]));
}

if (isset($_GET['export'])) {
    $rows = $pdo->query('SELECT id, created_at, name, email, phone, city, interests, availability, status, message, notes FROM volunteer_applications ORDER BY created_at DESC')->fetchAll();
    log_action('volunteer.export', 'volunteer', null, count($rows) . ' rows');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="volunteers-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['ID', 'Received', 'Name', 'Email', 'Phone', 'City', 'Interests', 'Availability', 'Status', 'Message', 'Notes']);
    foreach ($rows as $r) {
        foreach (['name', 'email', 'phone', 'city', 'interests', 'availability', 'message', 'notes'] as $k) if (preg_match('/^[=+\-@\t\r]/', (string)$r[$k])) $r[$k] = "'" . $r[$k];
        fputcsv($o, $r);
    }
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $st = $pdo->prepare('SELECT * FROM volunteer_applications WHERE id = ?');
    $st->execute([$id]);
    $m = $st->fetch();
    if (!$m) { echo '<div class="card"><p>That record does not exist. <a href="' . h(admin_url('volunteers')) . '">Back to the list</a></p></div>'; return; }
    ?>
    <p><a href="<?= h(admin_url('volunteers')) ?>">← Volunteers</a></p>
    <div class="card">
      <h1><?= h($m['name']) ?> <?= status_badge($m['status']) ?></h1>
      <p class="muted"><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a><?= $m['phone'] ? ' · ' . h($m['phone']) : '' ?><?= $m['city'] ? ' · ' . h($m['city']) : '' ?> · signed up <?= h(fmt_dt($m['created_at'])) ?></p>
      <p><b>Interests:</b> <?= h($m['interests'] ?: 'not said') ?><br><b>Free:</b> <?= h($m['availability'] ?: 'not said') ?></p>
      <?php if ($m['message']): ?><div class="msg-body"><?= nl2br(h($m['message'])) ?></div><?php endif; ?>
      <p class="muted">Agreed to be contacted on <?= h(fmt_dt($m['consent_at'])) ?>.</p>
      <p><a class="btn" href="mailto:<?= h($m['email']) ?>">Email</a></p>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="status">
        <?php foreach ($statuses as $s): if ($s === $m['status']) continue; ?><button class="btn sm ghost" name="status" value="<?= h($s) ?>">Mark <?= h($s) ?></button> <?php endforeach; ?>
      </form>
    </div>
    <form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="notes">
      <label for="notes">Internal notes</label><textarea id="notes" name="notes" rows="3" maxlength="2000"><?= h($m['notes'] ?? '') ?></textarea><button class="btn sm">Save note</button>
    </form>
    <form method="post" class="card danger-zone" data-confirm="Delete this volunteer record for ever?"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete">
      <button class="btn sm red">Delete record</button> <span class="muted">Delete it when the person asks, or when you no longer need it.</span>
    </form>
    <?php
    return;
}

$filter = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$q = trim((string)($_GET['q'] ?? ''));
$page_n = max(1, (int)($_GET['n'] ?? 1)); $per = 25;
$where = []; $args = [];
if ($filter !== '') { $where[] = 'status = ?'; $args[] = $filter; }
if ($q !== '') { $where[] = '(name LIKE ? OR email LIKE ? OR city LIKE ? OR interests LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($args, $like, $like, $like, $like); }
$wsql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$tot = $pdo->prepare('SELECT COUNT(*) FROM volunteer_applications' . $wsql); $tot->execute($args); $total = (int)$tot->fetchColumn();
$st = $pdo->prepare('SELECT id, name, email, city, interests, status, created_at FROM volunteer_applications' . $wsql . ' ORDER BY created_at DESC LIMIT ' . $per . ' OFFSET ' . (($page_n - 1) * $per));
$st->execute($args);
$rows = $st->fetchAll();
?>
<div class="head"><h1>Volunteers</h1><a class="btn sm ghost" href="<?= h(admin_url('volunteers', ['export' => 1])) ?>">Download all as CSV</a></div>
<form method="get" class="filters"><input type="hidden" name="p" value="volunteers">
  <select name="status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option value="<?= h($s) ?>" <?= $filter === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?></select>
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search name, email, city or interest"><button class="btn sm">Filter</button>
</form>
<div class="card list">
  <?php if (!$rows): ?><p class="muted">No volunteers yet. Share <b>/volunteer/</b> to get sign-ups.</p><?php endif; ?>
  <?php foreach ($rows as $m): ?>
    <a class="row-link" href="<?= h(admin_url('volunteers', ['id' => $m['id']])) ?>"><span><b><?= h($m['name']) ?></b> <small class="muted"><?= h($m['email']) ?><?= $m['city'] ? ' · ' . h($m['city']) : '' ?></small> <?= status_badge($m['status']) ?><br><small class="muted"><?= h(mb_strimwidth((string)$m['interests'], 0, 110, '…')) ?></small></span><small><?= h(fmt_dt($m['created_at'])) ?></small></a>
  <?php endforeach; ?>
</div>
<?php if ($total > $per): $pages = (int)ceil($total / $per); ?>
  <p class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page_n ? 'on' : '' ?>" href="<?= h(admin_url('volunteers', ['status' => $filter, 'q' => $q, 'n' => $i])) ?>"><?= $i ?></a> <?php endfor; ?></p>
<?php endif; ?>
