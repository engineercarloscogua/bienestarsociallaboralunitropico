<?php
// admin/comments.php - Moderación de comentarios del portal
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base = baseUrl();
$adminName = currentAdminName();
$allowedFilters = ['all', 'pending', 'published', 'rejected'];
$statusFilter = strtolower(trim((string)($_GET['status'] ?? $_POST['return_status'] ?? 'pending')));
if (!in_array($statusFilter, $allowedFilters, true)) $statusFilter = 'pending';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commentId = (string)($_POST['comment_id'] ?? '');
    $notice = 'not-found';

    if (isset($_POST['moderate_comment'])) {
        $targetStatus = strtolower(trim((string)($_POST['target_status'] ?? '')));
        if (moderateComment($commentId, $targetStatus)) {
            $notice = $targetStatus;
        }
    } elseif (isset($_POST['delete_comment'])) {
        deleteComment($commentId);
        $notice = 'deleted';
    }

    redirect($base . '/admin/comments.php?status=' . rawurlencode($statusFilter) . '&notice=' . rawurlencode($notice));
}

$noticeMessages = [
    'published' => ['success', 'Comentario publicado correctamente.'],
    'rejected' => ['success', 'Comentario rechazado y retirado del muro.'],
    'pending' => ['success', 'Comentario devuelto a revisión.'],
    'deleted' => ['success', 'Comentario eliminado. Si tenía respuestas, también fueron retiradas.'],
    'not-found' => ['error', 'No se encontró el comentario o el estado solicitado no es válido.'],
];
$notice = strtolower(trim((string)($_GET['notice'] ?? '')));
$alert = $noticeMessages[$notice] ?? null;

$comments = getAllComments();
$counts = ['all' => count($comments), 'pending' => 0, 'published' => 0, 'rejected' => 0];
foreach ($comments as $comment) {
    $status = normalizeCommentStatus((array)$comment);
    if (isset($counts[$status])) $counts[$status]++;
}
$filteredComments = $statusFilter === 'all'
    ? $comments
    : array_values(array_filter($comments, fn($comment) => normalizeCommentStatus((array)$comment) === $statusFilter));

