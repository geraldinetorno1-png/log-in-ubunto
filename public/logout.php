<?php
require __DIR__ . '/../src/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
if (!validCsrf(formString($_POST, 'csrf'))) { http_response_code(403); exit('Invalid form.'); }
destroyLoginSession();
redirect('/login.php?logout=1');
