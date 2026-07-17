<?php
// admin/dashboard.php — Panel de Control Principal
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base      = baseUrl();
$siteName  = getConfig('site_title', 'Bienestar Social Laboral');
$adminName = currentAdminName();

// Estadísticas desde el almacenamiento activo.
$allCards   = getAllCards();
$totalCards = count(array_filter($allCards, fn($c) => $c['is_active'] ?? true));
$totalPages = count(array_filter(readData()['pages'] ?? [], fn($p) => $p['is_active'] ?? true));
$analytics  = getAnalyticsSummary($_GET['analytics_month'] ?? null);
$monthNames = [
    '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
    '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
    '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
];
$monthParts = explode('-', $analytics['month']);
$analyticsMonthLabel = ($monthNames[$monthParts[1] ?? ''] ?? $analytics['month']) . ' ' . ($monthParts[0] ?? '');
$maxDayVisits = max($analytics['days'] ?: [0]);

// Handle config save
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_config'])) {
    $keys = ['site_title','site_subtitle','hero_title','hero_subtitle','footer_email','google_calendar_id'];
    $newConfig = [];
    foreach ($keys as $key) {
        if (isset($_POST[$key])) $newConfig[$key] = trim($_POST[$key]);
    }
    setConfigs($newConfig);
    // Handle password change
    if (!empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $passwordError = passwordPolicyError($_POST['new_password']);
            if ($passwordError) {
                $msg = 'ERROR: ' . $passwordError;
            } else {
                $newVersion = updateAdminPassword($_POST['new_password']);
                refreshAdminSessionVersion($newVersion);
                $msg = 'Contraseña actualizada correctamente.';
            }
        } else {
            $msg = 'ERROR: Las contraseñas no coinciden.';
        }
    }
    if (!$msg) $msg = 'Configuración guardada correctamente.';
}

