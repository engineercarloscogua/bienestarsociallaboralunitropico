<?php

require_once __DIR__ . '/functions.php';

define('DELEGATIONS_FILE', __DIR__ . '/../data/delegations.json');
define('DELEGATIONS_TEMPLATE_FILE', __DIR__ . '/../data/delegations.example.json');
define('DELEGATIONS_BACKUP_FILE', __DIR__ . '/../data/delegations.backup.json');
define('DELEGATIONS_LOCK_FILE', __DIR__ . '/../data/delegations.lock');

function delegationBooleanValue(mixed $value, bool $default = false): bool {
    if (is_bool($value)) return $value;
    if (is_int($value)) return $value === 1;
    if (is_string($value)) {
        $normalized = strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on', 'si', 'sí'], true)) return true;
        if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) return false;
    }
    return $default;
}

function delegationsModuleEnabled(): bool {
    $environment = getenv('APP_DELEGATIONS_ENABLED');
    if ($environment !== false && trim((string)$environment) !== '') {
        return delegationBooleanValue($environment);
    }
    $config = databaseConfig();
    return delegationBooleanValue($config['delegations_module_enabled'] ?? true, true);
}

function defaultDelegationsData(): array {
    return ['next_id' => 1, 'delegations' => []];
}

function normalizeDelegationsData(array $data): array {
    $records = array_values(array_filter((array)($data['delegations'] ?? []), 'is_array'));
    $highestId = 0;
    foreach ($records as &$record) {
        $record['id'] = max(0, (int)($record['id'] ?? 0));
        $record['is_active'] = !empty($record['is_active']) ? 1 : 0;
        $highestId = max($highestId, $record['id']);
    }
    unset($record);
    return [
        'next_id' => max($highestId + 1, (int)($data['next_id'] ?? 1)),
        'delegations' => $records,
    ];
}

function ensureDelegationsDataFile(): void {
    if (is_file(DELEGATIONS_FILE)) return;
    if (!is_dir(dirname(DELEGATIONS_FILE))) {
        throw new RuntimeException('No existe el directorio privado de datos.');
    }
    withFileLock(DELEGATIONS_LOCK_FILE, LOCK_EX, function (): void {
        if (is_file(DELEGATIONS_FILE)) return;
        $initialData = is_file(DELEGATIONS_TEMPLATE_FILE)
            ? normalizeDelegationsData(decodeJsonFile(DELEGATIONS_TEMPLATE_FILE))
            : defaultDelegationsData();
        if (!writeJsonAtomically(DELEGATIONS_FILE, $initialData)) {
            throw new RuntimeException('No fue posible crear data/delegations.json.');
        }
    });
}

function readDelegationsData(): array {
    ensureDelegationsDataFile();
    return withFileLock(
        DELEGATIONS_LOCK_FILE,
        LOCK_SH,
        fn(): array => normalizeDelegationsData(decodeJsonFile(DELEGATIONS_FILE))
    );
}

function updateDelegationsData(callable $mutator) {
    ensureDelegationsDataFile();
    return withFileLock(DELEGATIONS_LOCK_FILE, LOCK_EX, function () use ($mutator) {
        $data = normalizeDelegationsData(decodeJsonFile(DELEGATIONS_FILE));
        if (!writeJsonAtomically(DELEGATIONS_BACKUP_FILE, $data)) {
            throw new RuntimeException('No fue posible crear el respaldo previo de las delegaciones.');
        }
        $result = $mutator($data);
        if (!writeJsonAtomically(DELEGATIONS_FILE, normalizeDelegationsData($data))) {
            throw new RuntimeException('No fue posible guardar data/delegations.json.');
        }
        return $result;
    });
}

function delegationsStorageReady(): bool {
    if (!delegationsModuleEnabled()) return false;
    try {
        ensureDelegationsDataFile();
        readDelegationsData();
        return true;
    } catch (Throwable $error) {
        return false;
    }
}

function delegationsSchemaReady(): bool {
    // Alias conservado para las pruebas del prototipo; ahora comprueba JSON, no MariaDB.
    return delegationsStorageReady();
}

