<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!isSameOriginRequest()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Solicitud no autorizada.']);
    exit;
}

$retryAfter = consumeRateLimit('public_comment', clientSecurityKey('comments'), 6, 600, 3);
if ($retryAfter > 0) {
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    echo json_encode(['ok' => false, 'message' => 'Has enviado varios comentarios. Espera un momento antes de intentarlo de nuevo.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$message = trim($_POST['message'] ?? '');
$emoji = trim($_POST['emoji'] ?? '💚');
$rating = (int)($_POST['rating'] ?? 5);
$parentId = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($_POST['parent_id'] ?? ''));

if ($message === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'Escribe un comentario antes de enviarlo.']);
    exit;
}

$messageLength = function_exists('mb_strlen') ? mb_strlen($message, 'UTF-8') : strlen($message);
if ($messageLength > 600) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'El comentario no debe superar 600 caracteres.']);
    exit;
}

if ($parentId !== '' && !canReplyToComment($parentId)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No se pudo encontrar el comentario para responder.']);
    exit;
}

$comment = addComment($name, $message, $rating, $emoji, $parentId);
$responseMessage = $parentId !== ''
    ? 'Respuesta publicada. Gracias por seguir la conversación.'
    : 'Comentario publicado. Gracias por compartir tu experiencia.';

echo json_encode([
    'ok' => true,
    'message' => $responseMessage,
    'comment' => $comment,
], JSON_UNESCAPED_UNICODE);
