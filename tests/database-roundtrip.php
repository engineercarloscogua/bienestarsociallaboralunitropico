<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

function testNormalize($value) {
    if (!is_array($value)) return $value;

    $keys = array_keys($value);
    $isList = $keys === ($keys ? range(0, count($keys) - 1) : []);
    foreach ($value as $key => $item) $value[$key] = testNormalize($item);
    if (!$isList) ksort($value);
    return $value;
}

function testAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

function testFirstDifference($expected, $actual, string $path = '$'): ?string {
    if (gettype($expected) !== gettype($actual)) {
        return $path . ' (tipos ' . gettype($expected) . ' / ' . gettype($actual) . ')';
    }
    if (!is_array($expected)) return $expected === $actual ? null : $path;
    if (array_keys($expected) !== array_keys($actual)) return $path . ' (claves diferentes)';
    foreach ($expected as $key => $value) {
        $difference = testFirstDifference($value, $actual[$key], $path . '[' . $key . ']');
        if ($difference !== null) return $difference;
    }
    return null;
}

$sourceFile = file_exists(DATA_FILE) ? DATA_FILE : DATA_TEMPLATE_FILE;
$source = decodeJsonFile($sourceFile);
applyPendingDataMigrations($source);

testAssert(databaseSchemaIsReady(), 'El esquema no está listo.');
databaseSaveData($source);
$restored = databaseReadData();

$normalizedSource = testNormalize($source);
$normalizedRestored = testNormalize($restored);
$difference = testFirstDifference($normalizedSource, $normalizedRestored);
testAssert($difference === null, 'El contenido reconstruido desde MariaDB no coincide en ' . ($difference ?? 'un campo desconocido') . '.');

databaseUpdateData(function (array &$data): void {
    $data['config']['__database_roundtrip_test'] = 'ok';
});
testAssert((databaseReadData()['config']['__database_roundtrip_test'] ?? '') === 'ok', 'Falló la actualización transaccional.');

databaseUpdateSecurityState(function (array &$state): void {
    $state['__database_roundtrip_test'] = ['updated_at' => 'test'];
});

$testComment = addComment('Prueba MariaDB', 'Comentario transaccional de prueba.', 5, '✅');
testAssert(normalizeCommentStatus($testComment) === 'pending', 'El comentario nuevo no quedó pendiente.');
testAssert(!databaseCanReplyToComment($testComment['id']), 'Un comentario pendiente quedó visible para respuestas.');
testAssert(moderateComment($testComment['id'], 'published'), 'Falló la publicación del comentario.');
testAssert(databaseCanReplyToComment($testComment['id']), 'El comentario publicado no quedó disponible para respuestas.');
testAssert(moderateComment($testComment['id'], 'rejected'), 'Falló el rechazo del comentario.');
testAssert(!databaseCanReplyToComment($testComment['id']), 'Un comentario rechazado quedó visible para respuestas.');
deleteComment($testComment['id']);
testAssert(!databaseCanReplyToComment($testComment['id']), 'Falló la eliminación directa de comentarios.');

$testMonth = date('Y-m');
$visitsBefore = (int)(databaseReadData()['analytics']['monthly'][$testMonth]['visits'] ?? 0);
recordAnalyticsVisit('database-roundtrip-test', '/tests/database', 'Prueba de base');
$visitsAfter = (int)(databaseReadData()['analytics']['monthly'][$testMonth]['visits'] ?? 0);
testAssert($visitsAfter === $visitsBefore + 1, 'Falló la actualización directa de analítica.');

databaseUpdateData(function (array &$data): void {
    unset($data['config']['__database_roundtrip_test']);
});
databaseUpdateSecurityState(function (array &$state): void {
    unset($state['__database_roundtrip_test']);
});
databaseSaveData($source);

echo 'OK: esquema, importación, lectura y actualizaciones transaccionales verificadas.' . PHP_EOL;
