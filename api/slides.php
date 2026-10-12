<?php
/**
 * ATHLETICA — REST API: Hero Slides Endpoint
 * GET /api/slides.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_require_method('GET');

try {
    $sql = "
        SELECT id, title, subtitle, media_path, mobile_image, media_type, cta_text, cta_url, sort_order
        FROM hero_slides
        WHERE is_active = 1
        ORDER BY sort_order ASC, id ASC
    ";

    $stmt = $pdo->query($sql);
    $slides = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formattedSlides = array_map(static function (array $slide): array {
        return [
            'id'            => (int)$slide['id'],
            'title'         => (string)$slide['title'],
            'subtitle'      => $slide['subtitle'] ? (string)$slide['subtitle'] : null,
            'media_path'    => (string)$slide['media_path'],
            'desktop_image' => (string)$slide['media_path'],
            'mobile_image'  => !empty($slide['mobile_image']) ? (string)$slide['mobile_image'] : null,
            'media_type'    => (string)$slide['media_type'],
            'cta_text'      => $slide['cta_text'] ? (string)$slide['cta_text'] : null,
            'cta_url'       => $slide['cta_url'] ? (string)$slide['cta_url'] : null,
            'sort_order'    => (int)$slide['sort_order'],
        ];
    }, $slides ?: []);

    api_success($formattedSlides);

} catch (PDOException $e) {
    // If table doesn't exist yet or DB issue, gracefully return empty array
    api_success([]);
}
