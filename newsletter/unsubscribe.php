<?php
/** /newsletter/unsubscribe.php?t=...: one-click leave link for every newsletter (the token is stored only as a hash). */
require_once __DIR__ . '/../includes/page.php';

$t = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$state = 'invalid';
$pdo = null;
$uid = unsubscribe_id_from_token($t);
if ($uid !== null && ($pdo = db_try())) {
    $q = $pdo->prepare('SELECT id, status FROM newsletter_subscribers WHERE id = ?');
    $q->execute([$uid]);
    $row = $q->fetch();
    if ($row) {
        if ($row['status'] === 'unsubscribed') $state = 'done';
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pdo->prepare('UPDATE newsletter_subscribers SET status="unsubscribed", unsubscribed_at=NOW() WHERE id=?')->execute([$row['id']]);
            $state = 'done';
        } else $state = 'ask';
    }
}
page_open('Unsubscribe', '', false);
?>
<div class="fcard">
<h1>Unsubscribe</h1>
<?php if ($state === 'done'): ?><div class="fok">You will not receive our newsletter any more.</div>
<?php elseif ($state === 'ask'): ?><form class="fform" method="post" action="/newsletter/unsubscribe.php"><input type="hidden" name="t" value="<?= e($t) ?>"><p>Stop sending me the newsletter?</p><button class="go" type="submit">Unsubscribe</button></form>
<?php else: ?><div class="ferr">This link is not valid.</div><?php endif; ?>
</div>
<?php page_close();
