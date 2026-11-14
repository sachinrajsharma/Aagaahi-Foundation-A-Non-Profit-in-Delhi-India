<?php
/** /donate/verify.php: the browser comes back here after paying. The payment counts only if Razorpay's signature checks out. */
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/donations.php';

$order = (string)($_POST['order_id'] ?? ''); $pay = (string)($_POST['payment_id'] ?? ''); $sig = (string)($_POST['signature'] ?? '');
$ok = false; $id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($pdo = db_try()) && rzp_verify_payment_signature($order, $pay, $sig)
    && preg_match('/^[A-Za-z0-9_]{6,64}$/', $order) && preg_match('/^[A-Za-z0-9_]{6,64}$/', $pay)) {
    $q = $pdo->prepare('SELECT id, status FROM donations WHERE gateway_order_id = ? AND gateway = "razorpay"');
    $q->execute([$order]);
    $row = $q->fetch();
    if ($row) {
        $id = (int)$row['id'];
        $paid = donation_mark_paid($pdo, $id, $pay, null, 'razorpay');
        if ($paid) donation_send_receipt($paid);   // only the call that changed the row sends the receipt
        $ok = true;
    }
}
if ($ok) { header('Location: /donate/receipt.php?id=' . $id . '&t=' . receipt_token($id), true, 303); exit; }
http_response_code(400);
page_open('Payment not confirmed', '', false);
?>
<div class="fcard"><h1>We could not confirm the payment</h1>
<div class="ferr">If money left your account, do not worry: it will be matched to your donation automatically and your receipt will be emailed. Otherwise you can <a href="/donate/">try again</a>.</div></div>
<?php page_close();
