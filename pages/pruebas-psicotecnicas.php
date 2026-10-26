<?php
// pages/pruebas-psicotecnicas.php — Próximo módulo de pruebas psicotécnicas
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Pruebas Psicotécnicas';
$base = baseUrl();

require_once __DIR__ . '/../includes/header.php';
?>

<main class="content">

  <section class="psychometric-coming-soon" aria-labelledby="psychometric-title">
    <div class="psychometric-coming-soon-copy">
      <span class="psychometric-status">
        <?= icon('settings', '', 16) ?>
        Módulo en construcción
      </span>
      <p class="section-eyebrow">Gestión del Talento Humano</p>
      <h1 id="psychometric-title">Pruebas Psicotécnicas</h1>
      <p class="psychometric-lead">
        Próximamente, las personas que se encuentren en proceso de vinculación con Unitrópico podrán realizar aquí sus pruebas psicotécnicas de manera segura, clara y guiada, antes de formalizar su ingreso a la institución.
      </p>
      <p class="psychometric-note">
        El equipo de Talento Humano informará oportunamente cuándo estará disponible este servicio y cómo acceder al proceso.
      </p>
      <a class="detail-card-link" href="<?= $base ?>/pages/talento-humano.php">
        <?= icon('chevron-right', '', 15) ?>
        Volver a Gestión del Talento Humano
      </a>
    </div>

    <figure class="psychometric-coming-soon-image">
      <img
        src="<?= $base ?>/assets/uploads/pruebas-psicotecnicas-construccion.png"
        alt="Ilustración de una plataforma de pruebas psicotécnicas en construcción"
      >
    </figure>
  </section>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
