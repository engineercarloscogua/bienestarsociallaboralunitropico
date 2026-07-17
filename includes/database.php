<?php

/**
 * Configuración y conexión PDO.
 *
 * Las credenciales nunca deben quedar dentro del repositorio. El portal busca,
 * en orden, APP_DB_CONFIG, ../private/bienestar-database.php (fuera de
 * public_html) y config/database.local.php para desarrollo local.
 */

function databaseConfigCandidates(): array {
    $projectRoot = dirname(__DIR__);
    $configuredPath = trim((string)getenv('APP_DB_CONFIG'));

    return array_values(array_filter([
        $configuredPath !== '' ? $configuredPath : null,
        dirname($projectRoot) . '/private/bienestar-database.php',
        $projectRoot . '/config/database.local.php',
    ]));
}

function databaseConfigPath(): ?string {
    foreach (databaseConfigCandidates() as $candidate) {
        if (is_file($candidate)) return $candidate;
    }
    return null;
}

function databaseConfig(): ?array {
    static $loaded = false;
    static $config = null;

    if ($loaded) return $config;
    $loaded = true;

    $path = databaseConfigPath();
    if ($path === null) return null;

    $loadedConfig = require $path;
    if (!is_array($loadedConfig)) {
        throw new RuntimeException('El archivo privado de base de datos debe retornar un arreglo PHP.');
    }

    $config = $loadedConfig;
    return $config;
}

function storageDriver(): string {
    $environmentDriver = strtolower(trim((string)getenv('APP_STORAGE_DRIVER')));
    $config = databaseConfig();
    $driver = $environmentDriver !== ''
        ? $environmentDriver
        : strtolower(trim((string)($config['storage'] ?? 'json')));

    if (!in_array($driver, ['json', 'mysql'], true)) {
        throw new RuntimeException('APP_STORAGE_DRIVER/storage debe ser json o mysql.');
    }
    return $driver;
}

function databaseConnection(): PDO {
    static $connection = null;
    if ($connection instanceof PDO) return $connection;

    $config = databaseConfig();
    if ($config === null) {
        throw new RuntimeException('No se encontró la configuración privada de MariaDB.');
    }

    foreach (['host', 'database', 'username'] as $required) {
        if (trim((string)($config[$required] ?? '')) === '') {
            throw new RuntimeException('Falta el valor ' . $required . ' en la configuración privada de MariaDB.');
        }
    }

    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('La extensión PHP pdo_mysql no está habilitada.');
    }

    $host = trim((string)$config['host']);
    $port = max(1, (int)($config['port'] ?? 3306));
    $database = trim((string)$config['database']);
    $charset = trim((string)($config['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

    $connection = new PDO(
        $dsn,
        (string)$config['username'],
        (string)($config['password'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]
    );

    return $connection;
}

function databaseSchemaIsReady(?PDO $pdo = null): bool {
    try {
        $pdo = $pdo ?? databaseConnection();
        $statement = $pdo->query("SELECT COUNT(*) FROM storage_locks WHERE lock_name = 'content'");
        return (int)$statement->fetchColumn() === 1;
    } catch (Throwable $error) {
        return false;
    }
}

