<?php
// Consulta pública de delegaciones. No se agrega al menú lateral del portal.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/delegations.php';

requireDelegationsModule();

$pageTitle = 'Tabla de delegaciones';
$base = baseUrl();
$filters = delegationPublicFilterOptions($_GET);
$delegations = filterPublicDelegations(getPublicDelegations(), $filters);
$interimDelegations = array_values(array_filter($delegations, fn(array $row): bool => $row['delegation_type'] === 'interim'));
$fixedDelegations = array_values(array_filter($delegations, fn(array $row): bool => $row['delegation_type'] === 'fixed'));
$completedDelegations = array_values(array_filter($fixedDelegations, 'delegationIsCompleted'));
$periodLabel = match ($filters['period']) {
    'month' => delegationMonthLabel($filters['month']),
    'year' => 'Año ' . $filters['year'],
    'semester' => ($filters['semester'] === '2' ? 'Segundo' : 'Primer') . ' semestre de ' . $filters['year'],
    'range' => 'Rango de fechas',
    default => 'Todos los períodos',
};
$expandResults = $_GET !== [];

require_once __DIR__ . '/../includes/header.php';
?>

<main class="content delegations-page">
  <section class="delegations-hero">
    <div>
      <span class="delegations-eyebrow"><?= icon('clipboard-check', '', 14) ?> Información institucional</span>
      <h1>Tabla de delegaciones</h1>
      <p>Consulta las delegaciones institucionales registradas y actualizadas por Talento Humano.</p>
    </div>
    <div class="delegations-hero-mark" aria-hidden="true">
      <?= icon('users', '', 38) ?>
      <span>Talento Humano</span>
    </div>
  </section>

  <?php $filterContext = 'public'; require __DIR__ . '/../includes/delegation-filter-form.php'; ?>

  <p class="delegations-period-note">Período mostrado: <strong><?= e($periodLabel) ?></strong>. Haz clic en cada sección para consultar sus registros.</p>

  <div class="delegations-summary" aria-label="Resumen de delegaciones">
    <div><strong><?= count($delegations) ?></strong><span>Delegaciones encontradas</span></div>
    <div><strong><?= count($interimDelegations) ?></strong><span>Hasta proveer el cargo</span></div>
    <div class="<?= $completedDelegations ? 'has-completed' : '' ?>"><strong><?= count($completedDelegations) ?></strong><span>Delegaciones culminadas</span></div>
  </div>

  <?php
  $publicTables = [
      ['title' => 'Delegaciones hasta proveer el cargo', 'subtitle' => 'Encargos que permanecen vigentes hasta la provisión del cargo.', 'rows' => $interimDelegations, 'tone' => 'interim'],
      ['title' => 'Delegaciones de fecha fija', 'subtitle' => 'Encargos con una fecha de inicio y finalización definida.', 'rows' => $fixedDelegations, 'tone' => 'fixed'],
  ];
  foreach ($publicTables as $table):
  ?>
  <details class="delegations-table-card tone-<?= e($table['tone']) ?>" <?= $expandResults && $table['rows'] ? 'open' : '' ?>>
    <summary>
      <span class="delegations-table-icon"><?= icon($table['tone'] === 'fixed' ? 'calendar' : 'refresh-cw', '', 19) ?></span>
      <div class="delegations-table-heading">
        <h2><?= e($table['title']) ?></h2>
        <p><?= e($table['subtitle']) ?></p>
      </div>
      <span class="delegations-table-meta"><strong><?= count($table['rows']) ?></strong> registro<?= count($table['rows']) === 1 ? '' : 's' ?> · <?= e($periodLabel) ?></span>
      <span class="delegations-table-chevron" aria-hidden="true"></span>
    </summary>

    <?php if (!$table['rows']): ?>
      <div class="delegations-empty">
        <?= icon('clipboard-check', '', 28) ?>
        <p>No hay registros que coincidan en esta tabla.</p>
      </div>
    <?php else: ?>
      <div class="delegations-table-scroll">
        <table class="delegations-table">
          <thead><tr><th>Tipo de acto</th><th>Resolución / RR</th><th>Persona delegada</th><th>Cargo delegado</th><th>Dependencia</th><th>Fecha de inicio</th><th>Fecha de fin</th><th>Días</th><th>Estado</th></tr></thead>
          <tbody>
          <?php foreach ($table['rows'] as $delegation):
            $status = delegationStatusCode($delegation);
            $durationDays = delegationDurationDays($delegation);
          ?>
            <tr class="delegation-<?= e($status) ?>">
              <td data-label="Tipo de acto"><span class="delegations-act-type"><?= e(delegationResolutionType($delegation)) ?></span></td>
              <td data-label="Resolución / RR"><span class="delegations-resolution"><?= e($delegation['resolution_number']) ?></span></td>
              <td data-label="Persona delegada"><strong><?= e(delegationDisplayName($delegation)) ?></strong></td>
              <td data-label="Cargo delegado"><?= e($delegation['delegated_position']) ?></td>
              <td data-label="Dependencia"><?= e(delegationDisplayUnit($delegation) ?: 'No registrada') ?></td>
              <td data-label="Fecha de inicio"><time datetime="<?= e($delegation['start_date']) ?>"><?= e(delegationDateLabel($delegation['start_date'])) ?></time></td>
              <td data-label="Fecha de fin"><?= $delegation['end_date'] ? '<time datetime="' . e($delegation['end_date']) . '">' . e(delegationDateLabel($delegation['end_date'])) . '</time>' : '<span class="delegations-open-date">Hasta proveer</span>' ?></td>
              <td data-label="Días"><strong class="delegations-days"><?= $durationDays !== null ? (int)$durationDays : '—' ?></strong></td>
              <td data-label="Estado"><span class="delegations-state is-<?= e($status) ?>"><?= e(delegationStatusLabel($delegation)) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </details>
  <?php endforeach; ?>

  <aside class="delegations-note">
    <?= icon('shield-check', '', 18) ?>
    <p>Los filtros públicos muestran las delegaciones cuyo período coincide con la consulta, incluso si comenzaron antes. En amarillo se muestran las que culminan hoy; en rojo, las que culminaron antes de hoy.</p>
  </aside>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
