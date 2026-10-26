<?php

require_once __DIR__ . '/functions.php';

define('HERO_CAROUSEL_FILE', __DIR__ . '/../data/hero-carousel.json');
define('HERO_CAROUSEL_BACKUP_FILE', __DIR__ . '/../data/hero-carousel.backup.json');
define('HERO_CAROUSEL_LOCK_FILE', __DIR__ . '/../data/hero-carousel.lock');

function defaultHeroCarouselData(): array {
    return [
        'interval' => 6500,
        'slides' => [
            [
                'id' => 'default-talent',
                'image_url' => 'assets/uploads/talento-humano.png',
                'alt' => 'Identidad visual de Talento Humano de Unitrópico',
                'position' => 'center center',
                'active' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 'default-integrity',
                'image_url' => 'assets/uploads/politica-integridad-banner.png',
                'alt' => 'Política de Integridad institucional de Unitrópico',
                'position' => 'center center',
                'active' => true,
                'sort_order' => 2,
            ],
            [
                'id' => 'default-wellbeing',
                'image_url' => 'assets/uploads/incentivo-bicicleta-unitropico.png',
                'alt' => 'Bienestar y movilidad sostenible en Unitrópico',
                'position' => 'center center',
                'active' => true,
                'sort_order' => 3,
            ],
        ],
    ];
}

function heroCarouselText(string $value, int $limit = 180): string {
    $value = trim(strip_tags($value));
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $limit, 'UTF-8')
        : substr($value, 0, $limit);
}

function normalizeHeroCarouselData(array $data): array {
    $allowedPositions = ['center center', 'center top', 'center bottom', 'left center', 'right center'];
    $slides = [];

    foreach ((array)($data['slides'] ?? []) as $index => $slide) {
        if (!is_array($slide)) continue;
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($slide['id'] ?? ''));
        $imageUrl = ltrim(str_replace('\\', '/', trim((string)($slide['image_url'] ?? ''))), '/');
        if ($id === '' || !preg_match('~^assets/uploads/[a-zA-Z0-9._-]+$~', $imageUrl)) continue;

        $position = (string)($slide['position'] ?? 'center center');
        if (!in_array($position, $allowedPositions, true)) $position = 'center center';

        $slides[] = [
            'id' => $id,
            'image_url' => $imageUrl,
            'alt' => heroCarouselText((string)($slide['alt'] ?? 'Imagen del Portal de Bienestar Social Laboral')),
            'position' => $position,
            'active' => !empty($slide['active']),
            'sort_order' => max(0, (int)($slide['sort_order'] ?? ($index + 1))),
        ];
    }

    usort($slides, static fn(array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);

    return [
        'interval' => max(4000, min(12000, (int)($data['interval'] ?? 6500))),
        'slides' => array_values($slides),
    ];
}

function readHeroCarouselData(): array {
    if (!is_file(HERO_CAROUSEL_FILE)) return normalizeHeroCarouselData(defaultHeroCarouselData());
    return withFileLock(
        HERO_CAROUSEL_LOCK_FILE,
        LOCK_SH,
        static fn(): array => normalizeHeroCarouselData(decodeJsonFile(HERO_CAROUSEL_FILE))
    );
}

function updateHeroCarouselData(callable $mutator) {
    return withFileLock(HERO_CAROUSEL_LOCK_FILE, LOCK_EX, function () use ($mutator) {
        $data = is_file(HERO_CAROUSEL_FILE)
            ? normalizeHeroCarouselData(decodeJsonFile(HERO_CAROUSEL_FILE))
            : normalizeHeroCarouselData(defaultHeroCarouselData());

        if (is_file(HERO_CAROUSEL_FILE) && !writeJsonAtomically(HERO_CAROUSEL_BACKUP_FILE, $data)) {
            throw new RuntimeException('No fue posible crear el respaldo previo del carrusel.');
        }

        $result = $mutator($data);
        $data = normalizeHeroCarouselData($data);
        if (!writeJsonAtomically(HERO_CAROUSEL_FILE, $data)) {
            throw new RuntimeException('No fue posible guardar la configuración del carrusel.');
        }
        return $result;
    });
}

function heroCarouselSlides(bool $onlyActive = true): array {
    $slides = readHeroCarouselData()['slides'];
    if ($onlyActive) {
        $slides = array_values(array_filter($slides, static function (array $slide): bool {
            if (empty($slide['active'])) return false;
            $file = __DIR__ . '/../' . $slide['image_url'];
            return is_file($file);
        }));
    }

    if ($onlyActive && !$slides) {
        $fallback = normalizeHeroCarouselData(defaultHeroCarouselData())['slides'][0];
        return is_file(__DIR__ . '/../' . $fallback['image_url']) ? [$fallback] : [];
    }
    return $slides;
}

function heroCarouselInterval(): int {
    return (int)readHeroCarouselData()['interval'];
}

