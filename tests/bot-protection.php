<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test Browser';
require_once __DIR__ . '/../includes/functions.php';

function botProtectionAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$spam = <<<'TEXT'
I analyzed your website and competitors. Contact our Digital Strategy Consultant.
WhatsApp: +1 913 735 7607 Telegram: @professionals_bot
TEXT;

botProtectionAssert(isLikelyCommentSpam('Danish', $spam), 'No se detectó el mensaje comercial de prueba.');
botProtectionAssert(isLikelyCommentSpam('Visitante', 'Mira nuestra oferta en https://example.com'), 'No se detectó un enlace externo.');
botProtectionAssert(!isLikelyCommentSpam('Carlos', 'La sección de bienestar me pareció muy útil.'), 'Se bloqueó un comentario legítimo.');
botProtectionAssert(isLikelyAutomatedUserAgent('python-requests/2.32'), 'No se detectó un agente automatizado.');
botProtectionAssert(!isLikelyAutomatedUserAgent($_SERVER['HTTP_USER_AGENT']), 'Se bloqueó un navegador normal.');

unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_REFERER']);
botProtectionAssert(!isSameOriginRequest(false), 'Una solicitud sin origen superó la validación estricta.');
$_SERVER['HTTP_ORIGIN'] = 'http://localhost';
botProtectionAssert(isSameOriginRequest(false), 'Una solicitud del mismo origen fue rechazada.');
$_SERVER['HTTP_ORIGIN'] = 'https://example.com';
botProtectionAssert(!isSameOriginRequest(false), 'Una solicitud de otro origen fue aceptada.');

$token = issuePublicRequestToken('comment');
botProtectionAssert(validatePublicRequestToken('comment', $token), 'El token público recién creado no fue válido.');
consumePublicRequestToken('comment', $token);
botProtectionAssert(!validatePublicRequestToken('comment', $token), 'El token público se pudo reutilizar.');
$tooFastToken = issuePublicRequestToken('comment');
botProtectionAssert(!validatePublicRequestToken('comment', $tooFastToken, 3), 'Se aceptó un formulario enviado demasiado rápido.');
consumePublicRequestToken('comment', $tooFastToken);

$credentials = turnstileCredentials();
botProtectionAssert($credentials['enabled'] && $credentials['testing'], 'No se activaron las claves locales de prueba de Turnstile.');
botProtectionAssert(
    verifyTurnstileToken('XXXX.DUMMY.TOKEN.XXXX', 'comment'),
    'Cloudflare no validó el token de prueba de Turnstile.'
);
botProtectionAssert(!verifyTurnstileToken('', 'comment'), 'Turnstile aceptó un token vacío.');

$configured = databaseConfig() ?? [];
if (empty($configured['turnstile_site_key']) && empty($configured['turnstile_secret_key'])) {
    $_SERVER['HTTP_HOST'] = 'portal.example.com';
    botProtectionAssert(!turnstileCredentials()['enabled'], 'Turnstile se activó en producción sin claves.');
    botProtectionAssert(!verifyTurnstileToken('XXXX.DUMMY.TOKEN.XXXX', 'comment'), 'El backend quedó abierto sin claves de producción.');
}

echo 'OK: origen, tokens, filtros de bots, spam y Turnstile verificados.' . PHP_EOL;
