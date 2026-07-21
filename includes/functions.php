<?php
// ============================================================
// includes/functions.php — Funciones helpers del portal
// Almacenamiento intercambiable: JSON para transición o MariaDB mediante PDO.
// ============================================================

require_once __DIR__ . '/database-storage.php';

define('DATA_FILE', __DIR__ . '/../data/data.json');
define('DATA_TEMPLATE_FILE', __DIR__ . '/../data/data.example.json');
define('DATA_LOCK_FILE', __DIR__ . '/../data/data.lock');
define('DATA_MIGRATIONS_DIR', __DIR__ . '/../data/migrations');
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
 * Crear el archivo persistente solo en instalaciones nuevas.
 * data/data.json no se versiona para que los despliegues no sobrescriban
 * comentarios, credenciales, estadísticas ni cambios hechos desde el admin.
 */
function ensureDataFile(): void {
    if (file_exists(DATA_FILE)) return;

    withFileLock(DATA_LOCK_FILE, LOCK_EX, function (): void {
        if (file_exists(DATA_FILE)) return;
        if (!file_exists(DATA_TEMPLATE_FILE)) {
            throw new RuntimeException('No se encuentra la plantilla data/data.example.json.');
        }

        $initialData = decodeJsonFile(DATA_TEMPLATE_FILE);
        if (!$initialData || !writeJsonAtomically(DATA_FILE, $initialData)) {
            throw new RuntimeException('No fue posible inicializar data/data.json. Verifica los permisos de data/.');
        }
    });
}

function applyPendingDataMigrations(array &$data): bool {
    if (!is_dir(DATA_MIGRATIONS_DIR)) return false;

    $applied = array_values(array_filter((array)($data['_meta']['migrations'] ?? []), 'is_string'));
    $migrationFiles = glob(DATA_MIGRATIONS_DIR . '/*.php') ?: [];
    sort($migrationFiles, SORT_STRING);
    $changed = false;

    foreach ($migrationFiles as $migrationFile) {
        $migrationId = basename($migrationFile, '.php');
        if (in_array($migrationId, $applied, true)) continue;

        $migration = require $migrationFile;
        if (!is_callable($migration)) {
            throw new RuntimeException('Migración de datos inválida: ' . $migrationId);
        }

        $migration($data);
        $applied[] = $migrationId;
        $changed = true;
    }

    if ($changed) $data['_meta']['migrations'] = $applied;
    return $changed;
}

/**
 * Aplica cambios de contenido versionados una sola vez en el backend activo.
 */
function runDataMigrations(): void {
    static $completed = false;
    if ($completed) return;

    if (storageDriver() === 'mysql') {
        $applied = databaseAppliedDataMigrations();
        $migrationFiles = glob(DATA_MIGRATIONS_DIR . '/*.php') ?: [];
        $hasPendingMigration = false;
        foreach ($migrationFiles as $migrationFile) {
            if (!in_array(basename($migrationFile, '.php'), $applied, true)) {
                $hasPendingMigration = true;
                break;
            }
        }
        if ($hasPendingMigration) {
            databaseUpdateDataIfChanged(fn(array &$data): bool => applyPendingDataMigrations($data));
        }
    } else {
        withFileLock(DATA_LOCK_FILE, LOCK_EX, function (): void {
            $data = decodeJsonFile(DATA_FILE);
            if (applyPendingDataMigrations($data) && !writeJsonAtomically(DATA_FILE, $data)) {
                throw new RuntimeException('No fue posible aplicar las migraciones de datos.');
            }
        });
    }

    $completed = true;
}

function ensureDataReady(): void {
    if (storageDriver() === 'mysql') {
        if (!databaseSchemaIsReady()) {
            throw new RuntimeException('La base MariaDB no tiene el esquema del portal. Importa database/schema.sql.');
        }
    } else {
        ensureDataFile();
    }
    runDataMigrations();
}

/**
 * Leer todos los datos del almacenamiento activo.
 */
function readData(): array {
    ensureDataReady();
    if (isset($GLOBALS['portal_data_cache']) && is_array($GLOBALS['portal_data_cache'])) {
        return $GLOBALS['portal_data_cache'];
    }
    $data = storageDriver() === 'mysql'
        ? databaseReadData()
        : withFileLock(DATA_LOCK_FILE, LOCK_SH, fn() => decodeJsonFile(DATA_FILE));
    $GLOBALS['portal_data_cache'] = $data;
    return $data;
}

