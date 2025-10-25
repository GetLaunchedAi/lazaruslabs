<?php
// Always return JSON, even on fatals/warnings
header('Content-Type: application/json');

// Turn OFF HTML error output; log instead
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Convert warnings/notices to JSON errors
set_error_handler(function($severity, $message, $file, $line) {
  http_response_code(500);
  echo json_encode(['error' => "PHP error: $message in $file:$line"]);
  exit;
});

// Catch fatal parse/require errors too
register_shutdown_function(function () {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    http_response_code(500);
    echo json_encode(['error' => "Fatal error: {$e['message']} in {$e['file']}:{$e['line']}"]);
  }
});

// ---- Load composer autoload (support both locations) ----
$autoloads = [
  __DIR__ . '/vendor/autoload.php',     // composer ran in /api
  __DIR__ . '/../vendor/autoload.php',  // composer ran in project root
];
$loaded = false;
foreach ($autoloads as $a) {
  if (file_exists($a)) { require $a; $loaded = true; break; }
}
if (!$loaded) {
  http_response_code(500);
  echo json_encode(['error' => 'Stripe PHP library not found. Run `composer require stripe/stripe-php` in /api or project root.']);
  exit;
}

// ---- Keys: prefer env; optional fallback to config.php ----
$pk = getenv('STRIPE_PUBLIC_KEY') ?: '';
$sk = getenv('STRIPE_SECRET_KEY') ?: '';
if (!$sk || !preg_match('/^sk_(test|live)_/', $sk)) {
  // optional fallback if you store keys in config.php
  $cfg = __DIR__ . '/../config.php';
  if (file_exists($cfg)) { require $cfg; }
  if (!$sk && !empty($STRIPE_SECRET_KEY)) $sk = $STRIPE_SECRET_KEY;
}
if (!$sk || !preg_match('/^sk_(test|live)_/', $sk)) {
  http_response_code(500);
  echo json_encode(['error' => 'Stripe secret key missing or invalid on server']);
  exit;
}

\Stripe\Stripe::setApiKey($sk);

// ---- Read & validate JSON body ----
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body)) {
  http_response_code(400);
  echo json_encode(['error' => 'Request body is not valid JSON']);
  exit;
}
$cart = $body['cart'] ?? [];
if (!is_array($cart) || !count($cart)) {
  http_response_code(400);
  echo json_encode(['error' => 'Cart is empty']);
  exit;
}

// ---- Build line items ----
$line_items = [];
foreach ($cart as $i) {
  $name = trim($i['name'] ?? 'Item');
  $qty  = max(1, (int)($i['quantity'] ?? 1));
  if (!isset($i['price'])) {
    http_response_code(400);
    echo json_encode(['error' => "Missing price for '$name'"]);
    exit;
  }
  $unit_amount = (int) round(floatval($i['price']) * 100); // dollars -> cents
  if ($unit_amount < 50) { // Stripe min 50¢
    http_response_code(400);
    echo json_encode(['error' => "Unit amount too low for '$name'"]);
    exit;
  }
  $line_items[] = [
    'price_data' => [
      'currency' => 'usd',
      'product_data' => ['name' => $name],
      'unit_amount'  => $unit_amount,
    ],
    'quantity' => $qty,
  ];
}

// ---- URLs ----
$scheme = isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
  ? $_SERVER['HTTP_X_FORWARDED_PROTO']
  : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base = $scheme . '://' . $host;

$successUrl = $base . '/thank-you.php?sid={CHECKOUT_SESSION_ID}';
$cancelUrl  = $base . '/cart.php'; // change to cart.html if that’s your page

// ---- Create session ----
try {
  $session = \Stripe\Checkout\Session::create([
    'mode' => 'payment',
    'line_items' => $line_items,
    'success_url' => $successUrl,
    'cancel_url'  => $cancelUrl,
  ]);
  echo json_encode(['id' => $session->id]);
} catch (\Stripe\Exception\ApiErrorException $e) {
  http_response_code(500);
  echo json_encode(['error' => $e->getMessage()]);
}
