<?php
// admin/media.php — Gestión de Imágenes
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base      = baseUrl();
$uploadDir = __DIR__ . '/../assets/uploads/';
$msg       = '';

// Create uploads dir if it doesn't exist
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ── Handle DELETE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $fname = basename($_POST['filename'] ?? '');
    $fpath = $uploadDir . $fname;
    $allowedDeleteExtensions = ['jpg','jpeg','png','gif','webp'];
    $deleteExtension = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
    if ($fname && in_array($deleteExtension, $allowedDeleteExtensions, true) && file_exists($fpath) && !str_contains($fname, '..')) {
        unlink($fpath);
        $msg = 'Imagen eliminada.';
    }
}

// ── Handle UPLOAD ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file      = $_FILES['image'];
    $allowed   = ['image/jpeg','image/png','image/gif','image/webp'];
    $maxSize   = 5 * 1024 * 1024; // 5MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $msg = 'ERROR: Fallo al subir el archivo.';
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
        if ($finfo) finfo_close($finfo);
        if (!isset($allowed[$mime])) {
        $msg = 'ERROR: Solo se permiten imágenes JPEG, PNG, GIF o WebP.';
        } elseif ($file['size'] > $maxSize) {
        $msg = 'ERROR: El archivo supera los 5MB.';
        } else {
        $ext      = $allowed[$mime];
        $customName = preg_replace('/[^a-z0-9\-_]/', '', strtolower($_POST['custom_name'] ?? ''));
        $newName  = $customName !== '' ? $customName . '.' . $ext : bin2hex(random_bytes(12)) . '.' . $ext;
        $dest     = $uploadDir . $newName;
        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $msg = 'Imagen subida: ' . $newName;
        } else {
            $msg = 'ERROR: No se pudo guardar el archivo.';
        }
        }
    }
}

