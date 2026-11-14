<?php
/**
 * /donate/: donation form.
 *  • With Razorpay keys configured: creates a payment order and opens Razorpay's secure checkout.
 *  • Without keys: shows our UPI / bank details (from Settings) and records a pledge that staff confirm in the dashboard.
 * We store name, email, optional phone, amount, purpose and whether a 80G certificate is wanted. Never card, PAN or ID numbers.
 */
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/donations.php';

$errors = []; $limited = false; $stage = 'form'; $don = null; $orderId = '';
$v = ['name' => '', 'email' => '', 'phone' => '', 'purpose' => 'where-needed', 'amount' => '1000', 'custom' => '', 'wants_80g' => false];
$presets = [500, 1000, 2500, 5000];
$online = rzp_config() !== null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (form_looks_automated()) {
        $errors[] = 'Something went wrong. Please reload the page and try again.';
    } else {
        $v['name'] = clean_line((string)($_POST['name'] ?? ''), 120);
        $v['email'] = trim((string)($_POST['email'] ?? ''));
        $v['phone'] = clean_line((string)($_POST['phone'] ?? ''), 30);
        $v['purpose'] = array_key_exists((string)($_POST['purpose'] ?? ''), DONATION_PURPOSES) ? (string)$_POST['purpose'] : 'where-needed';
        $v['amount'] = (string)($_POST['amount'] ?? '');
        $v['custom'] = clean_line((string)($_POST['custom'] ?? ''), 12);
        $v['wants_80g'] = !empty($_POST['wants_80g']);
        $paise = $v['amount'] === 'custom' ? rupees_to_paise($v['custom']) : rupees_to_paise($v['amount']);
        if ($v['name'] === '') $errors[] = 'Please tell us your name.';
        if (!valid_email_address($v['email'])) $errors[] = 'Please enter a valid email address (we send your receipt there).';
        if (!valid_phone($v['phone'])) $errors[] = 'The phone number looks wrong.';
        if ($paise === null || $paise < DONATION_MIN_PAISE) $errors[] = 'The smallest donation is ' . paise_text(DONATION_MIN_PAISE) . '.';
        elseif ($paise > DONATION_MAX_PAISE) $errors[] = 'For gifts above ' . paise_text(DONATION_MAX_PAISE) . ' please write to us so we can arrange it properly.';
        if (empty($_POST['consent'])) $errors[] = 'Please agree to the note about how we use your details.';
        if (!$errors) {
            $pdo = db_try();
            if (!$pdo) {
                $errors[] = 'We could not start your donation just now. Please try again later or email us.';
            } else {
                try {
                    if (!form_rate_ok('donate', 10, $pdo)) {
                        $limited = true;
                    } else {
                        $row = ['name' => $v['name'], 'email' => $v['email'], 'phone' => $v['phone'], 'amount_paise' => $paise, 'purpose' => $v['purpose'], 'wants_80g' => $v['wants_80g']];
                        if ($online) {
                            $orderId = rzp_create_order($paise, 'don-' . bin2hex(random_bytes(6)), ['purpose' => $v['purpose']]);
                            if ($orderId === null) {
                                $errors[] = 'The payment service did not respond. Please try again in a moment.';
                            } else {
                                $don = donation_create($pdo, $row + ['gateway' => 'razorpay', 'order_id' => $orderId]);
                                $stage = 'pay';
                            }
                        } else {
                            $don = donation_create($pdo, $row + ['gateway' => 'upi']);
                            $stage = 'manual';
                            $ref = 'DON-' . $don;
                            $upi = (string)setting('upi_id', ''); $bank = (string)setting('bank_details', '');
                            send_mail($v['email'], 'Your donation pledge ' . $ref,
                                "Dear {$v['name']},\n\nThank you for pledging " . paise_text($paise) . " to {$ngo['name']}.\nPlease quote reference $ref when you pay.\n\n"
                                . ($upi !== '' ? "UPI: $upi\n" : '') . ($bank !== '' ? "Bank details:\n$bank\n" : '')
                                . "\nWe will email your receipt once the money reaches us.\n");
                        }
                    }
                } catch (Throwable $ex) { error_log('[aagahi] donation start failed: ' . $ex->getMessage()); $errors[] = 'We could not start your donation just now. Please try again later.'; }
            }
        }
    }
}
page_open('Donate', 'Support ' . $ngo['name'] . '. Your gift funds free study support, health camps and safety workshops in Delhi.');
$note = (string)setting('donation_note', '');
?>
<div class="fcard">
<h1>Donate</h1>
<?php if ($stage === 'pay'): $c = rzp_config(); ?>
  <p class="lead">Thank you. Press the button to pay <b><?= e(paise_text((int)$paise)) ?></b> securely. You can use UPI, cards or net banking.</p>
  <div id="pay-msg" class="ferr" hidden role="alert"></div>
  <form id="rzp-return" method="post" action="/donate/verify.php" hidden>
    <input type="hidden" name="order_id" value="<?= e($orderId) ?>"><input type="hidden" name="payment_id" id="rzp-pay"><input type="hidden" name="signature" id="rzp-sig">
  </form>
  <button class="go fform" style="display:block;width:100%" id="pay-btn" type="button"
    data-key="<?= e($c['key_id']) ?>" data-order="<?= e($orderId) ?>" data-amount="<?= (int)$paise ?>" data-name="<?= e($ngo['name']) ?>"
    data-donor="<?= e($v['name']) ?>" data-email="<?= e($v['email']) ?>" data-phone="<?= e($v['phone']) ?>" data-api="<?= e($c['api_base'] === 'https://api.razorpay.com' ? '' : $c['api_base']) ?>">Pay <?= e(paise_text((int)$paise)) ?></button>
  <p class="fnote">Payment is handled by Razorpay. We never see your card or UPI details.</p>
  <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
  <script src="assets/donate.js"></script>
