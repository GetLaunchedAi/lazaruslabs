<?php
// /config.php
// Prefer Apache/.htaccess env vars (live). Fall back to a local, gitignored file (dev).

$localKeys = [];
$localFile = __DIR__ . '/.local-keys.php';
if (file_exists($localFile)) {
  // Must return an array like:
  // return ['STRIPE_PUBLIC_KEY' => 'pk_test_...', 'STRIPE_SECRET_KEY' => 'sk_test_...'];
  $localKeys = include $localFile;
}

function env_or_local($key, $local) {
  $v = getenv($key);
  if ($v !== false && $v !== '') return $v;
  return $local[$key] ?? '';
}

$STRIPE_PUBLIC_KEY = env_or_local('STRIPE_PUBLIC_KEY', $localKeys);
$STRIPE_SECRET_KEY = env_or_local('STRIPE_SECRET_KEY', $localKeys);
