<?php
// CRUD administrativo del módulo experimental de delegaciones.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/delegation-import.php';
requireLogin();
requireDelegationsModule();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base = baseUrl();
$notice = trim((string)($_GET['notice'] ?? ''));
$errors = [];
$formData = null;
$catalogFormData = ['position_name' => '', 'position_unit' => ''];
$catalogPanelOpen = isset($_GET['catalog']);
$importPreview = $_SESSION['delegation_import_preview'] ?? null;
if (is_array($importPreview) && time() - (int)($importPreview['created_at'] ?? 0) > 1800) {
    unset($_SESSION['delegation_import_preview']);
    $importPreview = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_position'])) {
        $catalogPanelOpen = true;
        $catalogFormData = validateDelegationCatalogEntry($_POST);
        $errors = $catalogFormData['errors'];
        if (!$errors) {
            try {
                addDelegationCatalogEntry($catalogFormData['position'], $catalogFormData['unit']);
                redirect($base . '/admin/delegations.php?catalog=1&notice=position-created#delegation-catalog-card');
            } catch (Throwable $error) {
                $errors[] = $error->getMessage();
            }
        }
    }

    if (isset($_POST['delete_position'])) {
        $catalogPanelOpen = true;
        try {
            $deleted = deleteDelegationCatalogEntry((string)($_POST['position_name'] ?? ''));
            redirect($base . '/admin/delegations.php?catalog=1&notice=' . ($deleted ? 'position-deleted' : 'position-not-found') . '#delegation-catalog-card');
        } catch (Throwable $error) {
            $errors[] = 'No fue posible quitar el cargo del catálogo.';
        }
    }

    if (isset($_POST['cancel_delegation_import'])) {
        unset($_SESSION['delegation_import_preview']);
        redirect($base . '/admin/delegations.php#delegation-import-card');
    }

    if (isset($_POST['preview_delegation_import'])) {
        unset($_SESSION['delegation_import_preview']);
        $importPreview = null;
        $file = $_FILES['delegation_file'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK ||
            !is_uploaded_file((string)($file['tmp_name'] ?? '')) ||
            strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'xlsx') {
            $errors[] = 'Selecciona un archivo .xlsx válido de la plantilla.';
        } else {
            try {
                $rawRows = parseDelegationImportXlsx($file['tmp_name']);
                $validated = validateDelegationImportRows($rawRows, getAllDelegations());
                $errors = $validated['errors'];
                if (!$errors) {
                    $importPreview = [
                        'records' => $validated['records'],
                        'duplicates' => $validated['duplicates'],
                        'created_at' => time(),
                        'token' => bin2hex(random_bytes(16)),
                    ];
                    $_SESSION['delegation_import_preview'] = $importPreview;
                }
            } catch (Throwable $error) {
                $errors[] = $error->getMessage();
            }
        }
    }

    if (isset($_POST['confirm_delegation_import'])) {
        $token = (string)($_POST['import_token'] ?? '');
        if (!is_array($importPreview) || $token === '' || !hash_equals((string)$importPreview['token'], $token)) {
            $errors[] = 'La revisión de la importación venció. Vuelve a cargar la plantilla.';
        } else {
            try {
                $result = importDelegationRecords($importPreview['records'], currentAdminName());
                unset($_SESSION['delegation_import_preview']);
                redirect($base . '/admin/delegations.php?notice=imported&added=' . $result['added'] . '&skipped=' . ($result['skipped'] + $importPreview['duplicates']) . '#delegation-import-card');
            } catch (Throwable $error) {
                $errors[] = 'No fue posible importar los registros. No se reemplazaron los datos existentes.';
            }
        }
    }

    if (isset($_POST['delete_delegation'])) {
        $deleted = deleteDelegationRecord(max(0, (int)($_POST['delegation_id'] ?? 0)));
        redirect($base . '/admin/delegations.php?notice=' . ($deleted ? 'deleted' : 'not-found'));
    }

    if (isset($_POST['save_delegation'])) {
        $id = (int)($_POST['delegation_id'] ?? 0);
        ['errors' => $errors, 'data' => $formData] = validateDelegationInput($_POST, $id > 0 ? findDelegation($id) : null);
        if (!$errors) {
            try {
                saveDelegationRecord($formData, $id > 0 ? $id : null, currentAdminName());
                redirect($base . '/admin/delegations.php?notice=' . ($id > 0 ? 'updated' : 'created'));
            } catch (Throwable $error) {
                $errors[] = 'No fue posible guardar el registro. Recarga la página e inténtalo nuevamente.';
            }
        }
        $formData['id'] = $id;
    }
}

