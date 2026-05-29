<?php
/**
 * Template Name: Promo Hub Core
 *
 * Copia questo file nella cartella del tema WordPress attivo
 * (es. wp-content/themes/tuo-tema/) e crea una pagina con template "Promo Hub Core".
 *
 * Sostituisci HUB_PROMO_URL con l'URL embed della promo da hub-core admin.
 */
get_header();
?>
<main id="hub-promo-wrapper" style="max-width:1200px;margin:0 auto;padding:0 16px 48px">
    <iframe
        src="<?php echo esc_url('HUB_PROMO_URL'); ?>"
        title="Promozioni"
        style="width:100%;min-height:920px;border:0;border-radius:16px"
        loading="lazy"
    ></iframe>
</main>
<?php
get_footer();
