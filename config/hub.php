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

    // Elenco pubblico di piramide35.com: piramide:sync lo legge e porta tra le promo di inm35.it SOLO le voci scelte qui
    // (slug delle pagine paese, "settore-..." per i tipi di attività, "servizio-..." per i servizi). Si cambia con PIRAMIDE_SELEZIONE.
    'piramide_feed' => env('PIRAMIDE_FEED_URL', 'https://piramide35.com/feed/inm35.json'),
    'piramide_selezione' => array_filter(array_map('trim', explode(',', (string) env(
        'PIRAMIDE_SELEZIONE',
        'senise,colobraro,tursi,matera,corigliano-rossano,settore-bb-case-vacanze,settore-ristoranti-pizzerie,settore-aziende-agricole-cantine,settore-negozi-online-ecommerce'
    )))),

    // Bozze di promo create da post esterni (Facebook…): da quali host si scarica la foto (https, suffisso del dominio).
    'promo_import' => [
        'image_hosts' => ['fbcdn.net', 'cdninstagram.com', 'facebook.com'],
    ],

    // Vita dei siti creati con Max: chi non paga o non li usa più riceve un avviso, poi il sito va nell'archivio dell'amministratore.
    // Spento finché non si mette HUB_SITE_LIFECYCLE=true (il comando hub:sites-lifecycle senza --apply mostra solo cosa farebbe).
    'site_lifecycle' => [
        'enabled' => (bool) env('HUB_SITE_LIFECYCLE', false),
        'inactive_days' => (int) env('HUB_SITE_INACTIVE_DAYS', 120),
        'grace_days' => (int) env('HUB_SITE_GRACE_DAYS', 14),
        'unverified_days' => (int) env('HUB_SITE_UNVERIFIED_DAYS', 30),
    ],

    'default_password' => env('HUB_DEFAULT_PASSWORD', 'HubCore2026!'),

    // Immagini promo via Gemini (richiede billing). Se false: volantino + SVG tematici.
    'promo_ai_images' => env('PROMO_AI_IMAGES', false),

    'promo_included_quota' => (int) env('PROMO_INCLUDED_QUOTA', 20),

    'promo_ai_flyer_price' => (int) env('PROMO_AI_FLYER_PRICE', 9),

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
        'products' => [
            'key' => 'products',
            'label' => 'Prodotti',
            'description' => 'Prodotti con link Stripe e carrello',
            'emoji' => '📦',
            'active' => true,
            'for_types' => ['azienda', 'ente'],
        ],
        'quotes' => [
            'key' => 'quotes',
            'label' => 'Preventivi',
            'description' => 'Importo libero con link di pagamento Stripe',
            'emoji' => '🧾',
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
            'active' => true,
            'for_types' => ['azienda', 'ente'],
        ],
        'classifieds' => [
            'key' => 'classifieds',
            'label' => 'Annunci',
            'description' => 'Affitti, immobili e oggetti in vendita',
            'emoji' => '📋',
            'active' => true,
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
