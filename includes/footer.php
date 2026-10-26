<?php
// includes/footer.php - Footer institucional compartido del portal
$base = baseUrl();
$contactUrl = 'https://unitropico.edu.co/conocenos-quienes-somos-y-filosofia-institucional-de-unitropico/contactanos-atencion-al-ciudadano-y-canales-de-contacto-con-unitropico/';
$jsVersion = filemtime(__DIR__ . '/../assets/js/main.js');

// Índice público de búsqueda. Se construye en memoria con el contenido vigente;
// no crea tablas ni modifica los archivos JSON del portal.
$portalData = readData();
$programPages = is_array($portalData['program_pages'] ?? null) ? $portalData['program_pages'] : [];
$programSlugs = array_keys($programPages);
$searchIndex = [];
$searchKeys = [];

$portalUrl = static function (string $url) use ($base): string {
  $url = trim($url);
  if ($url === '' || preg_match('~^(?:https?:|mailto:|tel:|#)~i', $url)) {
    return $url;
  }
  return $base . '/' . ltrim($url, '/');
};

$addSearchEntry = static function (string $title, string $description, string $category, string $url) use (&$searchIndex, &$searchKeys): void {
  $title = trim(strip_tags($title));
  $description = trim(strip_tags($description));
  $url = trim($url);
  if ($title === '' || $url === '') return;

  $keySource = $title . '|' . $url;
  $key = function_exists('mb_strtolower') ? mb_strtolower($keySource, 'UTF-8') : strtolower($keySource);
  if (isset($searchKeys[$key])) return;
  $searchKeys[$key] = true;
  $searchIndex[] = [
    'title' => $title,
    'description' => $description,
    'category' => trim($category),
    'url' => $url,
  ];
};

foreach (getPages() as $portalPage) {
  $slug = (string)($portalPage['slug'] ?? '');
  if ($slug === '') continue;
  $url = $slug === 'inicio'
    ? $base . '/index.php'
    : (in_array($slug, $programSlugs, true)
      ? $base . '/pages/programa.php?slug=' . rawurlencode($slug)
      : $base . '/pages/' . $slug . '.php');
  $addSearchEntry(
    (string)($portalPage['title'] ?? $slug),
    (string)($portalPage['subtitle'] ?? ''),
    'Página',
    $url
  );
}

foreach (getAllCards() as $card) {
  if (!($card['is_active'] ?? true)) continue;
  $addSearchEntry(
    (string)($card['title'] ?? ''),
    (string)($card['description'] ?? ''),
    'Servicio',
    $portalUrl((string)($card['link_url'] ?? ''))
  );
}

foreach ($programPages as $slug => $programPage) {
  if (!is_array($programPage)) continue;
  $addSearchEntry(
    (string)($programPage['title'] ?? $slug),
    (string)($programPage['subtitle'] ?? $programPage['intro'] ?? ''),
    (string)($programPage['section'] ?? 'Programa'),
    $base . '/pages/programa.php?slug=' . rawurlencode((string)$slug)
  );
}

$addSearchEntry('Tabla de delegaciones', 'Consulta pública de delegaciones institucionales vigentes e históricas.', 'Consulta', $base . '/pages/delegaciones.php');
$addSearchEntry('Pruebas Psicotécnicas', 'Próximamente, pruebas psicotécnicas para personas nuevas antes de su vinculación.', 'Gestión del Talento Humano', $base . '/pages/pruebas-psicotecnicas.php');
?>
    <!-- End .content -->

    <footer class="site-footer">
      <div class="site-footer-main">
        <div class="footer-brand">
          <img src="<?= $base ?>/assets/uploads/logosimbolo.png" alt="Unitrópico">
        </div>

        <div class="footer-contact">
          <h2>Contacto</h2>
          <p>Carrera 19 # 39 - 40</p>
          <p>PBX +57 (601) 9157005</p>
          <p>Yopal, Casanare, Colombia</p>
        </div>

        <div class="footer-window">
          <h2>Ventanilla Única</h2>
          <a href="mailto:vur@unitropico.edu.co">vur@unitropico.edu.co</a>
          <a href="mailto:notificacionesjudiciales@unitropico.edu.co">notificacionesjudiciales@unitropico.edu.co</a>
          <a class="footer-contact-button" href="<?= e($contactUrl) ?>" target="_blank" rel="noopener noreferrer">
            Líneas de Contacto
          </a>
        </div>
      </div>

      <p class="footer-inspection">Universidad sujeta a inspección y vigilancia por el Ministerio de Educación Nacional.</p>

      <div class="site-footer-bottom">
        <nav>
          <span class="footer-text-link">Política de Privacidad</span>
          <span>·</span>
          <span class="footer-text-link">Términos y Condiciones</span>
          <span>·</span>
          <span class="footer-text-link">Mapa del Sitio</span>
        </nav>
        <span>Todos los derechos reservados</span>
      </div>
    </footer>

  </div><!-- /.main -->
</div><!-- /.layout -->

