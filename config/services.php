<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // «Continua con Google»: client OAuth del progetto Google Cloud «Hub Core inm35».
    // Indirizzo di ritorno da autorizzare: {APP_URL}/accedi/google/ritorno. Vuoti = tasto nascosto.
    'google_login' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    // «Collega Facebook»: i post della pagina con l'hashtag diventano promo (e vanno nel sito).
    // App Meta «Hub Core»: ritorno {APP_URL}/facebook/ritorno, webhook {APP_URL}/api/facebook/webhook (campo «feed» delle pagine).
    // Vuoti = funzione nascosta.
    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'verify_token' => env('FACEBOOK_VERIFY_TOKEN'),
        'graph_version' => env('FACEBOOK_GRAPH_VERSION', 'v27.0'),
        'hashtag' => env('FACEBOOK_HASHTAG', '#promo'),
    ],

    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        // Solo per prove in locale con un finto Gemini: in produzione resta l'indirizzo ufficiale.
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com'),
        // See https://ai.google.dev/gemini-api/docs/models and pricing (free tier)
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
        'fallback_models' => array_filter(array_map('trim', explode(',', env(
            'GEMINI_FALLBACK_MODELS',
            'gemini-2.5-flash-lite,gemini-3.5-flash'
        )))),
        // Image generation (may require billing on some accounts)
        'image_model' => env('GEMINI_IMAGE_MODEL', 'gemini-2.5-flash-image'),
        'image_fallback_models' => array_filter(array_map('trim', explode(',', env(
            'GEMINI_IMAGE_FALLBACK_MODELS',
            'gemini-3.1-flash-image,gemini-3-pro-image'
        )))),
    ],

    'hub' => [
        'webhook_url' => env('HUB_WEBHOOK_URL'),
        'webhook_secret' => env('HUB_WEBHOOK_SECRET'),
        'services_webhook_url' => env('HUB_SERVICES_WEBHOOK_URL'),
    ],

    // IndexNow: avvisa i motori di ricerca dei contenuti nuovi o cambiati (attivo da solo in produzione).
    'indexnow' => [
        'enabled' => env('INDEXNOW_ENABLED'),
        'key' => env('INDEXNOW_KEY'),
    ],

    'hub_billing' => [
        'secret_key' => env('HUB_STRIPE_SECRET_KEY'),
        'api_base' => env('HUB_STRIPE_API_BASE', 'https://api.stripe.com'),
        'webhook_secret' => env('HUB_STRIPE_WEBHOOK_SECRET'),
        // Prova gratuita (free_days) senza carta; poi l'euro di lancio; poi trial_days di prova prima del canone: il primo canone parte dopo ~4 mesi.
        'free_days' => (int) env('HUB_FREE_DAYS', 30),
        'trial_days' => (int) env('HUB_TRIAL_DAYS', 90),
        'monthly_price_eur' => (int) env('HUB_MONTHLY_PRICE_EUR', 9),
        'annual_price_eur' => (int) env('HUB_ANNUAL_PRICE_EUR', 79),
        // Offerta di lancio per i clienti azienda/ente: 1€ chiesto DOPO la prova gratuita (free_days),
        // tutti i moduli inclusi nel canone, nessuna attivazione per modulo. I privati
        // restano sempre gratis (subscription_status=free), non toccati da questa offerta.
        'launch_offer_price_eur' => (int) env('HUB_LAUNCH_OFFER_PRICE_EUR', 1),
        // Paghi quello che pubblichi (spento finché HUB_PAY_PER_USE=true): annunci gratis; promo, prodotti e servizi
        // 1 € l'uno, che scende con l'uso nel mese; chi ha il sito ne ha alcuni inclusi. Si pubblica subito con la
        // carta salvata e si addebita dopo bill_after_days giorni, tutto insieme (una sola commissione Stripe).
        'pay_per_use' => [
            'enabled' => (bool) env('HUB_PAY_PER_USE', true),
            'included_with_site' => (int) env('HUB_PPU_INCLUDED_WITH_SITE', 3), // per tipo: promo, prodotti, servizi
            'bill_after_days' => (int) env('HUB_PPU_BILL_AFTER_DAYS', 30),
            // Sito con l'IA: prezzo fisso, una volta sola alla prima pubblicazione (le modifiche non costano).
            'site_cents' => (int) env('HUB_PPU_SITE_CENTS', 100),
            // [da quante pubblicazioni pagate nel mese => centesimi]
            'steps' => [1 => 100, 6 => 80, 21 => 50],
            // Credito prepagato: 15 € in regalo la prima volta (li finanzia Hub Core), ricariche da 5 € in su,
            // fido senza carta per non fermare il lavoro (si salda con la ricarica).
            'welcome_credit_cents' => (int) env('HUB_PPU_WELCOME_CREDIT_CENTS', 1500),
            'topup_min_cents' => (int) env('HUB_PPU_TOPUP_MIN_CENTS', 500),
            'overdraft_cents' => (int) env('HUB_PPU_OVERDRAFT_CENTS', 500),
        ],
        // Un solo abbonamento, due livelli. Silver = tutto il necessario; Premium = in più sito web con Max, quote molto più alte, assistenza prioritaria.
        // Durante prova gratuita e lancio ogni cliente ha tutto (anche il sito web).
        'tiers' => [
            'silver' => [
                'label' => 'Silver',
                'monthly' => (int) env('HUB_MONTHLY_PRICE_EUR', 9),
                'annual' => (int) env('HUB_ANNUAL_PRICE_EUR', 79),
                'promo_quota' => (int) env('HUB_SILVER_PROMO_QUOTA', 20),
                'services_quota' => (int) env('HUB_SILVER_SERVICES_QUOTA', 20),
            ],
            'premium' => [
                'label' => 'Premium',
                'monthly' => (int) env('HUB_PREMIUM_MONTHLY_EUR', 19),
                'annual' => (int) env('HUB_PREMIUM_ANNUAL_EUR', 169),
                'promo_quota' => (int) env('HUB_PREMIUM_PROMO_QUOTA', 100),
                'services_quota' => (int) env('HUB_PREMIUM_SERVICES_QUOTA', 100),
            ],
        ],
    ],

    // Buoni credito vinti ai giochi di piramide35.com: M35G-{centesimi}-{AAMMGG}-{6 casuali}-{firma HMAC}.
    // Il segreto è lo stesso nel .env dei due siti (lo scrive Max). Senza segreto i buoni sono spenti.
    'game_vouchers' => [
        'secret' => env('GIOCO_BUONI_SEGRETO'),
        'monthly_limit' => (int) env('GIOCO_BUONI_MAX_MESE', 3),   // buoni per utente/attività al mese
        'max_cents' => (int) env('GIOCO_BUONI_MAX_CENTESIMI', 500), // un buono non vale più di così
    ],

];