// Reload config after save
$heroTitle    = getConfig('hero_title',           'Bienvenidos a Bienestar Social Laboral');
$heroSubtitle = getConfig('hero_subtitle',        'Impulsamos tu bienestar y crecimiento profesional en Unitrópico');
$siteTitle    = getConfig('site_title',           'Bienestar Social Laboral');
$siteSub      = getConfig('site_subtitle',        'Bienestar Social Laboral - Oficina de Talento Humano');
$footerEmail  = getConfig('footer_email',         'psicologiaorganizacional@unitropico.edu.co,climaorganizacional@unitropico.edu.co');
$calId        = getConfig('google_calendar_id',   '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Panel Admin Bienestar</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
  <meta name="robots" content="noindex,nofollow">
</head>
<body>
<div class="admin-layout">

  <!-- SIDEBAR -->
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <div class="logo">U</div>
      <div><h1>Panel Admin</h1><span>Unitrópico</span></div>
    </div>
    <nav class="admin-nav">
      <span class="admin-nav-label">Gestión</span>
      <a href="<?= $base ?>/admin/dashboard.php" class="admin-nav-item active">
        <?= icon('home','',16) ?> Dashboard
      </a>
      <a href="<?= $base ?>/admin/cards.php" class="admin-nav-item">
        <?= icon('layers','',16) ?> Tarjetas / Servicios
      </a>
      <a href="<?= $base ?>/admin/pages.php" class="admin-nav-item">
        <?= icon('layers','',16) ?> Subpáginas
      </a>
      <a href="<?= $base ?>/admin/comments.php" class="admin-nav-item">
        <?= icon('heart','',16) ?> Comentarios
      </a>
      <a href="<?= $base ?>/admin/media.php" class="admin-nav-item">
        <?= icon('image','',16) ?> Imágenes
      </a>
      <a href="<?= $base ?>/admin/database.php" class="admin-nav-item">
        <?= icon('settings','',16) ?> Base de datos
      </a>
      <span class="admin-nav-label">Portal</span>
      <a href="<?= $base ?>/index.php" class="admin-nav-item" target="_blank">
        <?= icon('external-link','',16) ?> Ver Sitio
      </a>
    </nav>
    <div class="admin-sidebar-footer">
      <div style="font-size:11px;color:var(--text-m);padding:4px 8px;margin-bottom:4px;"><?= e($adminName) ?></div>
      <?= adminLogoutForm($base) ?>
    </div>
  </aside>

  <!-- MAIN -->
  <div class="admin-main">
    <div class="admin-topbar">
      <h2>Dashboard</h2>
      <div class="admin-topbar-right">
        <a href="<?= $base ?>/index.php" target="_blank" class="btn btn-outline btn-sm">
          <?= icon('external-link','',13) ?> Ver sitio
        </a>
      </div>
    </div>

    <div class="admin-content">

      <?php if ($msg): ?>
      <div class="alert <?= str_starts_with($msg,'ERROR') ? 'alert-error' : 'alert-success' ?>">
        <?= str_starts_with($msg,'ERROR') ? icon('x','',15) : icon('save','',15) ?>
        <?= e($msg) ?>
      </div>
      <?php endif; ?>

      <!-- Stats -->
      <div class="admin-stats">
        <div class="stat-widget">
          <div class="stat-widget-icon" style="background:rgba(0,201,167,0.12);color:#00C9A7;"><?= icon('layers','',20) ?></div>
          <div class="stat-widget-info"><strong><?= $totalCards ?></strong><span>Tarjetas Activas</span></div>
        </div>
        <div class="stat-widget">
          <div class="stat-widget-icon" style="background:rgba(59,130,246,0.12);color:#60A5FA;"><?= icon('layers','',20) ?></div>
          <div class="stat-widget-info"><strong><?= $totalPages ?></strong><span>Páginas Activas</span></div>
        </div>
        <div class="stat-widget">
          <div class="stat-widget-icon" style="background:rgba(245,158,11,0.12);color:#F59E0B;"><?= icon('image','',20) ?></div>
          <div class="stat-widget-info">
            <strong><?= count(glob(__DIR__ . '/../assets/uploads/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: []) ?></strong>
            <span>Imágenes Subidas</span>
          </div>
        </div>
        <div class="stat-widget">
          <div class="stat-widget-icon" style="background:rgba(139,92,246,0.12);color:#A78BFA;"><?= icon('user','',20) ?></div>
          <div class="stat-widget-info"><strong>1</strong><span>Administrador</span></div>
        </div>
      </div>

      <!-- Analítica del portal -->
      <div class="widget analytics-widget" style="margin-bottom:20px;">
        <div class="widget-header">
          <h3 class="widget-title">
            <span class="widget-title-icon" style="background:rgba(0,107,91,0.10);color:var(--primary);"><?= icon('bar-chart-2','',16) ?></span>
            Visitas del portal
          </h3>
          <form method="GET" class="analytics-month-form">
            <label for="analytics_month">Mes</label>
            <select id="analytics_month" name="analytics_month" class="form-select" onchange="this.form.submit()">
              <?php $monthsForSelect = $analytics['months'] ?: [$analytics['month']]; ?>
              <?php foreach ($monthsForSelect as $month): ?>
              <?php $parts = explode('-', $month); $label = ($monthNames[$parts[1] ?? ''] ?? $month) . ' ' . ($parts[0] ?? ''); ?>
              <option value="<?= e($month) ?>" <?= $month === $analytics['month'] ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>

        <div class="analytics-summary">
          <div class="analytics-card">
            <span>Visitas en <?= e($analyticsMonthLabel) ?></span>
            <strong><?= (int)$analytics['visits'] ?></strong>
          </div>
          <div class="analytics-card">
            <span>Visitantes aproximados</span>
            <strong><?= (int)$analytics['visitors'] ?></strong>
          </div>
          <div class="analytics-card">
            <span>Secciones visitadas</span>
            <strong><?= count($analytics['sections']) ?></strong>
          </div>
        </div>

        <div class="analytics-grid">
          <div class="analytics-panel">
            <h4>Movimiento por día</h4>
            <?php if (empty($analytics['days'])): ?>
              <p class="analytics-empty">Aún no hay visitas registradas para este mes.</p>
            <?php else: ?>
              <div class="analytics-day-chart">
                <?php foreach ($analytics['days'] as $day => $count): ?>
                <?php $height = $maxDayVisits > 0 ? max(10, round(($count / $maxDayVisits) * 100)) : 10; ?>
                <div class="analytics-day" title="<?= e(date('d/m/Y', strtotime($day))) ?>: <?= (int)$count ?> visitas">
                  <span style="height:<?= (int)$height ?>%"></span>
                  <small><?= e(date('d', strtotime($day))) ?></small>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="analytics-panel">
            <h4>Secciones más visitadas</h4>
            <?php if (empty($analytics['sections'])): ?>
              <p class="analytics-empty">Cuando los visitantes naveguen el portal, aquí verás sus secciones favoritas.</p>
            <?php else: ?>
              <div class="analytics-section-list">
                <?php foreach (array_slice($analytics['sections'], 0, 8) as $section): ?>
                <?php $percent = $analytics['visits'] > 0 ? round(($section['visits'] / $analytics['visits']) * 100) : 0; ?>
                <div class="analytics-section-row">
                  <div>
                    <strong><?= e($section['name']) ?></strong>
                    <span><?= (int)$section['visitors'] ?> visitantes · <?= (int)$section['visits'] ?> visitas</span>
                  </div>
                  <div class="analytics-section-bar" aria-hidden="true">
                    <span style="width:<?= (int)$percent ?>%"></span>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="widget" style="margin-bottom:20px;">
        <div class="widget-header">
          <h3 class="widget-title"><?= icon('star','',16) ?> Accesos Rápidos</h3>
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <a href="<?= $base ?>/admin/cards.php?action=new" class="btn btn-accent btn-sm">
            <?= icon('plus','',14) ?> Nueva Tarjeta
          </a>
          <a href="<?= $base ?>/admin/pages.php?action=new" class="btn btn-outline btn-sm">
            <?= icon('plus','',14) ?> Nueva Subpágina
          </a>
          <a href="<?= $base ?>/admin/media.php" class="btn btn-outline btn-sm">
            <?= icon('image','',14) ?> Subir Imagen
          </a>
          <a href="<?= $base ?>/admin/comments.php" class="btn btn-outline btn-sm">
            <?= icon('heart','',14) ?> Moderar Comentarios
          </a>
          <a href="<?= $base ?>/index.php" target="_blank" class="btn btn-outline btn-sm">
            <?= icon('external-link','',14) ?> Ver Portal
          </a>
        </div>
      </div>

      <!-- Configuración del sitio -->
      <div class="widget">
        <div class="widget-header">
          <h3 class="widget-title">
            <span class="widget-title-icon" style="background:rgba(0,201,167,0.12);color:var(--accent);"><?= icon('settings','',16) ?></span>
            Configuración del Sitio
          </h3>
        </div>

        <form method="POST">
          <?= csrfField() ?>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="site_title">Nombre del Sitio</label>
              <input id="site_title" name="site_title" type="text" class="form-input"
                     value="<?= e($siteTitle) ?>" placeholder="Bienestar Social Laboral">
            </div>
            <div class="form-group">
              <label class="form-label" for="site_subtitle">Subtítulo del Sitio</label>
              <input id="site_subtitle" name="site_subtitle" type="text" class="form-input"
                     value="<?= e($siteSub) ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="hero_title">Título Principal del Hero</label>
              <input id="hero_title" name="hero_title" type="text" class="form-input"
                     value="<?= e($heroTitle) ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="hero_subtitle">Subtítulo del Hero</label>
              <input id="hero_subtitle" name="hero_subtitle" type="text" class="form-input"
                     value="<?= e($heroSubtitle) ?>">
            </div>
            <div class="form-group">
              <label class="form-label" for="footer_email">Correos de Contacto (footer)</label>
              <input id="footer_email" name="footer_email" type="text" class="form-input"
                     value="<?= e($footerEmail) ?>">
              <span class="form-hint">Puedes separar varios correos con coma.</span>
            </div>
            <div class="form-group">
              <label class="form-label" for="google_calendar_id">
                ID del Google Calendar
                <span style="font-weight:400;color:var(--text-m);margin-left:4px;">(ej: abc@group.calendar.google.com)</span>
              </label>
              <input id="google_calendar_id" name="google_calendar_id" type="text" class="form-input"
                     value="<?= e($calId) ?>" placeholder="tumail@group.calendar.google.com">
              <span class="form-hint">Copia el ID desde Google Calendar → Ajustes del calendario → ID de calendario</span>
            </div>
          </div>

          <div class="divider"></div>

          <h4 style="font-size:13px;font-weight:700;margin-bottom:14px;color:var(--text-p);">
            <?= icon('lock','',14) ?> Cambiar Contraseña (opcional)
          </h4>
          <div class="form-grid">
            <div class="form-group">
              <label class="form-label" for="new_password">Nueva Contraseña</label>
              <input id="new_password" name="new_password" type="password" class="form-input"
                     placeholder="Dejar vacío para no cambiar">
            </div>
            <div class="form-group">
              <label class="form-label" for="confirm_password">Confirmar Contraseña</label>
              <input id="confirm_password" name="confirm_password" type="password" class="form-input"
                     placeholder="Repetir nueva contraseña">
            </div>
          </div>

          <div style="margin-top:20px;display:flex;gap:10px;">
            <button type="submit" name="save_config" class="btn btn-accent">
              <?= icon('save','',15) ?> Guardar Configuración
            </button>
          </div>
        </form>
      </div>

    </div><!-- /.admin-content -->
  </div><!-- /.admin-main -->
</div>

<script src="<?= $base ?>/assets/js/admin.js"></script>
</body>
</html>
