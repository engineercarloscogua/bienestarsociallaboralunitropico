<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

$base = baseUrl();
$enabled = adminRecoveryEnabled();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCsrf();

    if (!isSameOriginRequest()) {
        http_response_code(403);
        $error = 'Solicitud no autorizada.';
    } else {
        $retryAfter = consumeRateLimit('admin_recovery', clientSecurityKey('admin-recovery'), 5, 900, 2);
        if ($retryAfter > 0) {
            $minutes = max(1, (int)ceil($retryAfter / 60));
            $error = "Demasiados intentos. Espera {$minutes} minuto(s).";
        } elseif (!$enabled) {
            $error = 'La recuperación administrativa no está habilitada.';
        } else {
            $token = trim((string)($_POST['recovery_token'] ?? ''));
            $newPassword = (string)($_POST['new_password'] ?? '');
            $confirmation = (string)($_POST['confirm_password'] ?? '');
            $passwordError = passwordPolicyError($newPassword);

            if ($passwordError !== '') {
                $error = $passwordError;
            } elseif (!hash_equals($newPassword, $confirmation)) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                try {
                    recoverAdminPassword($newPassword, $token);
                    clearRateLimit('admin_recovery', clientSecurityKey('admin-recovery'));
                    $message = 'Contraseña restablecida. El token quedó inutilizado; elimínalo ahora del archivo privado.';
                } catch (Throwable $exception) {
                    $error = 'No fue posible validar el token o ya fue utilizado.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Recuperar acceso — Panel Admin Bienestar</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
  <meta name="robots" content="noindex,nofollow">
</head>
<body>
<div class="login-page">
  <div class="login-bg-glow"></div>
  <div class="login-card">
    <div class="login-logo">U</div>
    <h1>Recuperar acceso</h1>
    <p>Bienestar Social Laboral — Unitrópico</p>

    <?php if ($message): ?>
    <div class="alert alert-success"><?= icon('lock','',16) ?> <?= e($message) ?></div>
    <?php elseif ($error): ?>
    <div class="alert alert-error"><?= icon('x','',16) ?> <?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$enabled): ?>
      <div class="alert alert-error">La recuperación está deshabilitada. Configura temporalmente <code>admin_recovery_token</code> en el archivo privado del servidor.</div>
    <?php elseif (!$message): ?>
    <form class="login-form" method="POST" autocomplete="off">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label" for="recovery_token">Token privado de recuperación</label>
        <input id="recovery_token" name="recovery_token" type="password" class="form-input" required autocomplete="off">
      </div>
      <div class="form-group">
        <label class="form-label" for="new_password">Nueva contraseña</label>
        <input id="new_password" name="new_password" type="password" class="form-input" required minlength="14" autocomplete="new-password">
        <span class="form-hint">Mínimo 14 caracteres con mayúsculas, minúsculas, número y símbolo.</span>
      </div>
      <div class="form-group">
        <label class="form-label" for="confirm_password">Confirmar contraseña</label>
        <input id="confirm_password" name="confirm_password" type="password" class="form-input" required minlength="14" autocomplete="new-password">
      </div>
      <button type="submit" class="btn-login">Restablecer contraseña</button>
    </form>
    <?php endif; ?>

    <div class="divider"></div>
    <p style="font-size:11px;color:var(--text-m);text-align:center;">
      <a href="<?= $base ?>/admin/login.php" style="color:var(--accent);">← Volver al acceso</a>
    </p>
  </div>
</div>
</body>
</html>