/**
 * Guardar datos en el almacenamiento activo (solo para el admin).
 */
function saveData(array $data): bool {
    ensureDataReady();
    $saved = storageDriver() === 'mysql'
        ? databaseSaveData($data)
        : withFileLock(DATA_LOCK_FILE, LOCK_EX, function () use ($data): bool {
        return writeJsonAtomically(DATA_FILE, $data);
    });
    if ($saved) $GLOBALS['portal_data_cache'] = $data;
    return $saved;
}

/**
 * Actualizar dentro de una única transacción protegida por bloqueo.
 */
function updateData(callable $mutator) {
    ensureDataReady();
    if (storageDriver() === 'mysql') {
        return databaseUpdateData(function (array &$data) use ($mutator) {
            $result = $mutator($data);
            $GLOBALS['portal_data_cache'] = $data;
            return $result;
        });
    }
    return withFileLock(DATA_LOCK_FILE, LOCK_EX, function () use ($mutator) {
        $data = decodeJsonFile(DATA_FILE);
        $result = $mutator($data);
        if (!writeJsonAtomically(DATA_FILE, $data)) {
            throw new RuntimeException('No fue posible guardar data/data.json.');
        }
        $GLOBALS['portal_data_cache'] = $data;
        return $result;
    });
}

function updateSecurityState(callable $mutator) {
    if (storageDriver() === 'mysql') {
        if (!databaseSchemaIsReady()) {
            throw new RuntimeException('La base MariaDB no tiene el esquema del portal.');
        }
        return databaseUpdateSecurityState($mutator);
    }
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

function isSameOriginRequest(bool $allowMissingSource = true): bool {
    $source = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
    if ($source === '') return $allowMissingSource;

    $sourceHost = strtolower((string)parse_url($source, PHP_URL_HOST));
    $serverHost = strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
    return $sourceHost !== '' && hash_equals($serverHost, $sourceHost);
}

function publicRequestIsSecure(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/**
 * Sesión separada del panel administrativo para tokens públicos de un solo uso.
 */
function ensurePublicSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('unitropico_public');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => publicRequestIsSecure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function purgePublicRequestTokens(): void {
    ensurePublicSession();
    $now = time();
    $tokens = (array)($_SESSION['public_request_tokens'] ?? []);

    foreach ($tokens as $scope => $scopeTokens) {
        $fresh = array_filter(
            (array)$scopeTokens,
            fn($createdAt) => (int)$createdAt >= $now - 7200
        );
        if ($fresh) $tokens[$scope] = array_slice($fresh, -80, null, true);
        else unset($tokens[$scope]);
    }

    $_SESSION['public_request_tokens'] = $tokens;
}

function issuePublicRequestToken(string $scope): string {
    $scope = preg_replace('/[^a-z0-9_-]/', '', strtolower($scope));
    if ($scope === '') throw new InvalidArgumentException('El alcance del token público no es válido.');

    purgePublicRequestTokens();
    $token = bin2hex(random_bytes(24));
    $_SESSION['public_request_tokens'][$scope][$token] = time();
    return $token;
}

function validatePublicRequestToken(string $scope, string $token, int $minimumAge = 0, int $maximumAge = 7200): bool {
    ensurePublicSession();
    $scope = preg_replace('/[^a-z0-9_-]/', '', strtolower($scope));
    $token = trim($token);
    if ($scope === '' || !preg_match('/^[a-f0-9]{48}$/', $token)) return false;

    $createdAt = (int)($_SESSION['public_request_tokens'][$scope][$token] ?? 0);
    $age = time() - $createdAt;
    return $createdAt > 0 && $age >= max(0, $minimumAge) && $age <= max(1, $maximumAge);
}

function consumePublicRequestToken(string $scope, string $token): void {
    ensurePublicSession();
    unset($_SESSION['public_request_tokens'][$scope][$token]);
}

function publicSessionVisitorId(): string {
    ensurePublicSession();
    if (empty($_SESSION['public_visitor_id'])) {
        $_SESSION['public_visitor_id'] = 'v_' . bin2hex(random_bytes(18));
    }
    return (string)$_SESSION['public_visitor_id'];
}

function currentRequestHost(): string {
    return strtolower((string)parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
}

function isLocalDevelopmentHost(): bool {
    $host = currentRequestHost();
    return in_array($host, ['', 'localhost', '127.0.0.1', '0.0.0.0', '::1'], true)
        || str_ends_with($host, '.test');
}

function turnstileCredentials(): array {
    $config = databaseConfig() ?? [];
    $siteKey = trim((string)($config['turnstile_site_key'] ?? ''));
    $secretKey = trim((string)($config['turnstile_secret_key'] ?? ''));
    $testing = false;

    if ($siteKey === '' && $secretKey === '' && isLocalDevelopmentHost()) {
        $siteKey = '1x00000000000000000000AA';
        $secretKey = '1x0000000000000000000000000000000AA';
        $testing = true;
    }

    return [
        'site_key' => $siteKey,
        'secret_key' => $secretKey,
        'enabled' => $siteKey !== '' && $secretKey !== '',
        'testing' => $testing,
    ];
}

function turnstileSiteKey(): string {
    $credentials = turnstileCredentials();
    return $credentials['enabled'] ? (string)$credentials['site_key'] : '';
}

function verifyTurnstileToken(string $token, string $expectedAction = 'comment'): bool {
    $credentials = turnstileCredentials();
    if (!$credentials['enabled']) return false;

    $token = trim($token);
    if ($token === '' || strlen($token) > 2048) return false;

    $payload = http_build_query([
        'secret' => $credentials['secret_key'],
        'response' => $token,
        'remoteip' => clientIpAddress(),
    ]);
    $response = false;

    if (function_exists('curl_init')) {
        $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
    } elseif (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL)) {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 8,
        ]]);
        $response = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
    }

    if (!is_string($response) || $response === '') return false;
    $result = json_decode($response, true);
    if (!is_array($result) || empty($result['success'])) return false;
    if ($credentials['testing']) return true;

    $action = trim((string)($result['action'] ?? ''));
    $hostname = strtolower(trim((string)($result['hostname'] ?? '')));
    return $action === $expectedAction
        && $hostname !== ''
        && hash_equals(currentRequestHost(), $hostname);
}

