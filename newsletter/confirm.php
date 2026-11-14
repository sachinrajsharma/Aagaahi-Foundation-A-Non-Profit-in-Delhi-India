<?php
/** /newsletter/confirm.php?t=...: the link in the confirmation email. Shows a button first, so mail scanners opening the link do not confirm by themselves. */
require_once __DIR__ . '/../includes/page.php';

$t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$state = 'invalid';
if (preg_match('/^[a-f0-9]{48}$/', $t) && ($pdo = db_try())) {
    require_once __DIR__ . '/../includes/mail.php';
    require_once __DIR__ . '/../includes/donations.php';
    $q = $pdo->prepare('SELECT id, email, status FROM newsletter_subscribers WHERE confirm_token_hash = ?');
    $q->execute([hash('sha256', $t)]);
    $row = $q->fetch();
    if ($row) {
        if ($row['status'] === 'confirmed') $state = 'done';
        elseif ($row['status'] === 'pending') {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $u = $pdo->prepare('UPDATE newsletter_subscribers SET status="confirmed", confirmed_at=NOW() WHERE id=? AND status="pending"');
                $u->execute([$row['id']]);
                $state = 'done';
                if ($u->rowCount() === 1) {
                    $leave = site_root_url() . '/newsletter/unsubscribe.php?t=' . unsubscribe_token((int)$row['id']);
                    send_mail((string)$row['email'], 'You are subscribed', "Thank you, you are now subscribed to the " . $ngo['name'] . " newsletter.\n\nTo leave at any time: $leave\n");
                }
            } else $state = 'ask';
        }
    }
}
page_open('Confirm subscription', '', false);
?>
<div class="fcard">
<h1>Confirm your subscription</h1>
<?php if ($state === 'done'): ?><div class="fok">You are subscribed. Thank you! <a href="/">Back to home</a></div>
<?php elseif ($state === 'ask'): ?><form class="fform" method="post" action="/newsletter/confirm.php"><input type="hidden" name="t" value="<?= e($t) ?>"><p>Press the button to start receiving our newsletter.</p><button class="go" type="submit">Yes, subscribe me</button></form>
<?php else: ?><div class="ferr">This link is not valid any more. You can <a href="/newsletter/">subscribe again</a>.</div><?php endif; ?>
</div>
<?php page_close();
