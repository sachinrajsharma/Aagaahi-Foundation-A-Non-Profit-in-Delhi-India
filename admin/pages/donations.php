<?php
/** Donations: pledges and payments, receipts, offline gifts. Admins only. */
require_once __DIR__ . '/../../includes/donations.php';
$pdo = db();
$statuses = ['created', 'paid', 'failed', 'refunded'];
$gateways = ['razorpay' => 'Razorpay', 'upi' => 'UPI', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'other' => 'Other'];

if (is_post()) {
    csrf_require();
    $act = (string)($_POST['action'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    if ($act === 'add') {                                                   // gift received offline (cash, cheque, direct transfer)
        $name = clean_line(post_str('name', 120), 120); $email = trim(post_str('email', 190)); $phone = clean_line(post_str('phone', 30), 30);
        $paise = rupees_to_paise(post_str('amount', 14));
        $gw = array_key_exists($_POST['gateway'] ?? '', $gateways) && $_POST['gateway'] !== 'razorpay' ? (string)$_POST['gateway'] : 'other';
        $purpose = array_key_exists($_POST['purpose'] ?? '', DONATION_PURPOSES) ? (string)$_POST['purpose'] : 'where-needed';
        if ($name === '' || !valid_email_address($email) || $paise === null || $paise < 100 || $paise > 100000000000 || !valid_phone($phone)) {
            flash('err', 'Please give a name, a valid email and an amount.');
            redirect(admin_url('donations', ['add' => 1]));
        }
        $newId = donation_create($pdo, ['name' => $name, 'email' => $email, 'phone' => $phone, 'amount_paise' => $paise, 'purpose' => $purpose, 'gateway' => $gw, 'wants_80g' => !empty($_POST['wants_80g'])]);
        $d = donation_mark_paid($pdo, $newId, null, null, $gw);
        log_action('donation.add', 'donation', $newId, paise_text($paise));
        if ($d && !empty($_POST['send_receipt'])) donation_send_receipt($d);
        flash('ok', 'Donation recorded' . ($d ? ' with receipt ' . $d['receipt_no'] : '') . '.');
        redirect(admin_url('donations', ['id' => $newId]));
    }
    if ($act === 'paid') {
        $d = donation_mark_paid($pdo, $id, null, null, null);
        if ($d) { log_action('donation.paid', 'donation', $id, $d['receipt_no']); if (!empty($_POST['send_receipt'])) donation_send_receipt($d); flash('ok', 'Marked as received. Receipt ' . $d['receipt_no'] . '.'); }
        else flash('err', 'This donation is already marked as paid.');
    } elseif ($act === 'failed' || $act === 'refunded') {
        $from = $act === 'failed' ? ['created'] : ['paid'];
        $u = $pdo->prepare('UPDATE donations SET status = ? WHERE id = ? AND status IN (' . implode(',', array_map(fn($s) => "'$s'", $from)) . ')');
        $u->execute([$act, $id]);
        if ($u->rowCount()) { log_action('donation.' . $act, 'donation', $id); flash('ok', 'Marked as ' . $act . '.'); } else flash('err', 'That change is not possible for this donation.');
    } elseif ($act === 'resend') {
        $q = $pdo->prepare("SELECT * FROM donations WHERE id = ? AND status = 'paid'"); $q->execute([$id]);
        if ($d = $q->fetch()) { donation_send_receipt($d); log_action('donation.resend', 'donation', $id); flash('ok', 'Receipt sent again.'); }
    }
    redirect(admin_url('donations', ['id' => $id]));
}

if (isset($_GET['export'])) {
    $rows = $pdo->query('SELECT id, created_at, paid_at, receipt_no, donor_name, donor_email, donor_phone, amount_paise, purpose, gateway, status, wants_80g FROM donations ORDER BY created_at DESC')->fetchAll();
    log_action('donation.export', 'donation', null, count($rows) . ' rows');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="donations-' . date('Y-m-d') . '.csv"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['ID', 'Created', 'Paid at', 'Receipt', 'Donor', 'Email', 'Phone', 'Amount (INR)', 'Purpose', 'Method', 'Status', 'Wants 80G']);
    foreach ($rows as $r) {
        foreach (['donor_name', 'donor_email', 'donor_phone'] as $k) if (preg_match('/^[=+\-@\t\r]/', (string)$r[$k])) $r[$k] = "'" . $r[$k];
        $r['amount_paise'] = number_format($r['amount_paise'] / 100, 2, '.', '');
        $r['wants_80g'] = $r['wants_80g'] ? 'yes' : 'no';
        fputcsv($o, $r);
    }
    exit;
}

if (isset($_GET['add'])): ?>
<p><a href="<?= h(admin_url('donations')) ?>">← Donations</a></p>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="add">
  <h1>Record a gift received offline</h1><p class="muted">For cash, cheque or a transfer straight to the bank account. It is marked received and given a receipt number.</p>
  <label for="d_name">Donor name</label><input id="d_name" name="name" required maxlength="120">
  <label for="d_email">Donor email (receipt goes here)</label><input id="d_email" name="email" type="email" required maxlength="190">
  <label for="d_phone">Phone (optional)</label><input id="d_phone" name="phone" maxlength="30">
  <label for="d_amount">Amount in rupees</label><input id="d_amount" name="amount" inputmode="decimal" required maxlength="14">
  <label for="d_gw">How it came</label><select id="d_gw" name="gateway"><?php foreach ($gateways as $k => $lab): if ($k === 'razorpay') continue; ?><option value="<?= h($k) ?>"><?= h($lab) ?></option><?php endforeach; ?></select>
  <label for="d_pur">Purpose</label><select id="d_pur" name="purpose"><?php foreach (DONATION_PURPOSES as $k => $lab): ?><option value="<?= h($k) ?>"><?= h($lab) ?></option><?php endforeach; ?></select>
  <label><input type="checkbox" name="wants_80g" value="1"> Donor asked about 80G</label>
  <label><input type="checkbox" name="send_receipt" value="1" checked> Email the receipt now</label>
  <button class="btn">Record gift</button>
</form>
<?php return; endif;

$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $st = $pdo->prepare('SELECT * FROM donations WHERE id = ?'); $st->execute([$id]); $d = $st->fetch();
    if (!$d) { echo '<div class="card"><p>That donation does not exist. <a href="' . h(admin_url('donations')) . '">Back</a></p></div>'; return; }
    ?>
    <p><a href="<?= h(admin_url('donations')) ?>">← Donations</a></p>
    <div class="card">
      <h1><?= h(paise_text((int)$d['amount_paise'])) ?> <?= status_badge($d['status']) ?></h1>
      <p><b><?= h($d['donor_name']) ?></b> · <a href="mailto:<?= h($d['donor_email']) ?>"><?= h($d['donor_email']) ?></a><?= $d['donor_phone'] ? ' · ' . h($d['donor_phone']) : '' ?></p>
      <p class="muted">Purpose: <?= h(donation_purpose_label((string)$d['purpose'])) ?> · Method: <?= h($gateways[$d['gateway']] ?? $d['gateway']) ?> · Started <?= h(fmt_dt($d['created_at'])) ?><?= $d['paid_at'] ? ' · Received ' . h(fmt_dt($d['paid_at'])) : '' ?></p>
      <p><?= $d['receipt_no'] ? 'Receipt <b>' . h($d['receipt_no']) . '</b> · <a target="_blank" href="' . h('/donate/receipt.php?id=' . (int)$d['id'] . '&t=' . receipt_token((int)$d['id'])) . '">open</a>' : 'No receipt yet.' ?>
         <?= $d['wants_80g'] ? ' · <b>asked about 80G</b>' : '' ?><br>
         <small class="muted">Reference to quote: DON-<?= (int)$d['id'] ?><?= $d['gateway_payment_id'] ? ' · payment id ' . h($d['gateway_payment_id']) : '' ?></small></p>
      <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
        <?php if ($d['status'] === 'created'): ?><label><input type="checkbox" name="send_receipt" value="1" checked> email the receipt</label>
          <button class="btn sm" name="action" value="paid">Mark money received</button> <button class="btn sm ghost" name="action" value="failed">Mark failed</button><?php endif; ?>
        <?php if ($d['status'] === 'failed'): ?><button class="btn sm" name="action" value="paid">Mark money received</button><?php endif; ?>
        <?php if ($d['status'] === 'paid'): ?><button class="btn sm ghost" name="action" value="resend">Email receipt again</button> <button class="btn sm ghost" name="action" value="refunded" data-confirm="Mark this donation as refunded? (Refund the money itself at your bank or payment provider.)">Mark refunded</button><?php endif; ?>
      </form>
    </div>
    <?php
    return;
}

