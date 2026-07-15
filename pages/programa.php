<?php
// pages/programa.php - Subpagina dinamica para programas y recursos
require_once __DIR__ . '/../includes/functions.php';

$slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($_GET['slug'] ?? ''));
$data = readData();
$program = $data['program_pages'][$slug] ?? null;

if (!$program) {
    http_response_code(404);
    $pageTitle = 'Contenido no encontrado';
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <main class="content">
      <div class="page-hero">
        <div class="page-hero-content">
          <div class="hero-badge"><?= icon('x', '', 13) ?> No encontrado</div>
          <h1>Contenido no encontrado</h1>
          <p>La subpagina solicitada no existe o aun no esta configurada.</p>
        </div>
      </div>
    </main>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $program['title'] ?? 'Programa';
$base = baseUrl();
$accent = $program['accent'] ?? '#006B5B';
$image = !empty($program['image_url']) ? $base . '/' . ltrim($program['image_url'], '/') : '';

require_once __DIR__ . '/../includes/header.php';
?>

<main class="content">

  <div class="page-hero" style="border-left-color:<?= e($accent) ?>;">
    <div class="page-hero-content">
      <div class="hero-badge" style="margin-bottom:14px;background:rgba(0,107,91,0.10);border-color:rgba(0,107,91,0.24);color:<?= e($accent) ?>;">
        <?= icon('layers', '', 13) ?> <?= e($program['section'] ?? 'Programa') ?>
      </div>
      <h1><?= e($program['title'] ?? 'Programa') ?></h1>
      <p><?= e($program['subtitle'] ?? '') ?></p>
    </div>
    <?php if ($image): ?>
    <div class="page-hero-media">
      <img src="<?= e($image) ?>" alt="<?= e($program['title'] ?? 'Programa') ?>">
    </div>
    <?php endif; ?>
  </div>

  <?php if (!empty($program['intro'])): ?>
  <section class="detail-intro">
    <p><?= e($program['intro']) ?></p>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['blocks'])): ?>
  <section class="detail-grid">
    <?php foreach ($program['blocks'] as $block): ?>
    <article class="detail-card">
      <?php if (!empty($block['image_url'])):
        $blockImage = str_starts_with($block['image_url'], 'http') ? $block['image_url'] : $base . '/' . ltrim($block['image_url'], '/');
      ?>
      <div class="detail-card-media">
        <img src="<?= e($blockImage) ?>" alt="<?= e($block['title'] ?? 'Contenido') ?>" loading="lazy">
      </div>
      <?php endif; ?>
      <div class="detail-card-icon" style="background:rgba(0,107,91,0.10);color:<?= e($accent) ?>;">
        <?= icon('sparkles', '', 20) ?>
      </div>
      <h2><?= e($block['title'] ?? '') ?></h2>
      <p><?= e($block['body'] ?? '') ?></p>
      <?php if (!empty($block['media_url'])): ?>
      <a class="detail-card-link" href="<?= e($block['media_url']) ?>" target="_blank" rel="noopener noreferrer">
        <?= icon('external-link', '', 15) ?>
        <?= e($block['media_label'] ?? 'Abrir recurso') ?>
      </a>
      <?php endif; ?>
    </article>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['embeds'])): ?>
  <section class="program-embeds" aria-labelledby="program-embeds-title">
    <div class="section-header">
      <h2 class="section-title" id="program-embeds-title">
        <span class="icon-badge" style="background:rgba(0,107,91,0.10);color:<?= e($accent) ?>;"><?= icon('calendar', '', 20) ?></span>
        Atención y agendamiento
      </h2>
    </div>

    <div class="program-embed-list">
      <?php foreach ($program['embeds'] as $embed):
        $embedType = in_array(($embed['type'] ?? ''), ['calendar', 'form'], true) ? $embed['type'] : 'resource';
        $embedUrl = sanitizeContentUrl((string)($embed['embed_url'] ?? ''), false);
        $externalUrl = sanitizeContentUrl((string)($embed['external_url'] ?? $embedUrl), false);
        if ($embedUrl === '') continue;
      ?>
      <article class="program-embed-card program-embed-card-<?= e($embedType) ?>">
        <div class="program-embed-copy">
          <span class="benefit-kicker"><?= $embedType === 'calendar' ? 'Agenda de citas' : 'Formulario institucional' ?></span>
          <h2><?= e($embed['title'] ?? 'Recurso de atención') ?></h2>
          <?php if (!empty($embed['description'])): ?>
          <p><?= e($embed['description']) ?></p>
          <?php endif; ?>
          <?php if ($externalUrl !== ''): ?>
          <a class="detail-card-link" href="<?= e($externalUrl) ?>" target="_blank" rel="noopener noreferrer">
            <?= icon('external-link', '', 15) ?>
            <?= e($embed['action_label'] ?? 'Abrir en una pestaña nueva') ?>
          </a>
          <?php endif; ?>
        </div>
        <div class="program-embed-frame">
          <iframe
            src="<?= e($embedUrl) ?>"
            title="<?= e($embed['title'] ?? 'Recurso de atención') ?>"
            loading="lazy"
            referrerpolicy="strict-origin-when-cross-origin"
          ></iframe>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['tool_cards'])): ?>
  <section class="toolkit-section">
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(0,124,114,0.10);color:<?= e($accent) ?>;"><?= icon('trending-up', '', 20) ?></span>
        <?= e($program['tool_cards_title'] ?? 'Herramientas practicas') ?>
      </h2>
    </div>
    <div class="toolkit-grid">
      <?php foreach ($program['tool_cards'] as $tool): ?>
      <details class="toolkit-card">
        <summary>
          <span class="toolkit-card-icon"><?= icon($tool['icon'] ?? 'sparkles', '', 22) ?></span>
          <span>
            <strong><?= e($tool['title'] ?? '') ?></strong>
            <em><?= e($tool['summary'] ?? '') ?></em>
          </span>
          <?= icon('chevron-right', '', 18) ?>
        </summary>
        <div class="toolkit-card-body">
          <p><?= e($tool['body'] ?? '') ?></p>
          <?php if (!empty($tool['tips'])): ?>
          <ul>
            <?php foreach ($tool['tips'] as $tip): ?>
            <li><?= e($tip) ?></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
      </details>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['media_sections'])): ?>
  <section class="media-library-section">
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(184,162,97,0.16);color:var(--bronze-dark);"><?= icon('external-link', '', 20) ?></span>
        <?= e($program['media_title'] ?? 'Recursos recomendados') ?>
      </h2>
    </div>
    <div class="media-library-grid">
      <?php foreach ($program['media_sections'] as $section): ?>
      <article class="media-library-card">
        <span class="benefit-kicker"><?= e($section['kicker'] ?? 'Recurso') ?></span>
        <h2><?= e($section['title'] ?? '') ?></h2>
        <?php if (!empty($section['body'])): ?>
        <p><?= e($section['body']) ?></p>
        <?php endif; ?>
        <div class="media-link-list">
          <?php foreach (($section['items'] ?? []) as $item): ?>
          <a class="media-link-item" href="<?= e($item['url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">
            <span>
              <strong><?= e($item['label'] ?? 'Abrir recurso') ?></strong>
              <?php if (!empty($item['description'])): ?>
              <em><?= e($item['description']) ?></em>
              <?php endif; ?>
            </span>
            <?= icon('external-link', '', 15) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['game_zone'])): ?>
  <section class="selfcare-game-section">
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(0,201,167,0.12);color:<?= e($accent) ?>;"><?= icon('sparkles', '', 20) ?></span>
        <?= e($program['game_zone']['title'] ?? 'Zona interactiva') ?>
      </h2>
    </div>
    <?php if (!empty($program['game_zone']['intro'])): ?>
    <p class="selfcare-game-intro"><?= e($program['game_zone']['intro']) ?></p>
    <?php endif; ?>

    <div class="selfcare-game-grid">
      <?php if (!empty($program['game_zone']['puzzle'])): ?>
      <article class="selfcare-game-card">
        <span class="benefit-kicker">Rompecabezas</span>
        <h2><?= e($program['game_zone']['puzzle']['title'] ?? 'Arma tu pausa') ?></h2>
        <p><?= e($program['game_zone']['puzzle']['body'] ?? '') ?></p>
        <div class="selfcare-puzzle" data-selfcare-puzzle>
          <?php foreach (($program['game_zone']['puzzle']['tiles'] ?? []) as $tile): ?>
          <button type="button" class="selfcare-puzzle-tile" data-step="<?= e($tile['step'] ?? '') ?>">
            <strong><?= e($tile['label'] ?? '') ?></strong>
            <span><?= e($tile['hint'] ?? '') ?></span>
          </button>
          <?php endforeach; ?>
        </div>
        <p class="selfcare-game-feedback" data-puzzle-feedback>Elige las piezas que necesitas hoy.</p>
      </article>
      <?php endif; ?>

      <?php if (!empty($program['game_zone']['riddles'])): ?>
      <article class="selfcare-game-card">
        <span class="benefit-kicker">Adivinanzas</span>
        <h2>Adivina el hábito</h2>
        <div class="selfcare-riddle-list">
          <?php foreach ($program['game_zone']['riddles'] as $riddle): ?>
          <div class="selfcare-riddle">
            <p><?= e($riddle['question'] ?? '') ?></p>
            <button type="button" class="detail-card-link" data-riddle-answer="<?= e($riddle['answer'] ?? '') ?>">
              <?= icon('sparkles', '', 15) ?>
              Ver respuesta
            </button>
            <strong class="selfcare-riddle-answer" aria-live="polite"></strong>
          </div>
          <?php endforeach; ?>
        </div>
      </article>
      <?php endif; ?>

      <?php if (!empty($program['game_zone']['word_search'])): ?>
      <article class="selfcare-game-card selfcare-word-card">
        <span class="benefit-kicker">Sopa de letras</span>
        <h2><?= e($program['game_zone']['word_search']['title'] ?? 'Palabras que cuidan') ?></h2>
        <div class="selfcare-word-grid" aria-label="Sopa de letras de autocuidado">
          <?php foreach (($program['game_zone']['word_search']['grid'] ?? []) as $row): ?>
            <?php foreach (preg_split('//u', $row, -1, PREG_SPLIT_NO_EMPTY) as $letter): ?>
            <span><?= e($letter) ?></span>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
        <div class="selfcare-word-list">
          <?php foreach (($program['game_zone']['word_search']['words'] ?? []) as $word): ?>
          <button type="button" class="year-pill" data-word-found><?= e($word) ?></button>
          <?php endforeach; ?>
        </div>
        <p class="selfcare-game-feedback" data-word-feedback>Marca cada palabra cuando la encuentres.</p>
      </article>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['pdf_resources'])): ?>
  <section class="pdf-resource-section">
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(184,162,97,0.16);color:var(--bronze-dark);"><?= icon('clipboard-check', '', 20) ?></span>
        <?= e($program['pdf_resources_title'] ?? 'Guías descargables') ?>
      </h2>
    </div>
    <div class="pdf-resource-grid">
      <?php foreach ($program['pdf_resources'] as $resource):
        $pdfUrl = !empty($resource['url']) ? (str_starts_with($resource['url'], 'http') ? $resource['url'] : $base . '/' . ltrim($resource['url'], '/')) : '';
      ?>
      <article class="pdf-resource-card">
        <div class="pdf-resource-copy">
          <span class="benefit-kicker"><?= e($resource['kicker'] ?? 'PDF') ?></span>
          <h2><?= e($resource['title'] ?? '') ?></h2>
          <?php if (!empty($resource['body'])): ?>
          <p><?= e($resource['body']) ?></p>
          <?php endif; ?>
          <?php if ($pdfUrl): ?>
          <a class="btn-primary" href="<?= e($pdfUrl) ?>" target="_blank" rel="noopener noreferrer">
            <?= icon('external-link', '', 15) ?>
            <?= e($resource['label'] ?? 'Abrir PDF') ?>
          </a>
          <?php endif; ?>
        </div>
        <?php if ($pdfUrl): ?>
        <div class="pdf-viewer-frame">
          <iframe src="<?= e($pdfUrl) ?>" title="<?= e($resource['title'] ?? 'PDF') ?>"></iframe>
        </div>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['policy_values'])): ?>
  <section class="integrity-section">
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(0,107,91,0.10);color:<?= e($accent) ?>;"><?= icon('shield-check', '', 20) ?></span>
        <?= e($program['policy_values_title'] ?? 'Conoce la politica') ?>
      </h2>
    </div>
    <?php if (!empty($program['policy_banner'])):
      $policyBanner = str_starts_with($program['policy_banner'], 'http') ? $program['policy_banner'] : $base . '/' . ltrim($program['policy_banner'], '/');
    ?>
    <div class="integrity-banner">
      <img src="<?= e($policyBanner) ?>" alt="<?= e($program['policy_values_title'] ?? 'Politica de integridad') ?>" loading="lazy">
    </div>
    <?php endif; ?>
    <div class="integrity-values-grid">
      <?php foreach ($program['policy_values'] as $value): ?>
      <details class="integrity-value-card">
        <summary>
          <span class="integrity-value-number"><?= e($value['number'] ?? '') ?></span>
          <span>
            <strong><?= e($value['title'] ?? '') ?></strong>
            <em><?= e($value['summary'] ?? '') ?></em>
          </span>
          <?= icon('chevron-right', '', 18) ?>
        </summary>
        <div class="integrity-value-body">
          <?php if (!empty($value['do'])): ?>
          <div>
            <h3>Lo que se debe hacer</h3>
            <p><?= e($value['do']) ?></p>
          </div>
          <?php endif; ?>
          <?php if (!empty($value['dont'])): ?>
          <div>
            <h3>Evita</h3>
            <p><?= e($value['dont']) ?></p>
          </div>
          <?php endif; ?>
        </div>
      </details>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['bulletin_sections'])): ?>
  <section class="bulletins-section">
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(184,162,97,0.16);color:var(--bronze-dark);"><?= icon('layers', '', 20) ?></span>
        <?= e($program['bulletins_title'] ?? 'Boletines') ?>
      </h2>
    </div>
    <div class="bulletin-year-grid">
      <?php foreach ($program['bulletin_sections'] as $section): ?>
      <article class="bulletin-year-card">
        <span class="year-pill"><?= e($section['year'] ?? '') ?></span>
        <h2><?= e($section['title'] ?? 'Boletines') ?></h2>
        <?php if (!empty($section['body'])): ?>
        <p><?= e($section['body']) ?></p>
        <?php endif; ?>
        <div class="bulletin-buttons">
          <?php foreach (($section['items'] ?? []) as $item): ?>
            <?php if (!empty($item['url'])): ?>
            <a class="detail-card-link" href="<?= e($item['url']) ?>" target="_blank" rel="noopener noreferrer">
              <?= icon('external-link', '', 15) ?>
              <?= e($item['label'] ?? 'Ver boletin') ?>
            </a>
            <?php else: ?>
            <span class="detail-card-link disabled" aria-disabled="true">
              <?= icon('calendar', '', 15) ?>
              <?= e($item['label'] ?? 'Proximamente') ?>
            </span>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['benefits'])): ?>
  <section class="benefits-section">
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(0,169,147,0.12);color:<?= e($accent) ?>;"><?= icon('award', '', 20) ?></span>
        <?= e($program['benefits_title'] ?? 'Beneficios e incentivos') ?>
      </h2>
    </div>
    <div class="benefits-grid">
      <?php foreach ($program['benefits'] as $benefit):
        $benefitImage = '';
        if (!empty($benefit['image_url'])) {
            $benefitImage = str_starts_with($benefit['image_url'], 'http') ? $benefit['image_url'] : $base . '/' . ltrim($benefit['image_url'], '/');
        }
      ?>
      <article class="benefit-card">
        <?php if ($benefitImage): ?>
        <div class="benefit-card-media">
          <img src="<?= e($benefitImage) ?>" alt="<?= e($benefit['title'] ?? 'Beneficio') ?>" loading="lazy">
        </div>
        <?php endif; ?>
        <div class="benefit-card-content">
          <span class="benefit-kicker"><?= e($benefit['kicker'] ?? 'Beneficio') ?></span>
          <h2><?= e($benefit['title'] ?? '') ?></h2>
          <?php if (!empty($benefit['body'])): ?>
          <p><?= e($benefit['body']) ?></p>
          <?php endif; ?>
          <?php if (!empty($benefit['actions'])): ?>
          <div class="benefit-actions">
            <?php foreach ($benefit['actions'] as $action): ?>
            <a class="btn-primary" href="<?= e($action['url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">
              <?= icon('external-link', '', 15) ?>
              <?= e($action['label'] ?? 'Abrir enlace') ?>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['year_sections'])): ?>
  <section class="year-section-list">
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(184,162,97,0.16);color:var(--bronze-dark);"><?= icon('calendar', '', 20) ?></span>
        Cursos por año
      </h2>
    </div>
    <div class="detail-grid">
      <?php foreach ($program['year_sections'] as $year): ?>
      <article class="detail-card year-card">
        <span class="year-pill"><?= e($year['year'] ?? '') ?></span>
        <h2>Vigencia <?= e($year['year'] ?? '') ?></h2>
        <p><?= e($year['body'] ?? '') ?></p>
        <?php if (!empty($year['url'])): ?>
        <a class="btn-primary" href="<?= e($year['url']) ?>" target="_blank" rel="noopener noreferrer">
          <?= icon('external-link', '', 15) ?>
          <?= e($year['label'] ?? 'Ir al curso') ?>
        </a>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($program['actions'])): ?>
  <section class="detail-actions">
    <?php foreach ($program['actions'] as $action): ?>
    <a class="btn-primary" href="<?= e($action['url'] ?? '#') ?>" target="_blank" rel="noopener noreferrer">
      <?= icon('external-link', '', 15) ?>
      <?= e($action['label'] ?? 'Abrir enlace') ?>
    </a>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
