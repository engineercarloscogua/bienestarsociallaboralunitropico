<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

function videoTestAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$youtubeId = 'tTqzpifzbN8';
$youtubeUrls = [
    'https://www.youtube.com/watch?v=' . $youtubeId,
    'https://youtu.be/' . $youtubeId . '?si=prueba',
    'https://www.youtube.com/shorts/' . $youtubeId,
    'https://www.youtube.com/embed/' . $youtubeId,
];

foreach ($youtubeUrls as $url) {
    $embed = videoEmbedData($url);
    videoTestAssert(($embed['provider'] ?? '') === 'youtube', 'No se detectó YouTube: ' . $url);
    videoTestAssert(
        ($embed['embed_url'] ?? '') === 'https://www.youtube-nocookie.com/embed/' . $youtubeId,
        'La URL privada de YouTube no coincide.'
    );
}

$driveId = '1AbCdEfGhIjKlMnOpQrStUvWxYz';
$drive = videoEmbedData('https://drive.google.com/file/d/' . $driveId . '/view?usp=sharing');
videoTestAssert(($drive['provider'] ?? '') === 'drive', 'No se detectó Google Drive.');
videoTestAssert(
    ($drive['embed_url'] ?? '') === 'https://drive.google.com/file/d/' . $driveId . '/preview',
    'La URL de vista previa de Drive no coincide.'
);

videoTestAssert(videoEmbedData('https://youtube.com.example.org/watch?v=' . $youtubeId) === null, 'Se aceptó un dominio falso.');
videoTestAssert(videoEmbedData('https://www.youtube.com/results?search_query=bienestar') === null, 'Se incrustó una búsqueda.');
videoTestAssert(videoEmbedData('javascript:alert(1)') === null, 'Se aceptó una URL insegura.');

echo 'OK: enlaces seguros de YouTube y Google Drive verificados.' . PHP_EOL;