// Get uploaded files
$extensions = ['jpg','jpeg','png','gif','webp'];
$files = [];
foreach ($extensions as $ext) {
    $files = array_merge($files, glob($uploadDir . '*.' . $ext) ?: []);
    $files = array_merge($files, glob($uploadDir . '*.' . strtoupper($ext)) ?: []);
}
usort($files, fn($a,$b) => filemtime($b) - filemtime($a));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Imágenes — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
  <style>
    .media-grid { display: grid; grid-template-columns: repeat(auto-fill,minmax(160px,1fr)); gap: 14px; }
    .media-item {
      background: var(--bg-card); border: 1px solid var(--border);
      border-radius: var(--radius-md); overflow: hidden; transition: var(--transition);
    }
    .media-item:hover { border-color: rgba(0,201,167,0.4); }
    .media-thumb {
      width: 100%; height: 110px; object-fit: cover; display: block;
      background: rgba(255,255,255,0.04);
    }
    .media-info { padding: 8px 10px; }
    .media-info p { font-size: 11px; color: var(--text-m); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .media-actions { display: flex; gap: 4px; padding: 0 8px 8px; }
    .upload-zone {
      border: 2px dashed var(--border); border-radius: var(--radius-md);
      padding: 36px; text-align: center; cursor: pointer;
      transition: var(--transition); margin-bottom: 22px;
    }
    .upload-zone:hover, .upload-zone.drag-over {
      border-color: var(--accent); background: rgba(0,201,167,0.06);
    }
    .upload-zone h3 { font-size: 14px; color: var(--text-p); margin-bottom: 6px; }
    .upload-zone p  { font-size: 12px; color: var(--text-m); }
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
      <a href="<?= $base ?>/admin/media.php" class="admin-nav-item active"><?= icon('image','',16) ?> Imágenes</a>
      <a href="<?= $base ?>/admin/database.php" class="admin-nav-item"><?= icon('settings','',16) ?> Base de datos</a>
      <span class="admin-nav-label">Portal</span>
      <a href="<?= $base ?>/index.php" class="admin-nav-item" target="_blank"><?= icon('external-link','',16) ?> Ver Sitio</a>
    </nav>
    <div class="admin-sidebar-footer">
      <div style="font-size:11px;color:var(--text-m);padding:4px 8px;margin-bottom:4px;"><?= e(currentAdminName()) ?></div>
      <?= adminLogoutForm($base) ?>
    </div>
  </aside>

  <div class="admin-main">
    <div class="admin-topbar">
      <h2>Gestión de Imágenes</h2>
      <div class="admin-topbar-right">
        <span style="font-size:12px;color:var(--text-m);"><?= count($files) ?> archivos · Máx. 5MB por imagen</span>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($msg): ?>
      <div class="alert <?= str_starts_with($msg,'ERROR') ? 'alert-error' : 'alert-success' ?>">
        <?= str_starts_with($msg,'ERROR') ? icon('x','',15) : icon('image','',15) ?> <?= e($msg) ?>
      </div>
      <?php endif; ?>

      <!-- Upload form -->
      <div class="widget" style="margin-bottom:20px;">
        <div class="widget-header">
          <h3 class="widget-title"><?= icon('image','',16) ?> Subir Nueva Imagen</h3>
        </div>
        <form method="POST" enctype="multipart/form-data" id="upload-form">
          <?= csrfField() ?>
          <div class="upload-zone" id="upload-zone" onclick="document.getElementById('file-input').click()">
            <div style="color:var(--accent);margin-bottom:10px;"><?= icon('image','',36) ?></div>
            <h3>Arrastra una imagen aquí o haz clic para seleccionar</h3>
            <p>JPEG, PNG, GIF, WebP — Máximo 5MB</p>
          </div>
          <input type="file" id="file-input" name="image" accept="image/*" style="display:none;" onchange="previewFile(this)">

          <div id="file-preview" style="display:none;margin-bottom:14px;">
            <img id="preview-img" style="max-height:120px;border-radius:8px;border:1px solid var(--border);">
            <p id="preview-name" style="font-size:12px;color:var(--text-m);margin-top:6px;"></p>
          </div>

          <div class="form-grid">
            <div class="form-group">
              <label class="form-label">Nombre personalizado (opcional)</label>
              <input name="custom_name" type="text" class="form-input" placeholder="ej: hero-bg, logo-unitropico">
              <span class="form-hint">Sin extensión. Si se deja vacío, se genera automáticamente.</span>
            </div>
            <div class="form-group" style="justify-content:flex-end;">
              <button type="submit" class="btn btn-accent" style="align-self:flex-end;">
                <?= icon('image','',15) ?> Subir Imagen
              </button>
            </div>
          </div>
        </form>

        <!-- Hero bg note -->
        <div style="margin-top:12px;padding:12px 14px;background:rgba(0,201,167,0.06);border:1px solid rgba(0,201,167,0.15);border-radius:var(--radius-sm);">
          <p style="font-size:12px;color:var(--text-s);">
            <?= icon('sparkles','',13) ?>
            <strong style="color:var(--accent);">Tip:</strong> Para cambiar la imagen de fondo del hero, sube una imagen con el nombre <code style="background:rgba(255,255,255,0.08);padding:1px 6px;border-radius:4px;">hero-bg</code> (JPEG/PNG).
          </p>
        </div>
      </div>

      <!-- Media grid -->
      <div class="widget">
        <div class="widget-header">
          <h3 class="widget-title"><?= icon('image','',16) ?> Biblioteca de Imágenes (<?= count($files) ?>)</h3>
        </div>
        <?php if (empty($files)): ?>
        <div style="text-align:center;padding:40px;color:var(--text-m);">
          <?= icon('image','',32) ?>
          <p style="margin-top:10px;font-size:13px;">No hay imágenes subidas todavía.</p>
        </div>
        <?php else: ?>
        <div class="media-grid">
          <?php foreach ($files as $fpath):
            $fname = basename($fpath);
            $fsize = round(filesize($fpath) / 1024, 1);
            $furl  = $base . '/assets/uploads/' . $fname;
          ?>
          <div class="media-item">
            <img src="<?= e($furl) ?>" alt="<?= e($fname) ?>" class="media-thumb" loading="lazy">
            <div class="media-info">
              <p title="<?= e($fname) ?>"><?= e($fname) ?></p>
              <p><?= $fsize ?> KB</p>
            </div>
            <div class="media-actions">
              <button onclick="copyUrl('<?= e($furl) ?>')" class="btn btn-outline btn-sm" style="flex:1;font-size:10.5px;padding:5px 8px;">
                Copiar URL
              </button>
              <form method="POST" onsubmit="return confirm('¿Eliminar esta imagen?');" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="filename" value="<?= e($fname) ?>">
                <button type="submit" name="delete_file" class="btn btn-danger btn-sm btn-icon">
                  <?= icon('trash','',13) ?>
                </button>
              </form>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<div id="copy-toast" style="position:fixed;bottom:24px;right:24px;background:var(--accent);color:#080D1A;padding:10px 20px;border-radius:40px;font-size:12.5px;font-weight:700;opacity:0;transition:opacity 0.3s;z-index:999;">
  ✓ URL copiada al portapapeles
</div>

<script>
function copyUrl(url) {
  navigator.clipboard.writeText(url).then(() => {
    const toast = document.getElementById('copy-toast');
    toast.style.opacity = '1';
    setTimeout(() => toast.style.opacity = '0', 2000);
  });
}

function previewFile(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('preview-img').src = e.target.result;
    document.getElementById('preview-name').textContent = file.name + ' — ' + Math.round(file.size/1024) + ' KB';
    document.getElementById('file-preview').style.display = 'block';
  };
  reader.readAsDataURL(file);
}

// Drag & drop
const zone = document.getElementById('upload-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('drag-over');
  const files = e.dataTransfer.files;
  if (files.length) {
    document.getElementById('file-input').files = files;
    previewFile(document.getElementById('file-input'));
  }
});
</script>
<script src="<?= $base ?>/assets/js/admin.js"></script>
</body>
</html>
