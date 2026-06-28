<?php
/**
 * Ponte SSO Beauty → Hub Core (promo admin)
 * Pattern come sharing/ponte.php: utente WP loggato → hub inm35.it già autenticato.
 */
require_once __DIR__.'/wp-load.php';

$dest = $_GET['dest'] ?? 'app';
if (! in_array($dest, ['app', 'promos'], true)) {
    $dest = 'app';
}

if (! is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(home_url('/hub-ponte.php?dest='.$dest)));
    exit;
}

if (! function_exists('beauty_hub_current_user_is_titolare') || ! beauty_hub_current_user_is_titolare()) {
    wp_die('Accesso riservato ai titolari Beauty of Image.', 'Non autorizzato', ['response' => 403]);
}

if (! function_exists('beauty_hub_bridge_url')) {
    wp_die('Plugin Hub Core non configurato (beauty-hub-core.php).', 'Errore configurazione', ['response' => 503]);
}

$url = beauty_hub_bridge_url($dest);

if (! $url) {
    wp_die('Ponte Hub non configurato: imposta BEAUTY_HUB_BRIDGE_SECRET uguale a HUB_BRIDGE_SECRET su inm35.it.', 'Configurazione mancante', ['response' => 503]);
}

wp_safe_redirect($url);
exit;
