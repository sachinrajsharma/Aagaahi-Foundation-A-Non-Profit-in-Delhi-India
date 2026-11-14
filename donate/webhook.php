<?php
/**
 * /donate/webhook.php: Razorpay calls this server to server (set it up in Razorpay dashboard > Webhooks, events
 * payment.captured, order.paid, payment.failed, with the secret you put in config.local.php as razorpay.webhook_secret).
 * Signature checked on the raw body; safe to be called many times for one payment.
 */
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/donations.php';

header('Content-Type: text/plain; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('POST only'); }
$raw = (string)file_get_contents('php://input');
if (strlen($raw) > 200000 || !rzp_verify_webhook($raw, (string)($_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? ''))) { http_response_code(400); exit('bad signature'); }

$j = json_decode($raw, true);
$event = is_array($j) ? (string)($j['event'] ?? '') : '';
$pay = $j['payload']['payment']['entity'] ?? [];
$ord = $j['payload']['order']['entity'] ?? [];
$orderId = (string)($pay['order_id'] ?? $ord['id'] ?? '');
$paymentId = (string)($pay['id'] ?? '');
$amount = isset($pay['amount']) ? (int)$pay['amount'] : (isset($ord['amount_paid']) ? (int)$ord['amount_paid'] : null);
$currency = (string)($pay['currency'] ?? $ord['currency'] ?? 'INR');

$pdo = db_try();
if (!$pdo) { http_response_code(503); exit('database unavailable'); }   // Razorpay will retry
if ($orderId === '' || !preg_match('/^[A-Za-z0-9_]{6,64}$/', $orderId)) { http_response_code(200); exit('ignored'); }

$q = $pdo->prepare('SELECT id, status FROM donations WHERE gateway_order_id = ? AND gateway = "razorpay"');
$q->execute([$orderId]);
$row = $q->fetch();
if (!$row) { http_response_code(200); exit('unknown order'); }

if (in_array($event, ['payment.captured', 'order.paid'], true)) {
    if ($currency !== 'INR' || $amount === null) { http_response_code(200); exit('mismatch'); }
    $paid = donation_mark_paid($pdo, (int)$row['id'], $paymentId !== '' ? $paymentId : null, $amount, 'razorpay');
    if ($paid) donation_send_receipt($paid);
    exit('ok');
}
if ($event === 'payment.failed') {
    $pdo->prepare('UPDATE donations SET status = "failed" WHERE id = ? AND status = "created"')->execute([(int)$row['id']]);
    exit('ok');
}
exit('ignored');
