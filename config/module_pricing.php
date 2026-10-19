<?php

/**
 * Listino unico. Il canone di Hub Core (config/services.php → hub_billing) comprende TUTTI i moduli:
 * nessuna attivazione e nessun canone per singolo modulo (restano a 0 per non rompere le viste).
 * Qui restano solo le quote incluse e gli extra facoltativi, tutti a prezzi bassi.
 */
return [

    'servizi' => [
        'label' => 'Servizi e prodotti (link di pagamento)',
        'activation_cents' => (int) env('PRICING_SERVIZI_ACTIVATION_EUR', 0) * 100,
        'monthly_cents' => (int) env('PRICING_SERVIZI_MONTHLY_EUR', 0) * 100,
        'included_per_month' => (int) env('PRICING_SERVIZI_INCLUDED', 20),
        'extra_staff_cents' => (int) env('PRICING_SERVIZI_EXTRA_STAFF_EUR', 10) * 100,
        'extra_self_cents' => (int) env('PRICING_SERVIZI_EXTRA_SELF_EUR', 2) * 100,
    ],

    'promo' => [
        'label' => 'Promo (pagina inm35.it + pagina cliente + banner home)',
        'activation_cents' => (int) env('PRICING_PROMO_ACTIVATION_EUR', 0) * 100,
        'monthly_cents' => (int) env('PRICING_PROMO_MONTHLY_EUR', 0) * 100,
        // Quota mensile dei privati (le aziende hanno hub.promo_included_quota).
        'included_per_month' => (int) env('PRICING_PROMO_INCLUDED', 2),
        'extra_staff_cents' => (int) env('PRICING_PROMO_EXTRA_STAFF_EUR', 25) * 100,
        'extra_self_cents' => (int) env('PRICING_PROMO_EXTRA_SELF_EUR', 5) * 100,
    ],

    'iva_percent' => (int) env('PRICING_IVA_PERCENT', 22),

];
