<?php
/**
 * includes/donations.php — donations: records, receipts and the optional Razorpay online payment.
 *
 * Two modes, chosen automatically:
 *   • Online: $ngo['razorpay'] = ['key_id'=>..., 'key_secret'=>..., 'webhook_secret'=>...] in includes/config.local.php.
 *     The donor pays on Razorpay's secure checkout. We never see card or UPI details. Payment is confirmed only after
 *     a signature check (browser return) or a signed webhook (server to server), both idempotent.
 *   • Manual: no keys. The page shows the UPI id / bank details from Settings and records a pledge that staff mark as
 *     received in the dashboard (which then issues the receipt).
 * Amounts are whole paise (integers). Nothing here stores card numbers, PAN or any ID document.
 */
require_once __DIR__ . '/helpers.php';   // e(), inr()
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/forms.php';

const DONATION_MIN_PAISE = 5000;          // Rs 50
const DONATION_MAX_PAISE = 50000000;      // Rs 5,00,000 (larger gifts: contact us, so bank transfer + KYC are handled properly)

const DONATION_PURPOSES = [
    'where-needed' => 'Where it is needed most',
    'education'    => 'Education and study support',
    'hygiene'      => 'Hygiene and health camps',
    'safety'       => 'Road and online safety',
    'rights'       => 'Workplace rights awareness',
];

function donation_purpose_label(string $key): string
{
    return DONATION_PURPOSES[$key] ?? 'Where it is needed most';
}

/** Rupees (e.g. 1500 or "1,500.50") to paise; null if not a sensible amount. */
function rupees_to_paise($v): ?int
{
    $s = str_replace([',', ' ', '₹'], '', trim((string)$v));
    if ($s === '' || !preg_match('/^\d{1,9}(\.\d{1,2})?$/', $s)) return null;
    return (int)round(((float)$s) * 100);
}

function paise_text(int $paise): string
{
    $r = intdiv($paise, 100); $p = $paise % 100;
    return '₹' . inr($r) . ($p ? '.' . str_pad((string)$p, 2, '0', STR_PAD_LEFT) : '');
}

/** Indian financial year of a date: 1 April to 31 March, e.g. "2026-27". */
function financial_year(?string $date = null): string
{
    $t = $date ? strtotime($date) : time();
    $y = (int)date('Y', $t); $m = (int)date('n', $t);
    $start = $m >= 4 ? $y : $y - 1;
    return $start . '-' . substr((string)($start + 1), -2);
}

/* ---------- Razorpay ---------- */

function rzp_config(): ?array
{
    global $ngo;
    $c = $ngo['razorpay'] ?? [];
    if (empty($c['key_id']) || empty($c['key_secret'])) return null;
    $c['api_base'] = rtrim((string)($c['api_base'] ?? 'https://api.razorpay.com'), '/');
    return $c;
}

