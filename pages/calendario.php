<?php
// pages/calendario.php — Calendario de Actividades
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Calendario de Actividades';
$page      = getPage('calendario');
$base      = baseUrl();
$calId     = getConfig('google_calendar_id', '');

require_once __DIR__ . '/../includes/header.php';
?>

<main class="content">

  <div class="page-hero">
    <div class="page-hero-content">
      <div class="hero-badge" style="margin-bottom:14px;background:rgba(184,162,97,0.16);border-color:rgba(184,162,97,0.32);color:#8F7E36;">
        <?= icon('calendar', '', 13) ?> Calendario de Bienestar
      </div>
      <h1><?= e($page['title'] ?? $pageTitle) ?></h1>
      <p><?= e($page['subtitle'] ?? 'Consulta, participa y únete a nuestras actividades de bienestar laboral') ?></p>
    </div>
    <div class="page-hero-media">
      <img src="<?= $base ?>/assets/uploads/simbolo-universidad.png" alt="Unitrópico">
    </div>
  </div>

  <!-- Actividades destacadas -->
  <div class="section-header" style="margin-bottom:20px;">
    <h2 class="section-title">
      <span class="icon-badge" style="background:rgba(184,162,97,0.16);color:#B8A261;"><?= icon('star', '', 20) ?></span>
      Actividades Destacadas
    </h2>
  </div>

  <div class="activity-showcase">
    <?php
    $activities = [
      [
        'icon'=>'heart',
        'color'=>'#007C72',
        'tone'=>'emerald',
        'title'=>'Talleres de Bienestar',
        'desc'=>'Espacios de autocuidado físico y emocional',
        'tag'=>'Cuerpo y mente',
        'detail'=>'Participa en encuentros para reconocer señales de estrés, fortalecer hábitos saludables y practicar herramientas sencillas de regulación emocional.',
        'meta'=>'Mensual'
      ],
      [
        'icon'=>'trending-up',
        'color'=>'#006B5B',
        'tone'=>'forest',
        'title'=>'Jornadas de Integración',
        'desc'=>'Actividades para fortalecer el equipo',
        'tag'=>'Clima laboral',
        'detail'=>'Experiencias grupales para mejorar confianza, colaboración, comunicación y sentido de pertenencia entre equipos de trabajo.',
        'meta'=>'Por equipos'
      ],
      [
        'icon'=>'sun',
        'color'=>'#B8A261',
        'tone'=>'gold',
        'title'=>'Deporte y Recreación',
        'desc'=>'Actividades físicas y deportivas grupales',
        'tag'=>'Movimiento',
        'detail'=>'Espacios de activación corporal, recreación y deporte para recuperar energía, compartir con otros y cuidar la salud física.',
        'meta'=>'Semanal'
      ],
      [
        'icon'=>'sparkles',
        'color'=>'#8F7E36',
        'tone'=>'olive',
        'title'=>'Espacios de Relajación',
        'desc'=>'Mindfulness, yoga y meditación guiada',
        'tag'=>'Pausa consciente',
        'detail'=>'Sesiones para bajar revoluciones, respirar mejor, relajar el cuerpo y volver a la jornada con mayor calma y enfoque.',
        'meta'=>'Pausas guiadas'
      ],
    ];
    foreach ($activities as $act):
    ?>
    <article class="activity-feature-card tone-<?= e($act['tone']) ?>" style="--activity-color:<?= e($act['color']) ?>;" tabindex="0">
      <div class="activity-feature-art" aria-hidden="true">
        <span class="activity-feature-icon"><?= icon($act['icon'], '', 28) ?></span>
        <span class="activity-feature-ring"></span>
        <span class="activity-feature-dot dot-a"></span>
        <span class="activity-feature-dot dot-b"></span>
      </div>
      <div class="activity-feature-body">
        <div class="activity-feature-top">
          <span class="activity-feature-tag"><?= e($act['tag']) ?></span>
          <span class="activity-feature-meta"><?= e($act['meta']) ?></span>
        </div>
        <h3><?= e($act['title']) ?></h3>
        <p><?= e($act['desc']) ?></p>
        <div class="activity-feature-detail">
          <p><?= e($act['detail']) ?></p>
        </div>
        <a class="activity-feature-link" href="#calendario-mensual">
          Ver fechas <?= icon('chevron-right', '', 15) ?>
        </a>
      </div>
    </article>
    <?php endforeach; ?>
  </div>

  <!-- Calendario embebido -->
  <div class="section-header" id="calendario-mensual">
    <h2 class="section-title">
      <span class="icon-badge" style="background:rgba(0,169,147,0.12);color:var(--accent);"><?= icon('calendar', '', 20) ?></span>
      Calendario Mensual
    </h2>
    <span style="font-size:12px;color:var(--text-muted);">Actualizado en tiempo real</span>
  </div>

  <div class="calendar-embed" style="margin-bottom:32px;">
    <div class="calendar-frame-wrap" style="min-height:600px;">
      <?php $calendarSource = $calId !== '' ? $calId : 'c_056309a68b85d35f32a88c03b98bb8f52cff6f980a765e9b60d9d8ce1f8987b4@group.calendar.google.com'; ?>
      <iframe
        src="https://calendar.google.com/calendar/embed?src=<?= urlencode($calendarSource) ?>&ctz=America%2FBogota"
        style="border:0;width:100%;height:600px;"
        width="800"
        height="600"
        frameborder="0"
        scrolling="no"
        title="Calendario de Actividades de Bienestar Laboral">
      </iframe>
    </div>
  </div>

  <!-- Nota -->
  <div style="background:rgba(0,201,167,0.06);border:1px solid rgba(0,201,167,0.15);border-radius:var(--radius-md);padding:20px 24px;">
    <p style="font-size:13px;color:var(--text-secondary);display:flex;align-items:flex-start;gap:10px;">
      <?= icon('sparkles', '', 16) ?>
      <span>No dejes pasar la oportunidad de cuidar tu bienestar y fortalecer los lazos con tu equipo. <strong style="color:var(--accent);">¡Te esperamos!</strong> 🎉</span>
    </p>
  </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

