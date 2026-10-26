<?php
// admin/hero-carousel.php — Gestión del banner principal
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/hero-carousel.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base = baseUrl();
$uploadDir = __DIR__ . '/../assets/uploads/';
$error = '';
$noticeMessages = [
    'uploaded' => 'Imagen agregada al carrusel.',
    'saved' => 'Configuración del carrusel actualizada.',
    'deleted' => 'Imagen retirada del carrusel.',
];
$notice = $noticeMessages[$_GET['notice'] ?? ''] ?? '';

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function redirectHeroAdmin(string $base, string $notice): never {
    header('Location: ' . $base . '/admin/hero-carousel.php?notice=' . rawurlencode($notice));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'upload') {
            $existing = readHeroCarouselData()['slides'];
            if (count($existing) >= 8) {
                throw new RuntimeException('El carrusel admite un máximo de 8 imágenes.');
            }

            $file = $_FILES['slide_image'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Selecciona una imagen válida para el carrusel.');
            }
            if ((int)$file['size'] > 6 * 1024 * 1024) {
                throw new RuntimeException('La imagen supera el límite de 6 MB.');
            }

            $mimeMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? (string)finfo_file($finfo, $file['tmp_name']) : '';
            if ($finfo) finfo_close($finfo);
            if (!isset($mimeMap[$mime]) || @getimagesize($file['tmp_name']) === false) {
                throw new RuntimeException('Solo se permiten imágenes reales en formato JPG, PNG o WebP.');
            }

            $id = 'slide-' . bin2hex(random_bytes(6));
            $filename = 'hero-' . $id . '.' . $mimeMap[$mime];
            $destination = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new RuntimeException('No fue posible guardar la imagen. Verifica los permisos de assets/uploads/.');
            }

            $alt = heroCarouselText((string)($_POST['slide_alt'] ?? ''), 180);
            if ($alt === '') $alt = 'Actividad de Bienestar Social Laboral en Unitrópico';

            updateHeroCarouselData(function (array &$data) use ($id, $filename, $alt): void {
                $data['slides'][] = [
                    'id' => $id,
                    'image_url' => 'assets/uploads/' . $filename,
                    'alt' => $alt,
                    'position' => 'center center',
                    'active' => true,
                    'sort_order' => count($data['slides']) + 1,
                ];
            });
            redirectHeroAdmin($base, 'uploaded');
        }

        if ($action === 'save') {
            $submitted = is_array($_POST['slides'] ?? null) ? $_POST['slides'] : [];
            $interval = max(4, min(12, (int)($_POST['interval_seconds'] ?? 7))) * 1000;
            updateHeroCarouselData(function (array &$data) use ($submitted, $interval): void {
                $data['interval'] = $interval;
                foreach ($data['slides'] as &$slide) {
                    $id = (string)$slide['id'];
                    $input = is_array($submitted[$id] ?? null) ? $submitted[$id] : [];
                    $slide['alt'] = heroCarouselText((string)($input['alt'] ?? $slide['alt']), 180);
                    $slide['position'] = (string)($input['position'] ?? $slide['position']);
                    $slide['sort_order'] = max(0, (int)($input['sort_order'] ?? $slide['sort_order']));
                    $slide['active'] = isset($input['active']);
                }
                unset($slide);
            });
            redirectHeroAdmin($base, 'saved');
        }

        if ($action === 'delete') {
            $slideId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['slide_id'] ?? ''));
            $imageToDelete = '';
            updateHeroCarouselData(function (array &$data) use ($slideId, &$imageToDelete): void {
                foreach ($data['slides'] as $slide) {
                    if (($slide['id'] ?? '') === $slideId) $imageToDelete = (string)($slide['image_url'] ?? '');
                }
                $data['slides'] = array_values(array_filter(
                    $data['slides'],
                    static fn(array $slide): bool => ($slide['id'] ?? '') !== $slideId
                ));
            });

            $filename = basename($imageToDelete);
            if (str_starts_with($filename, 'hero-slide-')) {
                $path = $uploadDir . $filename;
                if (is_file($path)) @unlink($path);
            }
            redirectHeroAdmin($base, 'deleted');
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$carousel = readHeroCarouselData();
$slides = $carousel['slides'];
$positionLabels = [
    'center center' => 'Centro',
    'center top' => 'Parte superior',
    'center bottom' => 'Parte inferior',
    'left center' => 'Izquierda',
    'right center' => 'Derecha',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Banner principal — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
  <meta name="robots" content="noindex,nofollow">
  <style>
    .hero-admin-intro { display:grid;grid-template-columns:minmax(0,1fr) auto;gap:20px;align-items:center; }
    .hero-admin-intro p { max-width:720px;color:var(--text-s);font-size:13px;line-height:1.6; }
    .hero-upload-grid { display:grid;grid-template-columns:minmax(0,1fr) minmax(280px,.55fr);gap:18px;align-items:end; }
    .hero-upload-zone { min-height:150px;display:grid;place-items:center;padding:24px;border:2px dashed var(--border);border-radius:var(--radius-md);background:var(--bg-soft);text-align:center;cursor:pointer; }
    .hero-upload-zone:hover { border-color:var(--accent); }
    .hero-upload-zone strong { display:block;margin:8px 0 4px;color:var(--text-p);font-size:14px; }
    .hero-upload-zone span { color:var(--text-m);font-size:12px; }
    .hero-slide-list { display:grid;gap:14px; }
    .hero-slide-admin { display:grid;grid-template-columns:220px minmax(0,1fr) auto;gap:16px;padding:14px;border:1px solid var(--border);border-radius:var(--radius-md);background:var(--bg-card); }
    .hero-slide-preview { width:220px;height:126px;object-fit:cover;border-radius:6px;border:1px solid var(--border);background:var(--bg-soft); }
    .hero-slide-fields { display:grid;grid-template-columns:minmax(0,1fr) 170px 90px;gap:10px;align-content:start; }
    .hero-slide-fields .wide { grid-column:1 / -1; }
    .hero-slide-toggle { display:flex;align-items:center;gap:8px;color:var(--text-s);font-size:12px; }
    .hero-slide-delete { align-self:start; }
    .hero-admin-note { margin-top:12px;padding:12px 14px;border-left:3px solid var(--accent);background:rgba(0,201,167,.06);color:var(--text-s);font-size:12px;line-height:1.5; }
    @media(max-width:900px){.hero-admin-intro,.hero-upload-grid{grid-template-columns:1fr}.hero-slide-admin{grid-template-columns:1fr}.hero-slide-preview{width:100%;height:190px}.hero-slide-fields{grid-template-columns:1fr}.hero-slide-fields .wide{grid-column:auto}.hero-slide-delete{justify-self:start}}
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
      <a href="<?= $base ?>/admin/hero-carousel.php" class="admin-nav-item active"><?= icon('image','',16) ?> Banner principal</a>
      <?php if (delegationsModuleEnabled()): ?><a href="<?= $base ?>/admin/delegations.php" class="admin-nav-item"><?= icon('clipboard-check','',16) ?> Delegaciones</a><?php endif; ?>
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
      <h2>Banner principal</h2>
      <div class="admin-topbar-right"><a href="<?= $base ?>/index.php" target="_blank" class="btn btn-outline btn-sm"><?= icon('external-link','',13) ?> Ver portada</a></div>
    </div>

    <div class="admin-content">
      <?php if ($notice): ?><div class="alert alert-success"><?= icon('save','',15) ?> <?= e($notice) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-error"><?= icon('x','',15) ?> <?= e($error) ?></div><?php endif; ?>

      <div class="widget" style="margin-bottom:20px;">
        <div class="hero-admin-intro">
          <div>
            <h3 class="widget-title" style="margin-bottom:7px;"><?= icon('image','',17) ?> Carrusel de bienvenida</h3>
            <p>Sube fotografías horizontales del bienestar universitario. El banner cambia automáticamente, permite navegación manual y respeta la preferencia de movimiento reducido del visitante.</p>
          </div>
          <span class="badge-sm badge-blue"><?= count($slides) ?> de 8 imágenes</span>
        </div>
      </div>

      <div class="widget" style="margin-bottom:20px;">
        <div class="widget-header"><h3 class="widget-title"><?= icon('upload','',16) ?> Agregar imagen</h3></div>
        <form method="POST" enctype="multipart/form-data" class="hero-upload-grid">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="upload">
          <label class="hero-upload-zone" for="hero-slide-image">
            <span><?= icon('image','',32) ?></span>
            <span><strong>Seleccionar una fotografía</strong>JPG, PNG o WebP · máximo 6 MB</span>
          </label>
          <input id="hero-slide-image" name="slide_image" type="file" accept="image/jpeg,image/png,image/webp" required style="position:absolute;left:-9999px;">
          <div>
            <div class="form-group">
              <label class="form-label" for="slide-alt">Descripción de la imagen</label>
              <input class="form-input" id="slide-alt" name="slide_alt" maxlength="180" placeholder="Ej.: Jornada de bienestar de servidores de Unitrópico">
              <span class="form-hint">Recomendado para accesibilidad. Usa fotografías horizontales de mínimo 1600 × 700 px.</span>
            </div>
            <button class="btn btn-accent" type="submit" style="margin-top:12px;"><?= icon('upload','',15) ?> Cargar al carrusel</button>
          </div>
        </form>
      </div>

      <div class="widget">
        <div class="widget-header"><h3 class="widget-title"><?= icon('settings','',16) ?> Orden y visualización</h3></div>
        <form method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="save">
          <div class="form-group" style="max-width:280px;margin-bottom:18px;">
            <label class="form-label" for="interval-seconds">Tiempo entre imágenes</label>
            <select class="form-select" id="interval-seconds" name="interval_seconds">
              <?php for ($seconds = 4; $seconds <= 12; $seconds++): ?>
              <option value="<?= $seconds ?>" <?= (int)round($carousel['interval'] / 1000) === $seconds ? 'selected' : '' ?>><?= $seconds ?> segundos</option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="hero-slide-list">
            <?php foreach ($slides as $slide): ?>
            <article class="hero-slide-admin">
              <img class="hero-slide-preview" src="<?= $base ?>/<?= e($slide['image_url']) ?>" alt="<?= e($slide['alt']) ?>" style="object-position:<?= e($slide['position']) ?>;">
              <div class="hero-slide-fields">
                <div class="form-group wide">
                  <label class="form-label">Descripción accesible</label>
                  <input class="form-input" name="slides[<?= e($slide['id']) ?>][alt]" value="<?= e($slide['alt']) ?>" maxlength="180">
                </div>
                <div class="form-group">
                  <label class="form-label">Enfoque</label>
                  <select class="form-select" name="slides[<?= e($slide['id']) ?>][position]">
                    <?php foreach ($positionLabels as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $slide['position'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group">
                  <label class="form-label">Orden</label>
                  <input class="form-input" type="number" min="0" max="99" name="slides[<?= e($slide['id']) ?>][sort_order]" value="<?= (int)$slide['sort_order'] ?>">
                </div>
                <label class="hero-slide-toggle">
                  <input type="checkbox" name="slides[<?= e($slide['id']) ?>][active]" value="1" <?= !empty($slide['active']) ? 'checked' : '' ?>> Visible en el portal
                </label>
              </div>
              <div class="hero-slide-delete">
                <button type="button" class="btn btn-danger btn-sm" onclick="if (confirm('¿Retirar esta imagen del carrusel?')) document.getElementById('delete-<?= e($slide['id']) ?>').submit();"><?= icon('trash','',13) ?> Eliminar</button>
              </div>
            </article>
            <?php endforeach; ?>
          </div>

          <div class="hero-admin-note">Los archivos subidos desde esta pantalla y la configuración del carrusel permanecen en producción aunque posteriormente actualices el portal desde GitHub.</div>
          <button class="btn btn-accent" type="submit" style="margin-top:18px;"><?= icon('save','',15) ?> Guardar cambios</button>
        </form>

        <?php foreach ($slides as $slide): ?>
        <form id="delete-<?= e($slide['id']) ?>" method="POST">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="slide_id" value="<?= e($slide['id']) ?>">
        </form>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<script src="<?= $base ?>/assets/js/admin.js"></script>
</body>
</html>
