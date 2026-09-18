<?php

require_once __DIR__ . '/../includes/delegations.php';

function catalogTest(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$legacy = [
    'next_id' => 2,
    'delegations' => [[
        'id' => 1, 'delegate_name' => 'PERSONA EXISTENTE',
        'delegated_position' => 'RECTOR', 'delegated_unit' => 'RECTORÍA',
    ]],
];
$default = delegationDefaultPositionCatalog();
catalogTest(delegationPositionCatalogFromData($legacy) === $default, 'Un JSON antiguo debe conservar el catálogo inicial.');

$validated = validateDelegationCatalogEntry([
    'position_name' => '  director   de programa  ',
    'position_unit' => '  escuela  de ciencias ',
]);
catalogTest(!$validated['errors'], 'Un cargo y dependencia válidos deben aceptarse.');
catalogTest($validated['position'] === 'DIRECTOR DE PROGRAMA', 'El cargo debe normalizarse en mayúsculas.');
catalogTest($validated['unit'] === 'ESCUELA DE CIENCIAS', 'La dependencia debe normalizarse en mayúsculas.');
catalogTest(count(validateDelegationCatalogEntry(['position_name' => '', 'position_unit' => ''])['errors']) === 2, 'Los dos campos son obligatorios.');

$data = $legacy;
appendDelegationCatalogEntry($data, $validated['position'], $validated['unit']);
catalogTest(count(delegationPositionCatalogFromData($data)) === count($default) + 1, 'El cargo nuevo debe sumarse al catálogo.');
catalogTest(delegationPositionCatalogFromData($data)['DIRECTOR DE PROGRAMA'] === 'ESCUELA DE CIENCIAS', 'La dependencia debe quedar asociada al cargo.');
catalogTest($data['delegations'] === $legacy['delegations'], 'Agregar un cargo no debe modificar las delegaciones existentes.');
$saved = normalizeDelegationsData(json_decode(json_encode($data, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR));
catalogTest(delegationPositionCatalogFromData($saved)['DIRECTOR DE PROGRAMA'] === 'ESCUELA DE CIENCIAS', 'El cargo debe persistir en el JSON.');

try {
    appendDelegationCatalogEntry($data, 'DÍRECTOR DE PROGRAMA', 'OTRA ESCUELA');
    throw new RuntimeException('Se aceptó un cargo duplicado.');
} catch (RuntimeException $error) {
    catalogTest($error->getMessage() === 'Ese cargo ya existe en el catálogo.', 'Se esperaba un aviso claro de duplicado.');
}

catalogTest(removeDelegationCatalogEntry($data, 'RECTOR'), 'Debe poder retirarse un cargo del catálogo.');
catalogTest(!isset(delegationPositionCatalogFromData($data)['RECTOR']), 'El cargo retirado no debe aparecer para registros nuevos.');
catalogTest($data['delegations'] === $legacy['delegations'], 'Quitar un cargo no debe modificar registros históricos.');
catalogTest(!removeDelegationCatalogEntry($data, 'CARGO INEXISTENTE'), 'Un cargo inexistente no debe figurar como eliminado.');
catalogTest(delegationPositionCatalogFromData(['position_catalog' => []]) === [], 'Un catálogo vacío guardado no debe reiniciarse con los valores iniciales.');

$edited = validateDelegationInput([
    'delegation_type' => 'interim', 'resolution_number' => '100', 'delegate_name' => 'Persona',
    'delegated_position' => 'RECTOR', 'start_date' => '2026-09-18', 'is_active' => '1',
], ['delegated_position' => 'RECTOR', 'delegated_unit' => 'DEPENDENCIA HISTÓRICA']);
catalogTest(!$edited['errors'] && $edited['data']['delegated_unit'] === 'DEPENDENCIA HISTÓRICA', 'Editar un registro no debe cambiar su dependencia histórica.');

echo "Delegations catalog tests: OK\n";
