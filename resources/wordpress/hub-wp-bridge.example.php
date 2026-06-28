<?php
/**
 * Esempio ponte SSO WordPress → Hub Core (beautyofimage.com / hub-ponte.php)
 *
 * Flusso come sharing/ponte.php:
 * 1. Titolare loggato su WordPress
 * 2. hub-ponte.php genera link firmato verso Hub
 * 3. Hub riconosce wp_username e apre admin promo (dest=promos) o home app
 *
 * Installazione: vedi beauty-hub-core.php v1.2+ e hub-ponte.php nella root WP.
 */
require_once dirname(__DIR__).'/wp-load.php';

if (! is_user_logged_in() || ! (function_exists('beauty_hub_current_user_is_titolare') && beauty_hub_current_user_is_titolare())) {
    wp_redirect(wp_login_url(home_url('/hub-ponte.php?dest=promos')));
    exit;
}

$dest = $_GET['dest'] ?? 'promos';
if (! in_array($dest, ['app', 'promos'], true)) {
    $dest = 'promos';
}

$url = beauty_hub_bridge_url($dest);

if (! $url) {
    wp_die('Configura BEAUTY_HUB_BRIDGE_SECRET uguale a HUB_BRIDGE_SECRET su Hub Core.');
}

wp_redirect($url);
exit;
