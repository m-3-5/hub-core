<?php
/**
 * Esempio ponte SSO WordPress → Hub Core (da installare in futuro su beautyofimage.com)
 *
 * Flusso come sharing/ponte.php:
 * 1. Utente loggato su WP (editor/admin)
 * 2. Questo script genera link firmato verso Hub
 * 3. Hub riconosce wp_username e apre la home app del tenant
 *
 * NON installare ancora — solo riferimento per integrazione futura.
 */
require_once dirname(__DIR__).'/wp-load.php';

if (! is_user_logged_in() || ! current_user_can('edit_others_posts')) {
    wp_redirect(wp_login_url(home_url('/hub-ponte.php')));
    exit;
}

$hubUrl = 'https://inm35.it'; // o https://hub-core.test in locale
$secret = 'STESSO_VALORE_DI_HUB_BRIDGE_SECRET';
$tenant = 'beauty-of-image';
$wpUser = strtolower(wp_get_current_user()->user_login);
$ts = time();
$sig = hash_hmac('sha256', $tenant.'|'.$wpUser.'|'.$ts, $secret);

$redirect = $hubUrl.'/auth/wp-bridge?'.http_build_query([
    'tenant' => $tenant,
    'wp_user' => $wpUser,
    'ts' => $ts,
    'sig' => $sig,
]);

wp_redirect($redirect);
exit;
