<?php
// admin/login.php — Login del Panel Administrador
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (isLoggedIn()) redirect('../admin/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'La sesión del formulario venció. Recarga la página e inténtalo nuevamente.';
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = $_POST['password'] ?? '';
        $retryAfter = 0;
        if (loginAdmin($user, $pass, $retryAfter)) {
            redirect('../admin/dashboard.php');
        } elseif ($retryAfter > 0) {
            $minutes = max(1, (int)ceil($retryAfter / 60));
            $error = "Demasiados intentos. Espera {$minutes} minuto(s) antes de volver a intentar.";
        } else {
            $error = 'Usuario o contraseña incorrectos. Verifica tus credenciales.';
        }
    }
}

$base = baseUrl();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — Bienestar Social Laboral</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
  <meta name="robots" content="noindex,nofollow">
</head>
<body>
<div class="login-page">
  <div class="login-bg-glow"></div>

  <div class="login-card">
    <div class="login-logo">U</div>
    <h1>Panel Administrador</h1>
    <p>Bienestar Social Laboral — Unitrópico</p>

    <?php if ($error): ?>
    <div class="alert alert-error">
      <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <?= e($error) ?>
    </div>
    <?php endif; ?>

    <form class="login-form" method="POST" autocomplete="on">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label" for="username">Usuario</label>
        <div class="input-group">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          <input id="username" name="username" type="text" class="form-input input-icon"
                 placeholder="admin" value="<?= e($_POST['username'] ?? '') ?>"
                 required autocomplete="username">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password">Contraseña</label>
        <div class="input-group">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <input id="password" name="password" type="password" class="form-input input-icon"
                 placeholder="••••••••" required autocomplete="current-password">
        </div>
      </div>

      <button type="submit" class="btn-login">Iniciar Sesión</button>
    </form>

    <div class="divider"></div>
    <p style="font-size:11px;color:var(--text-m);text-align:center;">
      <a href="<?= $base ?>/index.php" style="color:var(--accent);">← Volver al portal</a>
    </p>
    <?php if (adminRecoveryEnabled()): ?>
    <p style="font-size:11px;color:var(--text-m);text-align:center;margin-top:8px;">
      <a href="<?= $base ?>/admin/recover.php" style="color:var(--accent);">Recuperar acceso administrativo</a>
    </p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
