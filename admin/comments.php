<?php
// admin/comments.php - Moderación de comentarios del portal
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') requireValidCsrf();

$base = baseUrl();
$adminName = currentAdminName();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_comment'])) {
    deleteComment($_POST['comment_id'] ?? '');
    $msg = 'Comentario eliminado. Si tenía respuestas, también fueron retiradas.';
}

$comments = getAllComments();
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
          <?= icon('external-link','',13) ?> Ver comentarios
        </a>
      </div>
    </div>

    <div class="admin-content">
      <?php if ($msg): ?>
      <div class="alert alert-success"><?= icon('trash','',15) ?> <?= e($msg) ?></div>
      <?php endif; ?>

      <div class="widget">
        <div class="widget-header">
          <h3 class="widget-title"><?= icon('heart','',16) ?> Moderación de experiencias</h3>
        </div>

        <?php if (empty($comments)): ?>
          <div style="padding:34px;text-align:center;color:var(--text-m);">
            <?= icon('heart','',34) ?>
            <p style="margin-top:10px;">Aún no hay comentarios publicados.</p>
          </div>
        <?php else: ?>
          <div style="display:grid;gap:14px;">
            <?php foreach ($comments as $comment): ?>
            <article style="border:1px solid var(--border);border-radius:14px;padding:16px;background:#fff;">
              <div style="display:flex;justify-content:space-between;gap:14px;align-items:flex-start;">
                <div style="display:flex;gap:12px;">
                  <div style="width:44px;height:44px;border-radius:12px;background:rgba(0,107,91,0.10);display:flex;align-items:center;justify-content:center;font-size:23px;">
                    <?= e($comment['emoji'] ?? '💚') ?>
                  </div>
                  <div>
                    <h3 style="font-size:15px;font-weight:800;color:var(--text-p);margin-bottom:3px;"><?= e($comment['name'] ?? 'Visitante') ?></h3>
                    <div style="font-size:12px;color:#B8A261;font-weight:800;letter-spacing:1px;"><?= str_repeat('★', (int)($comment['rating'] ?? 5)) ?></div>
                    <time style="display:block;font-size:12px;color:var(--text-m);margin-top:4px;">
                      <?= e(date('d/m/Y H:i', strtotime($comment['created_at'] ?? 'now'))) ?>
                    </time>
                  </div>
                </div>
                <form method="POST" onsubmit="return confirm('¿Eliminar este comentario? Si es un comentario principal, también se eliminarán sus respuestas.');">
                  <?= csrfField() ?>
                  <input type="hidden" name="comment_id" value="<?= e($comment['id'] ?? '') ?>">
                  <button type="submit" name="delete_comment" class="btn btn-danger btn-sm">
                    <?= icon('trash','',13) ?> Eliminar
                  </button>
                </form>
              </div>
              <?php if (!empty($comment['parent_id'])): ?>
              <div style="display:inline-flex;margin-top:12px;padding:5px 9px;border-radius:999px;background:rgba(0,107,91,0.08);color:var(--text-p);font-size:11px;font-weight:800;">
                Respuesta a: <?= e($comment['parent_id']) ?>
              </div>
              <?php endif; ?>
              <p style="margin-top:14px;color:var(--text-s);font-size:14px;line-height:1.6;white-space:pre-wrap;"><?= e($comment['message'] ?? '') ?></p>
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
