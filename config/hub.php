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

    'modules' => [
        'promo' => [
            'key' => 'promo',
            'label' => 'Crea promo',
            'description' => 'Volantini e landing promozionali',
            'emoji' => '✨',
            'active' => true,
        ],
        'services' => [
            'key' => 'services',
            'label' => 'Servizi',
            'description' => 'Inserisci i tuoi servizi',
            'emoji' => '💆',
            'active' => false,
        ],
        'shop' => [
            'key' => 'shop',
            'label' => 'Vendi',
            'description' => 'Store e prodotti online',
            'emoji' => '🛍️',
            'active' => false,
        ],
        'agenda' => [
            'key' => 'agenda',
            'label' => 'Agenda',
            'description' => 'Prenotazioni e appuntamenti',
            'emoji' => '📅',
            'active' => false,
        ],
        'rentals' => [
            'key' => 'rentals',
            'label' => 'Affitti',
            'description' => 'B&B e case vacanza',
            'emoji' => '🏠',
            'active' => false,
        ],
        'website' => [
            'key' => 'website',
            'label' => 'Crea sito',
            'description' => 'Sito web della tua attività',
            'emoji' => '🌐',
            'active' => false,
        ],
        'classifieds' => [
            'key' => 'classifieds',
            'label' => 'Annunci',
            'description' => 'Bakeca e marketplace',
            'emoji' => '📋',
            'active' => false,
        ],
        'giftcard' => [
            'key' => 'giftcard',
            'label' => 'Gift card',
            'description' => 'Buoni regalo e sconti in card',
            'emoji' => '🎁',
            'active' => false,
        ],
        'loyalty' => [
            'key' => 'loyalty',
            'label' => 'Card clienti',
            'description' => 'Tessere fedeltà e sconti condivisi tra strutture',
            'emoji' => '💳',
            'active' => false,
        ],
    ],
];
