<?php
/** /newsletter/: subscribe (double opt-in). The page gives the same answer whether or not the address is already known. */
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/mail.php';
require_once __DIR__ . '/../includes/donations.php';   // site_root_url()

$msg = ''; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (form_looks_automated()) {
        $msg = 'Almost done! Check your inbox and press the confirm link.';
    } elseif (!valid_email_address($email)) {
        $err = 'Please enter a valid email address.';
    } else {
        $pdo = db_try();
        if (!$pdo) {
            $err = 'We could not save your subscription just now. Please try again later.';
        } else {
            try {
                if (!form_rate_ok('newsletter', 5, $pdo)) {
                    $err = 'Too many attempts. Please try again in an hour.';
                } else {
                    $q = $pdo->prepare('SELECT id, status FROM newsletter_subscribers WHERE email = ?');
                    $q->execute([$email]);
                    $row = $q->fetch();
                    if (!$row || $row['status'] !== 'confirmed') {
                        $ctok = bin2hex(random_bytes(24)); $utok = bin2hex(random_bytes(24));
                        if ($row) {
                            $pdo->prepare('UPDATE newsletter_subscribers SET status="pending", confirm_token_hash=?, unsubscribe_token_hash=?, consent_at=NOW(), unsubscribed_at=NULL WHERE id=?')
                                ->execute([hash('sha256', $ctok), hash('sha256', $utok), $row['id']]);
                        } else {
                            $pdo->prepare('INSERT INTO newsletter_subscribers (email, status, confirm_token_hash, unsubscribe_token_hash, consent_at) VALUES (?, "pending", ?, ?, NOW())')
                                ->execute([$email, hash('sha256', $ctok), hash('sha256', $utok)]);
                        }
                        $link = site_root_url() . '/newsletter/confirm.php?t=' . $ctok;
                        send_mail($email, 'Please confirm your subscription',
                            "Thank you for your interest in {$ngo['name']}.\n\nPlease confirm your subscription by opening this link:\n$link\n\nIf you did not ask for this, ignore this email and you will not be subscribed.\n",
                            mail_html('Confirm your subscription', '<p>Thank you for your interest in ' . e($ngo['name']) . '.</p><p><a href="' . e($link) . '" style="background:#14532d;color:#fff;padding:10px 20px;border-radius:99px;text-decoration:none;font-weight:bold">Confirm my subscription</a></p><p style="font-size:13px;color:#64748b">If you did not ask for this, ignore this email and you will not be subscribed.</p>'));
                    }
                    $msg = 'Almost done! Check your inbox and press the confirm link.';
                }
            } catch (Throwable $ex) { error_log('[aagahi] newsletter failed: ' . $ex->getMessage()); $err = 'We could not save your subscription just now. Please try again later.'; }
        }
    }
}
page_open('Newsletter', 'Get a short email from ' . $ngo['name'] . ' now and then.');
?>
<div class="fcard">
<h1>Our newsletter</h1>
<p class="lead">News from our programmes in Delhi, now and then. No spam, and you can leave at any time.</p>
<?php if ($msg): ?><div class="fok"><?= e($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="ferr" role="alert"><?= e($err) ?></div><?php endif; ?>
<?php if (!$msg): ?>
<form class="fform" method="post" action="/newsletter/">
  <?= form_hidden_fields() ?>
  <label>Your email<input type="email" name="email" required maxlength="190" autocomplete="email"></label>
  <button class="go" type="submit">Subscribe</button>
  <p class="fnote">We will send one email to confirm it is really you. Only the email address is stored.</p>
</form>
<?php endif; ?>
</div>
<?php page_close();
