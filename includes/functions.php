<?php
// ============================================================
// includes/functions.php — Funciones helpers del portal
// Usa data/data.json en lugar de MySQL — sin BD requerida
// ============================================================

define('DATA_FILE', __DIR__ . '/../data/data.json');
define('DATA_LOCK_FILE', __DIR__ . '/../data/data.lock');
define('SECURITY_STATE_FILE', __DIR__ . '/../data/security.json');
define('SECURITY_LOCK_FILE', __DIR__ . '/../data/security.lock');

function decodeJsonFile(string $file): array {
    if (!file_exists($file)) return [];

    $json = file_get_contents($file);
    if ($json === false || trim($json) === '') return [];

    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new RuntimeException('El archivo de datos no contiene JSON válido.');
    }
    return $data;
}

function encodeJsonData(array $data): string {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No fue posible convertir los datos a JSON.');
    }
    return $json . PHP_EOL;
}

function writeJsonAtomically(string $file, array $data): bool {
    $tmp = tempnam(dirname($file), 'json_');
    if ($tmp === false) return false;
    $written = file_put_contents($tmp, encodeJsonData($data), LOCK_EX);
    if ($written === false) {
        @unlink($tmp);
        return false;
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

function withFileLock(string $lockFile, int $operation, callable $callback) {
    $handle = fopen($lockFile, 'c+');
    if ($handle === false) {
        throw new RuntimeException('No fue posible abrir el archivo de bloqueo. Verifica los permisos de data/.');
    }

    try {
        if (!flock($handle, $operation)) {
            throw new RuntimeException('No fue posible bloquear el almacenamiento de datos.');
        }
        return $callback();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/**
 * Leer todos los datos del JSON
 */
function readData(): array {
    if (!file_exists(DATA_FILE)) {
        die('Error: No se encuentra data/data.json. Verifica la instalación.');
    }
    return withFileLock(DATA_LOCK_FILE, LOCK_SH, fn() => decodeJsonFile(DATA_FILE));
}

/**
 * Guardar datos de vuelta al JSON (solo para el admin)
 */
function saveData(array $data): bool {
    return withFileLock(DATA_LOCK_FILE, LOCK_EX, function () use ($data): bool {
        return writeJsonAtomically(DATA_FILE, $data);
    });
}

/**
 * Actualizar el JSON dentro de una única transacción protegida por bloqueo.
 */
function updateData(callable $mutator) {
    return withFileLock(DATA_LOCK_FILE, LOCK_EX, function () use ($mutator) {
        $data = decodeJsonFile(DATA_FILE);
        $result = $mutator($data);
        if (!writeJsonAtomically(DATA_FILE, $data)) {
            throw new RuntimeException('No fue posible guardar data/data.json.');
        }
        return $result;
    });
}

function updateSecurityState(callable $mutator) {
    return withFileLock(SECURITY_LOCK_FILE, LOCK_EX, function () use ($mutator) {
        $state = decodeJsonFile(SECURITY_STATE_FILE);
        $result = $mutator($state);
        if (!writeJsonAtomically(SECURITY_STATE_FILE, $state)) {
            throw new RuntimeException('No fue posible guardar el estado de seguridad.');
        }
        return $result;
    });
}

function clientIpAddress(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function clientSecurityKey(string $suffix = ''): string {
    return hash('sha256', clientIpAddress() . '|unitropico-th|' . $suffix);
}

/**
 * Consume un cupo de frecuencia. Devuelve 0 si se permite o segundos de espera.
 */
function consumeRateLimit(string $scope, string $key, int $maxEvents, int $windowSeconds, int $minInterval = 0): int {
    return updateSecurityState(function (array &$state) use ($scope, $key, $maxEvents, $windowSeconds, $minInterval): int {
        $now = time();
        $bucketKey = hash('sha256', $scope . '|' . $key);
        $events = $state['rate_limits'][$bucketKey] ?? [];
        $events = array_values(array_filter($events, fn($timestamp) => (int)$timestamp > $now - $windowSeconds));

        if ($minInterval > 0 && $events) {
            $last = (int)end($events);
            if ($last + $minInterval > $now) {
                $state['rate_limits'][$bucketKey] = $events;
                return $last + $minInterval - $now;
            }
        }

        if (count($events) >= $maxEvents) {
            $state['rate_limits'][$bucketKey] = $events;
            return max(1, (int)$events[0] + $windowSeconds - $now);
        }

        $events[] = $now;
        $state['rate_limits'][$bucketKey] = $events;

        if (count($state['rate_limits']) > 2000) {
            foreach ($state['rate_limits'] as $storedKey => $storedEvents) {
                $fresh = array_values(array_filter((array)$storedEvents, fn($timestamp) => (int)$timestamp > $now - 86400));
                if ($fresh) $state['rate_limits'][$storedKey] = $fresh;
                else unset($state['rate_limits'][$storedKey]);
            }
        }
        return 0;
    });
}

function clearRateLimit(string $scope, string $key): void {
    updateSecurityState(function (array &$state) use ($scope, $key): void {
        unset($state['rate_limits'][hash('sha256', $scope . '|' . $key)]);
    });
}

function isSameOriginRequest(): bool {
    $source = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($source === '') return true;

    $sourceHost = strtolower((string)parse_url($source, PHP_URL_HOST));
    $serverHost = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    return $sourceHost !== '' && hash_equals($serverHost, $sourceHost);
}

function sanitizeContentUrl(string $url, bool $allowRelative = true): string {
    $url = trim($url);
    if ($url === '') return '';
    $url = function_exists('mb_substr') ? mb_substr($url, 0, 2048, 'UTF-8') : substr($url, 0, 2048);

    if (str_starts_with($url, '#')) return $allowRelative ? $url : '';
    if (str_starts_with($url, '//')) return '';

    $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
    if ($scheme === '') return $allowRelative ? $url : '';
    return in_array($scheme, ['http', 'https', 'mailto'], true) ? $url : '';
}

/**
 * Obtener todas las páginas activas (para el menú de navegación)
 */
function getPages(): array {
    $data = readData();
    $pages = $data['pages'] ?? [];
    $pages = array_filter($pages, fn($p) => $p['is_active'] ?? true);
    usort($pages, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
    return array_values($pages);
}

/**
 * Obtener una página por su slug
 */
function getPage(string $slug): ?array {
    $data = readData();
    foreach ($data['pages'] ?? [] as $page) {
        if ($page['slug'] === $slug && ($page['is_active'] ?? true)) {
            return $page;
        }
    }
    return null;
}

/**
 * Obtener las tarjetas de una página específica
 */
function getCards(string $pageSlug): array {
    $data  = readData();
    $cards = $data['cards'][$pageSlug] ?? [];
    $cards = array_filter($cards, fn($c) => $c['is_active'] ?? true);
    usort($cards, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
    return array_values($cards);
}

/**
 * Obtener todas las tarjetas (para el admin)
 */
function getAllCards(): array {
    $data   = readData();
    $result = [];
    foreach ($data['cards'] ?? [] as $slug => $cards) {
        foreach ($cards as $card) {
            $card['page_slug'] = $slug;
            $result[] = $card;
        }
    }
    return $result;
}

/**
 * Registrar una visita anónima al portal.
 */
function recordAnalyticsVisit(string $visitorId, string $path, string $title = ''): void {
    $path = trim(strip_tags($path));
    $title = trim(strip_tags($title));
    $visitorId = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($visitorId));
    $path = function_exists('mb_substr') ? mb_substr($path, 0, 300, 'UTF-8') : substr($path, 0, 300);
    $title = function_exists('mb_substr') ? mb_substr($title, 0, 120, 'UTF-8') : substr($title, 0, 120);

    if ($path === '' || str_starts_with($path, '/admin') || str_starts_with($path, '/api')) {
        return;
    }

    if ($visitorId === '') {
        $visitorId = 'anonymous-' . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    $month = date('Y-m');
    $day = date('Y-m-d');
    $visitorHash = substr(hash('sha256', $visitorId . '|unitropico-th'), 0, 18);
    $section = analyticsSectionName($path, $title);

    updateData(function (array &$data) use ($month, $day, $visitorHash, $section, $path): void {
        if (!isset($data['analytics']['monthly']) || !is_array($data['analytics']['monthly'])) {
            $data['analytics'] = ['monthly' => []];
        }
        if (!isset($data['analytics']['monthly'][$month])) {
            $data['analytics']['monthly'][$month] = [
                'visits' => 0,
                'visitors' => [],
                'sections' => [],
                'days' => [],
            ];
        }

        $bucket = &$data['analytics']['monthly'][$month];
        $bucket['visits'] = (int)($bucket['visits'] ?? 0) + 1;
        $bucket['visitors'][$visitorHash] = true;
        $bucket['days'][$day] = (int)($bucket['days'][$day] ?? 0) + 1;

        if (!isset($bucket['sections'][$section])) {
            $bucket['sections'][$section] = ['visits' => 0, 'visitors' => [], 'path' => $path];
        }
        $bucket['sections'][$section]['visits'] = (int)($bucket['sections'][$section]['visits'] ?? 0) + 1;
        $bucket['sections'][$section]['visitors'][$visitorHash] = true;
        $bucket['sections'][$section]['path'] = $path;
    });
}

/**
 * Obtener resumen de analítica por mes para el panel admin.
 */
function getAnalyticsSummary(?string $month = null): array {
    $data = readData();
    $monthly = $data['analytics']['monthly'] ?? [];
    krsort($monthly);

    $availableMonths = array_keys($monthly);
    $selectedMonth = $month && isset($monthly[$month]) ? $month : ($availableMonths[0] ?? date('Y-m'));
    $bucket = $monthly[$selectedMonth] ?? [
        'visits' => 0,
        'visitors' => [],
        'sections' => [],
        'days' => [],
    ];

    $sections = [];
    foreach (($bucket['sections'] ?? []) as $name => $section) {
        $sections[] = [
            'name' => $name,
            'visits' => (int)($section['visits'] ?? 0),
            'visitors' => count($section['visitors'] ?? []),
            'path' => $section['path'] ?? '',
        ];
    }
    usort($sections, fn($a, $b) => $b['visits'] <=> $a['visits']);

    $days = $bucket['days'] ?? [];
    ksort($days);

    return [
        'month' => $selectedMonth,
        'months' => $availableMonths,
        'visits' => (int)($bucket['visits'] ?? 0),
        'visitors' => count($bucket['visitors'] ?? []),
        'sections' => $sections,
        'days' => $days,
    ];
}

function analyticsSectionName(string $path, string $title = ''): string {
    $pathOnly = parse_url($path, PHP_URL_PATH) ?: $path;
    $query = parse_url($path, PHP_URL_QUERY) ?: '';

    if (str_ends_with($pathOnly, '/index.php') || $pathOnly === '/' || $pathOnly === '') {
        return 'Inicio';
    }

    if (str_contains($pathOnly, '/pages/programa.php') && $query) {
        parse_str($query, $params);
        if (!empty($params['slug'])) {
            return ucwords(str_replace('-', ' ', (string)$params['slug']));
        }
    }

    $name = pathinfo($pathOnly, PATHINFO_FILENAME);
    $name = $name === '' ? $title : $name;
    $name = str_replace(['-', '_'], ' ', $name);
    return $name !== '' ? ucwords($name) : 'Página';
}

/**
 * Obtener un valor de configuración del sitio
 */
function getConfig(string $key, string $default = ''): string {
    static $config = null;
    if ($config === null) {
        $data   = readData();
        $config = $data['config'] ?? [];
    }
    return $config[$key] ?? $default;
}

/**
 * Actualizar un valor de configuración
 */
function setConfig(string $key, string $value): void {
    updateData(function (array &$data) use ($key, $value): void {
        $data['config'][$key] = $value;
    });
}

/**
 * Actualizar toda la configuración
 */
function setConfigs(array $newConfig): void {
    updateData(function (array &$data) use ($newConfig): void {
        foreach ($newConfig as $key => $value) {
            $data['config'][$key] = $value;
        }
    });
}

/**
 * Guardar o actualizar una tarjeta
 */
function saveCard(string $pageSlug, array $card): void {
    updateData(function (array &$data) use ($pageSlug, $card): void {
        if (!isset($data['cards'][$pageSlug])) {
            $data['cards'][$pageSlug] = [];
        }
        $found = false;
        foreach ($data['cards'][$pageSlug] as &$existing) {
            if (($existing['id'] ?? '') === ($card['id'] ?? '')) {
                $existing = $card;
                $found = true;
                break;
            }
        }
        unset($existing);
        if (!$found) $data['cards'][$pageSlug][] = $card;
    });
}

/**
 * Eliminar una tarjeta por id y page_slug
 */
function deleteCard(string $pageSlug, string $cardId): void {
    updateData(function (array &$data) use ($pageSlug, $cardId): void {
        if (isset($data['cards'][$pageSlug])) {
            $data['cards'][$pageSlug] = array_values(
                array_filter($data['cards'][$pageSlug], fn($c) => ($c['id'] ?? '') !== $cardId)
            );
        }
    });
}

/**
 * Guardar o actualizar una página
 */
function savePage(array $page): void {
    updateData(function (array &$data) use ($page): void {
        $found = false;
        foreach ($data['pages'] as &$existing) {
            if (($existing['slug'] ?? '') === $page['slug']) {
                $existing = $page;
                $found = true;
                break;
            }
        }
        unset($existing);
        if (!$found) {
            $data['pages'][] = $page;
            if (!isset($data['cards'][$page['slug']])) $data['cards'][$page['slug']] = [];
        }
        $corePages = ['inicio', 'talento-humano', 'desarrollo', 'bienestar', 'calendario'];
        if (!in_array($page['slug'], $corePages, true) && !isset($data['program_pages'][$page['slug']])) {
            $data['program_pages'][$page['slug']] = [
                'title' => $page['title'],
                'section' => 'Contenido del portal',
                'subtitle' => $page['subtitle'] ?? '',
                'image_url' => '',
                'accent' => '#006B5B',
                'intro' => $page['hero_text'] ?? '',
                'blocks' => [],
                'actions' => [],
                'year_sections' => [],
            ];
        }
    });
}

/**
 * Eliminar una página por slug
 */
function deletePage(string $slug): void {
    updateData(function (array &$data) use ($slug): void {
        $data['pages'] = array_values(array_filter($data['pages'], fn($p) => ($p['slug'] ?? '') !== $slug));
        unset($data['cards'][$slug]);
    });
}

/**
 * Obtener credenciales del admin
 */
function getAdminCredentials(): array {
    $data = readData();
    return $data['admin'] ?? [];
}

/**
 * Actualizar contraseña del admin
 */
function passwordPolicyError(string $password): string {
    if (strlen($password) < 14) return 'La contraseña debe tener al menos 14 caracteres.';
    if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)
        || !preg_match('/\d/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
        return 'La contraseña debe incluir mayúsculas, minúsculas, números y símbolos.';
    }
    return '';
}

function updateAdminPassword(string $newPassword): int {
    return updateData(function (array &$data) use ($newPassword): int {
        $data['admin']['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $data['admin']['auth_version'] = (int)($data['admin']['auth_version'] ?? 1) + 1;
        return $data['admin']['auth_version'];
    });
}

/**
 * Obtener comentarios visibles del portal.
 */
function getComments(): array {
    $data = readData();
    $comments = $data['comments'] ?? [];
    $comments = array_values(array_filter($comments, fn($comment) => !empty($comment['is_active'])));
    $threads = [];
    $repliesByParent = [];

    foreach ($comments as $comment) {
        $comment['replies'] = [];
        $parentId = trim((string)($comment['parent_id'] ?? ''));
        if ($parentId !== '') {
            $repliesByParent[$parentId][] = $comment;
        } else {
            $threads[] = $comment;
        }
    }

    foreach ($repliesByParent as &$replies) {
        usort($replies, fn($a, $b) => strcmp($a['created_at'] ?? '', $b['created_at'] ?? ''));
    }
    unset($replies);

    usort($threads, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    foreach ($threads as &$thread) {
        $thread['replies'] = $repliesByParent[$thread['id'] ?? ''] ?? [];
    }
    unset($thread);

    return array_values($threads);
}

/**
 * Obtener todos los comentarios para moderación.
 */
function getAllComments(): array {
    $data = readData();
    $comments = $data['comments'] ?? [];
    usort($comments, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
    return array_values($comments);
}

/**
 * Crear un comentario público.
 */
function addComment(string $name, string $message, int $rating = 5, string $emoji = '💚', string $parentId = ''): array {
    $name = trim(strip_tags($name));
    $message = trim(strip_tags($message));
    $emoji = trim(strip_tags($emoji));
    $parentId = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($parentId));

    if ($name === '') $name = 'Visitante';
    if ($emoji === '') $emoji = '💚';
    $rating = max(1, min(5, $rating));
    $cut = function (string $value, int $length): string {
        return function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length);
    };

    $comment = [
        'id' => uniqid('cm_', true),
        'name' => $cut($name, 60),
        'message' => $cut($message, 600),
        'rating' => $rating,
        'emoji' => $cut($emoji, 8),
        'parent_id' => $parentId,
        'created_at' => date('c'),
        'is_active' => true,
    ];

    updateData(function (array &$data) use ($comment): void {
        if (!isset($data['comments']) || !is_array($data['comments'])) $data['comments'] = [];
        array_unshift($data['comments'], $comment);
    });
    return $comment;
}

/**
 * Eliminar un comentario desde administración.
 */
function deleteComment(string $commentId): void {
    updateData(function (array &$data) use ($commentId): void {
        if (empty($data['comments']) || !is_array($data['comments'])) return;
        $data['comments'] = array_values(
            array_filter($data['comments'], fn($comment) => ($comment['id'] ?? '') !== $commentId && ($comment['parent_id'] ?? '') !== $commentId)
        );
    });
}

/**
 * Validar si un comentario visible puede recibir respuestas.
 */
function canReplyToComment(string $commentId): bool {
    $data = readData();
    foreach (($data['comments'] ?? []) as $comment) {
        if (($comment['id'] ?? '') === $commentId && !empty($comment['is_active']) && empty($comment['parent_id'])) {
            return true;
        }
    }
    return false;
}

/**
 * Sanitizar output HTML
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirigir a una URL
 */
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

/**
 * Determinar la URL base del sitio automáticamente
 */
function baseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if (!preg_match('/\A[a-z0-9.-]+(?::\d+)?\z/i', $host)) $host = 'localhost';
    $script   = dirname($_SERVER['SCRIPT_NAME']);
    $parts    = explode('/', trim($script, '/'));
    $base     = '';
    foreach ($parts as $part) {
        if (in_array($part, ['admin', 'pages', 'api', 'install', 'data'])) break;
        $base .= '/' . $part;
    }
    return rtrim($protocol . '://' . $host . $base, '/');
}

/**
 * Obtener ruta relativa de un asset
 */
function asset(string $path): string {
    return baseUrl() . '/' . ltrim($path, '/');
}

/**
 * Icono SVG inline por nombre
 */
function icon(string $name, string $class = '', int $size = 24): string {
    $icons = [
        'users'          => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'graduation-cap' => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>',
        'heart'          => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        'award'          => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"/></svg>',
        'brain'          => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"/><path d="M17.599 6.5a3 3 0 0 0 .399-1.375"/><path d="M6.003 5.125A3 3 0 0 0 6.401 6.5"/><path d="M3.477 10.896a4 4 0 0 1 .585-.396"/><path d="M19.938 10.5a4 4 0 0 1 .585.396"/><path d="M6 18a4 4 0 0 1-1.967-.516"/><path d="M19.967 17.484A4 4 0 0 1 18 18"/></svg>',
        'coffee'         => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 8h1a4 4 0 1 1 0 8h-1"/><path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z"/><line x1="6" x2="6" y1="2" y2="4"/><line x1="10" x2="10" y1="2" y2="4"/><line x1="14" x2="14" y1="2" y2="4"/></svg>',
        'heart-pulse'    => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><path d="M3.22 12H9.5l.5-1 2 4.5 2-7 1.5 3.5h5.27"/></svg>',
        'sparkles'       => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275Z"/><path d="M5 3v4"/><path d="M19 17v4"/><path d="M3 5h4"/><path d="M17 19h4"/></svg>',
        'shield-check'   => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>',
        'trending-up'    => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>',
        'sun'            => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>',
        'clipboard-check'=> '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg>',
        'bar-chart-2'    => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" x2="18" y1="20" y2="10"/><line x1="12" x2="12" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="14"/></svg>',
        'calendar'       => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>',
        'home'           => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'door-open'      => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h3"/><path d="M13 20h9"/><path d="M10 12v.01"/><path d="M13 4.562v16.157a1 1 0 0 1-1.242.97L5 20V5.562a2 2 0 0 1 1.515-1.94l4-1A2 2 0 0 1 13 4.561Z"/></svg>',
        'refresh-cw'     => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>',
        'star'           => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'chevron-right'  => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>',
        'external-link'  => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" x2="21" y1="14" y2="3"/></svg>',
        'menu'           => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>',
        'x'              => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
        'settings'       => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>',
        'log-out'        => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>',
        'plus'           => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>',
        'edit'           => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        'trash'          => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>',
        'image'          => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>',
        'save'           => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>',
        'lock'           => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'user'           => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        'layers'         => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m6.08 9.5-3.5 1.6a1 1 0 0 0 0 1.81l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9a1 1 0 0 0 0-1.83l-3.5-1.59"/><path d="m6.08 14.5-3.5 1.6a1 1 0 0 0 0 1.81l8.6 3.91a2 2 0 0 0 1.65 0l8.58-3.9a1 1 0 0 0 0-1.83l-3.5-1.59"/></svg>',
    ];
    return $icons[$name] ?? $icons['star'];
}
