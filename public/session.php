<?php
require __DIR__ . '/../src/bootstrap.php';
header('Content-Type: application/json');
if (sessionExpired(time())) { destroyLoginSession(); http_response_code(401); echo '{"active":false}'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validCsrf(formString($_POST, 'csrf'))) { http_response_code(403); echo '{"error":"csrf"}'; exit; }
    recordActivity(time());
} elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') { header('Allow: GET, POST'); http_response_code(405); exit; }
echo json_encode(['active' => true, 'remaining' => max(0, 300 - (time() - $_SESSION['last_activity']))]);
