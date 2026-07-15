<?php
// admin/cards.php — Gestión de Tarjetas de Servicios
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base      = baseUrl();
$pages     = getPages();
$msg       = '';
$uploadDir = __DIR__ . '/../assets/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ── Handle DELETE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_card'])) {
    deleteCard($_POST['page_slug_del'], $_POST['card_id']);
    $msg = 'Tarjeta eliminada.';
}

// Handle SAVE (create/update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_card'])) {
    $pageSlug = trim($_POST['page_slug'] ?? '');
    $imageUrl = trim($_POST['image_url'] ?? '');
    $uploadError = '';

    if (!empty($_FILES['card_image_file']['name'])) {
        $file = $_FILES['card_image_file'];
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];
        $maxSize = 5 * 1024 * 1024;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'ERROR: No se pudo subir la imagen.';
        } elseif ($file['size'] > $maxSize) {
            $uploadError = 'ERROR: La imagen supera los 5MB.';
        } else {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!isset($allowed[$mime])) {
                $uploadError = 'ERROR: Solo se permiten imagenes JPG, PNG, GIF o WebP.';
            } else {
                $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
                $safeName = preg_replace('/[^a-z0-9\-_]/', '-', strtolower($baseName));
                $safeName = trim(preg_replace('/-+/', '-', $safeName), '-');
                if ($safeName === '') $safeName = 'tarjeta';
                $newName = $safeName . '-' . uniqid() . '.' . $allowed[$mime];
                $dest = $uploadDir . $newName;

                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $imageUrl = 'assets/uploads/' . $newName;
                } else {
                    $uploadError = 'ERROR: No se pudo guardar la imagen.';
                }
            }
        }
    }

    if ($uploadError) {
        $msg = $uploadError;
    } else {
        $card = [
            'id'          => trim($_POST['card_id']     ?? '') ?: uniqid('c'),
            'title'       => trim($_POST['title']       ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'icon'        => trim($_POST['icon']        ?? 'star'),
            'color'       => trim($_POST['color']       ?? '#006056'),
            'image_url'   => $imageUrl,
            'link_url'    => trim($_POST['link_url']    ?? ''),
            'link_label'  => trim($_POST['link_label']  ?? ''),
            'sort_order'  => (int)($_POST['sort_order'] ?? 0),
            'is_active'   => isset($_POST['is_active']),
        ];
        saveCard($pageSlug, $card);
        $msg = 'Tarjeta guardada correctamente.';
    }
}

// Get all cards for display
$filterPage = $_GET['page_slug'] ?? '';
$cards = getAllCards();
if ($filterPage) {
    $cards = array_filter($cards, fn($c) => $c['page_slug'] === $filterPage);
    $cards = array_values($cards);
}

$availableIcons = ['star','users','graduation-cap','heart','award','brain','coffee','heart-pulse',
                   'sparkles','shield-check','trending-up','sun','clipboard-check','bar-chart-2',
                   'calendar','home','door-open','refresh-cw','layers','image','settings','user',
                   'save','external-link','plus','edit','trash'];

