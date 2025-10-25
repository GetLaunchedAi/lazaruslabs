<?php
// /admin/logout.php — clear the cookie and bounce to /admin
setcookie('admin_auth','',[
  'expires'=>time()-3600,
  'path'=>'/admin',
  'secure'=>isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off',
  'httponly'=>true,
  'samesite'=>'Lax',
]);
header('Location: /admin/', true, 302);
exit;
