<?php

require_once __DIR__ . '/database.php';

function databaseEncodeValue($value): string {
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('No fue posible serializar un valor para MariaDB.');
    }
    return $json;
}

function databaseDecodeValue(?string $json, $default = null) {
    if ($json === null || trim($json) === '') return $default;
    $value = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('MariaDB contiene un valor JSON inválido.');
    }
    return $value;
}

function databaseAcquireLock(PDO $pdo, string $lockName): void {
    $statement = $pdo->prepare('SELECT lock_name FROM storage_locks WHERE lock_name = ? FOR UPDATE');
    $statement->execute([$lockName]);
    if ($statement->fetchColumn() === false) {
        throw new RuntimeException('No existe el bloqueo de almacenamiento "' . $lockName . '". Importa database/schema.sql.');
    }
}

function databaseAppliedDataMigrations(): array {
    $statement = databaseConnection()->prepare("SELECT value_json FROM app_meta WHERE meta_key = 'migrations'");
    $statement->execute();
    $applied = databaseDecodeValue($statement->fetchColumn() ?: '[]', []);
    return array_values(array_filter((array)$applied, 'is_string'));
}

/**
 * Reconstruye el mismo arreglo que consumía el almacenamiento JSON. Mantener
 * este contrato permite usar la interfaz y el panel existentes sin duplicar
 * reglas de negocio durante la migración.
 */