$filter = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
$fy = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['fy'] ?? '')) ? (string)$_GET['fy'] : '';
$page_n = max(1, (int)($_GET['n'] ?? 1)); $per = 25;
$where = []; $args = [];
if ($filter !== '') { $where[] = 'status = ?'; $args[] = $filter; }
if ($fy !== '') { $y = (int)substr($fy, 0, 4); $where[] = 'COALESCE(paid_at, created_at) >= ? AND COALESCE(paid_at, created_at) < ?'; array_push($args, "$y-04-01", ($y + 1) . '-04-01'); }
$wsql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$tot = $pdo->prepare('SELECT COUNT(*), COALESCE(SUM(CASE WHEN status = "paid" THEN amount_paise END), 0) FROM donations' . $wsql); $tot->execute($args);
[$total, $sum] = array_map('intval', $tot->fetch(PDO::FETCH_NUM));
$st = $pdo->prepare('SELECT id, donor_name, donor_email, amount_paise, status, gateway, receipt_no, created_at FROM donations' . $wsql . ' ORDER BY created_at DESC LIMIT ' . $per . ' OFFSET ' . (($page_n - 1) * $per));
$st->execute($args);
$rows = $st->fetchAll();
$thisFy = financial_year();
$y0 = (int)substr($thisFy, 0, 4);
?>
<div class="head"><h1>Donations</h1><span><a class="btn sm" href="<?= h(admin_url('donations', ['add' => 1])) ?>">Record offline gift</a> <a class="btn sm ghost" href="<?= h(admin_url('donations', ['export' => 1])) ?>">Download CSV</a></span></div>
<p class="muted">Received in this view: <b><?= h(paise_text($sum)) ?></b> (paid donations only). Online payment is <b><?= rzp_config() ? 'on' : 'off: donors see your UPI / bank details, and you mark gifts received here' ?></b>.</p>
<form method="get" class="filters"><input type="hidden" name="p" value="donations">
  <select name="status"><option value="">All statuses</option><?php foreach ($statuses as $s): ?><option value="<?= h($s) ?>" <?= $filter === $s ? 'selected' : '' ?>><?= h($s === 'created' ? 'Waiting for money' : ucfirst($s)) ?></option><?php endforeach; ?></select>
  <select name="fy"><option value="">All years</option><?php for ($i = 0; $i < 4; $i++): $s = ($y0 - $i) . '-' . substr((string)($y0 - $i + 1), -2); ?><option value="<?= h($s) ?>" <?= $fy === $s ? 'selected' : '' ?>>Financial year <?= h($s) ?></option><?php endfor; ?></select>
  <button class="btn sm">Filter</button>
</form>
<div class="card list">
  <?php if (!$rows): ?><p class="muted">No donations found.</p><?php endif; ?>
  <?php foreach ($rows as $m): ?>
    <a class="row-link" href="<?= h(admin_url('donations', ['id' => $m['id']])) ?>"><span><b><?= h(paise_text((int)$m['amount_paise'])) ?></b> · <?= h($m['donor_name']) ?> <small class="muted"><?= h($m['donor_email']) ?></small> <?= status_badge($m['status']) ?><br><small class="muted"><?= h($gateways[$m['gateway']] ?? $m['gateway']) ?><?= $m['receipt_no'] ? ' · ' . h($m['receipt_no']) : '' ?></small></span><small><?= h(fmt_dt($m['created_at'])) ?></small></a>
  <?php endforeach; ?>
</div>
<?php if ($total > $per): $pages = (int)ceil($total / $per); ?>
  <p class="pager"><?php for ($i = 1; $i <= $pages; $i++): ?><a class="<?= $i === $page_n ? 'on' : '' ?>" href="<?= h(admin_url('donations', ['status' => $filter, 'fy' => $fy, 'n' => $i])) ?>"><?= $i ?></a> <?php endfor; ?></p>
<?php endif; ?>