function isLikelyAutomatedUserAgent(string $userAgent): bool {
    $userAgent = strtolower(trim($userAgent));
    if ($userAgent === '') return true;

    return (bool)preg_match(
        '/(?:bot|spider|crawler|slurp|curl|wget|python-requests|python-urllib|httpclient|headless|selenium|playwright|phantomjs|scrapy|ahrefs|semrush|mj12bot|facebookexternalhit)/i',
        $userAgent
    );
}

/**
 * Detecta mensajes incompatibles con el muro de experiencias. Se omiten en
 * silencio para no confirmar a los emisores automáticos que fueron detectados.
 */
function isLikelyCommentSpam(string $name, string $message): bool {
    $content = strtolower(trim($name . ' ' . $message));
    if ($content === '') return false;

    if (preg_match('~(?:https?://|www\.|\b[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}\b|\bt\.me/|\bwa\.me/)~iu', $content)) {
        return true;
    }

    if (preg_match('/\b(?:whats?app|telegram|backlinks?|digital marketing|marketing digital|seo services?|sales funnel|conversion funnel|competitors?|consultant|casino|cryptocurrency|investment opportunity)\b/iu', $content)) {
        return true;
    }

    $digits = preg_replace('/\D+/', '', $content);
    return strlen($digits) >= 9;
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
 * Convertir enlaces compartidos de YouTube y Google Drive en reproductores
 * seguros. Otros dominios no se incrustan y conservan su enlace normal.
 */
function videoEmbedData(string $url): ?array {
    $externalUrl = sanitizeContentUrl($url, false);
    if ($externalUrl === '') return null;

    $parts = parse_url($externalUrl);
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');
    if ($scheme !== 'https' || $host === '') return null;

    $youtubeHosts = [
        'youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com',
        'youtube-nocookie.com', 'www.youtube-nocookie.com', 'youtu.be',
    ];
    if (in_array($host, $youtubeHosts, true)) {
        $videoId = '';
        if ($host === 'youtu.be') {
            $videoId = trim(explode('/', trim($path, '/'))[0] ?? '');
        } elseif ($path === '/watch') {
            parse_str((string)($parts['query'] ?? ''), $query);
            $videoId = trim((string)($query['v'] ?? ''));
        } elseif (preg_match('~^/(?:embed|shorts|live)/([a-zA-Z0-9_-]{11})(?:/|$)~', $path, $match)) {
            $videoId = $match[1];
        }

        if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $videoId)) {
            return [
                'provider' => 'youtube',
                'provider_label' => 'YouTube',
                'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $videoId,
                'external_url' => $externalUrl,
            ];
        }
        return null;
    }

    if ($host === 'drive.google.com' || $host === 'www.drive.google.com') {
        $fileId = '';
        if (preg_match('~^/file/d/([a-zA-Z0-9_-]{10,})(?:/|$)~', $path, $match)) {
            $fileId = $match[1];
        } else {
            parse_str((string)($parts['query'] ?? ''), $query);
            $fileId = trim((string)($query['id'] ?? ''));
        }

        if (preg_match('/^[a-zA-Z0-9_-]{10,}$/', $fileId)) {
            return [
                'provider' => 'drive',
                'provider_label' => 'Google Drive',
                'embed_url' => 'https://drive.google.com/file/d/' . $fileId . '/preview',
                'external_url' => $externalUrl,
            ];
        }
    }

    return null;
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
    $now = time();
    $visitorHash = substr(hash('sha256', $visitorId . '|unitropico-th'), 0, 18);
    $visitHash = substr(hash('sha256', $visitorId . '|' . $path . '|unitropico-th-page'), 0, 24);
    $section = analyticsSectionName($path, $title);

    $updateBucket = function (array &$bucket) use ($day, $now, $visitorHash, $visitHash, $section, $path): void {
        if (!$bucket) {
            $bucket = [
                'visits' => 0,
                'visitors' => [],
                'sections' => [],
                'days' => [],
                'recent_visits' => [],
            ];
        }

        $recentVisits = array_filter(
            (array)($bucket['recent_visits'] ?? []),
            fn($timestamp) => (int)$timestamp > $now - 1800
        );
        if (isset($recentVisits[$visitHash])) {
            $bucket['recent_visits'] = $recentVisits;
            return;
        }
        $recentVisits[$visitHash] = $now;
        if (count($recentVisits) > 5000) {
            asort($recentVisits, SORT_NUMERIC);
            $recentVisits = array_slice($recentVisits, -5000, null, true);
        }
        $bucket['recent_visits'] = $recentVisits;

        $bucket['visits'] = (int)($bucket['visits'] ?? 0) + 1;
        $bucket['visitors'][$visitorHash] = true;
        $bucket['days'][$day] = (int)($bucket['days'][$day] ?? 0) + 1;

        if (!isset($bucket['sections'][$section])) {
            $bucket['sections'][$section] = ['visits' => 0, 'visitors' => [], 'path' => $path];
        }
        $bucket['sections'][$section]['visits'] = (int)($bucket['sections'][$section]['visits'] ?? 0) + 1;
        $bucket['sections'][$section]['visitors'][$visitorHash] = true;
        $bucket['sections'][$section]['path'] = $path;
    };

    ensureDataReady();
    if (storageDriver() === 'mysql') {
        databaseUpdateAnalyticsMonth($month, $updateBucket);
        unset($GLOBALS['portal_data_cache']);
        return;
    }

    updateData(function (array &$data) use ($month, $updateBucket): void {
        if (!isset($data['analytics']['monthly']) || !is_array($data['analytics']['monthly'])) {
            $data['analytics'] = ['monthly' => []];
        }
        if (!isset($data['analytics']['monthly'][$month])) $data['analytics']['monthly'][$month] = [];
        $updateBucket($data['analytics']['monthly'][$month]);
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
    if (storageDriver() === 'mysql') {
        ensureDataReady();
        $credentials = getAdminCredentials();
        $version = databaseUpdateAdminPassword(
            (string)($credentials['username'] ?? ''),
            password_hash($newPassword, PASSWORD_DEFAULT)
        );
        unset($GLOBALS['portal_data_cache']);
        return $version;
    }
    return updateData(function (array &$data) use ($newPassword): int {
        $data['admin']['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $data['admin']['auth_version'] = (int)($data['admin']['auth_version'] ?? 1) + 1;
        return $data['admin']['auth_version'];
    });
}

function adminRecoveryToken(): string {
    $config = databaseConfig();
    return trim((string)($config['admin_recovery_token'] ?? ''));
}

function adminRecoveryEnabled(): bool {
    return strlen(adminRecoveryToken()) >= 32;
}

/**
 * Restablecer la contraseña mediante un token privado de un solo uso.
 * El hash del token usado viaja con los datos para impedir su reutilización
 * incluso después de migrar de JSON a MariaDB.
 */
function recoverAdminPassword(string $newPassword, string $recoveryToken): int {
    $configuredToken = adminRecoveryToken();
    if (strlen($configuredToken) < 32 || !hash_equals($configuredToken, $recoveryToken)) {
        throw new RuntimeException('El token de recuperación no es válido.');
    }

    $tokenHash = hash('sha256', $recoveryToken);
    return updateData(function (array &$data) use ($newPassword, $tokenHash): int {
        $usedTokens = array_values(array_filter(
            (array)($data['_meta']['admin_recovery_tokens'] ?? []),
            'is_string'
        ));
        if (in_array($tokenHash, $usedTokens, true)) {
            throw new RuntimeException('Este token de recuperación ya fue utilizado.');
        }

        $data['admin']['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        $data['admin']['auth_version'] = (int)($data['admin']['auth_version'] ?? 1) + 1;
        $usedTokens[] = $tokenHash;
        $data['_meta']['admin_recovery_tokens'] = array_slice($usedTokens, -10);
        return $data['admin']['auth_version'];
    });
}

/**
 * Obtener comentarios visibles del portal.
 */
function commentModerationStatuses(): array {
    return ['pending', 'published', 'rejected'];
}

function normalizeCommentStatus(array $comment): string {
    $status = strtolower(trim((string)($comment['status'] ?? '')));
    if (in_array($status, commentModerationStatuses(), true)) return $status;
    return !empty($comment['is_active']) ? 'published' : 'rejected';
}

function commentStatusLabel(string $status): string {
    return match ($status) {
        'published' => 'Publicado',
        'rejected' => 'Rechazado',
        default => 'Pendiente',
    };
}

function getComments(): array {
    $data = readData();
    $comments = $data['comments'] ?? [];
    $comments = array_values(array_filter(
        $comments,
        fn($comment) => is_array($comment) && normalizeCommentStatus($comment) === 'published'
    ));
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
    foreach ($comments as &$comment) {
        if (!is_array($comment)) $comment = [];
        $comment['status'] = normalizeCommentStatus($comment);
        $comment['is_active'] = $comment['status'] === 'published';
    }
    unset($comment);
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
        'status' => 'pending',
        'is_active' => false,
    ];

    if (storageDriver() === 'mysql') {
        ensureDataReady();
        databaseInsertComment($comment);
        unset($GLOBALS['portal_data_cache']);
        return $comment;
    }

    updateData(function (array &$data) use ($comment): void {
        if (!isset($data['comments']) || !is_array($data['comments'])) $data['comments'] = [];
        array_unshift($data['comments'], $comment);
    });
    return $comment;
}

/**
 * Cambiar el estado de moderación de un comentario o respuesta.
 */
function moderateComment(string $commentId, string $status): bool {
    $commentId = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($commentId));
    $status = strtolower(trim($status));
    if ($commentId === '' || !in_array($status, commentModerationStatuses(), true)) return false;

    if (storageDriver() === 'mysql') {
        ensureDataReady();
        $updated = databaseUpdateCommentStatus($commentId, $status);
        unset($GLOBALS['portal_data_cache']);
        return $updated;
    }

    return (bool)updateData(function (array &$data) use ($commentId, $status): bool {
        if (empty($data['comments']) || !is_array($data['comments'])) return false;
        foreach ($data['comments'] as &$comment) {
            if (($comment['id'] ?? '') !== $commentId) continue;
            $comment['status'] = $status;
            $comment['is_active'] = $status === 'published';
            unset($comment);
            return true;
        }
        unset($comment);
        return false;
    });
}

/**
 * Eliminar un comentario desde administración.
 */
function deleteComment(string $commentId): void {
    if (storageDriver() === 'mysql') {
        ensureDataReady();
        databaseDeleteComment($commentId);
        unset($GLOBALS['portal_data_cache']);
        return;
    }
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
    if (storageDriver() === 'mysql') {
        ensureDataReady();
        return databaseCanReplyToComment($commentId);
    }
    $data = readData();
    foreach (($data['comments'] ?? []) as $comment) {
        if (
            ($comment['id'] ?? '') === $commentId
            && normalizeCommentStatus((array)$comment) === 'published'
            && empty($comment['parent_id'])
        ) {
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