$filterLabels = [
    'pending' => 'Pendientes',
    'published' => 'Publicados',
    'rejected' => 'Rechazados',
    'all' => 'Todos',
];
$statusStyles = [
    'pending' => ['#7A5A00', '#FFF7DD'],
    'published' => ['#006B5B', '#E8F5F1'],
    'rejected' => ['#B42318', '#FDECEA'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Comentarios — Panel Admin Bienestar</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base ?>/assets/css/admin.css">
  <meta name="robots" content="noindex,nofollow">
  <style>
    .moderation-filters{display:flex;gap:8px;flex-wrap:wrap;padding:16px 18px;border-bottom:1px solid var(--border)}
    .moderation-filter{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border:1px solid var(--border);border-radius:999px;background:#fff;color:var(--text-s);font-size:12px;font-weight:800;text-decoration:none}
    .moderation-filter:hover,.moderation-filter.active{border-color:var(--primary);background:rgba(0,107,91,.08);color:var(--primary)}
    .moderation-count{display:inline-flex;min-width:22px;height:22px;padding:0 6px;align-items:center;justify-content:center;border-radius:999px;background:var(--bg-soft);font-size:11px}
    .moderation-filter.active .moderation-count{background:var(--primary);color:#fff}
    .moderation-list{display:grid;gap:14px;padding:18px}
    .moderation-card{border:1px solid var(--border);border-radius:14px;padding:16px;background:#fff}
    .moderation-card-head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}
    .moderation-author{display:flex;gap:12px;min-width:0}
    .moderation-avatar{width:44px;height:44px;flex:0 0 44px;border-radius:12px;background:rgba(0,107,91,.10);display:flex;align-items:center;justify-content:center;font-size:23px}
    .moderation-meta h3{font-size:15px;font-weight:800;color:var(--text-p);margin-bottom:3px}
    .moderation-score{font-size:12px;color:#B8A261;font-weight:800;letter-spacing:1px}
    .moderation-time{display:block;font-size:12px;color:var(--text-m);margin-top:4px}
    .moderation-actions{display:flex;justify-content:flex-end;gap:7px;flex-wrap:wrap}
    .moderation-actions form{margin:0}
    .moderation-message{margin-top:14px;color:var(--text-s);font-size:14px;line-height:1.6;white-space:pre-wrap}
    .moderation-badge{display:inline-flex;align-items:center;margin-top:10px;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:800}
    .moderation-parent{display:inline-flex;margin:10px 0 0 7px;padding:5px 9px;border-radius:999px;background:rgba(0,107,91,.08);color:var(--text-p);font-size:11px;font-weight:800}
    @media(max-width:700px){.moderation-card-head{flex-direction:column}.moderation-actions{justify-content:flex-start}.moderation-list{padding:12px}.moderation-filters{padding:12px}}
  </style>
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="admin-brand">
      <div class="logo">U</div>
      <div><h1>Panel Admin</h1><span>Unitrópico</span></div>
    </div>
    <nav class="admin-nav">
      <span class="admin-nav-label">Gestión</span>
      <a href="<?= $base ?>/admin/dashboard.php" class="admin-nav-item"><?= icon('home','',16) ?> Dashboard</a>
      <a href="<?= $base ?>/admin/cards.php" class="admin-nav-item"><?= icon('layers','',16) ?> Tarjetas / Servicios</a>
      <a href="<?= $base ?>/admin/pages.php" class="admin-nav-item"><?= icon('layers','',16) ?> Subpáginas</a>
      <a href="<?= $base ?>/admin/comments.php" class="admin-nav-item active"><?= icon('heart','',16) ?> Comentarios</a>
      <a href="<?= $base ?>/admin/media.php" class="admin-nav-item"><?= icon('image','',16) ?> Imágenes</a>
      <a href="<?= $base ?>/admin/database.php" class="admin-nav-item"><?= icon('settings','',16) ?> Base de datos</a>
      <span class="admin-nav-label">Portal</span>
      <a href="<?= $base ?>/index.php" class="admin-nav-item" target="_blank"><?= icon('external-link','',16) ?> Ver Sitio</a>
    </nav>
    <div class="admin-sidebar-footer">
      <div style="font-size:11px;color:var(--text-m);padding:4px 8px;margin-bottom:4px;"><?= e($adminName) ?></div>
      <?= adminLogoutForm($base) ?>
    </div>
  </aside>

  <div class="admin-main">
    <div class="admin-topbar">
      <h2>Comentarios</h2>
      <div class="admin-topbar-right">
        <a href="<?= $base ?>/index.php#comentarios" target="_blank" class="btn btn-outline btn-sm">
          <?= icon('external-link','',13) ?> Ver muro
        </a>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($alert): ?>
      <div class="alert alert-<?= e($alert[0]) ?>">
        <?= icon($alert[0] === 'error' ? 'x' : 'shield-check','',15) ?> <?= e($alert[1]) ?>
      </div>
      <?php endif; ?>

      <div class="widget">
        <div class="widget-header">
          <div>
            <h3 class="widget-title"><?= icon('heart','',16) ?> Moderación de experiencias</h3>
            <p style="font-size:12px;color:var(--text-m);margin-top:5px;">Los comentarios nuevos permanecen ocultos hasta que sean publicados.</p>
          </div>
        </div>

        <nav class="moderation-filters" aria-label="Filtrar comentarios por estado">
          <?php foreach ($filterLabels as $filter => $label): ?>
          <a href="<?= $base ?>/admin/comments.php?status=<?= e($filter) ?>" class="moderation-filter <?= $statusFilter === $filter ? 'active' : '' ?>">
            <?= e($label) ?> <span class="moderation-count"><?= (int)$counts[$filter] ?></span>
          </a>
          <?php endforeach; ?>
        </nav>

        <?php if (empty($filteredComments)): ?>
          <div style="padding:40px 24px;text-align:center;color:var(--text-m);">
            <?= icon('heart','',34) ?>
            <p style="margin-top:10px;">No hay comentarios en el estado “<?= e($filterLabels[$statusFilter]) ?>”.</p>
          </div>
        <?php else: ?>
          <div class="moderation-list">
            <?php foreach ($filteredComments as $comment): ?>
            <?php
              $commentStatus = normalizeCommentStatus((array)$comment);
              [$statusColor, $statusBackground] = $statusStyles[$commentStatus];
            ?>
            <article class="moderation-card">
              <div class="moderation-card-head">
                <div class="moderation-author">
                  <div class="moderation-avatar"><?= e($comment['emoji'] ?? '💚') ?></div>
                  <div class="moderation-meta">
                    <h3><?= e($comment['name'] ?? 'Visitante') ?></h3>
                    <div class="moderation-score"><?= str_repeat('★', (int)($comment['rating'] ?? 5)) ?></div>
                    <time class="moderation-time"><?= e(date('d/m/Y H:i', strtotime($comment['created_at'] ?? 'now'))) ?></time>
                    <span class="moderation-badge" style="color:<?= e($statusColor) ?>;background:<?= e($statusBackground) ?>;">
                      <?= e(commentStatusLabel($commentStatus)) ?>
                    </span>
                    <?php if (!empty($comment['parent_id'])): ?>
                    <span class="moderation-parent">Respuesta a: <?= e($comment['parent_id']) ?></span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="moderation-actions">
                  <?php if ($commentStatus !== 'published'): ?>
                  <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="comment_id" value="<?= e($comment['id'] ?? '') ?>">
                    <input type="hidden" name="target_status" value="published">
                    <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                    <button type="submit" name="moderate_comment" class="btn btn-accent btn-sm"><?= icon('shield-check','',13) ?> Publicar</button>
                  </form>
                  <?php endif; ?>

                  <?php if ($commentStatus !== 'rejected'): ?>
                  <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="comment_id" value="<?= e($comment['id'] ?? '') ?>">
                    <input type="hidden" name="target_status" value="rejected">
                    <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                    <button type="submit" name="moderate_comment" class="btn btn-outline btn-sm"><?= icon('x','',13) ?> Rechazar</button>
                  </form>
                  <?php endif; ?>

                  <form method="POST" onsubmit="return confirm('¿Eliminar definitivamente este comentario? Si es un comentario principal, también se eliminarán sus respuestas.');">
                    <?= csrfField() ?>
                    <input type="hidden" name="comment_id" value="<?= e($comment['id'] ?? '') ?>">
                    <input type="hidden" name="return_status" value="<?= e($statusFilter) ?>">
                    <button type="submit" name="delete_comment" class="btn btn-danger btn-sm"><?= icon('trash','',13) ?> Eliminar</button>
                  </form>
                </div>
              </div>
              <p class="moderation-message"><?= e($comment['message'] ?? '') ?></p>
            </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
</body>
</html>
