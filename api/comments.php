<?php
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

if (!isSameOriginRequest(false)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Solicitud no autorizada.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$message = trim($_POST['message'] ?? '');
$emoji = trim($_POST['emoji'] ?? '💚');
$rating = (int)($_POST['rating'] ?? 5);
$parentId = preg_replace('/[^a-zA-Z0-9_.-]/', '', trim($_POST['parent_id'] ?? ''));
$requestToken = trim((string)($_POST['request_token'] ?? ''));
$turnstileToken = trim((string)($_POST['cf-turnstile-response'] ?? ''));
$honeypot = trim((string)($_POST['website'] ?? ''));

$respondAsReceived = static function (bool $isReply): void {
    echo json_encode([
        'ok' => true,
        'message' => $isReply
            ? 'Respuesta recibida. Quedará visible cuando sea aprobada por el equipo administrador.'
            : 'Comentario recibido. Quedará visible cuando sea aprobado por el equipo administrador.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
};

if ($honeypot !== '' || isLikelyCommentSpam($name, $message)) {
    $respondAsReceived($parentId !== '');
}

$retryAfter = consumeRateLimit('public_comment', clientSecurityKey('comments'), 3, 1800, 15);
if ($retryAfter > 0) {
    http_response_code(429);
    header('Retry-After: ' . $retryAfter);
    echo json_encode(['ok' => false, 'message' => 'Has enviado varios comentarios. Espera un momento antes de intentarlo de nuevo.']);
    exit;
}

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

if (!validatePublicRequestToken('comment', $requestToken, 3, 7200)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'La sesión del formulario venció o se envió demasiado rápido. Recarga la página e inténtalo nuevamente.']);
    exit;
}

if (!verifyTurnstileToken($turnstileToken, 'comment')) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No fue posible completar la verificación anti-bots. Inténtalo nuevamente.']);
    exit;
}

if ($parentId !== '' && !canReplyToComment($parentId)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => 'No se pudo encontrar el comentario para responder.']);
    exit;
}

$comment = addComment($name, $message, $rating, $emoji, $parentId);
consumePublicRequestToken('comment', $requestToken);
$responseMessage = $parentId !== ''
    ? 'Respuesta recibida. Quedará visible cuando sea aprobada por el equipo administrador.'
    : 'Comentario recibido. Quedará visible cuando sea aprobado por el equipo administrador.';

echo json_encode([
    'ok' => true,
    'message' => $responseMessage,
    'comment' => $comment,
    'request_token' => issuePublicRequestToken('comment'),
], JSON_UNESCAPED_UNICODE);
