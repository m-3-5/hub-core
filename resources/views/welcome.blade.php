<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6366f1">
    <title>Hub Core — La tua attività online, semplice</title>
    <meta name="description" content="Promo, servizi, negozio, agenda, affitti e sito web in un'unica app. Per aziende, privati ed enti — provalo gratis, nessuna carta richiesta.">
    <link rel="canonical" href="{{ url('/') }}">
    @include('layouts.partials.favicon')
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hub Core">
    <meta property="og:title" content="Hub Core — Tutto per la tua attività, in un'unica app">
    <meta property="og:description" content="Promo, servizi, negozio, agenda, affitti e sito web in un'unica app. Provalo gratis, nessuna carta richiesta.">
    <meta property="og:image" content="{{ asset('images/og-hub-core.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:locale" content="it_IT">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Hub Core — Tutto per la tua attività, in un'unica app">
    <meta name="twitter:description" content="Promo, servizi, negozio, agenda, affitti e sito web in un'unica app.">
    <meta name="twitter:image" content="{{ asset('images/og-hub-core.png') }}">
    <style>
        :root {
            --accent: #6366f1;
            --accent2: #ec4899;
            --text: #0f172a;
            --muted: #64748b;
            --card-w: min(320px, 78vw);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            color: var(--text);
            background: #fafbff;
            overflow-x: hidden;
        }
        .hero {
            display: grid;
            place-items: center;
            padding: 40px 20px 24px;
            text-align: center;
            background:
                radial-gradient(circle at 18% 22%, #eef2ff, transparent 42%),
                radial-gradient(circle at 82% 8%, #fce7f3, transparent 36%),
                #fafbff;
        }
        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.4rem);
            margin: 0 0 14px;
            line-height: 1.08;
            letter-spacing: -.02em;
        }
        .hero p {
            color: var(--muted);
            font-size: clamp(1rem, 2.2vw, 1.15rem);
            max-width: 580px;
            margin: 0 auto 30px;
            line-height: 1.55;
        }
        .cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn {
            padding: 14px 26px;
            border-radius: 14px;
            font-weight: 700;
            text-decoration: none;
            font-size: 1rem;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            box-shadow: 0 10px 30px rgba(99, 102, 241, .35);
        }
        .btn-secondary {
            background: #fff;
            color: var(--text);
            border: 1px solid #e2e8f0;
        }
        .section-label {
            text-align: center;
            margin: 8px 0 20px;
            font-size: .85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--muted);
        }
        .carousel-outer {
            position: relative;
            padding: 10px 0 50px;
            mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
        }
        .carousel-wrap {
            overflow: hidden;
            max-width: 100%;
        }
        .carousel-track {
            display: flex;
            gap: 22px;
            width: max-content;
            padding: 12px 24px 28px;
            animation: scroll 42s linear infinite;
        }
        .carousel-wrap:hover .carousel-track { animation-play-state: paused; }
        @media (prefers-reduced-motion: reduce) {
            .carousel-track { animation: none; }
        }
        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .flyer {
            width: var(--card-w);
            flex-shrink: 0;
            border-radius: 28px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .12);
            transform-style: preserve-3d;
            transition: transform .35s ease, box-shadow .35s ease;
            position: relative;
        }
        .flyer:hover {
            transform: perspective(900px) rotateY(-4deg) translateY(-6px) scale(1.02);
            box-shadow: 0 28px 60px rgba(15, 23, 42, .18);
            z-index: 2;
        }
        .flyer__img-wrap {
            width: 100%;
            aspect-ratio: 4 / 3;
            overflow: hidden;
            background: linear-gradient(160deg, #f1f5f9, #e2e8f0);
        }
        .flyer__img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            display: block;
        }
        .flyer__body {
            padding: 18px 18px 20px;
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        }
        .flyer__body h3 {
            margin: 0 0 6px;
            font-size: 1.15rem;
        }
        .flyer__body p {
            margin: 0 0 16px;
            color: var(--muted);
            font-size: .9rem;
            line-height: 1.45;
            min-height: 2.9em;
        }
        .flyer__cta {
            display: block;
            text-align: center;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: .92rem;
            text-decoration: none;
            color: #fff;
            box-shadow: 0 8px 20px rgba(0,0,0,.12);
            transition: filter .15s ease, transform .15s ease;
        }
        .flyer__cta:hover { filter: brightness(1.06); transform: translateY(-1px); }
        .register {
            max-width: 720px;
            margin: 0 auto 60px;
            padding: 0 20px;
            scroll-margin-top: 24px;
        }
        .register-card {
            background: #fff;
            border-radius: 28px;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 16px 48px rgba(15,23,42,.08);
            border: 1px solid #eef2ff;
        }
        .register-card h2 { margin: 0 0 10px; font-size: 1.6rem; }
        .register-card p { color: var(--muted); margin: 0 0 24px; line-height: 1.55; }
        .register-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .register-type {
            background: #f8fafc;
            border-radius: 16px;
            padding: 18px 14px;
        }
        .register-type strong { display: block; margin-bottom: 4px; }
        .register-type span { font-size: .88rem; color: var(--muted); }
        footer {
            text-align: center;
            padding: 28px 20px 40px;
            color: var(--muted);
            font-size: .9rem;
        }
        @media (min-width: 1100px) {
            :root { --card-w: 300px; }
        }
        .app-preview {
            max-width: 720px;
            margin: 0 auto 8px;
            padding: 0 20px;
        }
        .app-preview-intro {
            text-align: center;
            max-width: 480px;
            margin: 0 auto 20px;
            color: var(--muted);
            font-size: .92rem;
        }
        .app-preview-intro strong { color: var(--text); }
        .app-preview-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }
        .app-preview-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: inherit;
            text-align: center;
        }
        .app-preview-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 1.6rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .1);
            transition: transform .15s ease;
        }
        .app-preview-tile:hover .app-preview-icon { transform: translateY(-3px); }
        .app-preview-label { font-size: .74rem; font-weight: 700; line-height: 1.2; }
        @media (min-width: 640px) {
            .app-preview-grid { grid-template-columns: repeat(7, 1fr); }
            .app-preview-icon { width: 68px; height: 68px; font-size: 1.9rem; }
        }

        .how-it-works {
            max-width: 720px;
            margin: 36px auto 8px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
        }
        @media (min-width: 720px) {
            .how-it-works { grid-template-columns: repeat(3, 1fr); }
        }
        .how-step { display: flex; gap: 14px; align-items: flex-start; }
        .how-step-num {
            flex: 0 0 auto;
            width: 30px; height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            display: grid; place-items: center;
            font-size: .85rem; font-weight: 700;
        }
        .how-step h3 { margin: 2px 0 4px; font-size: .95rem; }
        .how-step p { margin: 0; color: var(--muted); font-size: .85rem; line-height: 1.45; }

        </style>
    @isset($jsonLd)
    <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endisset
