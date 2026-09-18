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
    if (!isset($data['delegations']) || !is_array($data['delegations'])) {
        throw new RuntimeException('El archivo de delegaciones no contiene una lista válida.');
    }
    $records = array_values($data['delegations']);
    $highestId = 0;
    foreach ($records as $record) {
        if (!is_array($record)) {
            throw new RuntimeException('El archivo de delegaciones contiene un registro inválido.');
        }
        $highestId = max($highestId, max(0, (int)($record['id'] ?? 0)));
    }
    delegationPositionCatalogFromData($data);
    // Los registros publicados con la estructura anterior se leen tal como están.
    // Los campos nuevos se resuelven en la vista, sin migrar ni reescribir el historial.
    $data['next_id'] = max($highestId + 1, (int)($data['next_id'] ?? 1));
    $data['delegations'] = $records;
    return $data;
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

function delegationDefaultPositionCatalog(): array {
    // Cargo en masculino => dependencia institucional corregida y acentuada.
    return [
        'SUBDIRECTOR DE CONTABILIDAD' => 'SUBDIRECCIÓN DE CONTABILIDAD',
        'PROFESOR PROVISIONAL EN COMISIÓN DE CARGO POR PERÍODO FIJO, DENOMINADO DIRECTOR DE LA ESCUELA DE CIENCIAS DEL LENGUAJE' => 'ESCUELA DE CIENCIAS DEL LENGUAJE',
        'PROFESOR PROVISIONAL EN COMISIÓN DE CARGO POR PERÍODO FIJO, DENOMINADO LÍDER DE DEPARTAMENTO' => 'DEPARTAMENTO DE CIENCIAS BÁSICAS TRANSVERSALES',
        'JEFE DE OFICINA DE TALENTO HUMANO' => 'OFICINA DE TALENTO HUMANO',
        'VICERRECTOR DE PROYECCIÓN SOCIAL' => 'VICERRECTORÍA DE PROYECCIÓN SOCIAL',
        'JEFE DE OFICINA DE GESTIÓN DOCUMENTAL' => 'OFICINA DE GESTIÓN DOCUMENTAL',
        'JEFE DE OFICINA DE ASEGURAMIENTO DE LA CALIDAD Y ACREDITACIÓN' => 'OFICINA DE ASEGURAMIENTO DE LA CALIDAD Y ACREDITACIÓN',
        'JEFE DE OFICINA DE PROYECTOS ESPECIALES Y RELACIONES INTERINSTITUCIONALES' => 'OFICINA DE PROYECTOS ESPECIALES Y RELACIONES INTERINSTITUCIONALES',
        'JEFE DE OFICINA DE CONTROL INTERNO Y GESTIÓN' => 'OFICINA DE CONTROL INTERNO Y DE GESTIÓN',
        'JEFE DE OFICINA DE ATENCIÓN AL CIUDADANO' => 'OFICINA DE ATENCIÓN AL CIUDADANO',
        'RECTOR' => 'RECTORÍA',
        'VICERRECTOR ADMINISTRATIVO Y FINANCIERO' => 'VICERRECTORÍA ADMINISTRATIVA Y FINANCIERA',
        'JEFE DE OFICINA DE BIENESTAR UNIVERSITARIO' => 'OFICINA DE BIENESTAR UNIVERSITARIO',
        'PROFESOR PROVISIONAL EN COMISIÓN DE CARGO POR PERÍODO FIJO, DENOMINADO VICERRECTOR ACADÉMICO' => 'VICERRECTORÍA ACADÉMICA',
        'SUBDIRECTOR DE FOMENTO DE LA INVESTIGACIÓN' => 'VICERRECTORÍA DE INVESTIGACIÓN',
        'DIRECTOR DE LA ESCUELA DE CIENCIAS HUMANAS' => 'ESCUELA DE CIENCIAS HUMANAS',
        'SECRETARIO GENERAL' => 'SECRETARÍA GENERAL',
        'VICERRECTOR DE INVESTIGACIONES' => 'VICERRECTORÍA DE INVESTIGACIÓN',
        'JEFE DE OFICINA DE CONTROL INTERNO DISCIPLINARIO' => 'OFICINA DE CONTROL INTERNO DISCIPLINARIO',
        'JEFE DE OFICINA DE ADMISIONES Y REGISTRO' => 'OFICINA DE ADMISIONES Y REGISTRO',
    ];
}

