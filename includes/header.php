<?php
// ============================================================
// includes/header.php — Header/Sidebar compartido del portal
// ============================================================
require_once __DIR__ . '/functions.php';

$pages    = getPages();
$programSlugs = array_keys(readData()['program_pages'] ?? []);
$base     = baseUrl();
$siteName = getConfig('site_title', 'Bienestar Social Laboral');
$siteSubtitle = getConfig('site_subtitle', 'Bienestar Social Laboral - Oficina de Talento Humano');
$footerEmails = array_filter(array_map('trim', explode(',', getConfig('footer_email', 'psicologiaorganizacional@unitropico.edu.co,climaorganizacional@unitropico.edu.co'))));
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
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@600;700;800;900&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/main.css?v=<?= $cssVersion ?>">
  <link rel="icon" type="image/png" href="<?= $base ?>/assets/uploads/logo-pestana-pagina.png?v=<?= $faviconVersion ?>">
</head>
<body>

<div class="institutional-bar" role="banner">
  <div class="institutional-bar-inner">
    <span class="institutional-brand" aria-label="Gobierno de Colombia">
      <img src="<?= $base ?>/assets/uploads/govco-logo.png" alt="GOV.CO">
    </span>
    <nav class="institutional-links" aria-label="Accesos institucionales">
      <a class="institutional-link institutional-home-link" href="https://unitropico.edu.co/" target="_blank" rel="noopener noreferrer" aria-label="Ir al sitio oficial de Unitrópico" title="Ir al sitio oficial de Unitrópico">
        <?= icon('home', '', 15) ?>
        <span>Unitrópico</span>
      </a>
      <a class="institutional-link" href="https://unitropico.edu.co/transparencia-y-acceso-a-la-informacion-publica-de-unitropico/" target="_blank" rel="noopener noreferrer">
        Transparencia y acceso a la información pública
      </a>
      <a class="institutional-link" href="https://unitropico.edu.co/conocenos-quienes-somos-y-filosofia-institucional-de-unitropico/normatividad-leyes-y-decretos-universitarios-de-unitropico/universidad-unitropico-transformacion-y-oficializacion-universitaria/" target="_blank" rel="noopener noreferrer">
        Transformación y oficialización universitaria
      </a>
    </nav>
  </div>
</div>

<canvas id="particles-canvas"></canvas>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="layout">

  <!-- ── SIDEBAR ── -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-logo">
        <img src="<?= $base ?>/assets/uploads/escudo-universidad.png" alt="Escudo Unitrópico">
      </div>
      <div class="brand-text">
        <h1>Unitrópico</h1>
        <span><?= e($siteSubtitle) ?></span>
      </div>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-label">Portal</span>
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
      <a href="<?= e($href) ?>" class="nav-item<?= $isActive ? ' active' : '' ?>">
        <?= icon($ico, '', 18) ?>
        <?= e($navPage['title']) ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="sidebar-footer-info">
        <p>Portal de Bienestar</p>
        <?php foreach ($footerEmails as $mail): ?>
          <p><?= e($mail) ?></p>
        <?php endforeach; ?>
      </div>
    </div>
  </aside>

  <!-- ── MAIN ── -->
  <div class="main">
    <!-- Top Bar -->
    <header class="topbar">
      <div class="topbar-left">
        <button class="btn-hamburger" id="btn-hamburger" aria-label="Menú">
          <?= icon('menu', '', 22) ?>
        </button>
        <nav class="breadcrumb">
          <a href="<?= $base ?>/index.php">Inicio</a>
          <?php if (!empty($pageTitle) && $pageTitle !== 'Inicio'): ?>
            <?= icon('chevron-right', '', 14) ?>
            <span class="current"><?= e($pageTitle) ?></span>
          <?php endif; ?>
        </nav>
      </div>
      <div class="topbar-right">
        <a href="<?= $base ?>/admin/dashboard.php" class="btn-admin-link">
          <?= icon('settings', '', 15) ?>
          Panel Admin
        </a>
      </div>
    </header>

    <!-- Content starts here — each page closes .main and .layout -->
