<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!isSameOriginRequest(false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Solicitud no autorizada.']);
    exit;
}

$requestToken = trim((string)($_POST['request_token'] ?? ''));
$visibleSeconds = (int)($_POST['visible_seconds'] ?? 0);
$hasInteraction = ($_POST['interaction'] ?? '') === '1';
$userAgent = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
$fetchSite = strtolower(trim((string)($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));

if (
    !$hasInteraction
    || $visibleSeconds < 8
    || isLikelyAutomatedUserAgent($userAgent)
    || ($fetchSite !== '' && $fetchSite !== 'same-origin')
    || !validatePublicRequestToken('analytics', $requestToken, 8, 7200)
) {
    echo json_encode(['ok' => true, 'counted' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$retryAfter = consumeRateLimit('analytics', clientSecurityKey('analytics'), 30, 3600, 2);
if ($retryAfter > 0) {
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    echo json_encode(['ok' => false, 'message' => 'Límite temporal de registro alcanzado.']);
    exit;
}

$visitorId = publicSessionVisitorId();
$path = $_POST['path'] ?? '';
$title = $_POST['title'] ?? '';

consumePublicRequestToken('analytics', $requestToken);
recordAnalyticsVisit($visitorId, $path, $title);

echo json_encode(['ok' => true, 'counted' => true], JSON_UNESCAPED_UNICODE);
