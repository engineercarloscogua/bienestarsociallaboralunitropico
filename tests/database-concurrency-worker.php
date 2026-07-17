<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

$workerId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($argv[1] ?? 'worker'));
recordAnalyticsVisit('concurrency-' . $workerId, '/tests/concurrency', 'Prueba de concurrencia');

