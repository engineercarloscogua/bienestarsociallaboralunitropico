<?php
// Variables requeridas: $filters, $base y $filterContext ('admin' o 'public').
$filterAction = $filterContext === 'admin' ? $base . '/admin/delegations.php' : $base . '/pages/delegaciones.php';
?>
<form class="delegation-filter-form" method="get" action="<?= e($filterAction) ?>" role="search">
  <div class="delegation-filter-field delegation-search-field">
    <label for="<?= e($filterContext) ?>-delegation-search">Buscar resolución o persona</label>
    <input id="<?= e($filterContext) ?>-delegation-search" type="search" name="q" maxlength="160" value="<?= e($filters['q']) ?>" placeholder="Número de resolución o nombre">
  </div>
  <div class="delegation-filter-field">
    <label for="<?= e($filterContext) ?>-delegation-period"><?= $filterContext === 'public' ? 'Filtrar por período' : 'Filtrar por fecha inicial' ?></label>
    <select id="<?= e($filterContext) ?>-delegation-period" name="period" data-delegation-period>
      <option value="all" <?= $filters['period'] === 'all' ? 'selected' : '' ?>>Todas las fechas</option>
      <option value="range" <?= $filters['period'] === 'range' ? 'selected' : '' ?>>Rango de fechas</option>
      <option value="month" <?= $filters['period'] === 'month' ? 'selected' : '' ?>>Mes</option>
      <option value="semester" <?= $filters['period'] === 'semester' ? 'selected' : '' ?>>Semestre</option>
      <option value="year" <?= $filters['period'] === 'year' ? 'selected' : '' ?>>Año</option>
    </select>
  </div>
  <div class="delegation-filter-details" data-delegation-details="range">
    <div class="delegation-filter-field"><label for="<?= e($filterContext) ?>-delegation-from">Desde</label><input id="<?= e($filterContext) ?>-delegation-from" type="date" name="from" value="<?= e($filters['from']) ?>"></div>
    <div class="delegation-filter-field"><label for="<?= e($filterContext) ?>-delegation-to">Hasta</label><input id="<?= e($filterContext) ?>-delegation-to" type="date" name="to" value="<?= e($filters['to']) ?>"></div>
  </div>
  <div class="delegation-filter-details" data-delegation-details="month">
    <div class="delegation-filter-field"><label for="<?= e($filterContext) ?>-delegation-month">Mes</label><input id="<?= e($filterContext) ?>-delegation-month" type="month" name="month" value="<?= e($filters['month'] !== '' ? $filters['month'] : substr(delegationToday(), 0, 7)) ?>"></div>
  </div>
  <div class="delegation-filter-details" data-delegation-details="semester year">
    <div class="delegation-filter-field"><label for="<?= e($filterContext) ?>-delegation-year">Año</label><input id="<?= e($filterContext) ?>-delegation-year" type="number" name="year" min="1900" max="2100" value="<?= e($filters['year'] !== '' ? $filters['year'] : substr(delegationToday(), 0, 4)) ?>"></div>
  </div>
  <div class="delegation-filter-details" data-delegation-details="semester">
    <div class="delegation-filter-field"><label for="<?= e($filterContext) ?>-delegation-semester">Semestre</label><select id="<?= e($filterContext) ?>-delegation-semester" name="semester"><option value="1" <?= $filters['semester'] === '1' ? 'selected' : '' ?>>Enero–junio</option><option value="2" <?= $filters['semester'] === '2' ? 'selected' : '' ?>>Julio–diciembre</option></select></div>
  </div>
  <div class="delegation-filter-actions">
    <button type="submit">Aplicar filtros</button>
    <a href="<?= e($filterAction) ?>"><?= $filterContext === 'public' ? 'Volver al mes actual' : 'Limpiar' ?></a>
  </div>
</form>
<script>
(() => {
  const form = document.currentScript.previousElementSibling;
  const period = form.querySelector('[data-delegation-period]');
  const sync = () => {
    form.querySelectorAll('[data-delegation-details]').forEach(group => {
      group.hidden = !group.dataset.delegationDetails.split(' ').includes(period.value);
    });
  };
  period.addEventListener('change', sync);
  sync();
})();
</script>
