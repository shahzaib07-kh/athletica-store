<?php
/**
 * ATHLETICA — API: Site Settings Endpoint
 * GET /api/settings.php
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

api_require_method('GET');

try {
    $stmt = $pdo->query("SELECT * FROM site_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        $settings = [];
    }

    $rawWhatsapp = !empty($settings['whatsapp_number']) ? trim($settings['whatsapp_number']) : null;
    $normalizedWhatsapp = $rawWhatsapp ? normalize_whatsapp_number($rawWhatsapp) : null;

    // Parse business hours JSON if present
    $businessHours = null;
    if (!empty($settings['business_hours'])) {
        $decoded = is_array($settings['business_hours']) 
            ? $settings['business_hours'] 
            : json_decode((string)$settings['business_hours'], true);
        if (is_array($decoded)) {
            $businessHours = $decoded;
        }
    }

    $data = [
        'site_name' => $settings['site_name'] ?? 'ATHLETICA',
        'tagline' => !empty($settings['tagline']) ? (string)$settings['tagline'] : null,
        'logo' => $settings['logo'] ?? null,
        'favicon' => $settings['favicon'] ?? null,
        'desktop_image' => $settings['desktop_image'] ?? null,
        'currency_symbol' => $settings['currency_symbol'] ?? 'Rs.',
        'delivery_fee_default' => number_format((float)($settings['delivery_fee_default'] ?? 0.00), 2, '.', ''),
        'whatsapp_number' => $rawWhatsapp,
        'whatsapp_normalized' => $normalizedWhatsapp,
        'email' => !empty($settings['email']) ? $settings['email'] : null,
        'phone' => !empty($settings['phone']) ? $settings['phone'] : null,
        'address' => !empty($settings['address']) ? $settings['address'] : null,
        'google_map_url' => !empty($settings['google_map_url']) ? $settings['google_map_url'] : null,
        'business_hours' => $businessHours,
        'footer_text' => $settings['footer_text'] ?? ('© ' . date('Y') . ' ATHLETICA. All rights reserved.'),
        'social_links' => [
            'facebook' => !empty($settings['facebook_url']) ? $settings['facebook_url'] : null,
            'instagram' => !empty($settings['instagram_url']) ? $settings['instagram_url'] : null,
            'tiktok' => !empty($settings['tiktok_url']) ? $settings['tiktok_url'] : null,
            'youtube' => !empty($settings['youtube_url']) ? $settings['youtube_url'] : null,
        ],
    ];

    api_success($data);

} catch (PDOException $e) {
    api_error('Database error fetching site settings.', 'DB_ERROR', 500);
}
