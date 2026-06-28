<?php
/**
 * Plugin Name: Beauty Hub Core Sync
 * Description: Sincronizza le promo da hub-core (inm35.it) e le mostra con [beauty_promos] senza iframe.
 * Version: 1.2.0
 * Author: Hub Core
 *
 * Installazione: copia in wp-content/mu-plugins/beauty-hub-core.php
 */

if (! defined('ABSPATH')) {
    exit;
}

// ── Configurazione ───────────────────────────────────────────────────────────
if (! defined('BEAUTY_HUB_URL')) {
    define('BEAUTY_HUB_URL', 'https://inm35.it');
}
if (! defined('BEAUTY_HUB_TENANT')) {
    define('BEAUTY_HUB_TENANT', 'beauty-of-image');
}
if (! defined('BEAUTY_HUB_WEBHOOK_SECRET')) {
    define('BEAUTY_HUB_WEBHOOK_SECRET', 'CAMBIA_QUESTO_SECRET_Uguale_A_HUB');
}
if (! defined('BEAUTY_HUB_OPTION_KEY')) {
    define('BEAUTY_HUB_OPTION_KEY', 'beauty_hub_promos_cache');
}
if (! defined('BEAUTY_HUB_BRIDGE_SECRET')) {
    define('BEAUTY_HUB_BRIDGE_SECRET', 'CAMBIA_STESSO_VALORE_HUB_BRIDGE_SECRET');
}

// ── Ponte SSO WordPress → Hub (titolari) ────────────────────────────────────

/** @return list<string> */
function beauty_hub_titolari_usernames(): array
{
    return apply_filters('beauty_hub_titolari_usernames', [
        'info',
        'pasquale',
        'emilia',
        'rosalia',
        'rzsvmeqjjinx',
    ]);
}

function beauty_hub_current_user_is_titolare(): bool
{
    if (! is_user_logged_in()) {
        return false;
    }

    return in_array(
        strtolower(wp_get_current_user()->user_login),
        beauty_hub_titolari_usernames(),
        true
    );
}

function beauty_hub_bridge_configured(): bool
{
    $secret = defined('BEAUTY_HUB_BRIDGE_SECRET') ? BEAUTY_HUB_BRIDGE_SECRET : '';

    return $secret !== '' && ! str_contains($secret, 'CAMBIA');
}

/** @param  'app'|'promos'  $dest */
function beauty_hub_bridge_url(string $dest = 'app'): ?string
{
    if (! beauty_hub_bridge_configured() || ! is_user_logged_in()) {
        return null;
    }

    if (! in_array($dest, ['app', 'promos'], true)) {
        $dest = 'app';
    }

    $hubUrl = rtrim(BEAUTY_HUB_URL, '/');
    $tenant = BEAUTY_HUB_TENANT;
    $wpUser = strtolower(wp_get_current_user()->user_login);
    $ts = time();
    $sig = hash_hmac('sha256', $tenant.'|'.$wpUser.'|'.$ts, BEAUTY_HUB_BRIDGE_SECRET);

    return $hubUrl.'/auth/wp-bridge?'.http_build_query([
        'tenant' => $tenant,
        'wp_user' => $wpUser,
        'ts' => $ts,
        'sig' => $sig,
        'dest' => $dest,
    ]);
}

// ── Cache ────────────────────────────────────────────────────────────────────
function beauty_hub_get_cache(): array
{
    $cache = get_option(BEAUTY_HUB_OPTION_KEY, []);

    return is_array($cache) ? $cache : [];
}

function beauty_hub_save_cache(array $data): void
{
    update_option(BEAUTY_HUB_OPTION_KEY, $data, false);
}

function beauty_hub_api_url(): string
{
    return rtrim(BEAUTY_HUB_URL, '/').'/api/v1/'.BEAUTY_HUB_TENANT.'/promos';
}

function beauty_hub_verify_signature(string $body, ?string $header): bool
{
    if (! $header || ! str_starts_with($header, 'sha256=')) {
        return false;
    }

    $expected = 'sha256='.hash_hmac('sha256', $body, BEAUTY_HUB_WEBHOOK_SECRET);

    return hash_equals($expected, $header);
}

