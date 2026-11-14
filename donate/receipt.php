<?php
/** /donate/receipt.php?id=..&t=..: printable acknowledgement of a donation. The link carries a signature, so only the donor (who got it by email) can open it. */
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/donations.php';

$id = (int)($_GET['id'] ?? 0); $t = (string)($_GET['t'] ?? '');
$d = null;
if ($id > 0 && hash_equals(receipt_token($id), $t) && ($pdo = db_try())) {
    $q = $pdo->prepare('SELECT * FROM donations WHERE id = ?');
    $q->execute([$id]);
    $d = $q->fetch() ?: null;
}
if (!$d) http_response_code(404);
header('Cache-Control: private, no-store');
page_open('Donation receipt', '', false);
?>
<div class="fcard">
<?php if (!$d): ?><h1>Receipt not found</h1><div class="ferr">This link is not valid.</div>
<?php elseif ($d['status'] !== 'paid'): ?><h1>Payment pending</h1><div class="fok">We have not confirmed this payment yet. Your receipt will be emailed as soon as we do.</div>
<?php else: ?>
  <h1>Thank you, <?= e($d['donor_name']) ?></h1>
  <div class="fok">Your donation has been received.</div>
  <table style="width:100%;border-collapse:collapse;margin:.5rem 0 1rem">
    <?php foreach ([['Receipt number', $d['receipt_no']], ['Date', date('j F Y', strtotime((string)$d['paid_at']))], ['Amount', paise_text((int)$d['amount_paise'])],
        ['Purpose', donation_purpose_label((string)$d['purpose'])], ['Received from', $d['donor_name'] . ' (' . $d['donor_email'] . ')'], ['Received by', $ngo['name']]] as [$k, $val]): ?>
    <tr><td style="padding:.5rem 0;color:#5d7064;width:38%;border-bottom:1px solid #e5efe8"><?= e($k) ?></td><td style="padding:.5rem 0;font-weight:700;border-bottom:1px solid #e5efe8"><?= e($val) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php if ($d['wants_80g']): ?><p class="fnote">You asked about section 80G. This is an acknowledgement, not an 80G certificate; our team will contact you about the certificate.</p><?php endif; ?>
  <?php if (setting('receipt_footer', '')): ?><p class="fnote"><?= e(setting('receipt_footer', '')) ?></p><?php endif; ?>
  <p class="noprint"><button class="btn" onclick="window.print()" style="border:0;cursor:pointer">Print or save as PDF</button></p>
<?php endif; ?>
</div>
<?php page_close();
