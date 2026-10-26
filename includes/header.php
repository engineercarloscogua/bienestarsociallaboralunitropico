<?php
// ============================================================
// includes/header.php — Encabezado y navegación compartidos del portal
// ============================================================
require_once __DIR__ . '/functions.php';

$pages    = getPages();
$programSlugs = array_keys(readData()['program_pages'] ?? []);
$base     = baseUrl();
$siteName = getConfig('site_title', 'Bienestar Social Laboral');
$siteSubtitle = getConfig('site_subtitle', 'Bienestar Social Laboral - Oficina de Talento Humano');
$cssVersion = filemtime(__DIR__ . '/../assets/css/main.css');
$faviconVersion = filemtime(__DIR__ . '/../assets/uploads/logo-pestana-pagina.png');
$analyticsRequestToken = issuePublicRequestToken('analytics');
if (!headers_sent()) {
  header('Cache-Control: private, no-store, max-age=0');
  header('Pragma: no-cache');
}

// Detectar página activa
$currentScript = basename($_SERVER['SCRIPT_FILENAME']);
$currentDir    = basename(dirname($_SERVER['SCRIPT_FILENAME']));

// Íconos de navegación por slug
$navIcons = [
  'inicio'         => 'home',
  'talento-humano' => 'users',
  'desarrollo'     => 'graduation-cap',
  'bienestar'      => 'heart',
  'calendario'     => 'calendar',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e(getConfig('site_description', 'Portal de Bienestar Social Laboral — Unitrópico')) ?>">
  <title><?= e($pageTitle ?? $siteName) ?> — Unitrópico</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@600;700;800;900&family=Raleway:wght@400;600&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/main.css?v=<?= $cssVersion ?>">
  <link rel="icon" type="image/png" href="<?= $base ?>/assets/uploads/logo-pestana-pagina.png?v=<?= $faviconVersion ?>">
</head>
<body>

<div class="institutional-bar" role="banner">
  <div class="institutional-bar-inner">
    <a class="institutional-brand" href="https://www.gov.co/" target="_blank" rel="noopener noreferrer" aria-label="Ir al Portal Único del Estado Colombiano GOV.CO">
      <img src="<?= $base ?>/assets/govco-logo-2026.svg" alt="GOV.CO">
    </a>
    <nav class="institutional-links" aria-label="Accesos institucionales">
      <a class="institutional-link" href="https://unitropico.edu.co/participa/">
        Participa
      </a>
      <a class="institutional-link" href="https://unitropico.edu.co/transparencia-y-acceso-a-la-informacion-publica-de-unitropico/" target="_blank" rel="noopener noreferrer">
        Transparencia
      </a>
      <a class="institutional-link" href="https://unitropico.edu.co/conocenos-quienes-somos-y-filosofia-institucional-de-unitropico/normatividad-leyes-y-decretos-universitarios-de-unitropico/universidad-unitropico-transformacion-y-oficializacion-universitaria/" target="_blank" rel="noopener noreferrer">
        Normatividad
      </a>
      <a class="institutional-link" href="https://unitropico.edu.co/#servicio-ciudadania">
        Atención al Usuario
      </a>
      <a class="institutional-link" href="https://unitropico.edu.co/formulacion-ptep-y-gestion-del-riesgos-de-unitropico/">
        Formulación PTEP
      </a>
    </nav>
  </div>
</div>

<canvas id="particles-canvas"></canvas>
<div class="portal-menu-overlay" id="sidebar-overlay"></div>

<!-- ── NAVEGACIÓN PRINCIPAL HORIZONTAL ── -->
<header class="portal-header">
  <div class="portal-header-main">
    <a class="portal-brand" href="<?= $base ?>/index.php" aria-label="Ir al inicio de Bienestar Social Laboral">
      <div class="brand-logo">
        <img src="<?= $base ?>/assets/uploads/escudo-universidad.png" alt="Escudo Unitrópico">
      </div>
      <div class="brand-text">
        <h1>Unitrópico</h1>
        <span><?= e($siteSubtitle) ?></span>
      </div>
    </a>

    <div class="portal-header-actions">
      <a href="<?= $base ?>/admin/dashboard.php" class="btn-admin-link">
        <?= icon('settings', '', 15) ?>
        <span>Panel Admin</span>
      </a>
      <button class="btn-hamburger" id="btn-hamburger" type="button" aria-label="Abrir menú principal" aria-controls="sidebar" aria-expanded="false">
        <?= icon('menu', '', 22) ?>
      </button>
    </div>
  </div>

  <nav class="portal-main-nav" id="sidebar" aria-label="Navegación principal">
    <div class="portal-main-nav-inner">
      <?php foreach ($pages as $navPage):
        $slug  = $navPage['slug'];
        $href  = ($slug === 'inicio')
               ? $base . '/index.php'
               : (in_array($slug, $programSlugs, true)
                    ? $base . '/pages/programa.php?slug=' . rawurlencode($slug)
                    : $base . '/pages/' . $slug . '.php');
        $ico   = $navIcons[$slug] ?? 'star';
        $isActive = ($currentScript === 'index.php' && $slug === 'inicio')
                 || ($currentScript === $slug . '.php')
                 || ($currentScript === 'programa.php' && ($_GET['slug'] ?? '') === $slug);
      ?>
      <a href="<?= e($href) ?>" class="nav-item<?= $isActive ? ' active' : '' ?>"<?= $isActive ? ' aria-current="page"' : '' ?>>
        <?= icon($ico, '', 18) ?>
        <span><?= e($navPage['title']) ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </nav>
</header>

<div class="layout">

  <!-- ── MAIN ── -->
  <div class="main">
    <!-- Ruta de navegación -->
    <header class="topbar">
      <div class="topbar-left">
        <nav class="breadcrumb">
          <a href="<?= $base ?>/index.php">Inicio</a>
          <?php if (!empty($pageTitle) && $pageTitle !== 'Inicio'): ?>
            <?= icon('chevron-right', '', 14) ?>
            <span class="current"><?= e($pageTitle) ?></span>
          <?php endif; ?>
        </nav>
      </div>
    </header>

    <!-- Content starts here — each page closes .main and .layout -->