function beauty_hub_sync_from_api(): bool
{
    $response = wp_remote_get(beauty_hub_api_url(), [
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

    beauty_hub_save_cache($data);

    return true;
}

// ── Webhook REST (hub-core → WordPress) ─────────────────────────────────────
add_action('rest_api_init', function () {
    register_rest_route('beauty-hub/v1', '/sync', [
        'methods' => 'POST',
        'callback' => 'beauty_hub_rest_sync',
        'permission_callback' => '__return_true',
    ]);
});

function beauty_hub_rest_sync(\WP_REST_Request $request): \WP_REST_Response
{
    $body = $request->get_body();
    $signature = $request->get_header('x_hub_signature');

    if (! beauty_hub_verify_signature($body, $signature)) {
        return new \WP_REST_Response(['error' => 'Invalid signature'], 401);
    }

    $data = json_decode($body, true);
    if (! is_array($data)) {
        return new \WP_REST_Response(['error' => 'Invalid JSON'], 400);
    }

    // Webhook singolo: arricchisci con pull completo per lista aggiornata
    beauty_hub_sync_from_api();

    return new \WP_REST_Response(['ok' => true, 'synced_at' => gmdate('c')], 200);
}

// ── Cron di backup (ogni 15 min) ─────────────────────────────────────────────
add_action('init', function () {
    if (! wp_next_scheduled('beauty_hub_cron_sync')) {
        wp_schedule_event(time(), 'quarterhour', 'beauty_hub_cron_sync');
    }
});

add_filter('cron_schedules', function (array $schedules): array {
    $schedules['quarterhour'] = [
        'interval' => 900,
        'display' => 'Every 15 minutes',
    ];

    return $schedules;
});

add_action('beauty_hub_cron_sync', 'beauty_hub_sync_from_api');

// Sync al primo utilizzo se cache vuota
add_action('init', function () {
    $cache = beauty_hub_get_cache();
    if (empty($cache['promos'])) {
        beauty_hub_sync_from_api();
    }
});

// ── Shortcode [beauty_promos] ───────────────────────────────────────────────
add_shortcode('beauty_promos', 'beauty_hub_shortcode_promos');

function beauty_hub_shortcode_promos($atts): string
{
    $atts = shortcode_atts(['columns' => '2'], $atts, 'beauty_promos');

    $cache = beauty_hub_get_cache();
    $promos = $cache['promos'] ?? [];
    $tenant = $cache['tenant'] ?? [];
    $color = esc_attr($tenant['primary_color'] ?? '#e91e8c');

    if (empty($promos)) {
        beauty_hub_sync_from_api();
        $cache = beauty_hub_get_cache();
        $promos = $cache['promos'] ?? [];
    }

    if (empty($promos)) {
        return '<p class="beauty-hub-empty">Nessuna promozione attiva al momento.</p>';
    }

    ob_start();
    ?>
    <style>
        .beauty-hub-grid{
            display:grid;
            grid-template-columns:repeat(<?php echo (int) $atts['columns']; ?>,minmax(0,1fr));
            gap:20px;
            margin:24px auto;
            max-width:min(920px,100%);
            padding:0 12px;
        }
        @media(max-width:768px){
            .beauty-hub-grid{grid-template-columns:1fr;max-width:380px}
        }
        .beauty-hub-card{
            border-radius:14px;
            overflow:hidden;
            background:#fff;
            box-shadow:0 6px 22px rgba(0,0,0,.07);
            border:1px solid rgba(0,0,0,.06);
            transition:transform .2s;
            max-width:420px;
            width:100%;
            justify-self:center;
        }
        .beauty-hub-card:hover{transform:translateY(-3px)}
        .beauty-hub-card__media{
            display:block;
            background:linear-gradient(180deg,#fdf8fb,#fff);
            padding:14px 16px 10px;
            border-bottom:1px solid rgba(0,0,0,.05);
        }
        .beauty-hub-card__media img{
            width:100%;
            height:auto;
            max-height:160px;
            object-fit:contain;
            object-position:center;
            display:block;
            margin:0 auto;
        }
        .beauty-hub-card__body{padding:16px 18px 18px}
        .beauty-hub-card h3{margin:0 0 6px;font-size:1.12rem;line-height:1.25;color:<?php echo $color; ?>}
        .beauty-hub-card p{margin:0 0 12px;color:#555;line-height:1.45;font-size:.88rem}
        .beauty-hub-actions{display:flex;flex-wrap:wrap;gap:8px}
        .beauty-hub-btn{display:inline-block;color:#fff!important;text-decoration:none;padding:8px 14px;border-radius:999px;font-weight:600;font-size:.82rem}
        .beauty-hub-btn--primary{background:<?php echo $color; ?>}
        .beauty-hub-btn--outline{background:#fff;color:<?php echo $color; ?>!important;border:2px solid <?php echo $color; ?>}
        .beauty-hub-btn--whatsapp{background:#25D366}
    </style>
    <div class="beauty-hub-grid">
        <?php foreach ($promos as $promo) :
            if (! is_array($promo)) {
                continue;
            }
            $title = esc_html($promo['title'] ?? 'Promozione');
            $desc = esc_html(wp_trim_words($promo['description'] ?? '', 22));
            $url = esc_url($promo['public_url'] ?? '#');
            $img = esc_url($promo['flyer_url'] ?? $promo['image_url'] ?? '');
            $links = $promo['links'] ?? [];
            ?>
            <article class="beauty-hub-card">
                <?php if ($img) : ?>
                    <a class="beauty-hub-card__media" href="<?php echo $url; ?>">
                        <img src="<?php echo $img; ?>" alt="<?php echo $title; ?>" loading="lazy">
                    </a>
                <?php endif; ?>
                <div class="beauty-hub-card__body">
                    <h3><?php echo $title; ?></h3>
                    <?php if ($desc) : ?><p><?php echo $desc; ?></p><?php endif; ?>
                    <div class="beauty-hub-actions">
                        <?php foreach ($links as $link) :
                            if (! is_array($link) || empty($link['url'])) {
                                continue;
                            }
                            $class = 'beauty-hub-btn beauty-hub-btn--primary';
                            if (($link['key'] ?? '') === 'all_promos') {
                                $class = 'beauty-hub-btn beauty-hub-btn--outline';
                            } elseif (($link['key'] ?? '') === 'whatsapp') {
                                $class = 'beauty-hub-btn beauty-hub-btn--whatsapp';
                            }
                            ?>
                            <a class="<?php echo esc_attr($class); ?>" href="<?php echo esc_url($link['url']); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($link['label'] ?? 'Apri'); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}