<?php elseif ($stage === 'manual'): ?>
  <div class="fok"><b>Thank you, <?= e($v['name']) ?>!</b> Your pledge of <b><?= e(paise_text((int)$paise)) ?></b> is noted. Reference: <b>DON-<?= (int)$don ?></b></div>
  <p>Please send the money using one of these, and quote the reference:</p>
  <?php if (setting('upi_id', '')): ?><div class="paybox"><b>UPI</b>
<?= e(setting('upi_id', '')) ?></div><?php endif; ?>
  <?php if (setting('bank_details', '')): ?><div class="paybox"><b>Bank transfer</b>
<?= e(setting('bank_details', '')) ?></div><?php endif; ?>
  <?php if (!setting('upi_id', '') && !setting('bank_details', '')): ?><p>We will email you the payment details shortly.</p><?php endif; ?>
  <p class="fnote">We email your receipt as soon as the money reaches us. A copy of these details was sent to <?= e($v['email']) ?>.</p>
<?php else: ?>
  <p class="lead">Your gift pays for free study support, hygiene and health camps, and safety workshops in Delhi.</p>
  <?php if ($note !== ''): ?><p class="fnote" style="margin-bottom:1rem"><?= e($note) ?></p><?php endif; ?>
  <?php if ($limited): ?><div class="ferr">You have started several donations recently. Please try again in an hour.</div><?php endif; ?>
  <?php if ($errors): ?><div class="ferr" role="alert"><?php foreach ($errors as $m) echo '<div>' . e($m) . '</div>'; ?></div><?php endif; ?>
  <form class="fform" method="post" action="/donate/">
    <?= form_hidden_fields() ?>
    <fieldset><legend>Amount</legend><div class="amounts">
      <?php foreach ($presets as $p): ?><label><input type="radio" name="amount" value="<?= $p ?>"<?= $v['amount'] === (string)$p ? ' checked' : '' ?>><span>₹<?= inr($p) ?></span></label><?php endforeach; ?>
      <label><input type="radio" name="amount" value="custom"<?= $v['amount'] === 'custom' ? ' checked' : '' ?>><span>Other</span></label>
    </div>
    <label style="margin-top:.7rem">Other amount in rupees<input type="text" inputmode="decimal" name="custom" maxlength="12" value="<?= e($v['custom']) ?>" placeholder="e.g. 750"></label></fieldset>
    <label>Where should it go?<select name="purpose"><?php foreach (DONATION_PURPOSES as $k => $lab): ?><option value="<?= e($k) ?>"<?= $v['purpose'] === $k ? ' selected' : '' ?>><?= e($lab) ?></option><?php endforeach; ?></select></label>
    <div class="row2"><label>Your name<input type="text" name="name" required maxlength="120" value="<?= e($v['name']) ?>" autocomplete="name"></label>
    <label>Email <small>(for your receipt)</small><input type="email" name="email" required maxlength="190" value="<?= e($v['email']) ?>" autocomplete="email"></label></div>
    <label>Phone <small>(optional)</small><input type="tel" name="phone" maxlength="30" value="<?= e($v['phone']) ?>" autocomplete="tel"></label>
    <label class="consent"><input type="checkbox" name="wants_80g" value="1"<?= $v['wants_80g'] ? ' checked' : '' ?>> <span>I would like to know about a tax certificate under section 80G. (We will write to you; never send your PAN by email or in this form.)</span></label>
    <label class="consent"><input type="checkbox" name="consent" value="1" required> <span>I agree that <?= e($ngo['name']) ?> may keep these details to record my donation and send my receipt.</span></label>
    <button class="go" type="submit"><?= $online ? 'Continue to payment' : 'Continue' ?></button>
    <p class="fnote"><?= $online ? 'You will pay on Razorpay\'s secure page.' : 'Next you will see how to send the money by UPI or bank transfer.' ?></p>
  </form>
<?php endif; ?>
</div>
<?php page_close();
