<?php

require_once __DIR__ . '/delegations.php';

const DELEGATION_IMPORT_HEADERS = [
    'Tipo de delegación', 'Tipo de acto', 'Número de resolución', 'Persona delegada',
    'Cargo delegado', 'Dependencia', 'Fecha inicial', 'Fecha final', 'Visible',
];

function delegationImportZipEntry(PharData $archive, string $name, int $limit): string {
    if (!isset($archive[$name])) throw new RuntimeException('La plantilla Excel no contiene la hoja esperada.');
    $entry = $archive[$name];
    if ($entry->getSize() > $limit) throw new RuntimeException('La hoja Excel supera el tamaño permitido.');
    $content = file_get_contents($entry->getPathname());
    if ($content === false || strlen($content) > $limit) throw new RuntimeException('No se pudo leer la hoja Excel.');
    return $content;
}

function delegationImportXml(string $content): DOMDocument {
    $xml = new DOMDocument();
    if (!$xml->loadXML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
        throw new RuntimeException('El archivo Excel contiene XML inválido.');
    }
    return $xml;
}

function delegationImportCellValue(DOMElement $cell, array $sharedStrings): string {
    $type = $cell->getAttribute('t');
    foreach ($cell->childNodes as $child) {
        if (!$child instanceof DOMElement) continue;
        if ($child->localName === 'f') throw new RuntimeException('La plantilla no debe contener fórmulas en los datos.');
        if ($child->localName === 'is') return trim($child->textContent);
        if ($child->localName !== 'v') continue;
        $value = trim($child->textContent);
        return $type === 's' ? (string)($sharedStrings[(int)$value] ?? '') : $value;
    }
    return '';
}

function delegationImportDate(string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    if (preg_match('/\A\d+(?:\.\d+)?\z/', $value) && (float)$value >= 1 && (float)$value <= 2958465) {
        return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int)floor((float)$value) . ' days')->format('Y-m-d');
    }
    if (preg_match('/\A(\d{1,2})\/(\d{1,2})\/(\d{4})\z/', $value, $parts)) {
        return checkdate((int)$parts[2], (int)$parts[1], (int)$parts[3])
            ? sprintf('%04d-%02d-%02d', (int)$parts[3], (int)$parts[2], (int)$parts[1])
            : $value;
    }
    return $value;
}

function parseDelegationImportXlsx(string $path): array {
    if (!is_file($path) || filesize($path) > 2 * 1024 * 1024) {
        throw new RuntimeException('El archivo debe ser .xlsx y pesar como máximo 2 MB.');
    }
    try {
        $archive = new PharData($path, 0, null, Phar::ZIP);
    } catch (Throwable $error) {
        throw new RuntimeException('No se pudo abrir el archivo .xlsx. Usa la plantilla descargada desde esta página.', 0, $error);
    }
    try {
        $sheetXml = delegationImportZipEntry($archive, 'xl/worksheets/sheet1.xml', 5 * 1024 * 1024);
        $sharedStrings = [];
        if (isset($archive['xl/sharedStrings.xml'])) {
            $stringsXml = delegationImportXml(delegationImportZipEntry($archive, 'xl/sharedStrings.xml', 3 * 1024 * 1024));
            foreach ($stringsXml->getElementsByTagName('si') as $string) {
                $sharedStrings[] = trim($string->textContent);
                if (count($sharedStrings) > 20000) throw new RuntimeException('Demasiados textos en el archivo Excel.');
            }
        }
        $sheet = delegationImportXml($sheetXml);
        $rows = [];
        $headerFound = false;
        foreach ($sheet->getElementsByTagName('row') as $row) {
            if (!$row instanceof DOMElement) continue;
            $number = (int)$row->getAttribute('r');
            $values = array_fill(0, 9, '');
            foreach ($row->getElementsByTagName('c') as $cell) {
                if (!$cell instanceof DOMElement) continue;
                if (!preg_match('/\A([A-Z]+)\d+\z/', $cell->getAttribute('r'), $matches)) continue;
                $column = 0;
                foreach (str_split($matches[1]) as $letter) $column = $column * 26 + ord($letter) - 64;
                if ($column < 1 || $column > 9) continue;
                $values[$column - 1] = delegationImportCellValue($cell, $sharedStrings);
            }
            if ($number === 1) {
                if ($values !== DELEGATION_IMPORT_HEADERS) {
                    throw new RuntimeException('Los encabezados no coinciden con la plantilla. Descarga una plantilla nueva y no cambies la primera fila.');
                }
                $headerFound = true;
                continue;
            }
            if (count(array_filter($values, fn(string $value): bool => trim($value) !== '')) > 0) {
                $rows[] = ['line' => $number, 'values' => $values];
            }
        }
        if (!$headerFound) throw new RuntimeException('No se encontró la primera fila de la plantilla.');
        if (!$rows) throw new RuntimeException('No hay delegaciones para importar en la hoja Delegaciones.');
        if (count($rows) > 1000) throw new RuntimeException('El máximo es de 1.000 delegaciones por archivo.');
        return $rows;
    } catch (PharException $error) {
        throw new RuntimeException('No se pudo abrir el archivo .xlsx. Usa la plantilla descargada desde esta página.', 0, $error);
    }
}

function validateDelegationImportRows(array $rawRows, array $existing): array {
    $records = [];
    $errors = [];
    $duplicates = 0;
    $known = [];
    foreach ($existing as $record) $known[delegationDuplicateKey($record)] = true;
    foreach ($rawRows as $raw) {
        [$type, $act, $resolution, $name, $position, $unit, $start, $end, $visible] = $raw['values'];
        $type = delegationSearchKey($type);
        $type = match ($type) {
            'hasta proveer el cargo' => 'interim',
            'fecha fija' => 'fixed',
            default => $type,
        };
        $visible = delegationSearchKey($visible);
        if ($visible !== '' && !in_array($visible, ['si', 'no'], true)) {
            $errors[] = 'Fila ' . $raw['line'] . ': Visible debe ser Sí o No.';
            continue;
        }
        $input = [
            'delegation_type' => $type,
            'resolution_type' => $act,
            'resolution_number' => $resolution,
            'delegate_name' => $name,
            'delegated_position' => mb_strtoupper(trim($position), 'UTF-8'),
            'delegated_unit' => $unit,
            'start_date' => delegationImportDate($start),
            'end_date' => delegationImportDate($end),
        ];
        if ($type === 'interim' && trim($end) !== '') {
            $errors[] = 'Fila ' . $raw['line'] . ': Deja vacía la fecha final cuando sea hasta proveer el cargo.';
            continue;
        }
        if ($visible !== 'no') $input['is_active'] = '1';
        $validated = validateDelegationInput($input, null, true);
        if ($validated['errors']) {
            $errors[] = 'Fila ' . $raw['line'] . ': ' . implode(' ', $validated['errors']);
            continue;
        }
        $key = delegationDuplicateKey($validated['data']);
        if (isset($known[$key])) {
            $duplicates++;
            continue;
        }
        $known[$key] = true;
        $records[] = $validated['data'];
    }
    return ['records' => $records, 'errors' => $errors, 'duplicates' => $duplicates];
}
