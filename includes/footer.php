<?php
// includes/footer.php - Footer institucional compartido del portal
$base = baseUrl();
$contactUrl = 'https://unitropico.edu.co/conocenos-quienes-somos-y-filosofia-institucional-de-unitropico/contactanos-atencion-al-ciudadano-y-canales-de-contacto-con-unitropico/';
$jsVersion = filemtime(__DIR__ . '/../assets/js/main.js');
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

<div class="talibri-widget" id="talibri-widget" aria-live="polite">
  <div class="talibri-bubble" id="talibri-bubble">
    Respira profundo. Tu bienestar también hace parte del trabajo.
  </div>
  <button class="talibri-button" id="talibri-button" type="button" aria-label="Talibrí, mascota de Talento Humano">
    <span class="talibri-stage" aria-hidden="true">
      <span class="talibri-shadow"></span>
      <span class="talibri-layer talibri-base"></span>
      <span class="talibri-layer talibri-tail"></span>
      <span class="talibri-layer talibri-body"></span>
      <span class="talibri-layer talibri-head"></span>
      <span class="talibri-layer talibri-wing"></span>
      <span class="talibri-eye-shine"></span>
    </span>
    <span class="sr-only">Talibrí</span>
  </button>
</div>

<script>
window.PORTAL_BASE = <?= json_encode($base, JSON_UNESCAPED_SLASHES) ?>;
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
