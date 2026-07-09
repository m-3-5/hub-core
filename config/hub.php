<?php

return [

  /*
  |--------------------------------------------------------------------------
  | WordPress bridge (ponte SSO da sito cliente)
  |--------------------------------------------------------------------------
  |
  | Il sito WordPress genera un link firmato verso /auth/wp-bridge con
  | tenant, wp_user, ts e sig = HMAC-SHA256(tenant|wp_user|ts, secret).
  |
  */
    'bridge_secret' => env('HUB_BRIDGE_SECRET'),

    'default_password' => env('HUB_DEFAULT_PASSWORD', 'HubCore2026!'),

    // Immagini promo via Gemini (richiede billing). Se false: volantino + SVG tematici.
    'promo_ai_images' => env('PROMO_AI_IMAGES', false),

    'promo_included_quota' => (int) env('PROMO_INCLUDED_QUOTA', 5),

    'promo_ai_flyer_price' => (int) env('PROMO_AI_FLYER_PRICE', 24),

    'workspace' => [
        'beauty-of-image' => [
            'database' => env('TENANT_BEAUTY_DATABASE', 'hub_beauty'),
            'username' => env('TENANT_BEAUTY_DB_USERNAME'),
            'password' => env('TENANT_BEAUTY_DB_PASSWORD'),
            'url' => env('TENANT_BEAUTY_URL'),
        ],
        'piramide35' => [
            'database' => env('TENANT_PIRAMIDE35_DATABASE', 'hub_piramide35'),
            'username' => env('TENANT_PIRAMIDE35_DB_USERNAME'),
            'password' => env('TENANT_PIRAMIDE35_DB_PASSWORD'),
            'url' => env('TENANT_PIRAMIDE35_URL'),
        ],
    ],

    // 'for_types' elenca a chi è rivolto il modulo (azienda, privato, ente).
    // Se un tenant non rientra, il modulo compare comunque in home ma come suggerimento
    // ("Solo per Aziende/Enti"), non cliccabile — vedi HubModules::forTenant().
    'modules' => [
        'promo' => [
            'key' => 'promo',
            'label' => 'Crea promo',
            'description' => 'Volantini e landing promozionali',
            'emoji' => '✨',
            'active' => true,
            'for_types' => ['azienda', 'privato', 'ente'],
        ],
        'services' => [
            'key' => 'services',
            'label' => 'Servizi',
            'description' => 'Link di pagamento Stripe per trattamenti',
            'emoji' => '💆',
            'active' => true,
            'for_types' => ['azienda', 'ente'],
        ],
        'shop' => [
            'key' => 'shop',
            'label' => 'Vendi',
            'description' => 'Store e prodotti online',
            'emoji' => '🛍️',
            'active' => false,
            'for_types' => ['azienda', 'ente'],
        ],
        'agenda' => [
            'key' => 'agenda',
            'label' => 'Agenda',
            'description' => 'Prenotazioni e appuntamenti',
            'emoji' => '📅',
            'active' => false,
            'for_types' => ['azienda', 'ente'],
        ],
        'rentals' => [
            'key' => 'rentals',
            'label' => 'Affitti',
            'description' => 'B&B e case vacanza',
            'emoji' => '🏠',
            'active' => false,
            'for_types' => ['azienda', 'privato', 'ente'],
        ],
        'website' => [
            'key' => 'website',
            'label' => 'Crea sito',
            'description' => 'Sito web della tua attività',
            'emoji' => '🌐',
            'active' => false,
            'for_types' => ['azienda', 'ente'],
        ],
        'classifieds' => [
            'key' => 'classifieds',
            'label' => 'Annunci',
            'description' => 'Bakeca e marketplace',
            'emoji' => '📋',
            'active' => false,
            'for_types' => ['azienda', 'privato', 'ente'],
        ],
        'giftcard' => [
            'key' => 'giftcard',
            'label' => 'Gift card',
            'description' => 'Buoni regalo e sconti in card',
            'emoji' => '🎁',
            'active' => false,
            'for_types' => ['azienda', 'ente'],
        ],
        'loyalty' => [
            'key' => 'loyalty',
            'label' => 'Card clienti',
            'description' => 'Tessere fedeltà e sconti condivisi tra strutture',
            'emoji' => '💳',
            'active' => false,
            'for_types' => ['azienda', 'ente'],
        ],
        'billing' => [
            'key' => 'billing',
            'label' => 'Abbonamento',
            'description' => 'Stato demo e pagamento Hub Core',
            'emoji' => '🧾',
            'active' => true,
            'for_types' => ['azienda', 'privato', 'ente'],
        ],
    ],
];
