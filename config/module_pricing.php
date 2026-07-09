<?php

return [

    'servizi' => [
        'label' => 'Servizi (link di pagamento/prodotti)',
        'activation_cents' => (int) env('PRICING_SERVIZI_ACTIVATION_EUR', 39) * 100,
        'monthly_cents' => (int) env('PRICING_SERVIZI_MONTHLY_EUR', 15) * 100,
        'included_per_month' => (int) env('PRICING_SERVIZI_INCLUDED', 3),
        'extra_staff_cents' => (int) env('PRICING_SERVIZI_EXTRA_STAFF_EUR', 25) * 100,
        'extra_self_cents' => (int) env('PRICING_SERVIZI_EXTRA_SELF_EUR', 12) * 100,
    ],

    'promo' => [
        'label' => 'Promo (pagina inm35.it + pagina cliente + banner home)',
        'activation_cents' => (int) env('PRICING_PROMO_ACTIVATION_EUR', 50) * 100,
        'monthly_cents' => (int) env('PRICING_PROMO_MONTHLY_EUR', 25) * 100,
        'included_per_month' => (int) env('PRICING_PROMO_INCLUDED', 1),
        'extra_staff_cents' => (int) env('PRICING_PROMO_EXTRA_STAFF_EUR', 75) * 100,
        'extra_self_cents' => (int) env('PRICING_PROMO_EXTRA_SELF_EUR', 35) * 100,
    ],

    'iva_percent' => (int) env('PRICING_IVA_PERCENT', 22),

];
