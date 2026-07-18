<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

function moderationTestAssert(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

$migration = require __DIR__ . '/../data/migrations/20260717_comment_moderation.php';
$data = [
    'comments' => [
        ['id' => 'active', 'is_active' => true],
        ['id' => 'inactive', 'is_active' => false],
        ['id' => 'pending', 'status' => 'pending', 'is_active' => true],
        ['id' => 'published', 'status' => 'published', 'is_active' => false],
        ['id' => 'rejected', 'status' => 'rejected', 'is_active' => true],
    ],
];

$migration($data);
$comments = array_column($data['comments'], null, 'id');

moderationTestAssert($comments['active']['status'] === 'published', 'No se preservó un comentario activo como publicado.');
moderationTestAssert($comments['inactive']['status'] === 'rejected', 'No se preservó un comentario inactivo como rechazado.');
moderationTestAssert($comments['pending']['is_active'] === false, 'Un comentario pendiente quedó activo.');
moderationTestAssert($comments['published']['is_active'] === true, 'Un comentario publicado quedó inactivo.');
moderationTestAssert($comments['rejected']['is_active'] === false, 'Un comentario rechazado quedó activo.');
moderationTestAssert(normalizeCommentStatus(['status' => 'pending', 'is_active' => true]) === 'pending', 'No se respetó el estado pendiente.');
moderationTestAssert(normalizeCommentStatus(['is_active' => true]) === 'published', 'Falló la compatibilidad con comentarios activos antiguos.');
moderationTestAssert(normalizeCommentStatus(['is_active' => false]) === 'rejected', 'Falló la compatibilidad con comentarios inactivos antiguos.');

echo 'OK: estados y migración de moderación verificados.' . PHP_EOL;
