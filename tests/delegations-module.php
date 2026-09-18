<?php

require_once __DIR__ . '/../includes/delegations.php';

function delegationTest(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

delegationTest(delegationsModuleEnabled(), 'El módulo local debe estar habilitado.');
delegationTest(delegationsStorageReady(), 'El archivo JSON debe estar disponible.');
delegationTest(delegationMonthBounds('2026-02') === ['2026-02-01', '2026-02-28'], 'Límites mensuales incorrectos.');
$catalog = delegationPositionCatalog();
delegationTest(count($catalog) === 20, 'Deben estar disponibles los 20 cargos proporcionados.');
delegationTest($catalog['SUBDIRECTOR DE CONTABILIDAD'] === 'SUBDIRECCIÓN DE CONTABILIDAD', 'La primera dependencia no coincide.');
delegationTest($catalog['VICERRECTOR DE INVESTIGACIONES'] === 'VICERRECTORÍA DE INVESTIGACIÓN', 'El cargo de investigaciones debe estar en masculino.');
foreach (array_keys($catalog) as $position) {
    delegationTest(mb_strtoupper($position, 'UTF-8') === $position, 'Los cargos deben estar en mayúsculas.');
    delegationTest(!str_contains($position, 'DIRECTORA') && !str_contains($position, 'VICERRECTORA'), 'Los cargos deben estar en masculino.');
}

$invalid = validateDelegationInput([
    'delegation_type' => 'fixed',
    'resolution_number' => 'RR TEST',
    'delegate_name' => 'Prueba',
    'delegated_position' => 'RECTOR',
    'start_date' => '2026-09-20',
    'end_date' => '2026-09-10',
    'is_active' => '1',
]);
delegationTest(count($invalid['errors']) === 1, 'Debe rechazarse una fecha final anterior a la inicial.');

$september = getPublicDelegationsForMonth('2026-09');
$october = getPublicDelegationsForMonth('2026-10');
$septemberResolutions = array_column($september, 'resolution_number');
$octoberResolutions = array_column($october, 'resolution_number');
delegationTest(in_array('1393', $septemberResolutions, true), 'La delegación abierta debe aparecer en septiembre.');
delegationTest(in_array('1586', $septemberResolutions, true), 'La delegación fija debe aparecer en septiembre.');
delegationTest(in_array('1393', $octoberResolutions, true), 'La delegación abierta debe continuar en octubre.');
delegationTest(!in_array('1586', $octoberResolutions, true), 'La delegación fija vencida no debe aparecer en octubre.');
$fixedSample = null;
foreach ($september as $delegation) {
    if (($delegation['resolution_number'] ?? '') === '1586') $fixedSample = $delegation;
}
delegationTest(is_array($fixedSample), 'No se encontró la delegación fija de prueba.');
delegationTest(delegationDurationDays($fixedSample) === 8, 'La duración debe contar ambos días, inicial y final.');
delegationTest(!delegationIsCompleted($fixedSample, '2026-09-13'), 'No debe culminar durante el último día.');
delegationTest(!delegationIsCompleted($fixedSample, '2026-09-12'), 'No debe culminar antes de la fecha final.');
delegationTest(delegationStatusCode($fixedSample, '2026-09-13') === 'ending', 'El último día debe estar próximo a culminar.');
delegationTest(delegationStatusLabel($fixedSample, '2026-09-13') === 'Delegación próxima a culminar', 'Etiqueta amarilla incorrecta.');
delegationTest(delegationIsCompleted($fixedSample, '2026-09-14'), 'Debe culminar el día siguiente a la fecha final.');
delegationTest(delegationStatusLabel($fixedSample, '2026-09-14') === 'Delegación culminada', 'Etiqueta roja incorrecta.');
delegationTest(delegationResolutionType($fixedSample) === 'Resolución rectoral N.º', 'Los registros antiguos deben mostrar el tipo de acto predeterminado.');

$normalized = validateDelegationInput([
    'delegation_type' => 'fixed',
    'resolution_type' => 'Resolución rectoral N.º',
    'resolution_number' => '9999',
    'delegate_name' => 'María Pérez',
    'delegated_position' => 'RECTOR',
    'start_date' => '2026-09-13',
    'end_date' => '2026-09-14',
    'is_active' => '1',
]);
delegationTest($normalized['data']['delegate_name'] === 'MARÍA PÉREZ', 'El nombre debe guardarse en mayúsculas conservando tildes.');
delegationTest($normalized['data']['delegated_unit'] === 'RECTORÍA', 'La dependencia debe derivarse del cargo seleccionado.');
$invalidPosition = validateDelegationInput([
    'delegation_type' => 'interim', 'resolution_number' => '100', 'delegate_name' => 'Ejemplo',
    'delegated_position' => 'CARGO NO AUTORIZADO', 'start_date' => '2026-09-18', 'is_active' => '1',
]);
delegationTest(count($invalidPosition['errors']) === 1, 'Un cargo nuevo fuera del catálogo debe rechazarse.');
$legacy = validateDelegationInput([
    'delegation_type' => 'interim', 'resolution_number' => '101', 'delegate_name' => 'Ejemplo',
    'delegated_position' => 'ORI', 'start_date' => '2026-09-18', 'is_active' => '1',
], ['delegated_position' => 'ORI', 'delegated_unit' => '']);
delegationTest(!$legacy['errors'] && $legacy['data']['delegated_position'] === 'ORI', 'Debe poder editarse un cargo antiguo sin reemplazarlo.');

$sampleRecords = [
    ['resolution_number' => '1393', 'delegate_name' => 'MARÍA PÉREZ', 'start_date' => '2026-03-12'],
    ['resolution_number' => '1586', 'delegate_name' => 'LUIS DINELDO', 'start_date' => '2026-08-05'],
];
delegationTest(count(filterDelegations($sampleRecords, delegationFilterOptions(['q' => 'maria']))) === 1, 'La búsqueda por nombre debe ignorar mayúsculas y tildes.');
delegationTest(count(filterDelegations($sampleRecords, delegationFilterOptions(['q' => '1586']))) === 1, 'La búsqueda por número de resolución falló.');
delegationTest(count(filterDelegations($sampleRecords, delegationFilterOptions(['period' => 'range', 'from' => '2026-03-01', 'to' => '2026-03-31']))) === 1, 'El rango de fechas falló.');
delegationTest(count(filterDelegations($sampleRecords, delegationFilterOptions(['period' => 'month', 'month' => '2026-08']))) === 1, 'El filtro mensual falló.');
delegationTest(count(filterDelegations($sampleRecords, delegationFilterOptions(['period' => 'semester', 'year' => '2026', 'semester' => '2']))) === 1, 'El filtro semestral falló.');
delegationTest(count(filterDelegations($sampleRecords, delegationFilterOptions(['period' => 'year', 'year' => '2026']))) === 2, 'El filtro anual falló.');

$originalData = readDelegationsData();
$originalBackupExists = is_file(DELEGATIONS_BACKUP_FILE);
$originalBackup = $originalBackupExists ? decodeJsonFile(DELEGATIONS_BACKUP_FILE) : null;
try {
    $valid = validateDelegationInput([
        'delegation_type' => 'fixed',
        'resolution_number' => 'RR-TEST-CRUD',
        'delegate_name' => 'Registro temporal',
        'delegated_position' => 'SECRETARIO GENERAL',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-03',
        'is_active' => '1',
    ]);
    delegationTest(!$valid['errors'], 'Un registro válido no debe producir errores.');
    $id = saveDelegationRecord($valid['data'], null, 'test');
    delegationTest($id > 0 && findDelegation($id) !== null, 'No fue posible crear o consultar el registro de prueba.');
    delegationTest(deleteDelegationRecord($id), 'No fue posible eliminar el registro de prueba.');
} finally {
    withFileLock(DELEGATIONS_LOCK_FILE, LOCK_EX, function () use ($originalData): void {
        if (!writeJsonAtomically(DELEGATIONS_FILE, $originalData)) {
            throw new RuntimeException('No fue posible restaurar el JSON después de la prueba.');
        }
    });
    if ($originalBackupExists && is_array($originalBackup)) {
        writeJsonAtomically(DELEGATIONS_BACKUP_FILE, $originalBackup);
    } elseif (is_file(DELEGATIONS_BACKUP_FILE)) {
        @unlink(DELEGATIONS_BACKUP_FILE);
    }
}

echo "Delegations JSON module tests: OK\n";
