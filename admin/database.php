<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base = baseUrl();
$adminName = currentAdminName();
$message = '';
$errorMessage = '';
$configPath = databaseConfigPath();
$driver = storageDriver();
$schemaReady = false;
$connectionError = '';
$databaseCounts = [];
$sourceData = [];
$sourceCounts = [];

if (file_exists(DATA_FILE)) {
    try {
        $sourceData = withFileLock(DATA_LOCK_FILE, LOCK_SH, fn() => decodeJsonFile(DATA_FILE));
        $sourceCounts = databaseDataCounts($sourceData);
    } catch (Throwable $error) {
        $connectionError = $error->getMessage();
    }
}

if ($configPath !== null) {
    try {
        $pdo = databaseConnection();
        $schemaReady = databaseSchemaIsReady($pdo);
        if ($schemaReady) $databaseCounts = databaseDataCounts(databaseReadData($pdo));
    } catch (Throwable $error) {
        $connectionError = $error->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_json'])) {
    try {
        if ($driver !== 'json') {
            throw new RuntimeException('La importación solo está disponible mientras el almacenamiento activo sea JSON.');
        }
        if (!verifyCurrentAdminPassword((string)($_POST['current_password'] ?? ''))) {
            throw new RuntimeException('La contraseña actual del administrador no es correcta.');
        }
        if ($configPath === null) {
            throw new RuntimeException('Primero crea el archivo privado de configuración de MariaDB.');
        }
        if (!$schemaReady) {
            throw new RuntimeException('Primero importa database/schema.sql desde phpMyAdmin.');
        }
        if (!$sourceData) {
            throw new RuntimeException('No se encontró data/data.json para importar.');
        }

        applyPendingDataMigrations($sourceData);
        databaseSaveData($sourceData);
        $databaseData = databaseReadData();
        $databaseCounts = databaseDataCounts($databaseData);
        $sourceCounts = databaseDataCounts($sourceData);

        if ($sourceCounts !== $databaseCounts) {
            throw new RuntimeException('La verificación de conteos no coincidió. MariaDB revirtió o no recibió todos los datos.');
        }
        if (($sourceData['admin']['password_hash'] ?? '') !== ($databaseData['admin']['password_hash'] ?? '')) {
            throw new RuntimeException('La verificación de la cuenta administrativa no coincidió.');
        }

        $message = 'Importación verificada. MariaDB contiene las páginas, tarjetas, comentarios, configuración, analítica y cuenta administrativa del JSON.';
    } catch (Throwable $error) {
        $errorMessage = $error->getMessage();
    }
}

$countsLabels = [
    'config' => 'Configuraciones',
    'pages' => 'Páginas',
    'cards' => 'Tarjetas',
    'program_pages' => 'Programas',
    'comments' => 'Comentarios',
    'analytics_months' => 'Meses de analítica',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Base de datos — Panel Admin Bienestar</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
  <meta name="robots" content="noindex,nofollow">
  <style>
    .db-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.db-card{border:1px solid var(--border);border-radius:14px;padding:18px;background:#fff}.db-card h3{font-size:15px;margin-bottom:10px;color:var(--text-p)}.db-status{display:inline-flex;align-items:center;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800}.db-ok{background:rgba(0,107,91,.1);color:#006b5b}.db-warn{background:rgba(210,148,0,.12);color:#8a6100}.db-counts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:14px}.db-count{padding:10px;border-radius:10px;background:#f5f8f7}.db-count strong{display:block;font-size:18px;color:var(--text-p)}.db-count span{font-size:11px;color:var(--text-m)}.db-steps{margin:0;padding-left:20px;color:var(--text-s);line-height:1.7;font-size:14px}.db-path{font-family:monospace;font-size:12px;word-break:break-word;background:#f5f8f7;padding:9px;border-radius:8px;color:var(--text-s)}@media(max-width:800px){.db-grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-brand"><div class="logo">U</div><div><h1>Panel Admin</h1><span>Unitrópico</span></div></div>
    <nav class="admin-nav">
      <span class="admin-nav-label">Gestión</span>
      <a href="<?= $base ?>/admin/dashboard.php" class="admin-nav-item"><?= icon('home','',16) ?> Dashboard</a>
      <a href="<?= $base ?>/admin/cards.php" class="admin-nav-item"><?= icon('layers','',16) ?> Tarjetas / Servicios</a>
      <a href="<?= $base ?>/admin/pages.php" class="admin-nav-item"><?= icon('layers','',16) ?> Subpáginas</a>
      <a href="<?= $base ?>/admin/comments.php" class="admin-nav-item"><?= icon('heart','',16) ?> Comentarios</a>
      <a href="<?= $base ?>/admin/media.php" class="admin-nav-item"><?= icon('image','',16) ?> Imágenes</a>
      <a href="<?= $base ?>/admin/database.php" class="admin-nav-item active"><?= icon('settings','',16) ?> Base de datos</a>
      <span class="admin-nav-label">Portal</span>
      <a href="<?= $base ?>/index.php" class="admin-nav-item" target="_blank"><?= icon('external-link','',16) ?> Ver Sitio</a>
    </nav>
    <div class="admin-sidebar-footer"><div style="font-size:11px;color:var(--text-m);padding:4px 8px;margin-bottom:4px;"><?= e($adminName) ?></div><?= adminLogoutForm($base) ?></div>
  </aside>

  <div class="admin-main">
    <div class="admin-topbar"><h2>Base de datos</h2></div>
    <div class="admin-content">
      <?php if ($message): ?><div class="alert alert-success"><?= icon('save','',15) ?> <?= e($message) ?></div><?php endif; ?>
      <?php if ($errorMessage): ?><div class="alert alert-error"><?= icon('x','',15) ?> <?= e($errorMessage) ?></div><?php endif; ?>

      <div class="db-grid">
        <section class="db-card">
          <h3>Almacenamiento activo</h3>
          <span class="db-status <?= $driver === 'mysql' ? 'db-ok' : 'db-warn' ?>"><?= $driver === 'mysql' ? 'MariaDB / PDO' : 'JSON (transición)' ?></span>
          <p style="margin-top:12px;color:var(--text-s);font-size:13px;line-height:1.6;">El cambio de backend es explícito para impedir que una falla de conexión divida los datos entre dos lugares.</p>
        </section>
        <section class="db-card">
          <h3>Conexión privada</h3>
          <span class="db-status <?= $configPath !== null && !$connectionError ? 'db-ok' : 'db-warn' ?>"><?= $configPath !== null && !$connectionError ? 'Conectada' : 'Pendiente' ?></span>
          <?php if ($configPath): ?><p class="db-path" style="margin-top:12px;"><?= e($configPath) ?></p><?php endif; ?>
          <?php if ($connectionError): ?><p style="margin-top:10px;color:#a32626;font-size:12px;"><?= e($connectionError) ?></p><?php endif; ?>
        </section>
        <section class="db-card">
          <h3>Esquema MariaDB</h3>
          <span class="db-status <?= $schemaReady ? 'db-ok' : 'db-warn' ?>"><?= $schemaReady ? 'Listo' : 'Sin importar' ?></span>
          <?php if ($databaseCounts): ?><div class="db-counts"><?php foreach ($countsLabels as $key => $label): ?><div class="db-count"><strong><?= (int)($databaseCounts[$key] ?? 0) ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div><?php endif; ?>
        </section>
        <section class="db-card">
          <h3>Origen JSON</h3>
          <span class="db-status <?= $sourceCounts ? 'db-ok' : 'db-warn' ?>"><?= $sourceCounts ? 'Disponible' : 'No encontrado' ?></span>
          <?php if ($sourceCounts): ?><div class="db-counts"><?php foreach ($countsLabels as $key => $label): ?><div class="db-count"><strong><?= (int)($sourceCounts[$key] ?? 0) ?></strong><span><?= e($label) ?></span></div><?php endforeach; ?></div><?php endif; ?>
        </section>
      </div>

      <section class="widget" style="margin-top:18px;">
        <div class="widget-header"><h3 class="widget-title"><?= icon('settings','',16) ?> Migración inicial segura</h3></div>
        <ol class="db-steps">
          <li>Rota la contraseña de la base que fue compartida en el chat.</li>
          <li>Crea fuera de <code>public_html</code> el archivo <code>private/bienestar-database.php</code> usando la plantilla, con <code>storage =&gt; json</code>.</li>
          <li>Importa <code>database/schema.sql</code> en la base desde phpMyAdmin.</li>
          <li>Ejecuta la importación aquí y comprueba que los conteos coincidan.</li>
          <li>Cambia solamente <code>storage =&gt; mysql</code> en el archivo privado y vuelve a abrir el portal.</li>
        </ol>

        <form method="POST" style="max-width:520px;margin-top:20px;" onsubmit="return confirm('Esto reemplazará el contenido actual de las tablas del portal con la copia JSON. ¿Continuar?');">
          <?= csrfField() ?>
          <label class="form-label" for="current_password">Contraseña actual del administrador</label>
          <input id="current_password" name="current_password" type="password" class="form-input" autocomplete="current-password" required>
          <p class="form-hint">Se solicita para autorizar la copia. La contraseña no se guarda ni se envía a GitHub.</p>
          <button type="submit" name="import_json" class="btn btn-accent" style="margin-top:12px;" <?= $driver !== 'json' || !$schemaReady || !$sourceCounts ? 'disabled' : '' ?>>
            <?= icon('save','',14) ?> Importar JSON a MariaDB y verificar
          </button>
        </form>
      </section>
    </div>
  </div>
</div>
</body>
</html>

