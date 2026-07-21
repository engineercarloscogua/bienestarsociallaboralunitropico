<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('APP_STORAGE_DRIVER=json');
require_once __DIR__ . '/../includes/functions.php';

function analyticsEngagementAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$snapshot = readData();
$month = date('Y-m');
$visitorId = 'analytics-test-' . bin2hex(random_bytes(8));
$path = '/tests/engagement-' . bin2hex(random_bytes(5));

try {
    $before = (int)(readData()['analytics']['monthly'][$month]['visits'] ?? 0);
    recordAnalyticsVisit($visitorId, $path, 'Prueba de interacción');
    recordAnalyticsVisit($visitorId, $path, 'Prueba de interacción');
    $after = (int)(readData()['analytics']['monthly'][$month]['visits'] ?? 0);
    analyticsEngagementAssert($after === $before + 1, 'La misma página se contó más de una vez durante 30 minutos.');
} finally {
    saveData($snapshot);
}

echo 'OK: deduplicación de visitas por visitante y página verificada.' . PHP_EOL;
