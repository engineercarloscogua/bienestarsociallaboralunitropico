<?php

require_once __DIR__ . '/../includes/delegations.php';

function publicViewAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$default = delegationPublicFilterOptions([], '2026-09-18');
publicViewAssert($default['period'] === 'month' && $default['month'] === '2026-09', 'La vista pública debe iniciar en el mes actual.');

$records = [
    ['resolution_number' => 'A', 'delegate_name' => 'ANA', 'delegation_type' => 'interim', 'start_date' => '2025-05-10', 'end_date' => null],
    ['resolution_number' => 'B', 'delegate_name' => 'BEATRIZ', 'delegation_type' => 'fixed', 'start_date' => '2026-08-01', 'end_date' => '2026-08-31'],
    ['resolution_number' => 'C', 'delegate_name' => 'CARLOS', 'delegation_type' => 'fixed', 'start_date' => '2026-08-28', 'end_date' => '2026-09-05'],
    ['resolution_number' => 'D', 'delegate_name' => 'DIANA', 'delegation_type' => 'fixed', 'start_date' => '2026-10-01', 'end_date' => '2026-10-03'],
];
$september = filterPublicDelegations($records, $default);
publicViewAssert(array_column($september, 'resolution_number') === ['A', 'C'], 'El mes debe incluir encargos que comenzaron antes y excluir los vencidos.');
$all = filterPublicDelegations($records, delegationPublicFilterOptions(['period' => 'all'], '2026-09-18'));
publicViewAssert(count($all) === 4, 'Todas las fechas deben recuperar el historial completo.');
$search = filterPublicDelegations($records, delegationPublicFilterOptions(['period' => 'all', 'q' => 'carlos'], '2026-09-18'));
publicViewAssert(array_column($search, 'resolution_number') === ['C'], 'La búsqueda pública debe seguir funcionando.');

echo "Delegations public month and accordion tests: OK\n";
