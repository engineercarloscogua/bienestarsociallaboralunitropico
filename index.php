<?php
// ============================================================
// index.php — Página Principal del Portal Bienestar Social Laboral
// ============================================================
require_once __DIR__ . '/includes/functions.php';

$pageTitle   = 'Inicio';
$heroTitle   = getConfig('hero_title',    'Bienvenidos a Bienestar Social Laboral');
$heroSub     = getConfig('hero_subtitle', 'Impulsamos tu bienestar y crecimiento profesional en Unitrópico');
$mainCards   = getCards('inicio');
$allCards    = getAllCards();
$resourceCount = count(array_filter($allCards, fn($card) => $card['is_active'] ?? true));
$sectionCount  = count(getPages());
$base        = baseUrl();
$turnstileSiteKey = turnstileSiteKey();

require_once __DIR__ . '/includes/header.php';
?>

<main class="content">

  <!-- ── HERO ── -->
  <section class="hero">
    <div class="hero-bg" id="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-glow"></div>
    <canvas class="hero-network" id="hero-network" aria-hidden="true"></canvas>
    <div class="hero-content">
      <h1 class="hero-title">
        <?php
          $parts = explode(' ', $heroTitle);
          $last  = array_pop($parts);
          echo implode(' ', $parts) . ' <span>' . htmlspecialchars($last) . '</span>';
        ?>
      </h1>
      <p class="hero-subtitle"><?= e($heroSub) ?></p>
      <div class="hero-actions">
        <a href="#services" class="btn-primary">
          <?= icon('layers', '', 16) ?>
          Ver Servicios
        </a>
        <a href="<?= $base ?>/pages/calendario.php" class="btn-outline">
          <?= icon('calendar', '', 16) ?>
          Calendario de Actividades
        </a>
      </div>
    </div>
  </section>

  <!-- ── STATS ── -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(0,169,147,0.12);color:#00C9A7;"><?= icon('users', '', 22) ?></div>
      <div class="stat-info"><strong><?= e((string)count($mainCards)) ?></strong><span>Áreas de servicio</span></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(0,107,91,0.10);color:#006B5B;"><?= icon('layers', '', 22) ?></div>
      <div class="stat-info"><strong><?= e((string)$resourceCount) ?></strong><span>Recursos publicados</span></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(184,162,97,0.16);color:#B8A261;"><?= icon('star', '', 22) ?></div>
      <div class="stat-info"><strong>24/7</strong><span>Apoyo Psicológico</span></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon" style="background:rgba(0,107,91,0.10);color:#007C72;"><?= icon('heart', '', 22) ?></div>
      <div class="stat-info"><strong><?= e((string)$sectionCount) ?></strong><span>Secciones para explorar</span></div>
    </div>
  </div>

  <!-- ── QUICK ROUTES ── -->
  <section class="home-routes" aria-labelledby="home-routes-title">
    <div class="section-header home-routes-header">
      <div class="section-heading">
        <span class="section-eyebrow">Encuentra tu camino</span>
        <h2 id="home-routes-title" class="section-title">¿Qué necesitas hoy?</h2>
        <p class="section-description">Elige una ruta y llega directamente a los recursos que pueden acompañarte.</p>
      </div>
      <span class="home-routes-note"><?= icon('sparkles', '', 14) ?> Explora a tu ritmo</span>
    </div>

    <div class="home-routes-grid">
      <a class="home-route-card" href="<?= $base ?>/pages/bienestar.php" style="--route-color:#007c72;--route-soft:rgba(0,124,114,0.10);">
        <span class="home-route-icon"><?= icon('heart', '', 22) ?></span>
        <span class="home-route-copy">
          <span class="home-route-kicker">Ruta 01</span>
          <strong>Quiero cuidar mi bienestar</strong>
          <small>Autocuidado, apoyo emocional y actividades para sentirte mejor.</small>
        </span>
        <span class="home-route-arrow" aria-hidden="true"><?= icon('chevron-right', '', 17) ?></span>
      </a>

      <a class="home-route-card" href="<?= $base ?>/pages/desarrollo.php" style="--route-color:#006b5b;--route-soft:rgba(0,107,91,0.10);">
        <span class="home-route-icon"><?= icon('graduation-cap', '', 22) ?></span>
        <span class="home-route-copy">
          <span class="home-route-kicker">Ruta 02</span>
          <strong>Quiero seguir creciendo</strong>
          <small>Liderazgo, formación y herramientas para fortalecer tu talento.</small>
        </span>
        <span class="home-route-arrow" aria-hidden="true"><?= icon('chevron-right', '', 17) ?></span>
      </a>

      <a class="home-route-card" href="<?= $base ?>/pages/talento-humano.php" style="--route-color:#8f7e36;--route-soft:rgba(184,162,97,0.16);">
        <span class="home-route-icon"><?= icon('sparkles', '', 22) ?></span>
        <span class="home-route-copy">
          <span class="home-route-kicker">Ruta 03</span>
          <strong>Quiero conocer mis beneficios</strong>
          <small>Estímulos, incentivos y programas que reconocen tu compromiso.</small>
        </span>
        <span class="home-route-arrow" aria-hidden="true"><?= icon('chevron-right', '', 17) ?></span>
      </a>
    </div>
  </section>

  <!-- ── SERVICES ── -->
  <section id="services">
    <div class="section-header">
      <div class="section-heading">
        <h2 class="section-title">
          <span class="icon-badge" style="background:rgba(0,169,147,0.12);color:var(--accent);"><?= icon('layers', '', 20) ?></span>
          Nuestros Servicios
        </h2>
        <p class="section-description">Un solo lugar para encontrar apoyo, desarrollo y experiencias de bienestar laboral.</p>
      </div>
      <span class="badge badge-accent"><?= count($mainCards) ?> áreas disponibles</span>
    </div>

    <div class="cards-grid cards-grid-3">
      <?php
        $cardColors = [
          '#006B5B' => ['bg' => 'rgba(0,107,91,0.10)', 'text' => '#006B5B', 'btn_bg' => 'rgba(0,107,91,0.10)', 'btn_text' => '#006B5B'],
          '#B8A261' => ['bg' => 'rgba(184,162,97,0.16)', 'text' => '#95843D', 'btn_bg' => 'rgba(184,162,97,0.18)', 'btn_text' => '#735F1A'],
          '#007C72' => ['bg' => 'rgba(0,124,114,0.10)', 'text' => '#007C72', 'btn_bg' => 'rgba(0,124,114,0.10)', 'btn_text' => '#007C72'],
        ];
        foreach ($mainCards as $card):
          $col = $cardColors[$card['color']] ?? $cardColors['#006B5B'];
          $link = $card['link_url'] ? (str_starts_with($card['link_url'], 'http') ? $card['link_url'] : $base . '/' . ltrim($card['link_url'], '/')) : '#';
          $isExternal = str_starts_with($card['link_url'] ?? '', 'http');
          $image = !empty($card['image_url']) ? $base . '/' . ltrim($card['image_url'], '/') : '';
      ?>
      <div class="mega-card" tabindex="0" style="--card-color:<?= e($card['color']) ?>">
        <?php if ($image): ?>
        <div class="mega-card-image">
          <img src="<?= e($image) ?>" alt="<?= e($card['title']) ?>" loading="lazy">
          <div class="card-description-overlay">
            <p><?= e($card['description']) ?></p>
          </div>
        </div>
        <?php endif; ?>
        <div class="mega-card-content">
          <div class="mega-card-icon" style="background:<?= e($col['bg']) ?>;color:<?= e($col['text']) ?>;">
            <?= icon($card['icon'] ?? 'star', '', 30) ?>
          </div>
          <h2><?= e($card['title']) ?></h2>
          <?php if (!$image): ?>
          <p><?= e($card['description']) ?></p>
          <?php endif; ?>
          <a href="<?= e($link) ?>"
             <?= $isExternal ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
             class="mega-card-link"
             style="background:<?= e($col['btn_bg']) ?>;color:<?= e($col['btn_text']) ?>;">
            <span><?= e($card['link_label'] ?? 'Explorar') ?></span>
            <?= icon('chevron-right', '', 18) ?>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ── CALENDAR ── -->
  <section>
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(184,162,97,0.16);color:#B8A261;"><?= icon('calendar', '', 20) ?></span>
        Calendario de Actividades
      </h2>
      <a href="<?= $base ?>/pages/calendario.php" class="badge badge-gold" style="text-decoration:none;">
        Ver completo <?= icon('chevron-right', '', 13) ?>
      </a>
    </div>

    <div class="calendar-section">
      <div class="calendar-info">
        <h3><?= icon('sparkles', '', 17) ?> Participa en tu bienestar</h3>
        <p>Te invitamos a ser parte de un espacio pensado para ti. Relájate, disfruta y fortalece tu bienestar con nuestras actividades programadas.</p>
        <div class="calendar-pulse">
          <?= icon('calendar', '', 16) ?>
          <span><strong>Planifica tu semana</strong><small>Revisa fechas, horarios y espacios disponibles.</small></span>
        </div>
        <ul class="activity-list">
          <li><span class="activity-dot" style="background:#00C9A7;"></span>Talleres de bienestar y autocuidado</li>
          <li><span class="activity-dot" style="background:#006B5B;"></span>Jornadas deportivas y culturales</li>
          <li><span class="activity-dot" style="background:#B8A261;"></span>Jornadas de integración y recreación</li>
          <li><span class="activity-dot" style="background:#007C72;"></span>Espacios de relajación y motivación</li>
          <li><span class="activity-dot" style="background:#8F7E36;"></span>Café conversemos con Talento Humano</li>
        </ul>
        <a href="<?= $base ?>/pages/calendario.php" class="btn-primary" style="margin-top:8px;width:fit-content;">
          <?= icon('calendar', '', 15) ?>
          Ver Calendario Completo
        </a>
      </div>

      <div class="calendar-embed">
        <div class="calendar-embed-header">
          <h3><?= icon('calendar', '', 16) ?> Eventos en Tiempo Real</h3>
          <span>Sincronizado con Google Calendar</span>
        </div>
        <?php $calId = getConfig('google_calendar_id', ''); ?>
        <div class="calendar-frame-wrap">
          <?php if ($calId): ?>
            <iframe
              src="https://calendar.google.com/calendar/embed?src=<?= urlencode($calId) ?>&ctz=America%2FBogota&showTitle=0&showNav=1&showDate=1&showPrint=0&showTabs=0&showCalendars=0&mode=MONTH"
              title="Calendario de Actividades de Bienestar Laboral">
            </iframe>
          <?php else: ?>
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;gap:12px;color:var(--text-muted);padding:32px;text-align:center;">
              <?= icon('calendar', '', 40) ?>
              <p style="font-size:14px;">Configura el ID de Google Calendar en el <a href="<?= $base ?>/admin/dashboard.php" style="color:var(--accent);">panel admin</a>.</p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <?php $comments = getComments(); ?>
  <section class="experience-section" id="comentarios">
    <div class="section-header">
      <h2 class="section-title">
        <span class="icon-badge" style="background:rgba(0,169,147,0.12);color:var(--accent);"><?= icon('heart', '', 20) ?></span>
        Experiencias del Portal
      </h2>
      <span class="badge badge-accent"><?= count($comments) ?> comentarios</span>
    </div>

    <div class="experience-layout">
      <?php $commentRequestToken = issuePublicRequestToken('comment'); ?>
      <form class="experience-form" id="comment-form" method="post" action="<?= $base ?>/api/comments.php">
        <input type="hidden" name="request_token" value="<?= e($commentRequestToken) ?>">
        <div class="comment-honeypot" aria-hidden="true">
          <label for="comment-website">No completar este campo</label>
          <input id="comment-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>
        <div>
          <span class="benefit-kicker">Tu opinión importa</span>
          <h3>Cuéntanos cómo te fue</h3>
          <p>Comparte tu experiencia usando el portal. Para cuidar este espacio, cada comentario será revisado antes de publicarse.</p>
        </div>

        <div class="comment-rating" aria-label="Calificación de experiencia">
          <?php for ($i = 5; $i >= 1; $i--): ?>
          <label>
            <input type="radio" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
            <span><?= str_repeat('★', $i) ?></span>
          </label>
          <?php endfor; ?>
        </div>

        <div class="comment-emoji-row">
          <?php foreach (['💚','😊','🌿','✨','🙌'] as $emoji): ?>
          <label>
            <input type="radio" name="emoji" value="<?= e($emoji) ?>" <?= $emoji === '💚' ? 'checked' : '' ?>>
            <span><?= e($emoji) ?></span>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="form-field">
          <label for="comment-name">Nombre</label>
          <input id="comment-name" name="name" type="text" maxlength="60" placeholder="Tu nombre o área">
        </div>
        <div class="form-field">
          <label for="comment-message">Comentario</label>
          <textarea id="comment-message" name="message" rows="4" maxlength="600" required placeholder="¿Qué te gustó del portal? ¿Qué mejorarías?"></textarea>
        </div>
        <?php if ($turnstileSiteKey !== ''): ?>
        <div class="turnstile-slot" data-turnstile-sitekey="<?= e($turnstileSiteKey) ?>" data-turnstile-action="comment" aria-label="Verificación anti-bots"></div>
        <?php endif; ?>
        <button type="submit" class="btn-primary">
          <?= icon('sparkles', '', 15) ?>
          Enviar comentario para revisión
        </button>
        <p class="comment-form-note">Los comentarios ofensivos, discriminatorios o que suplanten a otra persona no serán publicados.</p>
        <p class="comment-form-status" id="comment-form-status" aria-live="polite"></p>
      </form>

      <div class="comment-wall" id="comment-wall">
        <?php if ($comments): ?>
          <?php foreach (array_slice($comments, 0, 8) as $comment): ?>
          <?php $commentId = $comment['id'] ?? ''; ?>
          <article class="comment-card" data-comment-id="<?= e($commentId) ?>">
            <div class="comment-card-top">
              <span class="comment-emoji"><?= e($comment['emoji'] ?? '💚') ?></span>
              <div class="comment-main">
                <div class="comment-meta">
                  <h3><?= e($comment['name'] ?? 'Visitante') ?></h3>
                  <span>@portalTH</span>
                  <span aria-hidden="true">·</span>
                  <time datetime="<?= e($comment['created_at'] ?? '') ?>">
                    <?= e(date('d/m/Y H:i', strtotime($comment['created_at'] ?? 'now'))) ?>
                  </time>
                </div>
                <p><?= e($comment['message'] ?? '') ?></p>
                <div class="comment-score"><?= str_repeat('★', (int)($comment['rating'] ?? 5)) ?></div>
              </div>
            </div>
            <div class="comment-replies">
              <?php foreach (($comment['replies'] ?? []) as $reply): ?>
              <article class="comment-reply">
                <span class="comment-reply-avatar"><?= e($reply['emoji'] ?? '💬') ?></span>
                <div class="comment-reply-body">
                <div class="comment-reply-head">
                  <strong><?= e($reply['name'] ?? 'Visitante') ?></strong>
                  <span>@respuesta</span>
                  <span aria-hidden="true">·</span>
                  <time datetime="<?= e($reply['created_at'] ?? '') ?>">
                    <?= e(date('d/m/Y H:i', strtotime($reply['created_at'] ?? 'now'))) ?>
                  </time>
                </div>
                <p><?= e($reply['message'] ?? '') ?></p>
                </div>
              </article>
              <?php endforeach; ?>
            </div>
            <div class="comment-actions">
              <button type="button" class="comment-reply-toggle" data-reply-toggle>
                <?= icon('message-circle', '', 14) ?>
                Responder
              </button>
            </div>
            <form class="comment-reply-form" data-reply-form method="post" action="<?= $base ?>/api/comments.php" hidden>
              <input type="hidden" name="parent_id" value="<?= e($commentId) ?>">
              <input type="hidden" name="rating" value="<?= (int)($comment['rating'] ?? 5) ?>">
              <input type="hidden" name="emoji" value="💬">
              <input type="hidden" name="request_token" value="<?= e(issuePublicRequestToken('comment')) ?>">
              <div class="comment-honeypot" aria-hidden="true">
                <label>No completar este campo</label>
                <input name="website" type="text" tabindex="-1" autocomplete="off">
              </div>
              <div class="form-field">
                <label>Tu nombre</label>
                <input name="name" type="text" maxlength="60" placeholder="Nombre o área">
              </div>
              <div class="form-field">
                <label>Respuesta</label>
                <textarea name="message" rows="3" maxlength="600" required placeholder="Responde con respeto y buena energía"></textarea>
              </div>
              <?php if ($turnstileSiteKey !== ''): ?>
              <div class="turnstile-slot" data-turnstile-sitekey="<?= e($turnstileSiteKey) ?>" data-turnstile-action="comment" aria-label="Verificación anti-bots"></div>
              <?php endif; ?>
              <button type="submit" class="btn-primary btn-reply-submit">
                <?= icon('send', '', 14) ?>
                Enviar respuesta para revisión
              </button>
              <p class="comment-form-status" data-reply-status aria-live="polite"></p>
            </form>
          </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="comment-empty">
            <?= icon('heart', '', 34) ?>
            <h3>Sé la primera persona en comentar</h3>
            <p>Tu experiencia puede orientar a otros visitantes del portal.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

</main><!-- /.content -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>

