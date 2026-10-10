<?php

/**
 * Landing «Siti web e app a Corigliano-Rossano» (/siti-web-corigliano-rossano).
 * Prezzi, scadenza dell'offerta e contatti si cambiano qui o dal .env: nessuna modifica al codice.
 */
return [

    // Ultimo giorno dell'offerta (incluso). Dopo questa data la pagina mostra i prezzi di listino, senza sconto né conto alla rovescia.
    'offer_ends' => env('LANDING_OFFER_ENDS', '2026-11-10'),

    // Nota sotto i prezzi.
    'price_note' => env('LANDING_PRICE_NOTE', 'Prezzi IVA esclusa. Preventivo gratuito e senza impegno.'),

    // Contatti diretti: se vuoti, i pulsanti «Chiama» e «WhatsApp» non compaiono (resta il modulo).
    'phone' => env('LANDING_PHONE'),          // es. +39 0983 000000
    'whatsapp' => env('LANDING_WHATSAPP', '393487564418'),    // solo cifre con prefisso

    // Dove arrivano le richieste (se vuoto: LEADS_MONITOR_EMAIL). Le richieste restano comunque salvate in Dashboard → Richieste sito.
    'leads_email' => env('LANDING_LEADS_EMAIL'),

    // Gruppo Facebook della community (il nome si cambia qui o con LANDING_FB_GROUP_NAME quando verrà rinominato).
    'facebook_group' => [
        'url' => env('LANDING_FB_GROUP_URL', 'https://www.facebook.com/groups/1851508502319600'),
        'name' => env('LANDING_FB_GROUP_NAME', 'Il nostro gruppo Facebook'),
    ],

    // Promo «1 € per partire»: il cliente paga davvero start_eur, ha trial_days giorni per provare e configurare il sito
    // con l'IA, poi parte un abbonamento a riscatto: il prezzo del pacchetto diviso in `months` rate mensili (a fine rate si ferma da solo).
    'start' => [
        'enabled' => (bool) env('LANDING_START_ENABLED', true),
        'start_eur' => (int) env('LANDING_START_EUR', 1),
        'trial_days' => (int) env('LANDING_START_TRIAL_DAYS', 15),
        'months' => (int) env('LANDING_START_MONTHS', 10),
        // Frase mostrata sul checkout e sulla pagina: condizioni da confermare/adeguare.
        'terms' => env('LANDING_START_TERMS', 'Puoi annullare durante i giorni di prova: non pagherai altro oltre al primo euro.'),
    ],

    'city' => 'Corigliano-Rossano',
    'place' => 'Fabrizio',

    'plans' => [
        [
            'key' => 'vetrina',
            'name' => 'Vetrina',
            'tagline' => 'Per farti trovare online, subito.',
            'price_regular' => 490,
            'price_offer' => 290,
            'prefix' => '',
            'featured' => false,
            'features' => [
                'Una pagina scorrevole, chiara e veloce',
                'Pensata prima di tutto per lo smartphone',
                'Pulsanti Chiama e WhatsApp',
                'Mappa, orari e contatti',
                'Impostazioni base per comparire su Google',
            ],
        ],
        [
            'key' => 'aziendale',
            'name' => 'Aziendale',
            'tagline' => 'Il sito ufficiale della tua azienda.',
            'price_regular' => 890,
            'price_offer' => 590,
            'prefix' => '',
            'featured' => true,
            'features' => [
                'Più pagine: chi siamo, servizi, contatti',
                'Grafica con i colori e il logo della tua attività',
                'Modulo contatti che ti arriva subito',
                'Galleria foto e presentazione dei servizi',
                'Ottimizzato per Google e per i social',
            ],
        ],
        [
            'key' => 'professionale',
            'name' => 'Professionale su misura',
            'tagline' => 'Personalizzato in ogni dettaglio.',
            'price_regular' => 1490,
            'price_offer' => 990,
            'prefix' => 'da ',
            'featured' => false,
            'features' => [
                'Progetto e grafica disegnati sulle tue esigenze',
                'Funzioni su misura: prenotazioni, catalogo, area clienti',
                'Collegabile a promo, prodotti e pagamenti online',
                'Pagine e testi curati con te passo dopo passo',
                'Preventivo preciso dopo una chiacchierata',
            ],
        ],
    ],

    'zones' => [
        'Corigliano-Rossano', 'Schiavonea', 'Fabrizio', 'Cassano allo Ionio', 'Sibari', 'Cariati',
        'Crosia', 'Calopezzati', 'Trebisacce', 'Castrovillari', 'Tutta la Sibaritide',
    ],
];