function delegationPositionCatalogFromData(array $data): array {
    if (!array_key_exists('position_catalog', $data)) return delegationDefaultPositionCatalog();
    $catalog = $data['position_catalog'];
    if (!is_array($catalog)) throw new RuntimeException('El catálogo de cargos no es válido.');
    foreach ($catalog as $position => $unit) {
        if (!is_string($position) || $position === '' || !is_string($unit) || $unit === '') {
            throw new RuntimeException('El catálogo de cargos contiene una entrada inválida.');
        }
    }
    return $catalog;
}

function delegationPositionCatalog(): array {
    return delegationPositionCatalogFromData(readDelegationsData());
}

function delegationCatalogText(string $text): string {
    return mb_strtoupper(trim((string)preg_replace('/[\s\x{00A0}]+/u', ' ', $text)), 'UTF-8');
}

function validateDelegationCatalogEntry(array $input): array {
    $position = delegationCatalogText((string)($input['position_name'] ?? ''));
    $unit = delegationCatalogText((string)($input['position_unit'] ?? ''));
    $errors = [];
    if ($position === '' || mb_strlen($position, 'UTF-8') > 200) {
        $errors[] = 'Indica un cargo de máximo 200 caracteres.';
    }
    if ($unit === '' || mb_strlen($unit, 'UTF-8') > 200) {
        $errors[] = 'Indica una dependencia de máximo 200 caracteres.';
    }
    return ['errors' => $errors, 'position' => $position, 'unit' => $unit];
}

function appendDelegationCatalogEntry(array &$data, string $position, string $unit): void {
    $catalog = delegationPositionCatalogFromData($data);
    $search = delegationSearchKey($position);
    foreach ($catalog as $existing => $_) {
        if (delegationSearchKey($existing) === $search) {
            throw new RuntimeException('Ese cargo ya existe en el catálogo.');
        }
    }
    $catalog[$position] = $unit;
    $data['position_catalog'] = $catalog;
}

function removeDelegationCatalogEntry(array &$data, string $position): bool {
    $catalog = delegationPositionCatalogFromData($data);
    if (!array_key_exists($position, $catalog)) return false;
    unset($catalog[$position]);
    $data['position_catalog'] = $catalog;
    return true;
}

function addDelegationCatalogEntry(string $position, string $unit): void {
    requireDelegationsModule();
    $validated = validateDelegationCatalogEntry(['position_name' => $position, 'position_unit' => $unit]);
    if ($validated['errors']) throw new InvalidArgumentException(implode(' ', $validated['errors']));
    $position = $validated['position'];
    $unit = $validated['unit'];
    updateDelegationsData(function (array &$data) use ($position, $unit): void {
        appendDelegationCatalogEntry($data, $position, $unit);
    });
}

function deleteDelegationCatalogEntry(string $position): bool {
    requireDelegationsModule();
    return updateDelegationsData(function (array &$data) use ($position): bool {
        return removeDelegationCatalogEntry($data, $position);
    });
}

function delegationDisplayUnit(array $delegation): string {
    $stored = trim((string)($delegation['delegated_unit'] ?? ''));
    if ($stored !== '') return $stored;
    $position = (string)($delegation['delegated_position'] ?? '');
    // Los registros anteriores sin dependencia conservan su valor histórico
    // aunque el cargo se retire de las opciones para registros nuevos.
    return delegationDefaultPositionCatalog()[$position] ?? delegationPositionCatalog()[$position] ?? '';
}

function delegationResolutionType(array $delegation): string {
    $type = trim((string)($delegation['resolution_type'] ?? ''));
    return $type !== '' ? $type : 'Resolución rectoral N.º';
}

