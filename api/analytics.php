<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!isSameOriginRequest()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Solicitud no autorizada.']);
    exit;
}

$retryAfter = consumeRateLimit('analytics', clientSecurityKey('analytics'), 120, 60, 0);
if ($retryAfter > 0) {
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    echo json_encode(['ok' => false, 'message' => 'Límite temporal de registro alcanzado.']);
    exit;
}

$visitorId = $_POST['visitor_id'] ?? '';
$path = $_POST['path'] ?? '';
$title = $_POST['title'] ?? '';

recordAnalyticsVisit($visitorId, $path, $title);

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
