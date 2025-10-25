<?php
// /thank-you.php
// Renders an order confirmation from a Stripe Checkout Session (?sid=cs_...)
declare(strict_types=1);

// ---- basic security headers
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');

// ---- get the session id from the URL
$sid = $_GET['sid'] ?? '';
$err = null;
$session = null;
$pi = null;
$line_items = [];

try {
  // Find composer autoload whether you installed in / or /api
  $autoloads = [
    __DIR__ . '/vendor/autoload.php',
    __DIR__ . '/api/vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php'
  ];
  $loaded = false;
  foreach ($autoloads as $a) {
    if (is_file($a)) { require $a; $loaded = true; break; }
  }
  if (!$loaded) { throw new Exception('Stripe PHP library not found'); }

  // Load secret key (env first; optional config.php fallback)
  $sk = getenv('STRIPE_SECRET_KEY') ?: '';
  if (!$sk && is_file(__DIR__ . '/config.php')) {
    require __DIR__ . '/config.php';
    if (!empty($STRIPE_SECRET_KEY)) $sk = $STRIPE_SECRET_KEY;
  }
  if (!$sk || !preg_match('/^sk_(test|live)_/', $sk)) {
    throw new Exception('Stripe secret key missing/invalid on server');
  }

  if (!$sid || !preg_match('/^cs_(test|live)_[A-Za-z0-9]+$/', $sid)) {
    throw new Exception('Missing or invalid session id.');
  }

  \Stripe\Stripe::setApiKey($sk);

  // Pull the session + expand related objects for easy rendering
  $session = \Stripe\Checkout\Session::retrieve([
    'id' => $sid,
    'expand' => [
      'payment_intent',
      'line_items.data.price.product'
    ],
  ]);

  $pi = $session->payment_intent instanceof \Stripe\PaymentIntent
    ? $session->payment_intent
    : null;

  // If not expanded (older lib), fetch line items explicitly
  if (!isset($session->line_items)) {
    $li = \Stripe\Checkout\Session::allLineItems($sid, ['limit' => 100]);
    $line_items = $li->data;
  } else {
    $line_items = $session->line_items->data ?? [];
  }

} catch (\Throwable $e) {
  $err = $e->getMessage();
}

// Helper to format cents
function money_usd($cents): string {
  return '$' . number_format(((int)$cents)/100, 2);
}

// Basic page data
$paid   = $pi ? ($pi->status === 'succeeded') : ($session->payment_status ?? '') === 'paid';
$total  = $session->amount_total ?? ($pi->amount ?? 0);
$email  = $session->customer_details->email ?? '';
$name   = trim(($session->customer_details->name ?? '') ?: ($session->shipping->name ?? ''));
$brand  = $pi && isset($pi->charges->data[0]->payment_method_details->card->brand)
  ? $pi->charges->data[0]->payment_method_details->card->brand
  : '';
$last4  = $pi && isset($pi->charges->data[0]->payment_method_details->card->last4)
  ? $pi->charges->data[0]->payment_method_details->card->last4
  : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Thank you!</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f8fafc;color:#0f172a;margin:0}
    .wrap{max-width:880px;margin:48px auto;padding:0 20px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 4px 16px rgba(0,0,0,.04);padding:24px}
    h1{font-size:28px;margin:0 0 10px}
    .muted{color:#6b7280}
    .ok{color:#10b981;font-weight:700}
    .bad{color:#ef4444;font-weight:700}
    .grid{display:grid;grid-template-columns:1fr auto;gap:8px}
    .items{margin-top:18px;border-top:1px solid #e5e7eb;padding-top:12px}
    .item{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed #e5e7eb}
    .item:last-child{border-bottom:0}
    .total{display:flex;justify-content:space-between;margin-top:14px;font-weight:700}
    .btn{display:inline-block;margin-top:18px;background:#111827;color:#fff;padding:10px 14px;border-radius:10px;text-decoration:none}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <?php if ($err): ?>
        <h1>Thanks for your order</h1>
        <p class="bad">We couldn’t load your receipt right now.</p>
        <p class="muted">Error: <?= htmlspecialchars($err, ENT_QUOTES) ?></p>
        <a class="btn" href="/">Back to Home</a>
      <?php else: ?>
        <h1>Thanks<?= $name ? ', ' . htmlspecialchars($name) : '' ?>!</h1>
        <p class="muted">
          Order <strong><?= htmlspecialchars($sid) ?></strong><br>
          Status: <span class="<?= $paid ? 'ok' : 'bad' ?>"><?= $paid ? 'Paid' : 'Pending' ?></span>
          <?php if ($email): ?> • Receipt sent to <?= htmlspecialchars($email) ?><?php endif; ?>
          <?php if ($brand && $last4): ?> • Card <?= htmlspecialchars(strtoupper($brand)) ?> •••• <?= htmlspecialchars($last4) ?><?php endif; ?>
        </p>

        <?php if ($line_items): ?>
          <div class="items">
            <?php foreach ($line_items as $li): 
              $qty = (int)($li->quantity ?? 1);
              $prod = $li->price->product ?? null;
              $name = $prod && is_object($prod) ? ($prod->name ?? $li->description) : ($li->description ?? 'Item');
              $unit = $li->price->unit_amount ?? 0;
            ?>
              <div class="item">
                <div><?= htmlspecialchars($name) ?> × <?= $qty ?></div>
                <div><?= money_usd($unit * $qty) ?></div>
              </div>
            <?php endforeach; ?>
            <div class="total">
              <div>Total</div>
              <div><?= money_usd($total) ?></div>
            </div>
          </div>
        <?php endif; ?>

        <a class="btn" href="/">Continue Shopping</a>
      <?php endif; ?>
    </div>
  </div>
</body>
<script>
  try { localStorage.removeItem('cart'); sessionStorage.removeItem('cart'); } catch(e) {}
</script>
</html>
