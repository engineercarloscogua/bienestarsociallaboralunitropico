<?php
// Autenticación del panel administrador sin base de datos.
require_once __DIR__ . '/functions.php';

function isSecureRequest(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function sendAdminSecurityHeaders(): void {
    if (headers_sent()) return;
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
}

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('unitropico_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isSecureRequest(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}
sendAdminSecurityHeaders();

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrfToken(?string $token): bool {
    return is_string($token) && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function requireValidCsrf(): void {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(419);
        exit('La sesión del formulario venció. Recarga la página e inténtalo nuevamente.');
    }
}

function isLoggedIn(): bool {
    if (empty($_SESSION['admin_logged_in'])) return false;

    $now = time();
    if (($now - (int)($_SESSION['admin_last_activity'] ?? 0)) > 7200) {
        logoutAdmin();
        return false;
    }

    $userAgentHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    if (!hash_equals($_SESSION['admin_user_agent'] ?? '', $userAgentHash)) {
        logoutAdmin();
        return false;
    }

    $creds = getAdminCredentials();
    if ((int)($_SESSION['admin_auth_version'] ?? 0) !== (int)($creds['auth_version'] ?? 1)) {
        logoutAdmin();
        return false;
    }

    $_SESSION['admin_last_activity'] = $now;
    return true;
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . getAdminLoginUrl());
        exit;
    }
}

function getAdminLoginUrl(): string {
    return baseUrl() . '/admin/login.php';
}

function loginRateKey(string $username): string {
    return clientSecurityKey(strtolower(trim($username)));
}

function loginAdmin(string $username, string $password, int &$retryAfter = 0): bool {
    $rateKey = loginRateKey($username);
    $retryAfter = consumeRateLimit('admin_login', $rateKey, 5, 900, 1);
    if ($retryAfter > 0) return false;

    $creds = getAdminCredentials();
    $storedUsername = (string)($creds['username'] ?? '');
    $storedHash = (string)($creds['password_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidi');

    $validUser = $storedUsername !== '' && hash_equals($storedUsername, $username);
    $validPassword = password_verify($password, $storedHash);
    if (!$validUser || !$validPassword) return false;

    clearRateLimit('admin_login', $rateKey);
    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_user'] = $storedUsername;
    $_SESSION['admin_auth_version'] = (int)($creds['auth_version'] ?? 1);
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['admin_user_agent'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}

function verifyCurrentAdminPassword(string $password): bool {
    $creds = getAdminCredentials();
    return !empty($creds['password_hash']) && password_verify($password, $creds['password_hash']);
}

function refreshAdminSessionVersion(int $version): void {
    $_SESSION['admin_auth_version'] = $version;
    session_regenerate_id(true);
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function logoutAdmin(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool)($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
}

function currentAdminName(): string {
    return $_SESSION['admin_user'] ?? 'Administrador';
}

function adminLogoutForm(string $base): string {
    return '<form method="post" action="' . e($base) . '/api/auth.php" class="admin-logout-form">'
        . csrfField()
        . '<input type="hidden" name="action" value="logout">'
        . '<button type="submit" class="admin-logout-button">' . icon('log-out', '', 14) . ' Cerrar Sesión</button>'
        . '</form>';
}
