<?php
/**
 * Beauty Hub Control - Customizzazione Login e Menu Titolari
 * Include integrazione Magic Link per CRM Piramide35
 */

require_once __DIR__ . '/beauty-shortcodes.php';
require_once __DIR__ . '/shortcode-lavori.php'; 

add_filter( 'wp_nav_menu_items', 'gestione_hub_beauty', 10, 2 );

function gestione_hub_beauty( $items, $args ) {
    if ( $args->theme_location == 'primary' ) {
        if ( !is_user_logged_in() ) {
            // TASTO ACCEDI PER TUTTI (Clienti e Titolari)
            $items .= '<li class="menu-item login-btn"><a href="' . wp_login_url() . '" style="color:#d4af37; font-weight:bold;">✨ ACCEDI</a></li>';
        } else {
            $user = wp_get_current_user();
            $username = strtolower($user->user_login);
            $titolari = function_exists('beauty_hub_titolari_usernames')
                ? beauty_hub_titolari_usernames()
                : array( 'info', 'pasquale', 'emilia', 'rosalia', 'rzsvmeqjjinx' );

            if ( in_array( $username, $titolari, true ) ) {
                
                // --- INIZIO: GENERAZIONE MAGIC LINK E SHORTCODE ---
                $link_crm = genera_url_magico_crm();
                $link_hub_promo = home_url( '/hub-ponte.php?dest=promos' );
                // --- FINE MAGIC LINK ---

                // MENU TITOLARI (Pulito, senza stili invadenti)
                $items .= '<li class="menu-item menu-item-has-children">
                            <a href="#" style="color:#d4af37;">💎 AREA TITOLARI</a>
                            <ul class="sub-menu">
                                <li><a href="' . esc_url( $link_hub_promo ) . '" target="_blank" rel="noopener">🎯 Promo &amp; volantini</a></li>
                                <li><a href="/sharing/ponte.php">📂 File Condivisi</a></li>
                                <li><a href="/notifiche_wa/ponte_promemoria.php">📅 Prenotazioni & SMS</a></li>
                                <li><a href="/gestionale/ponte.php">📊 GESTIONALE</a></li>
                                <li><a href="/gestionale/nostri_lavori/index.php">📸 Gestione Lavori</a></li>
                                
                                <li><a href="' . $link_crm . '" target="_blank">📈 Servizi Attivi M 3.5</a></li>
                                
                                <li><a href="' . wp_logout_url( home_url() ) . '">🚪 Esci</a></li>
                            </ul>
                           </li>';
            } else {
                // MENU CLIENTE (Futuro)
                $items .= '<li class="menu-item"><a>👤 Ciao, ' . $user->display_name . '</a></li>';
            }
        }
    }
    return $items;
}

// --- FUNZIONE PER IL LINK E LO SHORTCODE ---
function genera_url_magico_crm() {
    $chiave_segreta = "SuperSegreto_Piramide35_2026!"; 
    $cf_pasquale = "DRNRSL74A43G786H,02227370760"; 
    $firma_digitale = hash('sha256', $cf_pasquale . $chiave_segreta);
    return "https://piramide35.com/clients/beauty/dashboard.php?cf=" . urlencode($cf_pasquale) . "&firma=" . $firma_digitale;
}

// Attiviamo lo shortcode per poterlo usare nel blocco HTML
add_shortcode('link_crm_m35', 'genera_url_magico_crm');

/**
 * CUSTOM BRANDING LOGIN
 */

// 1. Cambiamo il Logo e lo Stile
add_action( 'login_enqueue_scripts', 'beauty_custom_login_style' );
function beauty_custom_login_style() { ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url(<?php echo home_url(); ?>/wp-content/uploads/2026/03/Logo.png);
            height: 100px;
            width: 320px;
            background-size: contain;
            background-repeat: no-repeat;
            padding-bottom: 30px;
        }
        body.login {
            background: #fdfdfd; 
        }
        .login #login_error { border-left-color: #d4af37; }
        .login .message { border-left-color: #d4af37; }
        
        /* Tasto Accedi Oro */
        .wp-core-ui .button-primary {
            background-color: #d4af37 !important;
            border-color: #c4a030 !important;
            color: #fff !important;
            text-shadow: none !important;
            box-shadow: none !important;
        }
        .wp-core-ui .button-primary:hover {
            background-color: #b38f2a !important;
        }
    </style>
<?php }

// 2. Cambiamo il link del logo (punta alla Home del sito)
add_filter( 'login_headerurl', function() { return home_url(); } );

// 3. Cambiamo il titolo del logo
add_filter( 'login_headertext', function() { return 'Area Riservata Beauty of Image'; } );


/**
 * =========================================================
 * 3. REINDIRIZZAMENTO LOGIN (SOLO PER EDITORI/TITOLARI)
 * =========================================================
 */
add_filter( 'login_redirect', 'beauty_editor_login_redirect', 10, 3 );
function beauty_editor_login_redirect( $redirect_to, $request, $user ) {
    
    // Sicurezza di base: controlliamo che l'utente sia valido
    if ( is_a( $user, 'WP_User' ) ) {
        // Se l'utente è un EDITORE, lo mandiamo alla nuova pagina Hub
        if ( in_array( 'editor', (array) $user->roles ) ) {
            return home_url( '/hub-titolari/' );
        }
    }
    
    // Per tutti gli altri (Admin, Clienti, ecc.), non facciamo nulla.
    return $redirect_to;
}
?>