</head>
<body>
@if (session('success'))
    <p style="background:#e8f5e9;color:#2e7d32;padding:14px 20px;text-align:center;font-weight:600">{{ session('success') }}</p>
@endif
@if (request('checkout') === 'success')
    <p style="background:#e8f5e9;color:#2e7d32;padding:14px 20px;text-align:center;font-weight:600">Pagamento ricevuto! Controlla la tua email per impostare la password e iniziare.</p>
@elseif (request('checkout') === 'cancelled')
    <p style="background:#fff3e0;color:#e65100;padding:14px 20px;text-align:center;font-weight:600">Pagamento non completato — puoi registrarti di nuovo quando vuoi.</p>
@endif
<section class="hero">
    <div>
        <img src="{{ asset('images/logo-m35.png') }}" alt="M 3.5 S.R.L." width="120" height="104" style="display:block;margin:0 auto 14px;height:auto">
        <h1>Tutto per la tua attività,<br>in un'unica app</h1>
        <p>Promo, servizi, negozio, agenda, affitti e sito web. Per <strong>aziende</strong> e <strong>privati</strong> — semplice come le app del telefono.</p>
        <div class="cta">
            <a class="btn btn-primary" href="{{ route('admin.login') }}">Accedi</a>
            <a class="btn btn-secondary" href="{{ route('registration.create') }}">Registrati</a>
            <a class="btn btn-secondary" href="{{ route('pricing.show') }}">Prezzi</a>
        </div>
    </div>
</section>

<div class="app-preview">
    <p class="app-preview-intro">Così sarà la tua home appena entri — <strong>tocca una voce per iniziare</strong>, il resto lo trovi qui sotto spiegato per bene.</p>
    <div class="app-preview-grid">
        @foreach ($slides as $slide)
            <a class="app-preview-tile" href="{{ $slide['cta_url'] }}">
                <div class="app-preview-icon" style="background: linear-gradient(145deg, color-mix(in srgb, {{ $slide['accent'] }} 20%, #fff), #fff)">{{ $slide['emoji'] }}</div>
                <div class="app-preview-label">{{ $slide['title'] }}</div>
            </a>
        @endforeach
    </div>
