<?php
/**
 * Plugin Name: Beauty Hub Services Sync
 * Description: Sincronizza i servizi/pagamenti da hub-core (inm35.it) e li mostra con [beauty_services]. File indipendente da beauty-hub-core.php — non lo modifica, non ne dipende.
 * Version: 1.0.0
 * Author: Hub Core
 *
 * Installazione: copia in wp-content/mu-plugins/beauty-hub-services-sync.php (accanto agli altri file, senza toccarli)
 */

if (! defined('ABSPATH')) {
    exit;
}

// ── Configurazione ───────────────────────────────────────────────────────────
if (! defined('BEAUTY_HUB_URL')) {
    $beauty_hub_services_default_url = (str_contains(home_url(), '.test') || str_contains(home_url(), 'localhost'))
        ? 'http://hub-core.test'
        : 'https://inm35.it';
    define('BEAUTY_HUB_URL', $beauty_hub_services_default_url);
}
if (! defined('BEAUTY_HUB_TENANT')) {
    define('BEAUTY_HUB_TENANT', 'beauty-of-image');
}
if (! defined('BEAUTY_HUB_WEBHOOK_SECRET')) {
    // Se beauty-hub-core.php è già installato, questa costante è già definita lì con il vero secret
    // (deve combaciare con HUB_WEBHOOK_SECRET nel .env dell'hub) e questo default non viene mai usato.
    define('BEAUTY_HUB_WEBHOOK_SECRET', 'CAMBIA-QUESTO-SECRET');
}
if (! defined('BEAUTY_HUB_SERVICES_OPTION_KEY')) {
    define('BEAUTY_HUB_SERVICES_OPTION_KEY', 'beauty_hub_services_cache');
}

// ── Cache ────────────────────────────────────────────────────────────────────
function beauty_hub_services_get_cache(): array
{
    $cache = get_option(BEAUTY_HUB_SERVICES_OPTION_KEY, []);

    return is_array($cache) ? $cache : [];
}

function beauty_hub_services_save_cache(array $data): void
{
    update_option(BEAUTY_HUB_SERVICES_OPTION_KEY, $data, false);
}

function beauty_hub_services_api_url(): string
{
    return rtrim(BEAUTY_HUB_URL, '/').'/api/v1/'.BEAUTY_HUB_TENANT.'/services';
}

function beauty_hub_services_verify_signature(string $body, ?string $header): bool
{
    if (! $header || ! str_starts_with($header, 'sha256=')) {
        return false;
    }

    $expected = 'sha256='.hash_hmac('sha256', $body, BEAUTY_HUB_WEBHOOK_SECRET);

    return hash_equals($expected, $header);
}