<nav class="portal-tools" aria-label="Herramientas de consulta y accesibilidad">
  <button class="portal-tool-button" type="button" data-portal-tool="search" aria-controls="portal-search-panel" aria-expanded="false">
    <?= icon('search', '', 21) ?>
    <span>Buscar</span>
  </button>
  <button class="portal-tool-button" type="button" data-portal-tool="accessibility" aria-controls="portal-accessibility-panel" aria-expanded="false">
    <span class="portal-tool-aa" aria-hidden="true">Aa</span>
    <span>Accesibilidad</span>
  </button>
  <button class="portal-tool-button" type="button" data-portal-tool="quick" aria-controls="portal-quick-panel" aria-expanded="false">
    <?= icon('layers', '', 21) ?>
    <span>Accesos rápidos</span>
  </button>
  <button class="portal-tool-button portal-break-trigger" type="button" data-portal-tool="active-break" aria-controls="portal-active-break-panel" aria-expanded="false">
    <?= icon('heart-pulse', '', 21) ?>
    <span>Pausa activa</span>
  </button>
</nav>

<div class="portal-tool-backdrop" data-portal-tool-backdrop hidden></div>

<section class="portal-tool-panel portal-search-panel" id="portal-search-panel" data-portal-panel="search" role="dialog" aria-modal="true" aria-labelledby="portal-search-title" hidden>
  <div class="portal-tool-panel-header">
    <div>
      <span class="portal-tool-kicker">Consulta del portal</span>
      <h2 id="portal-search-title">Buscar información</h2>
    </div>
    <button class="portal-panel-close" type="button" data-portal-close aria-label="Cerrar buscador"><?= icon('x', '', 20) ?></button>
  </div>
  <label class="portal-search-field" for="portal-search-input">
    <?= icon('search', '', 20) ?>
    <input id="portal-search-input" type="search" autocomplete="off" placeholder="Escribe un programa, servicio o tema" aria-describedby="portal-search-help">
  </label>
  <p class="portal-tool-help" id="portal-search-help">Busca entre páginas, programas y tarjetas publicadas en el portal.</p>
  <div class="portal-search-results" id="portal-search-results" aria-live="polite"></div>
</section>

<section class="portal-tool-panel portal-accessibility-panel" id="portal-accessibility-panel" data-portal-panel="accessibility" role="dialog" aria-modal="true" aria-labelledby="portal-accessibility-title" hidden>
  <div class="portal-tool-panel-header">
    <div>
      <span class="portal-tool-kicker">Preferencias personales</span>
      <h2 id="portal-accessibility-title">Accesibilidad</h2>
    </div>
    <button class="portal-panel-close" type="button" data-portal-close aria-label="Cerrar opciones de accesibilidad"><?= icon('x', '', 20) ?></button>
  </div>

  <div class="portal-a11y-group">
    <span class="portal-a11y-label">Tamaño del contenido</span>
    <div class="portal-a11y-size" role="group" aria-label="Tamaño del contenido">
      <button type="button" data-a11y-size="small" aria-pressed="false">A−</button>
      <button type="button" data-a11y-size="normal" aria-pressed="true">A</button>
      <button type="button" data-a11y-size="large" aria-pressed="false">A+</button>
    </div>
  </div>

  <button class="portal-a11y-option" type="button" data-a11y-toggle="contrast" aria-pressed="false">
    <span>Alto contraste</span><span class="portal-switch" aria-hidden="true"></span>
  </button>
  <button class="portal-a11y-option" type="button" data-a11y-toggle="underline" aria-pressed="false">
    <span>Subrayar enlaces</span><span class="portal-switch" aria-hidden="true"></span>
  </button>
  <button class="portal-a11y-option" type="button" data-a11y-toggle="grayscale" aria-pressed="false">
    <span>Escala de grises</span><span class="portal-switch" aria-hidden="true"></span>
  </button>
  <button class="portal-a11y-reset" type="button" data-a11y-reset>Restablecer preferencias</button>
</section>

<section class="portal-tool-panel portal-quick-panel" id="portal-quick-panel" data-portal-panel="quick" role="dialog" aria-modal="true" aria-labelledby="portal-quick-title" hidden>
  <div class="portal-tool-panel-header">
    <div>
      <span class="portal-tool-kicker">Navegación directa</span>
      <h2 id="portal-quick-title">Accesos rápidos</h2>
    </div>
    <button class="portal-panel-close" type="button" data-portal-close aria-label="Cerrar accesos rápidos"><?= icon('x', '', 20) ?></button>
  </div>
  <nav class="portal-quick-links" aria-label="Accesos rápidos del portal">
    <a href="<?= $base ?>/index.php"><?= icon('home', '', 19) ?><span>Inicio</span><?= icon('chevron-right', '', 17) ?></a>
    <a href="<?= $base ?>/pages/talento-humano.php"><?= icon('users', '', 19) ?><span>Gestión del Talento Humano</span><?= icon('chevron-right', '', 17) ?></a>
    <a href="<?= $base ?>/pages/calendario.php"><?= icon('calendar', '', 19) ?><span>Calendario de Actividades</span><?= icon('chevron-right', '', 17) ?></a>
    <a href="<?= $base ?>/pages/delegaciones.php"><?= icon('clipboard-check', '', 19) ?><span>Tabla de delegaciones</span><?= icon('chevron-right', '', 17) ?></a>
    <a href="<?= $base ?>/pages/pruebas-psicotecnicas.php"><?= icon('brain', '', 19) ?><span>Pruebas Psicotécnicas</span><?= icon('chevron-right', '', 17) ?></a>
    <a href="<?= e($contactUrl) ?>" target="_blank" rel="noopener noreferrer"><?= icon('external-link', '', 19) ?><span>Atención al Usuario</span><?= icon('chevron-right', '', 17) ?></a>
  </nav>
