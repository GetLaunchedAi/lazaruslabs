<?php
// /public_html/api/save-products.php
declare(strict_types=1);

// Allow only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Allow: POST');
  echo 'Method Not Allowed';
  exit;
}

// Auth: rely on /admin login cookie instead of token header
if (empty($_COOKIE['admin_auth']) || $_COOKIE['admin_auth'] !== '1') {
  http_response_code(403);
  echo 'Forbidden: admin session required';
  exit;
}

// File paths
$root = dirname(__DIR__, 1); // /public_html
$dataFile = $root . '/products.json';
$backupDir = $root . '/backups';

// Read current file for backup/etag
$current = file_exists($dataFile) ? file_get_contents($dataFile) : '[]';
$currentEtag = '"' . sha1($current) . '"';

// Read incoming JSON
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
  http_response_code(400);
  echo 'Payload must be a JSON array';
  exit;
}

// Optional: light validation (slug or name must exist)
foreach ($data as $i => $p) {
  if (!is_array($p) || empty($p['slug'])) {
    http_response_code(400);
    echo "Item $i missing 'slug'";
    exit;
  }
}

// Ensure backup directory exists
if (!is_dir($backupDir)) {
  @mkdir($backupDir, 0755, true);
}

// Backup current file
$ts = date('Ymd-His');
@file_put_contents("$backupDir/products-$ts.json", $current);

// Atomic write (safe overwrite)
$tmp = $dataFile . '.tmp';
$newJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
if (file_put_contents($tmp, $newJson, LOCK_EX) === false) {
  http_response_code(500);
  echo 'Failed to write temp file';
  exit;
}
if (!@rename($tmp, $dataFile)) {
  if (file_put_contents($dataFile, $newJson, LOCK_EX) === false) {
    http_response_code(500);
    echo 'Failed to save file';
    exit;
  }
}
// Respond with new ETag and slug of last product (useful for redirects)
$newEtag = '"' . sha1($newJson) . '"';
header('Content-Type: application/json');
echo json_encode([
  'ok' => true,
  'etag' => $newEtag,
  'slug' => $data[count($data) - 1]['slug'] ?? null
]);