function databaseReadData(?PDO $pdo = null): array {
    $pdo = $pdo ?? databaseConnection();
    $ownsTransaction = !$pdo->inTransaction();

    if ($ownsTransaction) $pdo->beginTransaction();

    try {
        $data = [
            'config' => [],
            'pages' => [],
            'cards' => [],
            'admin' => [],
            'program_pages' => [],
            'comments' => [],
            'analytics' => ['monthly' => []],
            '_meta' => [],
        ];

        foreach ($pdo->query('SELECT config_key, value_json FROM site_config ORDER BY config_key') as $row) {
            $data['config'][$row['config_key']] = databaseDecodeValue($row['value_json']);
        }

        foreach ($pdo->query('SELECT slug, data_json FROM pages ORDER BY sort_order, slug') as $row) {
            $page = databaseDecodeValue($row['data_json'], []);
            if (!is_array($page)) $page = [];
            $page['slug'] = $page['slug'] ?? $row['slug'];
            $data['pages'][] = $page;
            $data['cards'][$row['slug']] = [];
        }

        foreach ($pdo->query('SELECT page_slug, card_id, data_json FROM cards ORDER BY page_slug, sort_order, card_id') as $row) {
            $card = databaseDecodeValue($row['data_json'], []);
            if (!is_array($card)) $card = [];
            $card['id'] = $card['id'] ?? $row['card_id'];
            $data['cards'][$row['page_slug']][] = $card;
        }

        $admin = $pdo->query('SELECT username, password_hash, auth_version FROM admin_users ORDER BY username LIMIT 1')->fetch();
        if ($admin) {
            $data['admin'] = [
                'username' => $admin['username'],
                'password_hash' => $admin['password_hash'],
                'auth_version' => (int)$admin['auth_version'],
            ];
        }

        foreach ($pdo->query('SELECT slug, content_json FROM program_pages ORDER BY slug') as $row) {
            $content = databaseDecodeValue($row['content_json'], []);
            $data['program_pages'][$row['slug']] = is_array($content) ? $content : [];
        }

        foreach ($pdo->query('SELECT id, data_json FROM comments ORDER BY created_at DESC, id DESC') as $row) {
            $comment = databaseDecodeValue($row['data_json'], []);
            if (!is_array($comment)) $comment = [];
            $comment['id'] = $comment['id'] ?? $row['id'];
            $data['comments'][] = $comment;
        }

        foreach ($pdo->query('SELECT month_key, data_json FROM analytics_months ORDER BY month_key') as $row) {
            $bucket = databaseDecodeValue($row['data_json'], []);
            $data['analytics']['monthly'][$row['month_key']] = is_array($bucket) ? $bucket : [];
        }

        foreach ($pdo->query('SELECT meta_key, value_json FROM app_meta ORDER BY meta_key') as $row) {
            $data['_meta'][$row['meta_key']] = databaseDecodeValue($row['value_json']);
        }

        if ($ownsTransaction) $pdo->commit();
        return $data;
    } catch (Throwable $error) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function databaseReplaceData(PDO $pdo, array $data): void {
    $pdo->exec('DELETE FROM cards');
    $pdo->exec('DELETE FROM pages');
    $pdo->exec('DELETE FROM program_pages');
    $pdo->exec('DELETE FROM comments');
    $pdo->exec('DELETE FROM analytics_months');
    $pdo->exec('DELETE FROM site_config');
    $pdo->exec('DELETE FROM admin_users');
    $pdo->exec('DELETE FROM app_meta');

    $statement = $pdo->prepare('INSERT INTO site_config (config_key, value_json) VALUES (?, ?)');
    foreach ((array)($data['config'] ?? []) as $key => $value) {
        $statement->execute([(string)$key, databaseEncodeValue($value)]);
    }

    $statement = $pdo->prepare(
        'INSERT INTO pages (slug, title, sort_order, is_active, data_json) VALUES (?, ?, ?, ?, ?)'
    );
    foreach ((array)($data['pages'] ?? []) as $page) {
        $slug = trim((string)($page['slug'] ?? ''));
        if ($slug === '') continue;
        $statement->execute([
            $slug,
            (string)($page['title'] ?? $slug),
            (int)($page['sort_order'] ?? 0),
            !empty($page['is_active']) ? 1 : 0,
            databaseEncodeValue($page),
        ]);
    }

    $statement = $pdo->prepare(
        'INSERT INTO cards (page_slug, card_id, title, sort_order, is_active, data_json) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ((array)($data['cards'] ?? []) as $pageSlug => $cards) {
        foreach ((array)$cards as $card) {
            $cardId = trim((string)($card['id'] ?? ''));
            if ($cardId === '') continue;
            $statement->execute([
                (string)$pageSlug,
                $cardId,
                (string)($card['title'] ?? $cardId),
                (int)($card['sort_order'] ?? 0),
                !empty($card['is_active']) ? 1 : 0,
                databaseEncodeValue($card),
            ]);
        }
    }

    $admin = (array)($data['admin'] ?? []);
    if (!empty($admin['username']) && !empty($admin['password_hash'])) {
        $statement = $pdo->prepare(
            'INSERT INTO admin_users (username, password_hash, auth_version) VALUES (?, ?, ?)'
        );
        $statement->execute([
            (string)$admin['username'],
            (string)$admin['password_hash'],
            max(1, (int)($admin['auth_version'] ?? 1)),
        ]);
    }

    $statement = $pdo->prepare('INSERT INTO program_pages (slug, content_json) VALUES (?, ?)');
    foreach ((array)($data['program_pages'] ?? []) as $slug => $content) {
        $statement->execute([(string)$slug, databaseEncodeValue($content)]);
    }

    $statement = $pdo->prepare(
        'INSERT INTO comments (id, name, message, rating, emoji, parent_id, created_at, is_active, data_json) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ((array)($data['comments'] ?? []) as $comment) {
        $id = trim((string)($comment['id'] ?? ''));
        if ($id === '') continue;
        $parentId = trim((string)($comment['parent_id'] ?? ''));
        $statement->execute([
            $id,
            (string)($comment['name'] ?? 'Visitante'),
            (string)($comment['message'] ?? ''),
            max(1, min(5, (int)($comment['rating'] ?? 5))),
            (string)($comment['emoji'] ?? '💚'),
            $parentId !== '' ? $parentId : null,
            (string)($comment['created_at'] ?? date('c')),
            !empty($comment['is_active']) ? 1 : 0,
            databaseEncodeValue($comment),
        ]);
    }

    $statement = $pdo->prepare('INSERT INTO analytics_months (month_key, data_json) VALUES (?, ?)');
    foreach ((array)($data['analytics']['monthly'] ?? []) as $month => $bucket) {
        $statement->execute([(string)$month, databaseEncodeValue($bucket)]);
    }

    $statement = $pdo->prepare('INSERT INTO app_meta (meta_key, value_json) VALUES (?, ?)');
    foreach ((array)($data['_meta'] ?? []) as $key => $value) {
        $statement->execute([(string)$key, databaseEncodeValue($value)]);
    }
}

function databaseSaveData(array $data): bool {
    $pdo = databaseConnection();
    $pdo->beginTransaction();
    try {
        databaseAcquireLock($pdo, 'content');
        databaseReplaceData($pdo, $data);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function databaseUpdateData(callable $mutator) {
    $pdo = databaseConnection();
    $pdo->beginTransaction();
    try {
        databaseAcquireLock($pdo, 'content');
        $data = databaseReadData($pdo);
        $result = $mutator($data);
        databaseReplaceData($pdo, $data);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

/**
 * Variante usada por migraciones: solo reescribe las tablas cuando el callback
 * confirma que realmente cambió el contenido.
 */
function databaseUpdateDataIfChanged(callable $mutator): bool {
    $pdo = databaseConnection();
    $pdo->beginTransaction();
    try {
        databaseAcquireLock($pdo, 'content');
        $data = databaseReadData($pdo);
        $changed = (bool)$mutator($data);
        if ($changed) databaseReplaceData($pdo, $data);
        $pdo->commit();
        return $changed;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function databaseUpdateSecurityState(callable $mutator) {
    $pdo = databaseConnection();
    $pdo->beginTransaction();
    try {
        databaseAcquireLock($pdo, 'security');
        $statement = $pdo->prepare("SELECT state_json FROM security_state WHERE state_key = 'global' FOR UPDATE");
        $statement->execute();
        $state = databaseDecodeValue($statement->fetchColumn() ?: '{}', []);
        if (!is_array($state)) $state = [];
        $result = $mutator($state);

        $statement = $pdo->prepare(
            "INSERT INTO security_state (state_key, state_json) VALUES ('global', ?) "
            . 'ON DUPLICATE KEY UPDATE state_json = VALUES(state_json), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([databaseEncodeValue($state)]);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function databaseUpdateAnalyticsMonth(string $month, callable $mutator): void {
    $pdo = databaseConnection();
    $pdo->beginTransaction();
    try {
        databaseAcquireLock($pdo, 'content');
        $statement = $pdo->prepare('SELECT data_json FROM analytics_months WHERE month_key = ? FOR UPDATE');
        $statement->execute([$month]);
        $bucket = databaseDecodeValue($statement->fetchColumn() ?: '{}', []);
        if (!is_array($bucket)) $bucket = [];
        $mutator($bucket);

        $statement = $pdo->prepare(
            'INSERT INTO analytics_months (month_key, data_json) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE data_json = VALUES(data_json), updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([$month, databaseEncodeValue($bucket)]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function databaseInsertComment(array $comment): void {
    $pdo = databaseConnection();
    $statement = $pdo->prepare(
        'INSERT INTO comments (id, name, message, rating, emoji, parent_id, created_at, is_active, data_json) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $parentId = trim((string)($comment['parent_id'] ?? ''));
    $statement->execute([
        (string)$comment['id'],
        (string)$comment['name'],
        (string)$comment['message'],
        (int)$comment['rating'],
        (string)$comment['emoji'],
        $parentId !== '' ? $parentId : null,
        (string)$comment['created_at'],
        !empty($comment['is_active']) ? 1 : 0,
        databaseEncodeValue($comment),
    ]);
}

function databaseDeleteComment(string $commentId): void {
    $statement = databaseConnection()->prepare('DELETE FROM comments WHERE id = ? OR parent_id = ?');
    $statement->execute([$commentId, $commentId]);
}

function databaseUpdateCommentStatus(string $commentId, string $status): bool {
    $pdo = databaseConnection();
    $pdo->beginTransaction();
    try {
        databaseAcquireLock($pdo, 'content');
        $statement = $pdo->prepare('SELECT data_json FROM comments WHERE id = ? FOR UPDATE');
        $statement->execute([$commentId]);
        $encoded = $statement->fetchColumn();
        if ($encoded === false) {
            $pdo->commit();
            return false;
        }

        $comment = databaseDecodeValue((string)$encoded, []);
        if (!is_array($comment)) $comment = [];
        $comment['status'] = $status;
        $comment['is_active'] = $status === 'published';

        $statement = $pdo->prepare(
            'UPDATE comments SET is_active = ?, data_json = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?'
        );
        $statement->execute([
            $status === 'published' ? 1 : 0,
            databaseEncodeValue($comment),
            $commentId,
        ]);
        $pdo->commit();
        return true;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function databaseCanReplyToComment(string $commentId): bool {
    $statement = databaseConnection()->prepare(
        "SELECT COUNT(*) FROM comments WHERE id = ? AND is_active = 1 AND (parent_id IS NULL OR parent_id = '')"
    );
    $statement->execute([$commentId]);
    return (int)$statement->fetchColumn() === 1;
}

function databaseUpdateAdminPassword(string $username, string $passwordHash): int {
    $pdo = databaseConnection();
    $pdo->beginTransaction();
    try {
        databaseAcquireLock($pdo, 'content');
        $statement = $pdo->prepare(
            'UPDATE admin_users SET password_hash = ?, auth_version = auth_version + 1 WHERE username = ?'
        );
        $statement->execute([$passwordHash, $username]);
        if ($statement->rowCount() !== 1) throw new RuntimeException('No se encontró la cuenta administrativa.');

        $statement = $pdo->prepare('SELECT auth_version FROM admin_users WHERE username = ?');
        $statement->execute([$username]);
        $version = (int)$statement->fetchColumn();
        $pdo->commit();
        return $version;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}

function databaseDataCounts(array $data): array {
    $cards = 0;
    foreach ((array)($data['cards'] ?? []) as $group) $cards += count((array)$group);

    return [
        'config' => count((array)($data['config'] ?? [])),
        'pages' => count((array)($data['pages'] ?? [])),
        'cards' => $cards,
        'program_pages' => count((array)($data['program_pages'] ?? [])),
        'comments' => count((array)($data['comments'] ?? [])),
        'analytics_months' => count((array)($data['analytics']['monthly'] ?? [])),
    ];
}