</div>

<div class="how-it-works">
    <div class="how-step">
        <span class="how-step-num">1</span>
        <div>
            <h3>Scegli cosa ti serve</h3>
            <p>Promo, servizi, negozio o affitti — attivi solo i moduli utili alla tua attività.</p>
        </div>
    </div>
    <div class="how-step">
        <span class="how-step-num">2</span>
        <div>
            <h3>Provalo gratis</h3>
            <p>Demo completa da subito, nessuna carta richiesta per cominciare.</p>
        </div>
    </div>
    <div class="how-step">
        <span class="how-step-num">3</span>
        <div>
            <h3>Vai online quando sei pronto</h3>
            <p>Le tue promo e i tuoi servizi compaiono subito sul tuo sito, senza toccare codice.</p>
        </div>
    </div>
</div>

<p class="section-label" id="funzioni">Sfoglia le possibilità</p>

<div class="carousel-outer">
    <div class="carousel-wrap">
        <div class="carousel-track" aria-label="Anteprima funzioni Hub Core">
            @foreach ($loopSlides as $slide)
                <article class="flyer">
                    <div class="flyer__img-wrap" style="background: linear-gradient(160deg, color-mix(in srgb, {{ $slide['accent'] }} 18%, #fff), color-mix(in srgb, {{ $slide['accent'] }} 8%, #f8fafc));">
                        <img
                            class="flyer__img"
                            src="{{ $slide['image_url'] }}"
                            alt="{{ $slide['title'] }} — Hub Core"
                            loading="lazy"
                            width="320"
                            height="240"
                        >
                    </div>
                    <div class="flyer__body">
                        <h3>{{ $slide['title'] }}</h3>
                        <p>{{ $slide['text'] }}</p>
                        <a
                            class="flyer__cta"
                            href="{{ $slide['cta_url'] }}"
                            style="background: linear-gradient(135deg, {{ $slide['accent'] }}, color-mix(in srgb, {{ $slide['accent'] }} 70%, #1e1b4b))"
                        >{{ $slide['cta'] }}</a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</div>

<section class="register" id="registrazione">
    <div class="register-card">
        <h2>Registrati su Hub Core</h2>
        <p>Privato: sempre gratis. Azienda/Ente: <strong>{{ config('services.hub_billing.free_days', 7) }} giorni gratis</strong>, senza carta; dopo la prima settimana basta <strong>€{{ config('services.hub_billing.launch_offer_price_eur', 1) }}</strong> (copre anche l'attivazione del primo modulo). Poi €{{ config('services.hub_billing.monthly_price_eur', 29) }}/mese, disdici quando vuoi.</p>

        @error('registration')
            <p style="background:#fdecea;color:#c62828;padding:12px 16px;border-radius:12px;margin-bottom:20px">{{ $message }}</p>
        @enderror

        @error('guest')
            <p style="background:#fdecea;color:#c62828;padding:12px 16px;border-radius:12px;margin-bottom:20px">{{ $message }}</p>
        @enderror
        <div class="cta" style="margin-bottom:14px">
            <a class="btn btn-primary" href="{{ route('registration.create') }}">Inizia la registrazione →</a>
        </div>
        <p style="color:#64748b;font-size:.9rem;margin:0 0 20px">Pochi passi, uno alla volta: tipo di account, nome, email. Ci vuole un minuto.</p>
        <div class="cta">
            <a class="btn btn-secondary" href="{{ route('admin.login') }}">Hai già un account? Accedi</a>
        </div>
    </div>
</section>


<footer>
    Hub Core — piattaforma multiservizi per aziende e privati<br>
    <a href="{{ route('pricing.show') }}" style="color:var(--accent);text-decoration:none;font-weight:600">Vedi i prezzi</a>
    ·
    <a href="{{ route('landing.web') }}" style="color:var(--accent);text-decoration:none;font-weight:600">Siti web e app a Corigliano-Rossano</a>
    @if (config('landing.facebook_group.url'))
    ·
    <a href="{{ config('landing.facebook_group.url') }}" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none;font-weight:600">{{ config('landing.facebook_group.name') }}</a>
    @endif
    ·
    <a href="{{ route('promo.hub-archive') }}" style="color:var(--accent);text-decoration:none;font-weight:600">Guarda tutte le promozioni attive →</a>
</footer>

@include('partials.max-public-chat')
</body>
</html>
