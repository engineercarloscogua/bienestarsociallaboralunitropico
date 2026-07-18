<?php

return static function (array &$data): void {
    if (empty($data['comments']) || !is_array($data['comments'])) return;

    $allowedStatuses = ['pending', 'published', 'rejected'];
    foreach ($data['comments'] as &$comment) {
        if (!is_array($comment)) continue;

        $status = strtolower(trim((string)($comment['status'] ?? '')));
        if (!in_array($status, $allowedStatuses, true)) {
            $status = !empty($comment['is_active']) ? 'published' : 'rejected';
        }

        $comment['status'] = $status;
        $comment['is_active'] = $status === 'published';
    }
    unset($comment);
};