</section>

<section class="portal-tool-panel portal-active-break-panel" id="portal-active-break-panel" data-portal-panel="active-break" role="dialog" aria-modal="true" aria-labelledby="portal-active-break-title" hidden>
  <div class="portal-tool-panel-header">
    <div>
      <span class="portal-tool-kicker">Bienestar durante la jornada</span>
      <h2 id="portal-active-break-title">Momento de pausa</h2>
    </div>
    <button class="portal-panel-close" type="button" data-portal-close aria-label="Cerrar pausa activa"><?= icon('x', '', 20) ?></button>
  </div>

  <div class="portal-break-welcome" data-break-view="welcome">
    <div class="portal-break-intro" aria-hidden="true">
      <?= icon('heart-pulse', '', 34) ?>
      <span></span>
    </div>
    <p class="portal-break-lead">Regálate unos minutos para cambiar de postura, respirar y recuperar la concentración.</p>

    <fieldset class="portal-break-duration">
      <legend>Elige la duración</legend>
      <label>
        <input type="radio" name="portal-break-duration" value="180" checked>
        <span><strong>3 minutos</strong><small>Pausa breve</small></span>
      </label>
      <label>
        <input type="radio" name="portal-break-duration" value="300">
        <span><strong>5 minutos</strong><small>Rutina completa</small></span>
      </label>
    </fieldset>

    <button class="portal-break-primary" type="button" data-break-start>
      <span aria-hidden="true">▶</span> Comenzar pausa
    </button>

    <label class="portal-break-reminder-label" for="portal-break-reminder">
      Recordarme otra pausa
      <select id="portal-break-reminder" data-break-reminder>
        <option value="0">Sin recordatorio</option>
        <option value="60">En 60 minutos</option>
        <option value="90">En 90 minutos</option>
        <option value="120">En 120 minutos</option>
      </select>
    </label>
    <p class="portal-break-privacy">La preferencia se guarda únicamente en este navegador.</p>
  </div>

  <div class="portal-break-session" data-break-view="session" hidden>
    <div class="portal-break-progress-row">
      <span data-break-step>Ejercicio 1 de 5</span>
      <strong data-break-clock>00:36</strong>
    </div>
    <div class="portal-break-progress" role="progressbar" aria-label="Progreso de la pausa" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
      <span data-break-progress></span>
    </div>

    <div class="portal-break-visual" aria-hidden="true">
      <span class="portal-break-breathe"></span>
      <img src="<?= $base ?>/assets/uploads/pausa-respiracion.png" alt="" data-break-image>
    </div>
    <span class="portal-break-category" data-break-category>Respiración</span>
    <h3 data-break-title>Respira con calma</h3>
    <p data-break-instruction>Inhala lentamente por la nariz y exhala sin forzar el ritmo.</p>

    <div class="portal-break-controls">
      <button type="button" data-break-toggle>⏸ Pausar</button>
      <button type="button" data-break-next>Siguiente →</button>
    </div>
    <button class="portal-break-finish" type="button" data-break-finish>Finalizar la pausa</button>
  </div>

  <div class="portal-break-complete" data-break-view="complete" hidden>
    <div class="portal-break-complete-icon" aria-hidden="true">✓</div>
    <h3>Pausa completada</h3>
    <p>Te tomaste un momento para cuidar tu bienestar. Vuelve a tus actividades a tu propio ritmo.</p>
    <p class="portal-break-daily" data-break-daily aria-live="polite"></p>
    <button class="portal-break-primary" type="button" data-break-again>Realizar otra pausa</button>
  </div>

  <p class="portal-break-safety">Realiza movimientos suaves. Detente si sientes dolor, mareo o malestar y sigue las recomendaciones de tu profesional de salud.</p>
</section>

<script>
window.PORTAL_BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
window.PORTAL_SEARCH_INDEX = <?= json_encode($searchIndex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.PORTAL_ANALYTICS_TOKEN = <?= json_encode($analyticsRequestToken ?? '') ?>;
window.PORTAL_TURNSTILE_SITE_KEY = <?= json_encode($turnstileSiteKey ?? '') ?>;
window.onTurnstileReady = function () {
  document.dispatchEvent(new CustomEvent('turnstile-ready'));
};
</script>
<?php if (!empty($turnstileSiteKey)): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?onload=onTurnstileReady&amp;render=explicit" async defer></script>
<?php endif; ?>
<script src="<?= $base ?>/assets/js/main.js?v=<?= $jsVersion ?>"></script>
</body>
</html>
