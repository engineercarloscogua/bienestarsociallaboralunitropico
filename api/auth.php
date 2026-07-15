<?php
// api/auth.php — Logout (sin MySQL)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    requireLogin();
    requireValidCsrf();
    logoutAdmin();
    redirect(baseUrl() . '/admin/login.php');
}
http_response_code(400);
echo json_encode(['error' => 'Acción no válida.']);
