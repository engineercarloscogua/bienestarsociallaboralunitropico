<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('APP_STORAGE_DRIVER=json');
require_once __DIR__ . '/../includes/functions.php';

function storageFlowAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$comment = addComment('Prueba moderación local', 'Comentario temporal para validar el flujo.', 5, '✅');
$commentId = $comment['id'];
$isPublic = static function () use ($commentId): bool {
    return count(array_filter(
        getComments(),
        fn(array $item): bool => ($item['id'] ?? '') === $commentId
    )) > 0;
};

try {
    storageFlowAssert(normalizeCommentStatus($comment) === 'pending', 'El comentario nuevo no quedó pendiente.');
    storageFlowAssert(!$isPublic(), 'El comentario pendiente apareció en el muro.');
    storageFlowAssert(moderateComment($commentId, 'published'), 'No se pudo publicar el comentario.');
    storageFlowAssert($isPublic(), 'El comentario publicado no apareció en el muro.');
    storageFlowAssert(moderateComment($commentId, 'rejected'), 'No se pudo rechazar el comentario.');
    storageFlowAssert(!$isPublic(), 'El comentario rechazado siguió en el muro.');
} finally {
    deleteComment($commentId);
}

storageFlowAssert(
    count(array_filter(getAllComments(), fn(array $item): bool => ($item['id'] ?? '') === $commentId)) === 0,
    'No se eliminó el comentario temporal.'
);

echo 'OK: flujo JSON pendiente, publicado, rechazado y eliminado verificado.' . PHP_EOL;