/** Creates an order on Razorpay; returns its id or null. */
function rzp_create_order(int $amountPaise, string $receiptRef, array $notes = []): ?string
{
    $c = rzp_config();
    if (!$c) return null;
    $payload = json_encode(['amount' => $amountPaise, 'currency' => 'INR', 'receipt' => $receiptRef, 'notes' => $notes]);
    $ch = curl_init($c['api_base'] . '/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_USERPWD => $c['key_id'] . ':' . $c['key_secret'], CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = is_string($body) ? json_decode($body, true) : null;
    return ($code >= 200 && $code < 300 && !empty($j['id']) && is_string($j['id'])) ? $j['id'] : null;
}

/** Checks the signature Razorpay's checkout returns to the browser. */
function rzp_verify_payment_signature(string $orderId, string $paymentId, string $signature): bool
{
    $c = rzp_config();
    if (!$c || $orderId === '' || $paymentId === '' || $signature === '') return false;
    return hash_equals(hash_hmac('sha256', $orderId . '|' . $paymentId, $c['key_secret']), strtolower($signature));
}

/** Checks the X-Razorpay-Signature header of a webhook against the raw request body. */
function rzp_verify_webhook(string $rawBody, string $signature): bool
{
    $c = rzp_config();
    if (!$c || empty($c['webhook_secret']) || $signature === '') return false;
    return hash_equals(hash_hmac('sha256', $rawBody, $c['webhook_secret']), strtolower($signature));
}

/* ---------- records ---------- */

function donation_create(PDO $pdo, array $d): int
{
    $pdo->prepare('INSERT INTO donations (donor_name, donor_email, donor_phone, amount_paise, currency, purpose, gateway, gateway_order_id, status, wants_80g)
                   VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([$d['name'], $d['email'], $d['phone'] !== '' ? $d['phone'] : null, $d['amount_paise'], 'INR', $d['purpose'], $d['gateway'],
                   $d['order_id'] ?? null, $d['status'] ?? 'created', empty($d['wants_80g']) ? 0 : 1]);
    return (int)$pdo->lastInsertId();
}

/**
 * Marks a donation as paid, once. Returns the donation row when THIS call made the change (so the caller sends the
 * receipt), or null if it was already paid / not found. $paymentId empty for offline money.
 */
function donation_mark_paid(PDO $pdo, int $id, ?string $paymentId = null, ?int $expectAmountPaise = null, ?string $gateway = null): ?array
{
    for ($try = 0; $try < 4; $try++) {
        try {
            $pdo->beginTransaction();
            $q = $pdo->prepare('SELECT * FROM donations WHERE id = ? FOR UPDATE');
            $q->execute([$id]);
            $d = $q->fetch();
            if (!$d || $d['status'] === 'paid') { $pdo->rollBack(); return null; }
            if ($expectAmountPaise !== null && (int)$d['amount_paise'] !== $expectAmountPaise) { $pdo->rollBack(); return null; }
            $fy = financial_year();
            $prefix = (string)(setting('receipt_prefix', 'AAG') ?: 'AAG');
            $like = $prefix . '/' . $fy . '/%';
            $m = $pdo->prepare("SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(receipt_no, '/', -1) AS UNSIGNED)), 0) FROM donations WHERE receipt_no LIKE ?");
            $m->execute([$like]);
            $receipt = $prefix . '/' . $fy . '/' . str_pad((string)((int)$m->fetchColumn() + 1), 5, '0', STR_PAD_LEFT);
            $pdo->prepare('UPDATE donations SET status = "paid", paid_at = NOW(), receipt_no = ?, gateway_payment_id = COALESCE(?, gateway_payment_id), gateway = COALESCE(?, gateway) WHERE id = ?')
                ->execute([$receipt, $paymentId !== '' ? $paymentId : null, $gateway, $id]);
            $pdo->commit();
            $q->execute([$id]);
            return $q->fetch() ?: null;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e->getCode() === '23000' && $try < 3) continue;   // receipt number or payment id collided: try again
            if ($e->getCode() === '23000') return null;            // same payment id already recorded on another row
            throw $e;
        }
    }
    return null;
}

/* ---------- receipts ---------- */

function receipt_token(int $id): string
{
    return substr(hash_hmac('sha256', 'receipt|' . $id, app_secret()), 0, 32);
}

function receipt_url(int $id): string
{
    return site_root_url() . '/donate/receipt.php?id=' . $id . '&t=' . receipt_token($id);
}

function site_root_url(): string
{
    global $ngo;
    $u = rtrim((string)($ngo['site_url'] ?? ''), '/');
    if ($u !== '' && !str_contains($u, 'github.io')) return $u;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

/** Emails the donor an acknowledgement and tells the team. */
function donation_send_receipt(array $d): void
{
    global $ngo;
    $amount = paise_text((int)$d['amount_paise']);
    $url = receipt_url((int)$d['id']);
    $text = "Dear {$d['donor_name']},\n\nThank you. We have received your donation of $amount for " . donation_purpose_label((string)$d['purpose']) . ".\n"
          . "Receipt number: {$d['receipt_no']}\nYour acknowledgement: $url\n\n"
          . (!empty($d['wants_80g']) ? "You asked about tax benefit under section 80G. Our team will write to you if we need your PAN to issue the 80G certificate; please do not send it by email.\n\n" : '')
          . "With gratitude,\n{$ngo['name']}\n";
    $html = mail_html('Thank you for your donation', '<p>Dear ' . e($d['donor_name']) . ',</p><p>We have received your donation of <b>' . e($amount) . '</b> for '
          . e(donation_purpose_label((string)$d['purpose'])) . '.</p><p>Receipt number: <b>' . e($d['receipt_no']) . '</b></p><p><a href="' . e($url) . '">View your acknowledgement</a></p>'
          . (!empty($d['wants_80g']) ? '<p>You asked about tax benefit under section 80G. Our team will write to you if we need your PAN to issue the certificate; please do not send it by email.</p>' : '')
          . '<p>With gratitude,<br>' . e($ngo['name']) . '</p>');
    send_mail((string)$d['donor_email'], 'Thank you: receipt ' . $d['receipt_no'], $text, $html);
    $notify = org_email();
    if ($notify !== '') {
        send_mail($notify, 'Donation received: ' . $amount . ' (' . $d['receipt_no'] . ')',
            "{$d['donor_name']} <{$d['donor_email']}> donated $amount.\nPurpose: " . donation_purpose_label((string)$d['purpose']) . "\n80G asked: " . (!empty($d['wants_80g']) ? 'yes' : 'no') . "\nReceipt: {$d['receipt_no']}\n", null, (string)$d['donor_email']);
    }
}
