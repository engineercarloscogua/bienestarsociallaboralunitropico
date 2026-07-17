<?php
// admin/pages.php — Gestión de Subpáginas
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base = baseUrl();
$msg  = '';
$uploadDir = __DIR__ . '/../assets/uploads/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

function parsePipeRows(string $text, array $keys): array {
    $items = [];
    foreach (preg_split('/\R/', trim($text)) as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $parts = array_map('trim', explode('|', $line));
        $item = [];
        foreach ($keys as $index => $key) {
            $item[$key] = $parts[$index] ?? '';
        }
        $items[] = $item;
    }
    return $items;
}

function rowsToText(array $rows, array $keys): string {
    $lines = [];
    foreach ($rows as $row) {
        $parts = [];
        foreach ($keys as $key) {
            $parts[] = $row[$key] ?? '';
        }
        $lines[] = implode(' | ', $parts);
    }
    return implode("\n", $lines);
}

function uploadAdminImage(string $field, string $uploadDir, string &$error): string {
    if (empty($_FILES[$field]['name'])) return '';

    $file = $_FILES[$field];
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    $maxSize = 5 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'ERROR: No se pudo subir la imagen.';
        return '';
    }
    if ($file['size'] > $maxSize) {
        $error = 'ERROR: La imagen supera los 5MB.';
        return '';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!isset($allowed[$mime])) {
        $error = 'ERROR: Solo se permiten imagenes JPG, PNG, GIF o WebP.';
        return '';
    }

    $baseName = pathinfo($file['name'], PATHINFO_FILENAME);
    $safeName = preg_replace('/[^a-z0-9\-_]/', '-', strtolower($baseName));
    $safeName = trim(preg_replace('/-+/', '-', $safeName), '-');
    if ($safeName === '') $safeName = 'contenido';
    $newName = $safeName . '-' . uniqid() . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
        $error = 'ERROR: No se pudo guardar la imagen.';
        return '';
    }
    return 'assets/uploads/' . $newName;
}

function normalizeProgramBlocks($rows): array {
    if (!is_array($rows)) return parsePipeRows((string)$rows, ['title','body','image_url']);
    $items = [];
    foreach ($rows as $row) {
        $title = trim($row['title'] ?? '');
        $body = trim($row['body'] ?? '');
        $image = trim($row['image_url'] ?? '');
        $mediaUrl = trim($row['media_url'] ?? '');
        $mediaLabel = trim($row['media_label'] ?? '');
        $layout = trim($row['layout'] ?? 'text');
        if ($title === '' && $body === '' && $image === '' && $mediaUrl === '') continue;
        $items[] = [
            'title'       => $title,
            'body'        => $body,
            'image_url'   => $image,
            'media_url'   => $mediaUrl,
            'media_label' => $mediaLabel,
            'layout'      => $layout ?: 'text',
        ];
    }
    return $items;
}

function normalizeProgramActions($rows): array {
    if (!is_array($rows)) return parsePipeRows((string)$rows, ['label','url']);
    $items = [];
    foreach ($rows as $row) {
        $label = trim($row['label'] ?? '');
        $url = trim($row['url'] ?? '');
        if ($label === '' && $url === '') continue;
        $items[] = ['label' => $label ?: 'Abrir enlace', 'url' => $url];
    }
    return $items;
}

function normalizeProgramYears($rows): array {
    if (!is_array($rows)) return parsePipeRows((string)$rows, ['year','body','label','url']);
    $items = [];
    foreach ($rows as $row) {
        $year = trim($row['year'] ?? '');
        $body = trim($row['body'] ?? '');
        $label = trim($row['label'] ?? '');
        $url = trim($row['url'] ?? '');
        if ($year === '' && $body === '' && $url === '') continue;
        $items[] = [
            'year'  => $year,
            'body'  => $body,
            'label' => $label ?: 'Abrir recurso',
            'url'   => $url,
        ];
    }
    return $items;
}