function beauty_hub_services_sync_from_api(): bool
{
    $response = wp_remote_get(beauty_hub_services_api_url(), [
        'timeout' => 20,
        'headers' => ['Accept' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return false;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (! is_array($data)) {
        return false;
    }

    beauty_hub_services_save_cache($data);

    return true;
}

// ── Webhook REST (hub-core → WordPress) — endpoint dedicato, separato da /sync ──
add_action('rest_api_init', function () {
    register_rest_route('beauty-hub/v1', '/sync-services', [
        'methods' => 'POST',
        'callback' => 'beauty_hub_services_rest_sync',
        'permission_callback' => '__return_true',
    ]);
});

function beauty_hub_services_rest_sync(\WP_REST_Request $request): \WP_REST_Response
{
    $body = $request->get_body();
    $signature = $request->get_header('x_hub_signature');

    if (! beauty_hub_services_verify_signature($body, $signature)) {
        return new \WP_REST_Response(['error' => 'Invalid signature'], 401);
    }

    beauty_hub_services_sync_from_api();

    return new \WP_REST_Response(['ok' => true, 'synced_at' => gmdate('c')], 200);
}

// ── Cron di backup (ogni 15 min) ─────────────────────────────────────────────
add_action('init', function () {
    if (! wp_next_scheduled('beauty_hub_services_cron_sync')) {
        wp_schedule_event(time(), 'beauty_hub_services_quarterhour', 'beauty_hub_services_cron_sync');
    }
});

add_filter('cron_schedules', function (array $schedules): array {
    if (! isset($schedules['beauty_hub_services_quarterhour'])) {
        $schedules['beauty_hub_services_quarterhour'] = [
            'interval' => 900,
            'display' => 'Every 15 minutes (Beauty Hub Services)',
        ];
    }

    return $schedules;
});

add_action('beauty_hub_services_cron_sync', 'beauty_hub_services_sync_from_api');

// Sync al primo utilizzo se cache vuota
add_action('init', function () {
    $cache = beauty_hub_services_get_cache();
    if (empty($cache['services'])) {
        beauty_hub_services_sync_from_api();
    }
});

// ── Shortcode [beauty_services] ──────────────────────────────────────────────
add_shortcode('beauty_services', 'beauty_hub_services_shortcode');

function beauty_hub_services_shortcode($atts): string
{
    $atts = shortcode_atts(['columns' => '3'], $atts, 'beauty_services');

    $cache = beauty_hub_services_get_cache();
    $services = $cache['services'] ?? [];
    $tenant = $cache['tenant'] ?? [];
    $color = esc_attr($tenant['primary_color'] ?? '#e91e8c');

    if (empty($services)) {
        beauty_hub_services_sync_from_api();
        $cache = beauty_hub_services_get_cache();
        $services = $cache['services'] ?? [];
    }

    if (empty($services)) {
        return '<p class="beauty-hub-services-empty">Nessun servizio disponibile al momento.</p>';
    }

    ob_start();
    ?>
    <style>
        .beauty-hub-services-grid{display:grid;grid-template-columns:repeat(<?php echo (int) $atts['columns']; ?>,minmax(0,1fr));gap:24px;margin:32px 0}
        @media(max-width:768px){.beauty-hub-services-grid{grid-template-columns:1fr}}
        .beauty-hub-service-card{border-radius:16px;overflow:hidden;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.08);border:1px solid rgba(0,0,0,.06);transition:transform .2s}
        .beauty-hub-service-card:hover{transform:translateY(-4px)}
        .beauty-hub-service-card img{width:100%;height:200px;object-fit:cover;display:block;background:linear-gradient(135deg,#f5f5f5,#fff)}
        .beauty-hub-service-card__body{padding:20px}
        .beauty-hub-service-card h3{margin:0 0 8px;font-size:1.25rem;color:<?php echo $color; ?>}
        .beauty-hub-service-card .price{font-weight:700;font-size:1.3rem;color:#333;margin-bottom:10px}
        .beauty-hub-service-card p{margin:0 0 16px;color:#555;line-height:1.5;font-size:.92rem}
        .beauty-hub-service-btn{display:inline-block;background:<?php echo $color; ?>;color:#fff!important;text-decoration:none;padding:12px 22px;border-radius:999px;font-weight:600;font-size:.92rem}
    </style>
    <div class="beauty-hub-services-grid">
        <?php foreach ($services as $service) :
            if (! is_array($service)) {
                continue;
            }
            $title = esc_html($service['title'] ?? 'Servizio');
            $desc = esc_html(wp_trim_words($service['description'] ?? '', 20));
            $price = esc_html($service['amount_label'] ?? '');
            $payUrl = esc_url($service['payment_url'] ?? '#');
            $img = esc_url($service['cover_image_url'] ?? '');
            ?>
            <article class="beauty-hub-service-card">
                <?php if ($img) : ?>
                    <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>" loading="lazy">
                <?php endif; ?>
                <div class="beauty-hub-service-card__body">
                    <h3><?php echo $title; ?></h3>
                    <?php if ($price) : ?><div class="price"><?php echo $price; ?></div><?php endif; ?>
                    <?php if ($desc) : ?><p><?php echo $desc; ?></p><?php endif; ?>
                    <a class="beauty-hub-service-btn" href="<?php echo $payUrl; ?>" target="_top">Prenota e paga ora</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}
