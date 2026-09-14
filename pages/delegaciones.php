<?php
// Consulta pública de delegaciones. No se agrega al menú lateral del portal.
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/delegations.php';

requireDelegationsModule();

$pageTitle = 'Tabla de delegaciones';
$base = baseUrl();
$delegations = getPublicDelegations();
$interimDelegations = array_values(array_filter($delegations, fn(array $row): bool => $row['delegation_type'] === 'interim'));
$fixedDelegations = array_values(array_filter($delegations, fn(array $row): bool => $row['delegation_type'] === 'fixed'));
$completedDelegations = array_values(array_filter($fixedDelegations, 'delegationIsCompleted'));

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

  <div class="delegations-summary" aria-label="Resumen de delegaciones">
    <div><strong><?= count($delegations) ?></strong><span>Delegaciones publicadas</span></div>
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
  <section class="delegations-table-card tone-<?= e($table['tone']) ?>">
    <header>
      <span class="delegations-table-icon"><?= icon($table['tone'] === 'fixed' ? 'calendar' : 'refresh-cw', '', 19) ?></span>
      <div>
        <h2><?= e($table['title']) ?></h2>
        <p><?= e($table['subtitle']) ?></p>
      </div>
    </header>

    <?php if (!$table['rows']): ?>
      <div class="delegations-empty">
        <?= icon('clipboard-check', '', 28) ?>
        <p>No hay registros publicados en esta tabla.</p>
      </div>
    <?php else: ?>
      <div class="delegations-table-scroll">
        <table class="delegations-table">
          <thead><tr><th>Resolución / RR</th><th>Persona delegada</th><th>Cargo delegado</th><th>Fecha de inicio</th><th>Fecha de fin</th><th>Días</th><th>Estado</th></tr></thead>
          <tbody>
          <?php foreach ($table['rows'] as $delegation):
            $completed = delegationIsCompleted($delegation);
            $durationDays = delegationDurationDays($delegation);
          ?>
            <tr class="<?= $completed ? 'delegation-completed' : '' ?>">
              <td data-label="Resolución / RR"><span class="delegations-resolution"><?= e($delegation['resolution_number']) ?></span></td>
              <td data-label="Persona delegada"><strong><?= e($delegation['delegate_name']) ?></strong></td>
              <td data-label="Cargo delegado"><?= e($delegation['delegated_position']) ?></td>
              <td data-label="Fecha de inicio"><time datetime="<?= e($delegation['start_date']) ?>"><?= e(delegationDateLabel($delegation['start_date'])) ?></time></td>
              <td data-label="Fecha de fin"><?= $delegation['end_date'] ? '<time datetime="' . e($delegation['end_date']) . '">' . e(delegationDateLabel($delegation['end_date'])) . '</time>' : '<span class="delegations-open-date">Hasta proveer</span>' ?></td>
              <td data-label="Días"><strong class="delegations-days"><?= $durationDays !== null ? (int)$durationDays : '—' ?></strong></td>
              <td data-label="Estado"><span class="delegations-state <?= $completed ? 'is-completed' : 'is-current' ?>"><?= e(delegationStatusLabel($delegation)) ?></span></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
  <?php endforeach; ?>

  <aside class="delegations-note">
    <?= icon('shield-check', '', 18) ?>
    <p>La información publicada corresponde a los registros administrados por Talento Humano. Los registros en rojo ya alcanzaron su fecha de finalización.</p>
  </aside>
</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