$editId = max(0, (int)($_GET['edit'] ?? 0));
if ($formData === null && $editId > 0) $formData = findDelegation($editId);
if ($formData === null) {
    $formData = [
        'id' => 0,
        'delegation_type' => 'interim',
        'resolution_type' => 'Resolución rectoral N.º',
        'resolution_number' => '',
        'delegate_name' => '',
        'delegated_position' => '',
        'delegated_unit' => '',
        'start_date' => delegationToday(),
        'end_date' => '',
        'is_active' => 1,
    ];
}
$positionCatalog = delegationPositionCatalog();
$selectedPosition = trim((string)($formData['delegated_position'] ?? ''));

$delegations = getAllDelegations();
$filters = delegationFilterOptions($_GET);
$filteredDelegations = filterDelegations($delegations, $filters);
$adminInterimDelegations = array_values(array_filter($filteredDelegations, fn(array $row): bool => $row['delegation_type'] === 'interim'));
$adminFixedDelegations = array_values(array_filter($filteredDelegations, fn(array $row): bool => $row['delegation_type'] === 'fixed'));
$activeDelegations = array_values(array_filter($delegations, fn(array $row): bool => delegationStatusCode($row) === 'active'));
$endingDelegations = array_values(array_filter($delegations, fn(array $row): bool => delegationStatusCode($row) === 'ending'));
$completedDelegations = array_values(array_filter($delegations, fn(array $row): bool => delegationStatusCode($row) === 'completed'));
$hiddenDelegations = array_values(array_filter($delegations, fn(array $row): bool => empty($row['is_active'])));