// ── Handle DELETE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_page'])) {
    $slug = $_POST['page_slug'] ?? '';
    $corePages = ['inicio','talento-humano','desarrollo','bienestar','calendario'];
    if (in_array($slug, $corePages)) {
        $msg = 'ERROR: No puedes eliminar las páginas principales del sistema.';
    } else {
        deletePage($slug);
        $msg = 'Subpágina eliminada.';
    }
}

// ── Handle SAVE ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_page'])) {
    $page = [
        'slug'        => preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? ''))),
        'title'       => trim($_POST['title'] ?? ''),
        'subtitle'    => trim($_POST['subtitle'] ?? ''),
        'hero_text'   => trim($_POST['hero_text'] ?? ''),
        'sort_order'  => (int)($_POST['sort_order'] ?? 99),
        'is_active'   => isset($_POST['is_active']),
    ];
    if (!$page['slug'] || !$page['title']) {
        $msg = 'ERROR: El slug y el título son obligatorios.';
    } else {
        savePage($page);
        $msg = 'Página guardada. Su contenido se abrirá desde la plantilla dinámica.';
    }
}

// Handle SAVE program/card content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_program'])) {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['program_slug'] ?? '')));
    $uploadError = '';
    $imageUrl = trim($_POST['program_image_url'] ?? '');
    $uploadedImage = uploadAdminImage('program_image_file', $uploadDir, $uploadError);
    if ($uploadedImage) $imageUrl = $uploadedImage;

    if ($uploadError) {
        $msg = $uploadError;
    } elseif (!$slug || !trim($_POST['program_title'] ?? '')) {
        $msg = 'ERROR: El slug y el titulo del contenido son obligatorios.';
    } else {
        updateData(function (array &$data) use ($slug, $imageUrl): void {
            $existing = $data['program_pages'][$slug] ?? [];
            $data['program_pages'][$slug] = array_merge($existing, [
                'title'         => trim($_POST['program_title'] ?? ''),
                'section'       => trim($_POST['program_section'] ?? ''),
                'subtitle'      => trim($_POST['program_subtitle'] ?? ''),
                'image_url'     => $imageUrl,
                'accent'        => trim($_POST['program_accent'] ?? '#006B5B') ?: '#006B5B',
                'intro'         => trim($_POST['program_intro'] ?? ''),
                'blocks'        => normalizeProgramBlocks($_POST['program_blocks'] ?? []),
                'actions'       => normalizeProgramActions($_POST['program_actions'] ?? []),
                'year_sections' => normalizeProgramYears($_POST['program_years'] ?? []),
            ]);
        });
        $msg = 'Contenido de tarjeta guardado correctamente.';
    }
}