$imageFiles = [];
foreach (['jpg','jpeg','png','gif','webp'] as $ext) {
    $imageFiles = array_merge($imageFiles, glob($uploadDir . '*.' . $ext) ?: []);
    $imageFiles = array_merge($imageFiles, glob($uploadDir . '*.' . strtoupper($ext)) ?: []);
}
usort($imageFiles, fn($a, $b) => strcasecmp(basename($a), basename($b)));

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Gestión de Tarjetas — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <!-- SIDEBAR -->
  <aside class="admin-sidebar">
    <div class="admin-brand"><div class="logo">U</div><div><h1>Panel Admin</h1><span>Unitrópico</span></div></div>
    <nav class="admin-nav">
      <span class="admin-nav-label">Gestión</span>
      <a href="<?= $base ?>/admin/dashboard.php" class="admin-nav-item"><?= icon('home','',16) ?> Dashboard</a>
      <a href="<?= $base ?>/admin/cards.php" class="admin-nav-item active"><?= icon('layers','',16) ?> Tarjetas / Servicios</a>
      <a href="<?= $base ?>/admin/pages.php" class="admin-nav-item"><?= icon('layers','',16) ?> Subpáginas</a>
      <a href="<?= $base ?>/admin/media.php" class="admin-nav-item"><?= icon('image','',16) ?> Imágenes</a>
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
      <h2>Tarjetas de Servicios</h2>
      <div class="admin-topbar-right">
        <button onclick="document.getElementById('modal-card').classList.remove('hidden')" class="btn btn-accent btn-sm">
          <?= icon('plus','',14) ?> Nueva Tarjeta
        </button>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($msg): ?>
      <div class="alert <?= str_starts_with($msg, 'ERROR') ? 'alert-error' : 'alert-success' ?>">
        <?= str_starts_with($msg, 'ERROR') ? icon('x','',15) : icon('save','',15) ?> <?= e($msg) ?>
      </div>
      <?php endif; ?>

      <!-- Filter by page -->
      <div class="widget" style="margin-bottom:16px;padding:14px 18px;">
        <form method="GET" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
          <label class="form-label" style="margin:0;">Filtrar por página:</label>
          <select name="page_slug" class="form-select" style="width:auto;" onchange="this.form.submit()">
            <option value="">— Todas las páginas —</option>
            <?php foreach ($pages as $p): ?>
            <option value="<?= e($p['slug']) ?>" <?= $filterPage === $p['slug'] ? 'selected' : '' ?>>
              <?= e($p['title']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <!-- Cards table -->
      <div class="widget">
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Tarjeta</th>
                <th>Página</th>
                <th>Imagen</th>
                <th>Ícono / Color</th>
                <th>Orden</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($cards)): ?>
              <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-m);">No hay tarjetas. Crea la primera.</td></tr>
              <?php endif; ?>
              <?php foreach ($cards as $c): ?>
              <tr>
                <td>
                  <div style="font-weight:600;font-size:13px;color:var(--text-p);"><?= e($c['title']) ?></div>
                  <div style="font-size:11px;color:var(--text-m);max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e(substr($c['description'],0,80)) ?>…</div>
                </td>
                <td><span class="badge-sm badge-blue"><?= e($c['page_slug']) ?></span></td>
                <td>
                  <?php if (!empty($c['image_url'])): ?>
                  <img src="<?= e($base . '/' . ltrim($c['image_url'], '/')) ?>" alt="<?= e($c['title']) ?>" class="admin-thumb">
                  <?php else: ?>
                  <span style="font-size:11px;color:var(--text-m);">Sin imagen</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display:flex;align-items:center;gap:8px;">
                    <div style="width:16px;height:16px;border-radius:4px;background:<?= e($c['color']) ?>;border:1px solid rgba(255,255,255,0.1);"></div>
                    <code style="font-size:11px;color:var(--text-m);"><?= e($c['icon']) ?></code>
                  </div>
                </td>
                <td><span style="color:var(--text-m);">#<?= (int)$c['sort_order'] ?></span></td>
                <td>
                  <?php if ($c['is_active']): ?>
                    <span class="badge-sm badge-green">Activo</span>
                  <?php else: ?>
                    <span class="badge-sm badge-red">Inactivo</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <?php
                      $cardJson = json_encode($c, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
                    ?>
                    <button type="button" data-card="<?= e($cardJson) ?>" onclick="editCardFromButton(this)" class="btn btn-outline btn-sm btn-icon" title="Editar" aria-label="Editar <?= e($c['title']) ?>">
                      <?= icon('edit','',14) ?>
                    </button>
                    <form method="POST" onsubmit="return confirm('¿Eliminar esta tarjeta?');" style="display:inline;">
                      <?= csrfField() ?>
                      <input type="hidden" name="card_id" value="<?= e($c['id']) ?>">
                      <input type="hidden" name="page_slug_del" value="<?= e($c['page_slug']) ?>">
                      <button type="submit" name="delete_card" class="btn btn-danger btn-sm btn-icon" title="Eliminar">
                        <?= icon('trash','',14) ?>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Create/Edit Card -->
<div id="modal-card" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-title">Nueva Tarjeta</h3>
      <button onclick="closeModal()"><?= icon('x','',18) ?></button>
    </div>
    <form method="POST" id="card-form" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="card_id" id="f-id" value="0">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Página destino *</label>
          <select name="page_slug" id="f-page" class="form-select" required>
            <?php foreach ($pages as $p): ?>
            <option value="<?= e($p['slug']) ?>"><?= e($p['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Orden de aparición</label>
          <input name="sort_order" id="f-order" type="number" class="form-input" value="0" min="0">
        </div>
        <div class="form-group full">
          <label class="form-label">Título de la tarjeta *</label>
          <input name="title" id="f-title" type="text" class="form-input" required placeholder="Ej: Política de Estímulos">
        </div>
        <div class="form-group full">
          <label class="form-label">Descripción</label>
          <textarea name="description" id="f-description" class="form-textarea" placeholder="Descripción breve del servicio..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">URL del enlace</label>
          <input name="link_url" id="f-link" type="text" class="form-input" placeholder="https://... o pages/talento-humano.php">
          <span class="form-hint">Deja vacío si no hay enlace</span>
        </div>
        <div class="form-group">
          <label class="form-label">Etiqueta del botón</label>
          <input name="link_label" id="f-link-label" type="text" class="form-input" placeholder="Ej: Ver más, Acceder...">
        </div>
        <div class="form-group full">
          <label class="form-label">Imagen de la tarjeta</label>
          <div class="image-picker">
            <div class="image-picker-preview">
              <img id="f-image-preview" src="" alt="Vista previa">
              <span id="f-image-empty">Sin imagen</span>
            </div>
            <div class="image-picker-fields">
              <select name="image_url" id="f-image" class="form-select" onchange="updateImagePreview()">
                <option value="">Sin imagen</option>
                <?php foreach ($imageFiles as $imgPath):
                  $fileName = basename($imgPath);
                  $relative = 'assets/uploads/' . $fileName;
                ?>
                <option value="<?= e($relative) ?>"><?= e($fileName) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="form-hint">Puedes escoger una imagen existente o subir una nueva aqui mismo.</span>
              <input name="card_image_file" id="f-image-file" type="file" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewUploadedImage(this)">
              <span class="form-hint">Si subes una imagen nueva, reemplaza la seleccion actual al guardar.</span>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Color de acento</label>
          <div style="display:flex;gap:8px;align-items:center;">
            <input name="color" id="f-color" type="color" class="form-input" value="#006056" style="padding:2px;height:38px;width:60px;cursor:pointer;">
            <input id="f-color-text" type="text" class="form-input" value="#006056" style="font-family:monospace;"
                   oninput="document.getElementById('f-color').value=this.value">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Ícono</label>
          <input name="icon" id="f-icon" type="text" class="form-input" value="star" placeholder="star" readonly>
          <div class="icon-grid" style="margin-top:8px;" id="icon-grid">
            <?php foreach ($availableIcons as $ico): ?>
            <button type="button" class="icon-btn" data-icon="<?= e($ico) ?>" title="<?= e($ico) ?>"
                    onclick="selectIcon('<?= e($ico) ?>')">
              <?= icon($ico, '', 16) ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Estado</label>
          <div style="display:flex;align-items:center;gap:8px;margin-top:8px;">
            <input type="checkbox" name="is_active" id="f-active" checked style="width:16px;height:16px;accent-color:var(--accent);">
            <label for="f-active" style="font-size:13px;color:var(--text-s);">Tarjeta activa (visible en el portal)</label>
          </div>
        </div>
      </div>
      <div style="display:flex;gap:10px;margin-top:18px;">
        <button type="submit" name="save_card" class="btn btn-accent"><?= icon('save','',15) ?> Guardar</button>
        <button type="button" onclick="closeModal()" class="btn btn-outline">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
function closeModal() {
  document.getElementById('modal-card').classList.add('hidden');
  document.getElementById('modal-title').textContent = 'Nueva Tarjeta';
  document.getElementById('card-form').reset();
  document.getElementById('f-id').value = '0';
  // Reset icon selection
  document.querySelectorAll('.icon-btn').forEach(b => b.classList.remove('selected'));
  document.querySelectorAll('.icon-btn')[0]?.classList.add('selected');
  document.getElementById('f-icon').value = 'star';
  document.getElementById('f-image-file').value = '';
  updateImagePreview();
}

function editCardFromButton(button) {
  try {
    const card = JSON.parse(button.dataset.card || '{}');
    editCard(card);
  } catch (error) {
    alert('No se pudo abrir la edición de esta tarjeta. Recarga la página e inténtalo nuevamente.');
    console.error('Error leyendo datos de tarjeta', error);
  }
}

function editCard(card) {
  document.getElementById('modal-title').textContent = 'Editar Tarjeta';
  document.getElementById('f-id').value           = card.id;
  document.getElementById('f-page').value         = card.page_slug;
  document.getElementById('f-title').value        = card.title;
  document.getElementById('f-description').value  = card.description || '';
  document.getElementById('f-link').value         = card.link_url || '';
  document.getElementById('f-link-label').value   = card.link_label || '';
  document.getElementById('f-image').value        = card.image_url || '';
  document.getElementById('f-image-file').value   = '';
  updateImagePreview();
  document.getElementById('f-color').value        = card.color || '#006056';
  document.getElementById('f-color-text').value   = card.color || '#006056';
  document.getElementById('f-icon').value         = card.icon || 'star';
  document.getElementById('f-order').value        = card.sort_order || 0;
  document.getElementById('f-active').checked     = card.is_active == 1;

  // Highlight icon
  document.querySelectorAll('.icon-btn').forEach(b => {
    b.classList.toggle('selected', b.dataset.icon === card.icon);
  });
  document.getElementById('modal-card').classList.remove('hidden');
}

function selectIcon(name) {
  document.getElementById('f-icon').value = name;
  document.querySelectorAll('.icon-btn').forEach(b => {
    b.classList.toggle('selected', b.dataset.icon === name);
  });
}


function updateImagePreview() {
  const select = document.getElementById('f-image');
  const preview = document.getElementById('f-image-preview');
  const empty = document.getElementById('f-image-empty');
  const value = select?.value || '';
  if (!preview || !empty) return;

  if (value) {
    preview.src = '<?= $base ?>/' + value.replace(/^\/+/, '');
    preview.style.display = 'block';
    empty.style.display = 'none';
  } else {
    preview.removeAttribute('src');
    preview.style.display = 'none';
    empty.style.display = 'block';
  }
}


function previewUploadedImage(input) {
  const file = input.files?.[0];
  const preview = document.getElementById('f-image-preview');
  const empty = document.getElementById('f-image-empty');
  if (!file || !preview || !empty) {
    updateImagePreview();
    return;
  }
  const reader = new FileReader();
  reader.onload = (event) => {
    preview.src = event.target.result;
    preview.style.display = 'block';
    empty.style.display = 'none';
  };
  reader.readAsDataURL(file);
}

document.addEventListener('DOMContentLoaded', updateImagePreview);

// Sync color picker ↔ text input
document.getElementById('f-color')?.addEventListener('input', function() {
  document.getElementById('f-color-text').value = this.value;
});

window.closeModal = closeModal;
window.editCardFromButton = editCardFromButton;
window.editCard = editCard;
window.selectIcon = selectIcon;
window.updateImagePreview = updateImagePreview;
window.previewUploadedImage = previewUploadedImage;
</script>
<script src="<?= $base ?>/assets/js/admin.js"></script>
</body>
</html>