function requireDelegationsModule(): void {
    if (delegationsStorageReady()) return;
    http_response_code(503);
    exit('La tabla de delegaciones no está disponible en este entorno.');
}

function normalizeDelegationMonth(?string $month): string {
    $month = trim((string)$month);
    if (preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $month)) return $month;
    return date('Y-m');
}

function delegationMonthBounds(string $month): array {
    $month = normalizeDelegationMonth($month);
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01');
    return [$start->format('Y-m-d'), $start->modify('last day of this month')->format('Y-m-d')];
}

function delegationMonthLabel(string $month): string {
    $month = normalizeDelegationMonth($month);
    [$year, $number] = array_map('intval', explode('-', $month));
    $names = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return ucfirst($names[$number]) . ' de ' . $year;
}

function delegationDateLabel(?string $date): string {
    if (!$date) return '';
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed ? $parsed->format('d/m/Y') : $date;
}

function delegationTypeLabel(string $type): string {
    return $type === 'fixed' ? 'Fecha fija' : 'Hasta proveer el cargo';
}

function delegationPeriodLabel(array $delegation): string {
    if (($delegation['delegation_type'] ?? '') === 'interim') {
        return 'Desde ' . delegationDateLabel($delegation['start_date'] ?? null) . ' hasta proveer el cargo';
    }
    return delegationDateLabel($delegation['start_date'] ?? null) . ' al ' . delegationDateLabel($delegation['end_date'] ?? null);
}

function delegationDurationDays(array $delegation): ?int {
    if (($delegation['delegation_type'] ?? '') !== 'fixed') return null;
    $startDate = (string)($delegation['start_date'] ?? '');
    $endDate = (string)($delegation['end_date'] ?? '');
    if (!delegationValidDate($startDate) || !delegationValidDate($endDate) || $endDate < $startDate) return null;
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
    return $start->diff($end)->days + 1;
}

function delegationIsCompleted(array $delegation, ?string $today = null): bool {
    if (($delegation['delegation_type'] ?? '') !== 'fixed') return false;
    $endDate = (string)($delegation['end_date'] ?? '');
    if (!delegationValidDate($endDate)) return false;
    $today = $today !== null && delegationValidDate($today) ? $today : date('Y-m-d');
    return $endDate <= $today;
}

function delegationStatusLabel(array $delegation): string {
    if (empty($delegation['is_active'])) return 'Oculta';
    return delegationIsCompleted($delegation) ? 'Culminada' : 'Vigente';
}

