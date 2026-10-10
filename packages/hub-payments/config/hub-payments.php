<?php

return [

    'connection' => env('HUB_PAYMENTS_DB_CONNECTION'),

    'services_included_quota' => (int) env('HUB_PAYMENTS_SERVICES_QUOTA', 3),

    'services_paid_price' => (int) env('HUB_PAYMENTS_SERVICES_PAID_PRICE', 9),

    'currency' => env('HUB_PAYMENTS_CURRENCY', 'eur'),

    // Solo per prove in locale con un finto Stripe: in produzione resta l'indirizzo ufficiale.
    'stripe_api_base' => env('HUB_PAYMENTS_STRIPE_API_BASE', 'https://api.stripe.com'),

    // Trattenute di Stripe sulle carte (indicative, per spiegarle ai venditori: le tariffe vere le applica Stripe).
    'fees' => [
        'card_percent' => (float) env('HUB_STRIPE_FEE_PERCENT', 1.5),
        'card_fixed_cents' => (int) env('HUB_STRIPE_FEE_FIXED_CENTS', 25),
    ],

    // Pagamenti protetti: giorni di attesa prima di girare i soldi al venditore se il cliente non conferma né segnala problemi.
    'protected' => [
        'hold_days' => (int) env('HUB_PROTECTED_HOLD_DAYS', 7),
    ],

    'types' => [
        'service' => 'Servizio',
        'promo' => 'Promo a pagamento',
        'product' => 'Prodotto',
    ],

];
