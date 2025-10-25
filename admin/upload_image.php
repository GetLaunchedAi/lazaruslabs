<?php
// /admin/upload_image.php
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

if (empty($_COOKIE['admin_auth']) || $_COOKIE['admin_auth'] !== '1') {
  http_response_code(401);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'No file uploaded']);
  exit;
}

// product name must come from the form
if (empty($_POST['productName'])) {
  http_response_code(400);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Missing productName']);
  exit;
}

// Validate size
$maxBytes = 8 * 1024 * 1024;
if ($_FILES['image']['size'] > $maxBytes) {
  http_response_code(413);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'File too large (max 8MB)']);
  exit;
}

// Mime validation
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($_FILES['image']['tmp_name']);
$allowed = [
  'image/jpeg' => 'jpg',
  'image/png'  => 'png',
  'image/webp' => 'webp',
];

if (!isset($allowed[$mime])) {
  http_response_code(415);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Unsupported image type']);
  exit;
}

// Slugify product name
function slug($s) {
  $s = preg_replace('~[^\pL\d]+~u', '-', $s);
  $s = trim($s, '-');
  $s = iconv('UTF-8', 'ASCII//TRANSLIT', $s);
  $s = preg_replace('~[^-\w]+~', '', $s);
  $s = strtolower($s);
  return $s ?: 'product';
}

$slug = slug($_POST['productName']);
$ext  = $allowed[$mime];
$filename = "{$slug}.{$ext}";

// Save
$webRelDir = '/images/products';
$fsAbsDir  = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $webRelDir;
if (!is_dir($fsAbsDir)) mkdir($fsAbsDir, 0755, true);

$destPath = $fsAbsDir . '/' . $filename;
if (!move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
  exit;
}
@chmod($destPath, 0644);

header('Content-Type: application/json');
echo json_encode([
  'ok' => true,
  'filename' => $filename,
  'url' => $webRelDir . '/' . $filename,
]);
