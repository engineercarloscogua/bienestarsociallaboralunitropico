<?php
// pages/talento-humano.php — Gestión del Talento Humano
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Gestión del Talento Humano';
$page      = getPage('talento-humano');
$cards     = getCards('talento-humano');
$base      = baseUrl();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="content">

  <!-- Hero de la página -->
  <div class="page-hero">
    <div class="page-hero-content">
      <div class="hero-badge" style="margin-bottom:14px;">
        <?= icon('users', '', 13) ?> Talento Humano
      </div>
      <h1><?= e($page['title'] ?? $pageTitle) ?></h1>
      <p><?= e($page['subtitle'] ?? 'Programas de estímulos, incentivos y desarrollo del talento') ?></p>
    </div>
    <div class="page-hero-media">
      <img src="<?= $base ?>/assets/uploads/talento-humano.png" alt="Talento Humano Unitrópico">
    </div>
  </div>

  <!-- Sección de tarjetas -->
  <div class="section-header">
    <h2 class="section-title">
      <span class="icon-badge" style="background:rgba(0,169,147,0.12);color:var(--accent);"><?= icon('layers', '', 20) ?></span>
      Programas y Servicios
    </h2>
    <span class="badge badge-accent"><?= count($cards) ?> programas</span>
  </div>

  <div class="cards-grid">
    <?php foreach ($cards as $i => $card):
      $link       = $card['link_url'] ? (str_starts_with($card['link_url'], 'http') ? $card['link_url'] : $base . '/' . ltrim($card['link_url'], '/')) : '#';
      $isExternal = str_starts_with($card['link_url'] ?? '', 'http');
      $colHex     = $card['color'] ?? '#006056';

      // Generate RGBA from hex for backgrounds
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
    <?= icon('layers', '', 40) ?>
    <p style="margin-top:12px;font-size:14px;">No hay programas configurados aún. <a href="<?= $base ?>/admin/cards.php" style="color:var(--accent);">Agregar desde el admin</a>.</p>
  </div>
  <?php endif; ?>

  <!-- Nota informativa -->
  <div style="background:rgba(0,201,167,0.06);border:1px solid rgba(0,201,167,0.15);border-radius:var(--radius-md);padding:20px 24px;margin-top:12px;">
    <p style="font-size:13px;color:var(--text-secondary);display:flex;align-items:flex-start;gap:10px;">
      <?= icon('sparkles', '', 16) ?>
      <span>Para mayor información sobre cualquiera de estos programas, comunícate con el área de <strong style="color:var(--accent);">Talento Humano</strong> de Unitrópico. Estamos aquí para acompañarte en cada etapa de tu desarrollo profesional.</span>
    </p>
  </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