function delegationDisplayName(array $delegation): string {
    return mb_strtoupper(trim((string)($delegation['delegate_name'] ?? '')), 'UTF-8');
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

function delegationToday(): string {
    return (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
}

function delegationStatusCode(array $delegation, ?string $today = null): string {
    if (empty($delegation['is_active'])) return 'hidden';
    if (($delegation['delegation_type'] ?? '') !== 'fixed') return 'active';
    $endDate = (string)($delegation['end_date'] ?? '');
    if (!delegationValidDate($endDate)) return 'active';
    $today = $today !== null && delegationValidDate($today) ? $today : delegationToday();
    if ($endDate < $today) return 'completed';
    if ($endDate === $today) return 'ending';
    return 'active';
}

function delegationIsCompleted(array $delegation, ?string $today = null): bool {
    return delegationStatusCode($delegation, $today) === 'completed';
}

function delegationStatusLabel(array $delegation, ?string $today = null): string {
    return match (delegationStatusCode($delegation, $today)) {
        'hidden' => 'Oculta',
        'completed' => 'Delegación culminada',
        'ending' => 'Delegación próxima a culminar',
        default => 'Vigente',
    };
}

function delegationValidDate(string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function validateDelegationInput(array $input, ?array $existing = null, bool $allowHistoricalPosition = false): array {
    $type = strtolower(trim((string)($input['delegation_type'] ?? '')));
    $resolutionType = trim((string)($input['resolution_type'] ?? 'Resolución rectoral N.º'));
    $resolution = trim((string)($input['resolution_number'] ?? ''));
    $name = mb_strtoupper(trim((string)($input['delegate_name'] ?? '')), 'UTF-8');
    $position = trim((string)($input['delegated_position'] ?? ''));
    $catalog = delegationPositionCatalog();
    $legacyPosition = $existing !== null && $position === (string)($existing['delegated_position'] ?? '');
    $unit = $legacyPosition
        ? delegationDisplayUnit($existing)
        : ($catalog[$position] ?? ($allowHistoricalPosition ? mb_strtoupper(trim((string)($input['delegated_unit'] ?? '')), 'UTF-8') : ''));
    $startDate = trim((string)($input['start_date'] ?? ''));
    $endDate = trim((string)($input['end_date'] ?? ''));
    $errors = [];

    if (!in_array($type, ['interim', 'fixed'], true)) $errors[] = 'Selecciona un tipo de delegación válido.';
    if ($resolutionType === '' || mb_strlen($resolutionType) > 100) $errors[] = 'El tipo de acto es obligatorio y debe tener máximo 100 caracteres.';
    if ($resolution === '' || mb_strlen($resolution) > 80) $errors[] = 'El número de resolución es obligatorio y debe tener máximo 80 caracteres.';
    if ($name === '' || mb_strlen($name) > 160) $errors[] = 'El nombre del delegado es obligatorio y debe tener máximo 160 caracteres.';
    if ($position === '' || mb_strlen($position) > 200 || (!isset($catalog[$position]) && !$legacyPosition && !$allowHistoricalPosition)) {
        $errors[] = 'Selecciona un cargo delegado de la lista.';
    }
    if ($allowHistoricalPosition && !isset($catalog[$position]) && ($unit === '' || mb_strlen($unit) > 200)) {
        $errors[] = 'Indica la dependencia del cargo histórico (máximo 200 caracteres).';
    }
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
            'resolution_type' => $resolutionType,
            'resolution_number' => $resolution,
            'delegate_name' => $name,
            'delegated_position' => $position,
            'delegated_unit' => $unit,
            'start_date' => $startDate,
            'end_date' => $endDate !== '' ? $endDate : null,
            'is_active' => isset($input['is_active']) ? 1 : 0,
        ],
    ];
}

function delegationFilterOptions(array $input): array {
    $search = trim((string)($input['q'] ?? ''));
    $period = (string)($input['period'] ?? 'all');
    if (!in_array($period, ['all', 'range', 'month', 'semester', 'year'], true)) $period = 'all';
    $year = trim((string)($input['year'] ?? ''));
    $month = trim((string)($input['month'] ?? ''));
    $semester = (string)($input['semester'] ?? '1');
    $from = trim((string)($input['from'] ?? ''));
    $to = trim((string)($input['to'] ?? ''));
    $start = null;
    $end = null;

    if ($period === 'range') {
        $start = delegationValidDate($from) ? $from : null;
        $end = delegationValidDate($to) ? $to : null;
        if ($start === null && $end === null) $period = 'all';
    } elseif ($period === 'month' && preg_match('/\A\d{4}-(0[1-9]|1[0-2])\z/', $month)) {
        [$start, $end] = delegationMonthBounds($month);
    } elseif (($period === 'year' || $period === 'semester') && preg_match('/\A\d{4}\z/', $year)) {
        $start = $year . ($period === 'semester' && $semester === '2' ? '-07-01' : '-01-01');
        $end = $year . ($period === 'semester' && $semester !== '2' ? '-06-30' : '-12-31');
    } else {
        $period = 'all';
    }

    return [
        'q' => mb_substr($search, 0, 160), 'period' => $period,
        'year' => $year, 'month' => $month, 'semester' => $semester,
        'from' => $from, 'to' => $to, 'start' => $start, 'end' => $end,
    ];
}

function delegationPublicFilterOptions(array $input, ?string $today = null): array {
    if (!array_key_exists('period', $input)) {
        $today = $today !== null && delegationValidDate($today) ? $today : delegationToday();
        $input['period'] = 'month';
        $input['month'] = substr($today, 0, 7);
    }
    return delegationFilterOptions($input);
}

function filterDelegations(array $records, array $filters): array {
    $query = delegationSearchKey($filters['q'] ?? '');
    $start = $filters['start'] ?? null;
    $end = $filters['end'] ?? null;
    return array_values(array_filter($records, static function (array $row) use ($query, $start, $end): bool {
        $date = (string)($row['start_date'] ?? '');
        if ($start !== null && $date < $start) return false;
        if ($end !== null && $date > $end) return false;
        if ($query === '') return true;
        $haystack = delegationSearchKey((string)($row['resolution_number'] ?? '') . ' ' . (string)($row['delegate_name'] ?? ''));
        return mb_strpos($haystack, $query, 0, 'UTF-8') !== false;
    }));
}

function filterPublicDelegations(array $records, array $filters): array {
    // La consulta pública muestra delegaciones que estuvieron vigentes durante
    // el período, aunque hayan comenzado en un mes anterior.
    $searchOnly = $filters;
    $searchOnly['start'] = null;
    $searchOnly['end'] = null;
    $matched = filterDelegations($records, $searchOnly);
    $periodStart = $filters['start'] ?? null;
    $periodEnd = $filters['end'] ?? null;
    if ($periodStart === null && $periodEnd === null) return $matched;
    return array_values(array_filter($matched, static function (array $record) use ($periodStart, $periodEnd): bool {
        $startDate = (string)($record['start_date'] ?? '');
        $endDate = (string)($record['end_date'] ?? '');
        if (!delegationValidDate($startDate)) return false;
        if ($periodEnd !== null && $startDate > $periodEnd) return false;
        return $periodStart === null || !delegationValidDate($endDate) || $endDate >= $periodStart;
    }));
}

function delegationSearchKey(string $text): string {
    $text = mb_strtolower(trim($text), 'UTF-8');
    return strtr($text, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
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
                $existing = array_merge($existing, $record, [
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

function delegationDuplicateKey(array $record): string {
    return delegationSearchKey(implode('|', [
        delegationResolutionType($record),
        trim((string)($record['resolution_number'] ?? '')),
        trim((string)($record['delegate_name'] ?? '')),
        trim((string)($record['start_date'] ?? '')),
    ]));
}

function appendDelegationRecords(array &$data, array $records, string $admin): array {
    $known = [];
    foreach ($data['delegations'] as $existing) $known[delegationDuplicateKey($existing)] = true;
    $added = 0;
    $skipped = 0;
    $now = date(DATE_ATOM);
    foreach ($records as $record) {
        $key = delegationDuplicateKey($record);
        if (isset($known[$key])) {
            $skipped++;
            continue;
        }
        $known[$key] = true;
        $newId = max(1, (int)$data['next_id']);
        $data['next_id'] = $newId + 1;
        $data['delegations'][] = array_merge($record, [
            'id' => $newId,
            'created_by' => $admin,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $added++;
    }
    return ['added' => $added, 'skipped' => $skipped];
}

function importDelegationRecords(array $records, string $admin): array {
    requireDelegationsModule();
    return updateDelegationsData(function (array &$data) use ($records, $admin): array {
        return appendDelegationRecords($data, $records, $admin);
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
