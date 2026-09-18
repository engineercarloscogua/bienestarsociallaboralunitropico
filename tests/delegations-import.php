<?php

require_once __DIR__ . '/../includes/delegation-import.php';

function delegationImportAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

delegationImportAssert(is_file(__DIR__ . '/../assets/templates/delegaciones.xlsx'), 'Falta la plantilla Excel descargable.');
delegationImportAssert(delegationImportDate('43889.5') === '2020-02-28', 'La fecha serial de Excel no se interpretó.');
delegationImportAssert(delegationImportDate('2020-02-28') === '2020-02-28', 'La fecha ISO cambió.');

// El JSON ya publicado no tiene resolution_type ni delegated_unit. Leerlo no debe migrarlo.
$legacyRecords = json_decode(file_get_contents(__DIR__ . '/../data/delegations.example.json'), true, 512, JSON_THROW_ON_ERROR)['delegations'];
$legacyRecords[] = [
    'id' => 12, 'delegation_type' => 'fixed', 'resolution_number' => 'PENDIENTE',
    'delegate_name' => 'Emilse Sandoval Ramirez', 'delegated_position' => 'LIDER Y/O COORDINADOR DE PROGRAMA',
    'start_date' => '2026-09-21', 'end_date' => '2026-09-25', 'is_active' => 1,
];
$legacySnapshot = ['next_id' => 13, 'delegations' => $legacyRecords, '_meta' => ['source' => 'produccion']];
$compatible = normalizeDelegationsData($legacySnapshot);
delegationImportAssert($compatible === $legacySnapshot, 'La lectura cambió los registros o metadatos antiguos.');
delegationImportAssert(delegationResolutionType($legacyRecords[0]) === 'Resolución rectoral N.º', 'Falta el valor visible del tipo de acto antiguo.');
delegationImportAssert(delegationDisplayName($legacyRecords[0]) === 'NEFFER SAN.', 'El nombre antiguo debe verse en mayúsculas sin modificar el JSON.');
delegationImportAssert(delegationDisplayUnit($legacyRecords[0]) === '', 'Una dependencia antigua desconocida debe permanecer vacía.');
$legacyEdit = validateDelegationInput([
    'delegation_type' => 'fixed', 'resolution_type' => 'Resolución rectoral N.º',
    'resolution_number' => 'PENDIENTE', 'delegate_name' => 'Emilse Sandoval Ramirez',
    'delegated_position' => 'LIDER Y/O COORDINADOR DE PROGRAMA',
    'start_date' => '2026-09-21', 'end_date' => '2026-09-25', 'is_active' => '1',
], $legacyRecords[count($legacyRecords) - 1]);
delegationImportAssert(!$legacyEdit['errors'], 'Un cargo heredado fuera del catálogo no se puede editar.');

$rawRows = [
    ['line' => 2, 'values' => ['Fecha fija', 'Resolución rectoral N.º', '0007', 'María Pérez', 'RECTOR', '', '43889.5', '43892.5', 'Sí']],
    ['line' => 3, 'values' => ['Hasta proveer el cargo', 'Resolución rectoral N.º', 'A-8', 'Carlos López', 'CARGO HISTÓRICO', 'UNIDAD HISTÓRICA', '2018-01-03', '', 'No']],
];
$validated = validateDelegationImportRows($rawRows, []);
delegationImportAssert(!$validated['errors'] && count($validated['records']) === 2, 'No se aceptaron las dos filas válidas.');
delegationImportAssert($validated['records'][0]['resolution_number'] === '0007', 'Se perdieron los ceros iniciales de la resolución.');
delegationImportAssert($validated['records'][0]['delegate_name'] === 'MARÍA PÉREZ', 'No se normalizó el nombre.');
delegationImportAssert($validated['records'][1]['delegated_unit'] === 'UNIDAD HISTÓRICA', 'Se perdió la dependencia histórica.');
delegationImportAssert($validated['records'][1]['is_active'] === 0, 'La visibilidad No no se respetó.');
$duplicates = validateDelegationImportRows([$rawRows[0], $rawRows[0]], []);
delegationImportAssert(count($duplicates['records']) === 1 && $duplicates['duplicates'] === 1, 'Los duplicados dentro del archivo no se omitieron.');
$bad = validateDelegationImportRows([['line' => 9, 'values' => ['Fecha fija', 'Resolución rectoral N.º', '1', 'X', 'RECTOR', '', '2020-02-28', '', 'Sí']]], []);
delegationImportAssert(count($bad['errors']) === 1 && str_contains($bad['errors'][0], 'Fila 9'), 'Faltó el aviso de fecha final obligatoria.');

$newData = $legacySnapshot;
$first = appendDelegationRecords($newData, $validated['records'], 'test');
delegationImportAssert($first === ['added' => 2, 'skipped' => 0], 'No se importaron exactamente dos registros nuevos.');
$second = appendDelegationRecords($newData, $validated['records'], 'test');
delegationImportAssert($second === ['added' => 0, 'skipped' => 2], 'La segunda carga debía omitir los duplicados.');
delegationImportAssert(count($newData['delegations']) === count($legacySnapshot['delegations']) + 2, 'La importación modificó el número de registros antiguos.');
delegationImportAssert(array_slice($newData['delegations'], 0, count($legacySnapshot['delegations'])) === $legacySnapshot['delegations'], 'La importación reescribió datos de delegaciones antiguas.');
delegationImportAssert($newData['_meta'] === $legacySnapshot['_meta'], 'Se perdieron metadatos existentes.');

echo "Delegation bulk import tests: OK\n";
