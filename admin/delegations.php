<?php
// CRUD administrativo del módulo experimental de delegaciones.
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
requireDelegationsModule();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base = baseUrl();
$notice = trim((string)($_GET['notice'] ?? ''));
$errors = [];
$formData = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_delegation'])) {
        $deleted = deleteDelegationRecord(max(0, (int)($_POST['delegation_id'] ?? 0)));
        redirect($base . '/admin/delegations.php?notice=' . ($deleted ? 'deleted' : 'not-found'));
    }

    if (isset($_POST['save_delegation'])) {
        $id = (int)($_POST['delegation_id'] ?? 0);
        ['errors' => $errors, 'data' => $formData] = validateDelegationInput($_POST);
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
        'resolution_number' => '',
        'delegate_name' => '',
        'delegated_position' => '',
        'start_date' => date('Y-m-d'),
        'end_date' => '',
        'is_active' => 1,
    ];
}

$delegations = getAllDelegations();
$adminInterimDelegations = array_values(array_filter($delegations, fn(array $row): bool => $row['delegation_type'] === 'interim'));
$adminFixedDelegations = array_values(array_filter($delegations, fn(array $row): bool => $row['delegation_type'] === 'fixed'));
$activeDelegations = array_values(array_filter($delegations, fn(array $row): bool => !empty($row['is_active']) && !delegationIsCompleted($row)));
$completedDelegations = array_values(array_filter($delegations, fn(array $row): bool => !empty($row['is_active']) && delegationIsCompleted($row)));
$hiddenDelegations = array_values(array_filter($delegations, fn(array $row): bool => empty($row['is_active'])));

$noticeMessages = [
    'created' => ['success', 'Delegación creada correctamente.'],
    'updated' => ['success', 'Delegación actualizada correctamente.'],
    'deleted' => ['success', 'Delegación eliminada.'],
    'not-found' => ['error', 'No se encontró la delegación solicitada.'],
];
$alert = $noticeMessages[$notice] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Delegaciones — Panel Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
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
      <?php if ($errors): ?><div class="alert alert-error"><?= icon('x','',15) ?><div><strong>No fue posible guardar:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div></div><?php endif; ?>

      <section class="delegations-admin-summary" aria-label="Resumen de delegaciones">
        <article><span class="summary-icon is-total"><?= icon('clipboard-check','',19) ?></span><div><strong><?= count($delegations) ?></strong><span>Registradas</span></div></article>
        <article><span class="summary-icon is-active"><?= icon('users','',19) ?></span><div><strong><?= count($activeDelegations) ?></strong><span>Vigentes</span></div></article>
        <article><span class="summary-icon is-completed"><?= icon('calendar','',19) ?></span><div><strong><?= count($completedDelegations) ?></strong><span>Culminadas</span></div></article>
        <article><span class="summary-icon is-hidden"><?= icon('eye-off','',19) ?></span><div><strong><?= count($hiddenDelegations) ?></strong><span>Ocultas</span></div></article>
      </section>

      <div class="delegations-admin-stack">
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
            <div class="form-field"><label class="form-label" for="delegation-resolution">Resolución / RR</label><input class="form-input" id="delegation-resolution" name="resolution_number" maxlength="80" required value="<?= e($formData['resolution_number']) ?>" placeholder="Ej. RR 1393 de 2026"></div>
            <div class="form-field"><label class="form-label" for="delegation-person">Persona delegada</label><input class="form-input" id="delegation-person" name="delegate_name" maxlength="160" required value="<?= e($formData['delegate_name']) ?>"></div>
            <div class="form-field"><label class="form-label" for="delegation-position">Cargo a delegar</label><input class="form-input" id="delegation-position" name="delegated_position" maxlength="200" required value="<?= e($formData['delegated_position']) ?>"></div>
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
          <div class="widget-header"><div><h3 class="widget-title"><?= icon('clipboard-check','',16) ?> Registros administrados</h3><p><?= count($delegations) ?> delegación<?= count($delegations) === 1 ? '' : 'es' ?> en las dos tablas públicas.</p></div></div>

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
              <div class="admin-empty">No hay registros en esta tabla.</div>
            <?php else: ?>
            <div class="delegations-admin-table-wrap">
              <table class="delegations-admin-table">
                <thead><tr><th>Resolución</th><th>Persona y cargo</th><th>Fecha inicial</th><th>Fecha final</th><th>Días</th><th>Estado</th><th class="actions-column">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($adminTable['rows'] as $delegation):
                  $completed = delegationIsCompleted($delegation);
                  $durationDays = delegationDurationDays($delegation);
                ?>
                  <tr class="<?= $completed ? 'is-completed' : '' ?> <?= empty($delegation['is_active']) ? 'is-hidden' : '' ?>">
                    <td data-label="Resolución"><span class="delegations-admin-resolution">RR <?= e($delegation['resolution_number']) ?></span></td>
                    <td data-label="Persona y cargo"><strong class="delegations-admin-person"><?= e($delegation['delegate_name']) ?></strong><span class="delegations-admin-position"><?= e($delegation['delegated_position']) ?></span></td>
                    <td data-label="Fecha inicial"><time datetime="<?= e($delegation['start_date']) ?>"><?= e(delegationDateLabel($delegation['start_date'])) ?></time></td>
                    <td data-label="Fecha final"><?= $delegation['end_date'] ? '<time datetime="' . e($delegation['end_date']) . '">' . e(delegationDateLabel($delegation['end_date'])) . '</time>' : '<span class="delegations-open-ended">Hasta proveer</span>' ?></td>
                    <td data-label="Días"><strong><?= $durationDays !== null ? (int)$durationDays : '—' ?></strong></td>
                    <td data-label="Estado"><span class="status-badge <?= empty($delegation['is_active']) ? 'status-inactive' : ($completed ? 'status-completed' : 'status-active') ?>"><?= e(delegationStatusLabel($delegation)) ?></span></td>
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
  const syncEndDate = () => {
    const fixed = type.value === 'fixed';
    endField.classList.toggle('is-disabled', !fixed);
    endInput.required = fixed;
    endInput.disabled = !fixed;
    if (!fixed) endInput.value = '';
  };
  type.addEventListener('change', syncEndDate);
  syncEndDate();
})();
</script>
</body>
</html>