function delegationValidDate(string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function validateDelegationInput(array $input): array {
    $type = strtolower(trim((string)($input['delegation_type'] ?? '')));
    $resolution = trim((string)($input['resolution_number'] ?? ''));
    $name = trim((string)($input['delegate_name'] ?? ''));
    $position = trim((string)($input['delegated_position'] ?? ''));
    $startDate = trim((string)($input['start_date'] ?? ''));
    $endDate = trim((string)($input['end_date'] ?? ''));
    $errors = [];

    if (!in_array($type, ['interim', 'fixed'], true)) $errors[] = 'Selecciona un tipo de delegación válido.';
    if ($resolution === '' || mb_strlen($resolution) > 80) $errors[] = 'El número de resolución es obligatorio y debe tener máximo 80 caracteres.';
    if ($name === '' || mb_strlen($name) > 160) $errors[] = 'El nombre del delegado es obligatorio y debe tener máximo 160 caracteres.';
    if ($position === '' || mb_strlen($position) > 200) $errors[] = 'El cargo delegado es obligatorio y debe tener máximo 200 caracteres.';
    if (!delegationValidDate($startDate)) $errors[] = 'Indica una fecha inicial válida.';
    if ($type === 'fixed') {
        if (!delegationValidDate($endDate)) {
            $errors[] = 'Las delegaciones de fecha fija requieren fecha final.';
        } elseif (delegationValidDate($startDate) && $endDate < $startDate) {
            $errors[] = 'La fecha final no puede ser anterior a la inicial.';
        }
    } else {
        $endDate = '';
    }

    return [
        'errors' => $errors,
        'data' => [
            'delegation_type' => $type,
            'resolution_number' => $resolution,
            'delegate_name' => $name,
            'delegated_position' => $position,
            'start_date' => $startDate,
            'end_date' => $endDate !== '' ? $endDate : null,
            'is_active' => isset($input['is_active']) ? 1 : 0,
        ],
    ];
}

function delegationOverlapsMonth(array $delegation, string $month): bool {
    [$monthStart, $monthEnd] = delegationMonthBounds($month);
    $startDate = (string)($delegation['start_date'] ?? '');
    $endDate = $delegation['end_date'] ?? null;
    return $startDate !== '' && $startDate <= $monthEnd && ($endDate === null || $endDate === '' || $endDate >= $monthStart);
}

function sortDelegations(array &$delegations, bool $newestFirst = false): void {
    usort($delegations, function (array $left, array $right) use ($newestFirst): int {
        $active = (int)($right['is_active'] ?? 0) <=> (int)($left['is_active'] ?? 0);
        if ($active !== 0) return $active;
        $date = strcmp((string)($left['start_date'] ?? ''), (string)($right['start_date'] ?? ''));
        if ($date !== 0) return $newestFirst ? -$date : $date;
        return strcasecmp((string)($left['delegate_name'] ?? ''), (string)($right['delegate_name'] ?? ''));
    });
}

function getPublicDelegationsForMonth(string $month): array {
    requireDelegationsModule();
    $records = array_values(array_filter(
        readDelegationsData()['delegations'],
        fn(array $row): bool => !empty($row['is_active']) && delegationOverlapsMonth($row, $month)
    ));
    sortDelegations($records);
    return $records;
}

function getPublicDelegations(): array {
    requireDelegationsModule();
    $records = array_values(array_filter(
        readDelegationsData()['delegations'],
        fn(array $row): bool => !empty($row['is_active'])
    ));
    sortDelegations($records, true);
    return $records;
}

function getAllDelegations(?string $month = null, ?string $type = null): array {
    requireDelegationsModule();
    $records = readDelegationsData()['delegations'];
    if ($month !== null && $month !== '') {
        $records = array_values(array_filter($records, fn(array $row): bool => delegationOverlapsMonth($row, $month)));
    }
    if (in_array($type, ['interim', 'fixed'], true)) {
        $records = array_values(array_filter($records, fn(array $row): bool => ($row['delegation_type'] ?? '') === $type));
    }
    sortDelegations($records, true);
    return $records;
}

function findDelegation(int $id): ?array {
    requireDelegationsModule();
    foreach (readDelegationsData()['delegations'] as $record) {
        if ((int)$record['id'] === $id) return $record;
    }
    return null;
}

function saveDelegationRecord(array $record, ?int $id, string $admin): int {
    requireDelegationsModule();
    return updateDelegationsData(function (array &$data) use ($record, $id, $admin): int {
        $now = date(DATE_ATOM);
        if ($id !== null) {
            foreach ($data['delegations'] as &$existing) {
                if ((int)$existing['id'] !== $id) continue;
                $createdAt = $existing['created_at'] ?? $now;
                $createdBy = $existing['created_by'] ?? $admin;
                $existing = array_merge($record, [
                    'id' => $id,
                    'created_by' => $createdBy,
                    'created_at' => $createdAt,
                    'updated_at' => $now,
                ]);
                unset($existing);
                return $id;
            }
            unset($existing);
            throw new RuntimeException('No se encontró la delegación que intentas actualizar.');
        }

        $newId = max(1, (int)$data['next_id']);
        $data['next_id'] = $newId + 1;
        $data['delegations'][] = array_merge($record, [
            'id' => $newId,
            'created_by' => $admin,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $newId;
    });
}

function deleteDelegationRecord(int $id): bool {
    requireDelegationsModule();
    return updateDelegationsData(function (array &$data) use ($id): bool {
        $before = count($data['delegations']);
        $data['delegations'] = array_values(array_filter(
            $data['delegations'],
            fn(array $record): bool => (int)$record['id'] !== $id
        ));
        return count($data['delegations']) < $before;
    });
}