$noticeMessages = [
    'created' => ['success', 'Delegación creada correctamente.'],
    'updated' => ['success', 'Delegación actualizada correctamente.'],
    'deleted' => ['success', 'Delegación eliminada.'],
    'not-found' => ['error', 'No se encontró la delegación solicitada.'],
    'imported' => ['success', 'Importación terminada: ' . max(0, (int)($_GET['added'] ?? 0)) . ' agregadas y ' . max(0, (int)($_GET['skipped'] ?? 0)) . ' duplicadas omitidas.'],
    'position-created' => ['success', 'Cargo y dependencia agregados al catálogo.'],
    'position-deleted' => ['success', 'Cargo retirado de las opciones. Las delegaciones existentes no se modificaron.'],
    'position-not-found' => ['error', 'Ese cargo ya no está en el catálogo.'],
];
$alert = $noticeMessages[$notice] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Delegaciones — Panel Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/../assets/css/admin.css') ?>">
  <meta name="robots" content="noindex,nofollow">
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
      <a href="<?= $base ?>/admin/delegations.php" class="admin-nav-item active"><?= icon('clipboard-check','',16) ?> Delegaciones</a>
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
      <h2>Tabla de delegaciones</h2>
      <div class="admin-topbar-right">
        <a href="<?= $base ?>/pages/delegaciones.php" target="_blank" class="btn btn-outline btn-sm"><?= icon('external-link','',13) ?> Ver página pública</a>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($alert): ?><div class="alert alert-<?= e($alert[0]) ?>"><?= icon($alert[0] === 'error' ? 'x' : 'save','',15) ?> <?= e($alert[1]) ?></div><?php endif; ?>
      <?php if ($errors): ?><div class="alert alert-error"><?= icon('x','',15) ?><div><strong>Revisa lo siguiente:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>

      <section class="delegations-admin-summary" aria-label="Resumen de delegaciones">
        <article><span class="summary-icon is-total"><?= icon('clipboard-check','',19) ?></span><div><strong><?= count($delegations) ?></strong><span>Registradas</span></div></article>
        <article><span class="summary-icon is-active"><?= icon('users','',19) ?></span><div><strong><?= count($activeDelegations) ?></strong><span>Vigentes</span></div></article>
        <article><span class="summary-icon is-ending"><?= icon('calendar','',19) ?></span><div><strong><?= count($endingDelegations) ?></strong><span>Por culminar hoy</span></div></article>
        <article><span class="summary-icon is-completed"><?= icon('calendar','',19) ?></span><div><strong><?= count($completedDelegations) ?></strong><span>Culminadas</span></div></article>
        <article><span class="summary-icon is-hidden"><?= icon('eye-off','',19) ?></span><div><strong><?= count($hiddenDelegations) ?></strong><span>Ocultas</span></div></article>
      </section>

      <div class="delegations-admin-stack">
        <section class="widget delegations-import-card" id="delegation-import-card">
          <div class="widget-header"><div><h3 class="widget-title"><?= icon('upload','',16) ?> Carga masiva de delegaciones</h3><p>Agrega delegaciones de años anteriores sin reemplazar las existentes. El archivo se revisa antes de guardar.</p></div></div>
          <div class="delegations-import-content">
            <a class="btn btn-outline" href="<?= $base ?>/assets/templates/delegaciones.xlsx" download="plantilla-delegaciones.xlsx"><?= icon('download','',14) ?> Descargar plantilla Excel</a>
            <form method="post" enctype="multipart/form-data" class="delegations-import-form">
              <?= csrfField() ?>
              <label class="form-label" for="delegation-file">Archivo Excel completado (.xlsx, máximo 2 MB y 1.000 registros)</label>
              <input class="form-input" id="delegation-file" type="file" name="delegation_file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
              <button class="btn btn-primary" type="submit" name="preview_delegation_import"><?= icon('eye','',14) ?> Revisar importación</button>
            </form>
            <?php if (is_array($importPreview)): ?>
              <div class="delegations-import-preview">
                <h4>Revisión de la carga</h4>
                <p><strong><?= count($importPreview['records']) ?></strong> registros nuevos. <strong><?= (int)$importPreview['duplicates'] ?></strong> duplicados omitidos. Nada se ha guardado todavía.</p>
                <?php if ($importPreview['records']): ?>
                  <div class="delegations-import-preview-list">
                    <?php foreach (array_slice($importPreview['records'], 0, 12) as $row): ?>
                      <div><span><?= e($row['resolution_number']) ?></span><strong><?= e($row['delegate_name']) ?></strong><span><?= e(delegationDateLabel($row['start_date'])) ?></span></div>
                    <?php endforeach; ?>
                    <?php if (count($importPreview['records']) > 12): ?><p>Y <?= count($importPreview['records']) - 12 ?> registros más.</p><?php endif; ?>
                  </div>
                <?php endif; ?>
                <div class="delegations-import-actions">
                  <?php if ($importPreview['records']): ?><form method="post"><?= csrfField() ?><input type="hidden" name="import_token" value="<?= e($importPreview['token']) ?>"><button class="btn btn-primary" type="submit" name="confirm_delegation_import" onclick="return confirm('¿Agregar los registros nuevos al historial de delegaciones?');"><?= icon('save','',14) ?> Confirmar y guardar</button></form><?php endif; ?>
                  <form method="post"><?= csrfField() ?><button class="btn btn-outline" type="submit" name="cancel_delegation_import">Cancelar revisión</button></form>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </section>

        <details class="widget delegations-catalog-card" id="delegation-catalog-card" <?= $catalogPanelOpen ? 'open' : '' ?>>
          <summary class="delegations-catalog-summary">
            <span><strong>Catálogo de cargos y dependencias</strong><small>Agrega opciones para el formulario de delegaciones.</small></span>
            <span class="delegations-catalog-count"><?= count($positionCatalog) ?> cargos</span>
          </summary>
          <div class="delegations-catalog-content">
            <form method="post" class="delegations-catalog-form">
              <?= csrfField() ?>
              <div class="form-field"><label class="form-label" for="position-name">Cargo delegado</label><input class="form-input delegations-uppercase" id="position-name" name="position_name" maxlength="200" required value="<?= e((string)($catalogFormData['position'] ?? $catalogFormData['position_name'] ?? '')) ?>" placeholder="Ej. DIRECTOR DE ESCUELA"></div>
              <div class="form-field"><label class="form-label" for="position-unit">Dependencia</label><input class="form-input delegations-uppercase" id="position-unit" name="position_unit" maxlength="200" required value="<?= e((string)($catalogFormData['unit'] ?? $catalogFormData['position_unit'] ?? '')) ?>" placeholder="Ej. ESCUELA DE CIENCIAS"></div>
              <button class="btn btn-primary" type="submit" name="add_position"><?= icon('plus','',14) ?> Agregar cargo</button>
            </form>
            <p class="delegations-catalog-help">Quitar un cargo solo lo elimina de esta lista. Las delegaciones ya registradas conservan sus datos.</p>
            <div class="delegations-catalog-list" role="list" aria-label="Cargos disponibles">
              <?php foreach ($positionCatalog as $position => $unit): ?>
                <div class="delegations-catalog-row" role="listitem">
                  <div><strong><?= e($position) ?></strong><span><?= e($unit) ?></span></div>
                  <form method="post" onsubmit="return confirm('¿Quitar este cargo de las opciones? Las delegaciones existentes no se borrarán.');">
                    <?= csrfField() ?><input type="hidden" name="position_name" value="<?= e($position) ?>">
                    <button class="btn btn-danger btn-sm" type="submit" name="delete_position"><?= icon('trash','',13) ?> Quitar</button>
                  </form>
                </div>
              <?php endforeach; ?>
              <?php if (!$positionCatalog): ?><p class="admin-empty">Aún no hay cargos disponibles. Agrega el primero arriba.</p><?php endif; ?>
            </div>
          </div>
        </details>

        <section class="widget delegations-admin-form-card" id="delegation-form-card">
          <div class="widget-header"><div><h3 class="widget-title"><?= icon((int)$formData['id'] > 0 ? 'edit' : 'plus','',16) ?> <?= (int)$formData['id'] > 0 ? 'Editar delegación' : 'Nueva delegación' ?></h3><p>Registra únicamente actos administrativos confirmados.</p></div></div>
          <form method="post" class="delegations-admin-form" id="delegation-form">
            <?= csrfField() ?>
            <input type="hidden" name="delegation_id" value="<?= (int)$formData['id'] ?>">
            <div class="form-field delegation-field-type">
              <label class="form-label" for="delegation-type">Tipo de delegación</label>
              <select class="form-select" id="delegation-type" name="delegation_type" required>
                <option value="interim" <?= $formData['delegation_type'] === 'interim' ? 'selected' : '' ?>>Hasta proveer el cargo</option>
                <option value="fixed" <?= $formData['delegation_type'] === 'fixed' ? 'selected' : '' ?>>Fecha fija</option>
              </select>
            </div>
            <div class="form-field"><label class="form-label" for="delegation-resolution-type">Tipo de acto</label><input class="form-input" id="delegation-resolution-type" name="resolution_type" maxlength="100" required value="<?= e(delegationResolutionType($formData)) ?>" placeholder="Resolución rectoral N.º"></div>
            <div class="form-field"><label class="form-label" for="delegation-resolution">Resolución / RR</label><input class="form-input" id="delegation-resolution" name="resolution_number" maxlength="80" required value="<?= e($formData['resolution_number']) ?>" placeholder="Ej. RR 1393 de 2026"></div>
            <div class="form-field"><label class="form-label" for="delegation-person">Persona delegada</label><input class="form-input delegation-uppercase" id="delegation-person" name="delegate_name" maxlength="160" required value="<?= e(delegationDisplayName($formData)) ?>" autocomplete="off"><small>Se guardará en MAYÚSCULAS.</small></div>
            <div class="form-field delegation-position-field">
              <label class="form-label" for="delegation-position">Cargo delegado</label>
              <select class="form-select" id="delegation-position" name="delegated_position" required>
                <option value="" <?= $selectedPosition === '' ? 'selected' : '' ?>>Selecciona un cargo</option>
                <?php if ($selectedPosition !== '' && !isset($positionCatalog[$selectedPosition])): ?>
                  <option value="<?= e($selectedPosition) ?>" data-unit="<?= e(delegationDisplayUnit($formData)) ?>" selected><?= e($selectedPosition) ?> (cargo existente)</option>
                <?php endif; ?>
                <?php foreach ($positionCatalog as $position => $unit): ?>
                  <option value="<?= e($position) ?>" data-unit="<?= e($unit) ?>" <?= $selectedPosition === $position ? 'selected' : '' ?>><?= e($position) ?></option>
                <?php endforeach; ?>
              </select>
              <small>¿Falta un cargo? <a href="#delegation-catalog-card" onclick="document.getElementById('delegation-catalog-card').open=true">Agrégalo al catálogo</a>.</small>
            </div>
            <div class="form-field"><label class="form-label" for="delegation-unit">Dependencia</label><input class="form-input" id="delegation-unit" value="<?= e(delegationDisplayUnit($formData)) ?>" placeholder="Se completa con el cargo" readonly><small>Se asigna automáticamente.</small></div>
            <div class="form-field"><label class="form-label" for="delegation-start">Fecha inicial</label><input class="form-input" id="delegation-start" type="date" name="start_date" required value="<?= e($formData['start_date']) ?>"></div>
            <div class="form-field" id="delegation-end-field"><label class="form-label" for="delegation-end">Fecha final</label><input class="form-input" id="delegation-end" type="date" name="end_date" value="<?= e((string)$formData['end_date']) ?>"><small>Obligatoria solo para fecha fija.</small></div>
            <div class="delegations-admin-form-footer">
              <label class="admin-check"><input type="checkbox" name="is_active" value="1" <?= !empty($formData['is_active']) ? 'checked' : '' ?>> Visible para el público</label>
              <div class="delegations-admin-form-actions">
              <?php if ((int)$formData['id'] > 0): ?><a class="btn btn-outline" href="<?= $base ?>/admin/delegations.php">Cancelar edición</a><?php endif; ?>
              <button class="btn btn-primary" type="submit" name="save_delegation"><?= icon('save','',14) ?> Guardar delegación</button>
              </div>
            </div>
          </form>
        </section>

        <section class="widget delegations-admin-list-card">
          <div class="widget-header"><div><h3 class="widget-title"><?= icon('clipboard-check','',16) ?> Registros administrados</h3><p><?= count($filteredDelegations) ?> de <?= count($delegations) ?> delegaciones coinciden con los filtros actuales.</p></div></div>
          <?php $filterContext = 'admin'; require __DIR__ . '/../includes/delegation-filter-form.php'; ?>

          <?php
          $adminTables = [
              ['title' => 'Hasta proveer el cargo', 'rows' => $adminInterimDelegations, 'type' => 'interim'],
              ['title' => 'Fecha fija', 'rows' => $adminFixedDelegations, 'type' => 'fixed'],
          ];
          foreach ($adminTables as $adminTable):
          ?>
          <section class="delegations-admin-group">
            <header><h4><?= e($adminTable['title']) ?></h4><span><?= count($adminTable['rows']) ?> registro<?= count($adminTable['rows']) === 1 ? '' : 's' ?></span></header>
            <?php if (!$adminTable['rows']): ?>
              <div class="admin-empty">No hay registros que coincidan en esta tabla.</div>
            <?php else: ?>
            <div class="delegations-admin-table-wrap">
              <table class="delegations-admin-table">
                <thead><tr><th>Tipo de acto</th><th>Resolución</th><th>Persona, cargo y dependencia</th><th>Fecha inicial</th><th>Fecha final</th><th>Días</th><th>Estado</th><th class="actions-column">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($adminTable['rows'] as $delegation):
                  $status = delegationStatusCode($delegation);
                  $durationDays = delegationDurationDays($delegation);
                ?>
                  <tr class="is-<?= e($status) ?>">
                    <td data-label="Tipo de acto"><span class="delegations-admin-act-type"><?= e(delegationResolutionType($delegation)) ?></span></td>
                    <td data-label="Resolución"><span class="delegations-admin-resolution">RR <?= e($delegation['resolution_number']) ?></span></td>
                    <td data-label="Persona, cargo y dependencia"><strong class="delegations-admin-person"><?= e(delegationDisplayName($delegation)) ?></strong><span class="delegations-admin-position"><?= e($delegation['delegated_position']) ?></span><span class="delegations-admin-unit"><?= e(delegationDisplayUnit($delegation) ?: 'Dependencia no registrada') ?></span></td>
                    <td data-label="Fecha inicial"><time datetime="<?= e($delegation['start_date']) ?>"><?= e(delegationDateLabel($delegation['start_date'])) ?></time></td>
                    <td data-label="Fecha final"><?= $delegation['end_date'] ? '<time datetime="' . e($delegation['end_date']) . '">' . e(delegationDateLabel($delegation['end_date'])) . '</time>' : '<span class="delegations-open-ended">Hasta proveer</span>' ?></td>
                    <td data-label="Días"><strong><?= $durationDays !== null ? (int)$durationDays : '—' ?></strong></td>
                    <td data-label="Estado"><span class="status-badge status-<?= e($status) ?>"><?= e(delegationStatusLabel($delegation)) ?></span></td>
                    <td data-label="Acciones">
                      <div class="delegations-admin-row-actions">
                        <a class="btn btn-outline btn-sm" href="?edit=<?= (int)$delegation['id'] ?>#delegation-form-card" title="Editar delegación"><?= icon('edit','',13) ?> Editar</a>
                        <form method="post" onsubmit="return confirm('¿Eliminar esta delegación definitivamente?');"><?= csrfField() ?><input type="hidden" name="delegation_id" value="<?= (int)$delegation['id'] ?>"><button class="btn btn-danger btn-sm" type="submit" name="delete_delegation" title="Eliminar delegación"><?= icon('trash','',13) ?> Eliminar</button></form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php endif; ?>
          </section>
          <?php endforeach; ?>
        </section>
      </div>
    </div>
  </div>
</div>
<script>
(() => {
  const type = document.getElementById('delegation-type');
  const endField = document.getElementById('delegation-end-field');
  const endInput = document.getElementById('delegation-end');
  const position = document.getElementById('delegation-position');
  const unit = document.getElementById('delegation-unit');
  const syncEndDate = () => {
    const fixed = type.value === 'fixed';
    endField.classList.toggle('is-disabled', !fixed);
    endInput.required = fixed;
    endInput.disabled = !fixed;
    if (!fixed) endInput.value = '';
  };
  type.addEventListener('change', syncEndDate);
  position.addEventListener('change', () => {
    unit.value = position.selectedOptions[0]?.dataset.unit || '';
  });
  syncEndDate();
})();
</script>
</body>
</html>