$data  = readData();
$pages = $data['pages'] ?? [];
usort($pages, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
$corePages = ['inicio','talento-humano','desarrollo','bienestar','calendario'];
$programPages = $data['program_pages'] ?? [];
ksort($programPages);

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
  <title>Subpáginas — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-brand"><div class="logo">U</div><div><h1>Panel Admin</h1><span>Unitrópico</span></div></div>
    <nav class="admin-nav">
      <span class="admin-nav-label">Gestión</span>
      <a href="<?= $base ?>/admin/dashboard.php" class="admin-nav-item"><?= icon('home','',16) ?> Dashboard</a>
      <a href="<?= $base ?>/admin/cards.php" class="admin-nav-item"><?= icon('layers','',16) ?> Tarjetas / Servicios</a>
      <a href="<?= $base ?>/admin/pages.php" class="admin-nav-item active"><?= icon('layers','',16) ?> Subpáginas</a>
      <a href="<?= $base ?>/admin/media.php" class="admin-nav-item"><?= icon('image','',16) ?> Imágenes</a>
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
      <h2>Gestión de Subpáginas</h2>
      <div class="admin-topbar-right">
        <button onclick="document.getElementById('modal-page').classList.remove('hidden')" class="btn btn-accent btn-sm">
          <?= icon('plus','',14) ?> Nueva Subpágina
        </button>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($msg): ?>
      <div class="alert <?= str_starts_with($msg,'ERROR') ? 'alert-error' : 'alert-success' ?>">
        <?= str_starts_with($msg,'ERROR') ? icon('x','',15) : icon('save','',15) ?> <?= e($msg) ?>
      </div>
      <?php endif; ?>

      <div class="widget" style="margin-bottom:16px;padding:14px 18px;">
        <p style="font-size:12.5px;color:var(--text-s);display:flex;gap:8px;align-items:flex-start;">
          <?= icon('sparkles','',15) ?>
          <span>Al crear una nueva subpágina, también debes crear el archivo <code style="background:rgba(255,255,255,0.06);padding:1px 6px;border-radius:4px;font-size:11px;">pages/[slug].php</code> en el servidor para que sea accesible.</span>
        </p>
      </div>

      <div class="widget">
        <div class="widget-header">
          <h3 class="widget-title">
            <span class="widget-title-icon" style="background:rgba(0,107,91,0.10);color:var(--primary);"><?= icon('layers','',16) ?></span>
            Paginas principales
          </h3>
        </div>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Página</th>
                <th>Slug / URL</th>
                <th>Orden</th>
                <th>Estado</th>
                <th>Tipo</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($pages as $p): $isCore = in_array($p['slug'], $corePages); ?>
              <tr>
                <td>
                  <div style="font-weight:600;"><?= e($p['title']) ?></div>
                  <div style="font-size:11px;color:var(--text-m);"><?= e(substr($p['subtitle'],0,60)) ?></div>
                </td>
                <td>
                  <code style="font-size:11px;color:var(--text-m);">
                    <?= $p['slug'] === 'inicio' ? '/index.php' : (isset($programPages[$p['slug']]) ? '/pages/programa.php?slug=' . rawurlencode($p['slug']) : '/pages/' . e($p['slug']) . '.php') ?>
                  </code>
                </td>
                <td><span style="color:var(--text-m);">#<?= (int)$p['sort_order'] ?></span></td>
                <td><?= $p['is_active'] ? '<span class="badge-sm badge-green">Activa</span>' : '<span class="badge-sm badge-red">Inactiva</span>' ?></td>
                <td><?= $isCore ? '<span class="badge-sm badge-blue">Sistema</span>' : '<span class="badge-sm" style="background:rgba(139,92,246,0.12);color:#A78BFA;border:1px solid rgba(139,92,246,0.2);">Personalizada</span>' ?></td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <button onclick='editPage(<?= json_encode($p) ?>)' class="btn btn-outline btn-sm btn-icon" title="Editar">
                      <?= icon('edit','',14) ?>
                    </button>
                    <?php if (!$isCore): ?>
                    <form method="POST" onsubmit="return confirm('¿Eliminar esta página?');" style="display:inline;">
                      <?= csrfField() ?>
                      <input type="hidden" name="page_slug" value="<?= e($p['slug']) ?>">
                      <button type="submit" name="delete_page" class="btn btn-danger btn-sm btn-icon" title="Eliminar">
                        <?= icon('trash','',14) ?>
                      </button>
                    </form>
                    <?php endif; ?>
                    <a href="<?= $base ?><?= $p['slug'] === 'inicio' ? '/index.php' : (isset($programPages[$p['slug']]) ? '/pages/programa.php?slug=' . rawurlencode($p['slug']) : '/pages/' . e($p['slug']) . '.php') ?>"
                       target="_blank" class="btn btn-outline btn-sm btn-icon" title="Ver página">
                      <?= icon('external-link','',14) ?>
                    </a>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="widget" style="margin-top:18px;">
        <div class="widget-header">
          <h3 class="widget-title">
            <span class="widget-title-icon" style="background:rgba(184,162,97,0.16);color:var(--bronze-dark);"><?= icon('edit','',16) ?></span>
            Contenido de tarjetas
          </h3>
          <button type="button" onclick="openProgramModal()" class="btn btn-accent btn-sm">
            <?= icon('plus','',14) ?> Nuevo contenido
          </button>
        </div>
        <p class="form-hint" style="margin-bottom:14px;">
          Estos contenidos son los que se abren desde tarjetas como Recursos de Autocuidado, Politica de Integridad, Induccion y Reinduccion.
        </p>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Contenido</th>
                <th>Slug para tarjeta</th>
                <th>Imagen</th>
                <th>Bloques</th>
                <th>Enlaces</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($programPages)): ?>
              <tr><td colspan="6" style="text-align:center;padding:28px;color:var(--text-m);">No hay contenidos configurados.</td></tr>
              <?php endif; ?>
              <?php foreach ($programPages as $slug => $program):
                $programForEdit = $program;
                $programForEdit['slug'] = $slug;
                $programForEdit['blocks_text'] = rowsToText($program['blocks'] ?? [], ['title','body','image_url']);
                $programForEdit['actions_text'] = rowsToText($program['actions'] ?? [], ['label','url']);
                $programForEdit['years_text'] = rowsToText($program['year_sections'] ?? [], ['year','body','label','url']);
                $programJson = json_encode($programForEdit, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
              ?>
              <tr>
                <td>
                  <div style="font-weight:700;color:var(--text-p);"><?= e($program['title'] ?? $slug) ?></div>
                  <div style="font-size:11px;color:var(--text-m);"><?= e($program['section'] ?? '') ?></div>
                </td>
                <td>
                  <code style="font-size:11px;color:var(--text-m);">pages/programa.php?slug=<?= e($slug) ?></code>
                </td>
                <td>
                  <?php if (!empty($program['image_url'])): ?>
                  <img src="<?= e($base . '/' . ltrim($program['image_url'], '/')) ?>" alt="<?= e($program['title'] ?? $slug) ?>" class="admin-thumb">
                  <?php else: ?>
                  <span style="font-size:11px;color:var(--text-m);">Sin imagen</span>
                  <?php endif; ?>
                </td>
                <td><span class="badge-sm badge-blue"><?= count($program['blocks'] ?? []) ?> bloques</span></td>
                <td><span class="badge-sm badge-green"><?= count($program['actions'] ?? []) + count($program['year_sections'] ?? []) ?> enlaces</span></td>
                <td>
                  <div style="display:flex;gap:6px;">
                    <button type="button" data-program="<?= e($programJson) ?>" onclick="editProgramFromButton(this)" class="btn btn-outline btn-sm btn-icon" title="Editar contenido">
                      <?= icon('edit','',14) ?>
                    </button>
                    <a href="<?= $base ?>/pages/programa.php?slug=<?= e($slug) ?>" target="_blank" class="btn btn-outline btn-sm btn-icon" title="Ver contenido">
                      <?= icon('external-link','',14) ?>
                    </a>
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

<!-- MODAL: Page -->
<div id="modal-page" class="modal-overlay hidden">
  <div class="modal">
    <div class="modal-header">
      <h3 id="page-modal-title">Nueva Subpágina</h3>
      <button onclick="closePageModal()"><?= icon('x','',18) ?></button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="page_id" id="p-id" value="0">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Slug (URL) *</label>
          <input name="slug" id="p-slug" type="text" class="form-input" required
                 placeholder="ej: bienestar-deportivo" pattern="[a-z0-9\-]+">
          <span class="form-hint">Solo letras minúsculas, números y guiones. Ej: "mi-nueva-pagina"</span>
        </div>
        <div class="form-group">
          <label class="form-label">Orden en el menú</label>
          <input name="sort_order" id="p-order" type="number" class="form-input" value="99" min="1">
        </div>
        <div class="form-group full">
          <label class="form-label">Título de la página *</label>
          <input name="title" id="p-title" type="text" class="form-input" required placeholder="Ej: Bienestar Deportivo">
        </div>
        <div class="form-group full">
          <label class="form-label">Subtítulo / descripción corta</label>
          <input name="subtitle" id="p-subtitle" type="text" class="form-input" placeholder="Ej: Actividades deportivas para todo el equipo">
        </div>
        <div class="form-group full">
          <label class="form-label">Texto del hero</label>
          <textarea name="hero_text" id="p-hero" class="form-textarea" placeholder="Texto introductorio de la página..."></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Estado</label>
          <div style="display:flex;align-items:center;gap:8px;margin-top:8px;">
            <input type="checkbox" name="is_active" id="p-active" checked style="width:16px;height:16px;accent-color:var(--accent);">
            <label for="p-active" style="font-size:13px;color:var(--text-s);">Página activa en el menú</label>
          </div>
        </div>
      </div>
      <div style="margin-top:18px;display:flex;gap:10px;">
        <button type="submit" name="save_page" class="btn btn-accent"><?= icon('save','',15) ?> Guardar</button>
        <button type="button" onclick="closePageModal()" class="btn btn-outline">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL: Program/Card Content -->
<div id="modal-program" class="modal-overlay hidden">
  <div class="modal modal-wide">
    <div class="modal-header">
      <h3 id="program-modal-title">Editar contenido de tarjeta</h3>
      <button type="button" onclick="closeProgramModal()"><?= icon('x','',18) ?></button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Slug del contenido *</label>
          <input name="program_slug" id="program-slug" type="text" class="form-input" required pattern="[a-z0-9\-]+" placeholder="recursos-autocuidado">
          <span class="form-hint">La tarjeta debe enlazar a <code>pages/programa.php?slug=este-slug</code>.</span>
        </div>
        <div class="form-group">
          <label class="form-label">Color institucional</label>
          <input name="program_accent" id="program-accent" type="color" class="form-input" value="#006B5B" style="padding:2px;height:38px;width:70px;cursor:pointer;">
        </div>
        <div class="form-group full">
          <label class="form-label">Titulo *</label>
          <input name="program_title" id="program-title" type="text" class="form-input" required placeholder="Recursos de Autocuidado">
        </div>
        <div class="form-group">
          <label class="form-label">Seccion</label>
          <input name="program_section" id="program-section" type="text" class="form-input" placeholder="Bienestar Laboral">
        </div>
        <div class="form-group">
          <label class="form-label">Subtitulo</label>
          <input name="program_subtitle" id="program-subtitle" type="text" class="form-input" placeholder="Materiales para fortalecer habitos saludables">
        </div>
        <div class="form-group full">
          <label class="form-label">Imagen principal</label>
          <div class="image-picker">
            <div class="image-picker-preview">
              <img id="program-image-preview" src="" alt="Vista previa">
              <span id="program-image-empty">Sin imagen</span>
            </div>
            <div class="image-picker-fields">
              <select name="program_image_url" id="program-image" class="form-select" onchange="updateProgramImagePreview()">
                <option value="">Sin imagen</option>
                <?php foreach ($imageFiles as $imgPath):
                  $fileName = basename($imgPath);
                  $relative = 'assets/uploads/' . $fileName;
                ?>
                <option value="<?= e($relative) ?>"><?= e($fileName) ?></option>
                <?php endforeach; ?>
              </select>
              <span class="form-hint">Puedes escoger una imagen ya cargada.</span>
              <input name="program_image_file" id="program-image-file" type="file" class="form-input" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewProgramUpload(this)">
              <span class="form-hint">O subir una nueva imagen para este contenido.</span>
            </div>
          </div>
        </div>
        <div class="form-group full">
          <label class="form-label">Introduccion</label>
          <textarea name="program_intro" id="program-intro" class="form-textarea" placeholder="Texto introductorio de la subpagina..."></textarea>
        </div>
        <div class="form-group full">
          <label class="form-label">Bloques de contenido</label>
          <div id="program-blocks-list" class="cms-repeat-list"></div>
          <button type="button" class="btn btn-outline btn-sm" onclick="addProgramBlock()"><?= icon('plus','',14) ?> Agregar seccion</button>
          <span class="form-hint">Usa secciones para textos, cuadros con imagen, documentos, videos o recursos externos.</span>
        </div>
        <div class="form-group full">
          <label class="form-label">Enlaces / botones</label>
          <div id="program-actions-list" class="cms-repeat-list compact"></div>
          <button type="button" class="btn btn-outline btn-sm" onclick="addProgramAction()"><?= icon('plus','',14) ?> Agregar enlace</button>
        </div>
        <div class="form-group full">
          <label class="form-label">Secciones por anio</label>
          <div id="program-years-list" class="cms-repeat-list compact"></div>
          <button type="button" class="btn btn-outline btn-sm" onclick="addProgramYear()"><?= icon('plus','',14) ?> Agregar anio/recurso</button>
          <span class="form-hint">Ideal para cursos, vigencias, cronogramas o recursos por periodo.</span>
        </div>
      </div>
      <div style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap;">
        <button type="submit" name="save_program" class="btn btn-accent"><?= icon('save','',15) ?> Guardar contenido</button>
        <button type="button" onclick="closeProgramModal()" class="btn btn-outline">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
function closePageModal() {
  document.getElementById('modal-page').classList.add('hidden');
  document.getElementById('page-modal-title').textContent = 'Nueva Subpágina';
  document.getElementById('p-id').value = '0';
}
function editPage(page) {
  document.getElementById('page-modal-title').textContent = 'Editar Página';
  document.getElementById('p-id').value       = page.id;
  document.getElementById('p-slug').value     = page.slug;
  document.getElementById('p-title').value    = page.title;
  document.getElementById('p-subtitle').value = page.subtitle || '';
  document.getElementById('p-hero').value     = page.hero_text || '';
  document.getElementById('p-order').value    = page.sort_order;
  document.getElementById('p-active').checked = page.is_active == 1;
  document.getElementById('modal-page').classList.remove('hidden');
}

function openProgramModal() {
  document.getElementById('program-modal-title').textContent = 'Nuevo contenido de tarjeta';
  document.getElementById('program-slug').value = '';
  document.getElementById('program-title').value = '';
  document.getElementById('program-section').value = '';
  document.getElementById('program-subtitle').value = '';
  document.getElementById('program-accent').value = '#006B5B';
  document.getElementById('program-image').value = '';
  document.getElementById('program-image-file').value = '';
  document.getElementById('program-intro').value = '';
  document.getElementById('program-blocks').value = '';
  document.getElementById('program-actions').value = '';
  document.getElementById('program-years').value = '';
  updateProgramImagePreview();
  document.getElementById('modal-program').classList.remove('hidden');
}

function closeProgramModal() {
  document.getElementById('modal-program').classList.add('hidden');
}

function editProgramFromButton(button) {
  const program = JSON.parse(button.dataset.program || '{}');
  document.getElementById('program-modal-title').textContent = 'Editar contenido de tarjeta';
  document.getElementById('program-slug').value = program.slug || '';
  document.getElementById('program-title').value = program.title || '';
  document.getElementById('program-section').value = program.section || '';
  document.getElementById('program-subtitle').value = program.subtitle || '';
  document.getElementById('program-accent').value = program.accent || '#006B5B';
  document.getElementById('program-image').value = program.image_url || '';
  document.getElementById('program-image-file').value = '';
  document.getElementById('program-intro').value = program.intro || '';
  document.getElementById('program-blocks').value = program.blocks_text || '';
  document.getElementById('program-actions').value = program.actions_text || '';
  document.getElementById('program-years').value = program.years_text || '';
  updateProgramImagePreview();
  document.getElementById('modal-program').classList.remove('hidden');
}

function updateProgramImagePreview() {
  const value = document.getElementById('program-image')?.value || '';
  const preview = document.getElementById('program-image-preview');
  const empty = document.getElementById('program-image-empty');
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

function previewProgramUpload(input) {
  const file = input.files?.[0];
  const preview = document.getElementById('program-image-preview');
  const empty = document.getElementById('program-image-empty');
  if (!file || !preview || !empty) {
    updateProgramImagePreview();
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

window.openProgramModal = openProgramModal;
window.closeProgramModal = closeProgramModal;
window.editProgramFromButton = editProgramFromButton;
window.updateProgramImagePreview = updateProgramImagePreview;
window.previewProgramUpload = previewProgramUpload;
</script>
<script>
const programImageOptionsCms = <?= json_encode(array_map(fn($path) => 'assets/uploads/' . basename($path), $imageFiles), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
let programBlockIndexCms = 0;
let programActionIndexCms = 0;
let programYearIndexCms = 0;

function cmsEscape(value) {
  return String(value ?? '').replace(/[&<>"']/g, (char) => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
  }[char]));
}

function cmsImageSelect(name, value = '') {
  const options = ['<option value="">Sin imagen</option>'].concat(
    programImageOptionsCms.map((path) => `<option value="${cmsEscape(path)}" ${path === value ? 'selected' : ''}>${cmsEscape(path.replace('assets/uploads/', ''))}</option>`)
  );
  return `<select name="${name}" class="form-select">${options.join('')}</select>`;
}

function clearProgramRepeaters() {
  document.getElementById('program-blocks-list').innerHTML = '';
  document.getElementById('program-actions-list').innerHTML = '';
  document.getElementById('program-years-list').innerHTML = '';
  programBlockIndexCms = 0;
  programActionIndexCms = 0;
  programYearIndexCms = 0;
}

function removeRepeatItem(button) {
  button.closest('.cms-repeat-item')?.remove();
}

function addProgramBlock(block = {}) {
  const index = programBlockIndexCms++;
  const item = document.createElement('div');
  item.className = 'cms-repeat-item';
  item.innerHTML = `
    <div class="cms-repeat-header">
      <strong>Seccion de contenido</strong>
      <button type="button" class="btn btn-danger btn-sm" onclick="removeRepeatItem(this)">Eliminar</button>
    </div>
    <div class="cms-repeat-grid">
      <div class="form-group">
        <label class="form-label">Tipo de bloque</label>
        <select name="program_blocks[${index}][layout]" class="form-select">
          <option value="text" ${(block.layout || 'text') === 'text' ? 'selected' : ''}>Solo texto</option>
          <option value="image" ${block.layout === 'image' ? 'selected' : ''}>Imagen / grafico</option>
          <option value="text_image" ${block.layout === 'text_image' ? 'selected' : ''}>Texto + imagen</option>
          <option value="media" ${block.layout === 'media' ? 'selected' : ''}>Multimedia / enlace</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Imagen del bloque</label>
        ${cmsImageSelect(`program_blocks[${index}][image_url]`, block.image_url || '')}
      </div>
      <div class="form-group full">
        <label class="form-label">Titulo</label>
        <input name="program_blocks[${index}][title]" type="text" class="form-input" value="${cmsEscape(block.title || '')}" placeholder="Ej: Proposito">
      </div>
      <div class="form-group full">
        <label class="form-label">Texto</label>
        <textarea name="program_blocks[${index}][body]" class="form-textarea" placeholder="Escribe el contenido de esta seccion...">${cmsEscape(block.body || '')}</textarea>
      </div>
      <div class="form-group">
        <label class="form-label">URL de video, multimedia o documento</label>
        <input name="program_blocks[${index}][media_url]" type="url" class="form-input" value="${cmsEscape(block.media_url || '')}" placeholder="https://youtube.com/... o https://drive.google.com/...">
        <span class="form-hint">YouTube y videos compartidos desde Google Drive se incrustan automáticamente. En Drive, habilita acceso para quienes tengan el enlace.</span>
      </div>
      <div class="form-group">
        <label class="form-label">Texto del boton</label>
        <input name="program_blocks[${index}][media_label]" type="text" class="form-input" value="${cmsEscape(block.media_label || '')}" placeholder="Abrir recurso">
      </div>
    </div>
  `;
  document.getElementById('program-blocks-list').appendChild(item);
}

function addProgramAction(action = {}) {
  const index = programActionIndexCms++;
  const item = document.createElement('div');
  item.className = 'cms-repeat-item compact';
  item.innerHTML = `
    <div class="cms-repeat-grid two">
      <div class="form-group">
        <label class="form-label">Etiqueta</label>
        <input name="program_actions[${index}][label]" type="text" class="form-input" value="${cmsEscape(action.label || '')}" placeholder="Abrir recurso oficial">
      </div>
      <div class="form-group">
        <label class="form-label">URL</label>
        <input name="program_actions[${index}][url]" type="url" class="form-input" value="${cmsEscape(action.url || '')}" placeholder="https://...">
      </div>
    </div>
    <button type="button" class="btn btn-danger btn-sm" onclick="removeRepeatItem(this)">Eliminar enlace</button>
  `;
  document.getElementById('program-actions-list').appendChild(item);
}

function addProgramYear(year = {}) {
  const index = programYearIndexCms++;
  const item = document.createElement('div');
  item.className = 'cms-repeat-item';
  item.innerHTML = `
    <div class="cms-repeat-header">
      <strong>Recurso por anio o vigencia</strong>
      <button type="button" class="btn btn-danger btn-sm" onclick="removeRepeatItem(this)">Eliminar</button>
    </div>
    <div class="cms-repeat-grid">
      <div class="form-group">
        <label class="form-label">Anio / periodo</label>
        <input name="program_years[${index}][year]" type="text" class="form-input" value="${cmsEscape(year.year || '')}" placeholder="2026">
      </div>
      <div class="form-group">
        <label class="form-label">Etiqueta del boton</label>
        <input name="program_years[${index}][label]" type="text" class="form-input" value="${cmsEscape(year.label || '')}" placeholder="Ir al curso">
      </div>
      <div class="form-group full">
        <label class="form-label">Descripcion</label>
        <textarea name="program_years[${index}][body]" class="form-textarea" placeholder="Describe este recurso o vigencia...">${cmsEscape(year.body || '')}</textarea>
      </div>
      <div class="form-group full">
        <label class="form-label">URL</label>
        <input name="program_years[${index}][url]" type="url" class="form-input" value="${cmsEscape(year.url || '')}" placeholder="https://...">
      </div>
    </div>
  `;
  document.getElementById('program-years-list').appendChild(item);
}

function openProgramModal() {
  document.getElementById('program-modal-title').textContent = 'Nuevo contenido de tarjeta';
  document.getElementById('program-slug').value = '';
  document.getElementById('program-title').value = '';
  document.getElementById('program-section').value = '';
  document.getElementById('program-subtitle').value = '';
  document.getElementById('program-accent').value = '#006B5B';
  document.getElementById('program-image').value = '';
  document.getElementById('program-image-file').value = '';
  document.getElementById('program-intro').value = '';
  clearProgramRepeaters();
  addProgramBlock();
  updateProgramImagePreview();
  document.getElementById('modal-program').classList.remove('hidden');
}

function editProgramFromButton(button) {
  const program = JSON.parse(button.dataset.program || '{}');
  document.getElementById('program-modal-title').textContent = 'Editar contenido de tarjeta';
  document.getElementById('program-slug').value = program.slug || '';
  document.getElementById('program-title').value = program.title || '';
  document.getElementById('program-section').value = program.section || '';
  document.getElementById('program-subtitle').value = program.subtitle || '';
  document.getElementById('program-accent').value = program.accent || '#006B5B';
  document.getElementById('program-image').value = program.image_url || '';
  document.getElementById('program-image-file').value = '';
  document.getElementById('program-intro').value = program.intro || '';
  clearProgramRepeaters();
  (program.blocks?.length ? program.blocks : [{}]).forEach(addProgramBlock);
  (program.actions || []).forEach(addProgramAction);
  (program.year_sections || []).forEach(addProgramYear);
  updateProgramImagePreview();
  document.getElementById('modal-program').classList.remove('hidden');
}

window.openProgramModal = openProgramModal;
window.editProgramFromButton = editProgramFromButton;
window.addProgramBlock = addProgramBlock;
window.addProgramAction = addProgramAction;
window.addProgramYear = addProgramYear;
window.removeRepeatItem = removeRepeatItem;
</script>
<script src="<?= $base ?>/assets/js/admin.js"></script>
</body>
</html>
