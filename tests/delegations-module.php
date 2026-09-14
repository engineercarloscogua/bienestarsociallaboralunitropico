<?php

require_once __DIR__ . '/../includes/delegations.php';

function delegationTest(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

delegationTest(delegationsModuleEnabled(), 'El módulo local debe estar habilitado.');
delegationTest(delegationsStorageReady(), 'El archivo JSON debe estar disponible.');
delegationTest(delegationMonthBounds('2026-02') === ['2026-02-01', '2026-02-28'], 'Límites mensuales incorrectos.');

$invalid = validateDelegationInput([
    'delegation_type' => 'fixed',
    'resolution_number' => 'RR TEST',
    'delegate_name' => 'Prueba',
    'delegated_position' => 'Cargo',
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
delegationTest(delegationIsCompleted($fixedSample, '2026-09-13'), 'Debe culminar al alcanzar la fecha final.');
delegationTest(!delegationIsCompleted($fixedSample, '2026-09-12'), 'No debe culminar antes de la fecha final.');

$originalData = readDelegationsData();
$originalBackupExists = is_file(DELEGATIONS_BACKUP_FILE);
$originalBackup = $originalBackupExists ? decodeJsonFile(DELEGATIONS_BACKUP_FILE) : null;
try {
    $valid = validateDelegationInput([
        'delegation_type' => 'fixed',
        'resolution_number' => 'RR-TEST-CRUD',
        'delegate_name' => 'Registro temporal',
        'delegated_position' => 'Cargo temporal',
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
