<?php

require_once __DIR__ . '/../config/app.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['status' => 'unauthenticated']);
    exit;
}

$_SESSION['last_activity'] = time();

echo json_encode([
    'status' => 'active',
    'timestamp' => time(),
    'timeout' => SESSION_INACTIVITY_TIMEOUT
]);
exit;
