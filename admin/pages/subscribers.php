<?php
/** Newsletter subscribers (double opt-in). Only confirmed people should be emailed. */
$pdo = db();
$statuses = ['pending', 'confirmed', 'unsubscribed'];

if (is_post()) {
    csrf_require();
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare('DELETE FROM newsletter_subscribers WHERE id = ?')->execute([$id]);
        log_action('subscriber.delete', 'subscriber', $id);
        flash('ok', 'Subscriber deleted.');
    }
    redirect(admin_url('subscribers', array_filter(['status' => (string)($_POST['status'] ?? '')])));
}

if (isset($_GET['export'])) {                    // the mailing list: confirmed subscribers only, with a ready unsubscribe link
    require_once __DIR__ . '/../../includes/forms.php';
    require_once __DIR__ . '/../../includes/donations.php';
    $rows = $pdo->query("SELECT id, email, confirmed_at FROM newsletter_subscribers WHERE status = 'confirmed' ORDER BY confirmed_at")->fetchAll();
    log_action('subscriber.export', 'subscriber', null, count($rows) . ' confirmed');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-confirmed-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Email', 'Confirmed', 'Unsubscribe link']);
    foreach ($rows as $r) fputcsv($o, [$r['email'], $r['confirmed_at'], site_root_url() . '/newsletter/unsubscribe.php?t=' . unsubscribe_token((int)$r['id'])]);
    exit;
}

$filter = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$q = trim((string)($_GET['q'] ?? ''));
$page_n = max(1, (int)($_GET['n'] ?? 1)); $per = 50;
$where = []; $args = [];
if ($filter !== '') { $where[] = 'status = ?'; $args[] = $filter; }
if ($q !== '') { $where[] = 'email LIKE ?'; $args[] = '%' . addcslashes($q, '%_\\') . '%'; }
$wsql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$tot = $pdo->prepare('SELECT COUNT(*) FROM newsletter_subscribers' . $wsql); $tot->execute($args); $total = (int)$tot->fetchColumn();
$st = $pdo->prepare('SELECT id, email, status, created_at, confirmed_at FROM newsletter_subscribers' . $wsql . ' ORDER BY created_at DESC LIMIT ' . $per . ' OFFSET ' . (($page_n - 1) * $per));
$st->execute($args);
$rows = $st->fetchAll();
$counts = [];
foreach ($pdo->query('SELECT status, COUNT(*) n FROM newsletter_subscribers GROUP BY status') as $r) $counts[$r['status']] = (int)$r['n'];
?>
<div class="head"><h1>Newsletter</h1><a class="btn sm ghost" href="<?= h(admin_url('subscribers', ['export' => 1])) ?>">Download confirmed list (CSV)</a></div>
<p class="muted"><?= (int)($counts['confirmed'] ?? 0) ?> confirmed · <?= (int)($counts['pending'] ?? 0) ?> waiting for their confirmation click · <?= (int)($counts['unsubscribed'] ?? 0) ?> left. Email only the <b>confirmed</b> ones.</p>
<form method="get" class="filters"><input type="hidden" name="p" value="subscribers">
  <select name="status"><option value="">All</option><?php foreach ($statuses as $s): ?><option value="<?= h($s) ?>" <?= $filter === $s ? 'selected' : '' ?>><?= h(ucfirst($s)) ?></option><?php endforeach; ?></select>
  <input type="search" name="q" value="<?= h($q) ?>" placeholder="Search email"><button class="btn sm">Filter</button>
</form>
<div class="card list">
  <?php if (!$rows): ?><p class="muted">No subscribers found.</p><?php endif; ?>
  <?php foreach ($rows as $m): ?>
    <div class="row-link"><span><b><?= h($m['email']) ?></b> <?= status_badge($m['status']) ?><br><small class="muted">Signed up <?= h(fmt_dt($m['created_at'])) ?><?= $m['confirmed_at'] ? ' · confirmed ' . h(fmt_dt($m['confirmed_at'])) : '' ?></small></span>
      <form method="post" data-confirm="Delete this subscriber?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><input type="hidden" name="status" value="<?= h($filter) ?>"><button class="btn sm red">Delete</button></form></div>
  <?php endforeach; ?>
</div>
<?php if ($total > $per): $pages = (int)ceil($total / $per); ?>
  <p class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page_n ? 'on' : '' ?>" href="<?= h(admin_url('subscribers', ['status' => $filter, 'q' => $q, 'n' => $i])) ?>"><?= $i ?></a> <?php endfor; ?></p>
<?php endif; ?>
