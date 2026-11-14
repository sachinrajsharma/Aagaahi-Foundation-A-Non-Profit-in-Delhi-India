<?php
/** /volunteer/: volunteer sign-up. Adults only (18+); we keep name, email, optional phone and city, interests, availability and a note. */
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/mail.php';

$errors = []; $done = false; $limited = false;
$v = ['name' => '', 'email' => '', 'phone' => '', 'city' => '', 'availability' => '', 'message' => '', 'interests' => []];
$allowed = volunteer_interests();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (form_looks_automated()) {
        $done = true;   // say nothing useful to bots
    } else {
        $v['name'] = clean_line((string)($_POST['name'] ?? ''), 120);
        $v['email'] = trim((string)($_POST['email'] ?? ''));
        $v['phone'] = clean_line((string)($_POST['phone'] ?? ''), 30);
        $v['city'] = clean_line((string)($_POST['city'] ?? ''), 80);
        $v['availability'] = clean_line((string)($_POST['availability'] ?? ''), 200);
        $v['message'] = clean_text((string)($_POST['message'] ?? ''), 2000);
        $v['interests'] = array_values(array_intersect($allowed, array_map('strval', (array)($_POST['interests'] ?? []))));
        if ($v['name'] === '') $errors[] = 'Please tell us your name.';
        if (!valid_email_address($v['email'])) $errors[] = 'Please enter a valid email address.';
        if (!valid_phone($v['phone'])) $errors[] = 'The phone number looks wrong. Use digits, spaces, + or -.';
        if (empty($_POST['adult'])) $errors[] = 'Volunteers must be 18 or older. If you are younger, ask a parent or teacher to write to us.';
        if (empty($_POST['consent'])) $errors[] = 'Please agree that we may contact you about volunteering.';
        if (!$errors) {
            $pdo = db_try();
            if (!$pdo) {
                $errors[] = 'We could not save your sign-up just now. Please email us instead.';
            } else {
                try {
                    if (!form_rate_ok('volunteer', 3, $pdo)) {
                        $limited = true;
                    } else {
                        $pdo->prepare('INSERT INTO volunteer_applications (name, email, phone, city, interests, availability, message, consent_at, ip_hash) VALUES (?,?,?,?,?,?,?,NOW(),?)')
                            ->execute([$v['name'], $v['email'], $v['phone'] ?: null, $v['city'] ?: null, implode(', ', $v['interests']) ?: null, $v['availability'] ?: null, $v['message'] ?: null, ip_hash()]);
                        $done = true;
                        send_mail($v['email'], 'Thank you for offering to volunteer',
                            "Dear {$v['name']},\n\nThank you for offering your time to {$ngo['name']}. Our team will read your details and write to you soon.\n\nWith thanks,\n{$ngo['name']}\n");
                        if (org_email() !== '') send_mail(org_email(), 'New volunteer: ' . $v['name'], "Name: {$v['name']}\nEmail: {$v['email']}\nCity: {$v['city']}\nInterests: " . implode(', ', $v['interests']) . "\n\nSee the dashboard > Volunteers.\n", null, $v['email']);
                    }
                } catch (Throwable $ex) { error_log('[aagahi] volunteer save failed: ' . $ex->getMessage()); $errors[] = 'We could not save your sign-up just now. Please try again later.'; }
            }
        }
    }
}
page_open('Volunteer', 'Offer your time to ' . $ngo['name'] . ': teaching, hygiene camps, road and online safety, and more.');
?>
<div class="fcard">
<h1>Volunteer with us</h1>
<p class="lead">Give a few hours and change a life in Delhi. Tell us a little about you and we will be in touch.</p>
<?php if ($done): ?>
  <div class="fok"><b>Thank you!</b> We have your details and will write to you soon. <a href="/">Back to home</a></div>
<?php else: ?>
  <?php if ($limited): ?><div class="ferr">You have sent several sign-ups recently. Please try again in an hour.</div><?php endif; ?>
  <?php if ($errors): ?><div class="ferr" role="alert"><?php foreach ($errors as $m) echo '<div>' . e($m) . '</div>'; ?></div><?php endif; ?>
  <form class="fform" method="post" action="/volunteer/">
    <?= form_hidden_fields() ?>
    <div class="row2"><label>Your name<input type="text" name="name" required maxlength="120" value="<?= e($v['name']) ?>" autocomplete="name"></label>
    <label>Email<input type="email" name="email" required maxlength="190" value="<?= e($v['email']) ?>" autocomplete="email"></label></div>
    <div class="row2"><label>Phone <small>(optional)</small><input type="tel" name="phone" maxlength="30" value="<?= e($v['phone']) ?>" autocomplete="tel"></label>
    <label>City <small>(optional)</small><input type="text" name="city" maxlength="80" value="<?= e($v['city']) ?>"></label></div>
    <fieldset><legend>How would you like to help?</legend><div class="checks">
      <?php foreach ($allowed as $i): ?><label><input type="checkbox" name="interests[]" value="<?= e($i) ?>"<?= in_array($i, $v['interests'], true) ? ' checked' : '' ?>> <?= e($i) ?></label><?php endforeach; ?>
    </div></fieldset>
    <label>When are you free? <small>(optional)</small><input type="text" name="availability" maxlength="200" value="<?= e($v['availability']) ?>" placeholder="e.g. weekends, evenings"></label>
    <label>Anything else? <small>(optional)</small><textarea name="message" rows="4" maxlength="2000"><?= e($v['message']) ?></textarea></label>
    <label class="consent"><input type="checkbox" name="adult" value="1" required> <span>I am 18 years or older.</span></label>
    <label class="consent"><input type="checkbox" name="consent" value="1" required> <span>I agree that <?= e($ngo['name']) ?> may keep these details and contact me about volunteering. I can ask to have them deleted at any time.</span></label>
    <button class="go" type="submit">Send my details</button>
    <p class="fnote">We keep only what you type here. We never ask for ID documents.</p>
  </form>
<?php endif; ?>
</div>
<?php page_close();
