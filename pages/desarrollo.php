<?php
// pages/desarrollo.php — Desarrollo Profesional
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Desarrollo Profesional';
$page      = getPage('desarrollo');
$cards     = getCards('desarrollo');
$base      = baseUrl();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="content">

  <div class="page-hero">
    <div class="page-hero-content">
      <div class="hero-badge" style="margin-bottom:14px;background:rgba(0,107,91,0.10);border-color:rgba(0,107,91,0.24);color:#006B5B;">
        <?= icon('graduation-cap', '', 13) ?> Desarrollo Profesional
      </div>
      <h1><?= e($page['title'] ?? $pageTitle) ?></h1>
      <p><?= e($page['subtitle'] ?? 'Capacítate y crece profesionalmente con Unitrópico') ?></p>
    </div>
    <div class="page-hero-media">
      <img src="<?= $base ?>/assets/uploads/herramientas-liderazgo.png" alt="Desarrollo profesional">
    </div>
  </div>

  <div class="section-header">
    <h2 class="section-title">
      <span class="icon-badge" style="background:rgba(0,107,91,0.10);color:#006B5B;"><?= icon('trending-up', '', 20) ?></span>
      Rutas de Desarrollo
    </h2>
    <span class="badge" style="background:rgba(0,107,91,0.10);color:#006B5B;border-color:rgba(0,107,91,0.24);"><?= count($cards) ?> programas</span>
  </div>

  <div class="cards-grid">
    <?php foreach ($cards as $card):
      $link       = $card['link_url'] ? (str_starts_with($card['link_url'], 'http') ? $card['link_url'] : $base . '/' . ltrim($card['link_url'], '/')) : '#';
      $isExternal = str_starts_with($card['link_url'] ?? '', 'http');
      $colHex     = $card['color'] ?? '#1D4ED8';
      $r = hexdec(substr($colHex,1,2));
      $g = hexdec(substr($colHex,3,2));
      $b = hexdec(substr($colHex,5,2));
      $image = !empty($card['image_url']) ? $base . '/' . ltrim($card['image_url'], '/') : '';
    ?>
    <div class="service-card" tabindex="0" style="--card-color:<?= e($colHex) ?>">
      <?php if ($image): ?>
      <div class="card-image">
        <img src="<?= e($image) ?>" alt="<?= e($card['title']) ?>" loading="lazy">
        <div class="card-description-overlay">
          <p><?= e($card['description']) ?></p>
        </div>
      </div>
      <?php endif; ?>
      <div class="service-card-content">
        <div class="card-icon" style="background:rgba(<?= $r ?>,<?= $g ?>,<?= $b ?>,0.12);color:<?= e($colHex) ?>;">
          <?= icon($card['icon'] ?? 'star', '', 26) ?>
        </div>
        <div class="card-body">
          <h3><?= e($card['title']) ?></h3>
          <?php if (!$image): ?>
          <p><?= e($card['description']) ?></p>
          <?php endif; ?>
        </div>
        <?php if ($card['link_url'] && $card['link_url'] !== '#'): ?>
        <a href="<?= e($link) ?>"
           <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
           class="card-link"
           style="background:rgba(<?= $r ?>,<?= $g ?>,<?= $b ?>,0.12);color:<?= e($colHex) ?>;">
          <span><?= e($card['link_label'] ?? 'Ver mas') ?></span>
          <?= icon($isExternal ? 'external-link' : 'chevron-right', '', 15) ?>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if (empty($cards)): ?>
  <div style="text-align:center;padding:60px 20px;color:var(--text-muted);">
    <?= icon('graduation-cap', '', 40) ?>
    <p style="margin-top:12px;font-size:14px;">No hay programas configurados. <a href="<?= $base ?>/admin/cards.php" style="color:var(--accent);">Agregar desde el admin</a>.</p>
  </div>
  <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

