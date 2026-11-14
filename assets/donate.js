// Opens Razorpay checkout for the order prepared by /donate/ and returns the signed result to /donate/verify.php.
(function () {
  var b = document.getElementById('pay-btn');
  if (!b) return;
  var msg = document.getElementById('pay-msg');
  function fail(t) { if (msg) { msg.textContent = t; msg.hidden = false; } b.disabled = false; }
  b.addEventListener('click', function () {
    if (typeof Razorpay === 'undefined') { fail('The payment window could not load. Check your connection and try again.'); return; }
    b.disabled = true;
    var rz = new Razorpay({
      key: b.dataset.key, order_id: b.dataset.order, amount: Number(b.dataset.amount), currency: 'INR', name: b.dataset.name,
      prefill: { name: b.dataset.donor, email: b.dataset.email, contact: b.dataset.phone },
      theme: { color: '#14532d' },
      handler: function (r) {
        document.getElementById('rzp-pay').value = r.razorpay_payment_id;
        document.getElementById('rzp-sig').value = r.razorpay_signature;
        document.getElementById('rzp-return').submit();
      },
      modal: { ondismiss: function () { b.disabled = false; } }
    });
    rz.on('payment.failed', function () { fail('The payment did not go through. No money was taken. You can try again.'); });
    rz.open();
  });
})();
