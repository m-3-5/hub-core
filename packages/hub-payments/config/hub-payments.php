<?php

return [

    'connection' => env('HUB_PAYMENTS_DB_CONNECTION'),

    'services_included_quota' => (int) env('HUB_PAYMENTS_SERVICES_QUOTA', 3),

    'services_paid_price' => (int) env('HUB_PAYMENTS_SERVICES_PAID_PRICE', 9),

    'currency' => env('HUB_PAYMENTS_CURRENCY', 'eur'),

    'payment_methods' => ['card', 'klarna'],

    'types' => [
        'service' => 'Servizio',
        'promo' => 'Promo a pagamento',
        'product' => 'Prodotto',
    ],

];
