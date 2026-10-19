<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6366f1">
    <title>Hub Core — La tua attività online, semplice</title>
    <meta name="description" content="Promo, servizi, negozio, agenda, affitti e sito web in un'unica app. Per aziende, privati ed enti — provalo gratis, nessuna carta richiesta.">
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="search" type="application/opensearchdescription+xml" title="Hub Core" href="{{ route('opensearch') }}">
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
        :root { --accent: #6366f1; --accent2: #8b5cf6; --text: #12122b; --muted: #667085; --line: rgba(18,18,43,.09); }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Segoe UI", system-ui, -apple-system, sans-serif; color: var(--text); background: #f6f7ff; overflow-x: hidden; -webkit-font-smoothing: antialiased; }
        a { color: inherit; }
        .wl-flash { margin: 0; padding: 12px 20px; text-align: center; font-weight: 600; }
        .wl-flash--ok { background: #e8f5e9; color: #2e7d32; }
        .wl-flash--warn { background: #fff3e0; color: #e65100; }

        /* ── Hero ── */
        .wl-hero { position: relative; overflow: hidden; background: radial-gradient(60% 90% at 88% 0%, #c7d2fe 0, transparent 62%), radial-gradient(50% 70% at 0% 40%, #fbcfe8 0, transparent 60%), #f6f7ff; }
        .wl-hero__in { max-width: 1120px; margin: 0 auto; padding: 56px 20px 40px; display: grid; grid-template-columns: 1.1fr .9fr; gap: 32px; align-items: center; }
        .wl-chip { display: inline-flex; align-items: center; gap: 8px; background: #fff; border: 1px solid var(--line); border-radius: 999px; padding: 6px 14px 6px 8px; font-size: .84rem; font-weight: 700; color: #4338ca; box-shadow: 0 4px 14px rgba(99,102,241,.12); }
        .wl-chip i { width: 22px; height: 22px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; display: grid; place-items: center; font-style: normal; }
        .wl-hero h1 { font-size: clamp(2.3rem, 5.4vw, 4rem); line-height: 1.04; letter-spacing: -.035em; margin: 18px 0 16px; }
        .wl-hero h1 em { font-style: normal; background: linear-gradient(120deg, var(--accent), #a855f7 60%, #ec4899); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .wl-hero p.lead { font-size: clamp(1.02rem, 2.2vw, 1.2rem); color: var(--muted); line-height: 1.55; max-width: 520px; margin: 0 0 26px; }
        .wl-btns { display: flex; flex-wrap: wrap; gap: 12px; }
        .wl-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 52px; padding: 0 26px; border-radius: 16px; font-weight: 800; font-size: 1.02rem; text-decoration: none; border: 1px solid var(--line); background: #fff; color: var(--text); transition: transform .15s, box-shadow .15s; }
        .wl-btn:hover { transform: translateY(-2px); }
        .wl-btn--main { background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; border: 0; box-shadow: 0 12px 28px rgba(99,102,241,.38); }
        .wl-trust { display: flex; flex-wrap: wrap; gap: 8px 18px; margin: 22px 0 0; padding: 0; list-style: none; color: var(--muted); font-size: .9rem; font-weight: 600; }
        .wl-trust li { display: flex; align-items: center; gap: 6px; }
        .wl-trust svg { color: #16a34a; }

        /* ── Telefono ── */
        .wl-phone-wrap { display: grid; place-items: center; }
        .wl-phone { width: 276px; border-radius: 42px; padding: 9px; background: #12122b; box-shadow: 0 40px 80px rgba(67,56,202,.35), 0 0 0 2px rgba(255,255,255,.4) inset; transform: rotate(2.5deg); }
        .wl-screen { position: relative; border-radius: 34px; background: #f4f5fb; overflow: hidden; height: 540px; display: flex; flex-direction: column; }
        .wl-screen__bar { display: flex; justify-content: space-between; padding: 12px 20px 0; font-size: .7rem; font-weight: 700; color: #12122b; }
        .wl-app-top { display: flex; align-items: center; gap: 10px; padding: 14px 16px 8px; }
        .wl-av { width: 40px; height: 40px; border-radius: 14px; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; display: grid; place-items: center; font-weight: 800; }
        .wl-app-top b { display: block; font-size: .95rem; }
        .wl-app-top small { color: var(--muted); font-size: .72rem; }
        .wl-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; padding: 6px 16px 10px; }
        .wl-kpi { background: #fff; border-radius: 14px; padding: 9px 10px; box-shadow: 0 2px 8px rgba(18,18,43,.05); }
        .wl-kpi b { display: block; font-size: 1.15rem; line-height: 1.1; }
        .wl-kpi span { font-size: .62rem; color: var(--muted); font-weight: 700; }
        .wl-tiles { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; padding: 4px 16px; }
        .wl-tile { background: #fff; border-radius: 18px; padding: 12px; display: flex; flex-direction: column; gap: 8px; box-shadow: 0 2px 8px rgba(18,18,43,.05); font-weight: 800; font-size: .82rem; }
        .wl-ic { width: 38px; height: 38px; border-radius: 13px; display: grid; place-items: center; color: #fff; background: var(--c, var(--accent)); box-shadow: 0 6px 14px color-mix(in srgb, var(--c, var(--accent)) 40%, transparent); }
        .wl-tabs { margin-top: auto; display: flex; justify-content: space-around; background: #fff; border-top: 1px solid var(--line); padding: 8px 6px 12px; color: #98a2b3; }
        .wl-tabs span { display: grid; justify-items: center; gap: 2px; font-size: .56rem; font-weight: 700; }
        .wl-tabs .on { color: var(--accent); }

        /* ── Sezioni ── */
        .wl-sec { max-width: 1120px; margin: 0 auto; padding: 44px 20px 0; }
        .wl-sec h2 { font-size: clamp(1.5rem, 3.4vw, 2.1rem); letter-spacing: -.02em; margin: 0 0 6px; }
        .wl-sec > p { margin: 0 0 22px; color: var(--muted); }
        .wl-head { display: flex; justify-content: space-between; align-items: end; gap: 12px; flex-wrap: wrap; margin-bottom: 18px; }
        .wl-head h2 { margin: 0; }
        .wl-more { color: var(--accent); font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }

        .wl-mods { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .wl-mod { display: flex; flex-direction: column; gap: 10px; background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 18px; text-decoration: none; box-shadow: 0 1px 2px rgba(18,18,43,.04), 0 10px 26px rgba(18,18,43,.05); transition: transform .15s, box-shadow .15s; }
        .wl-mod:hover { transform: translateY(-3px); box-shadow: 0 16px 36px rgba(18,18,43,.1); }
        .wl-mod .wl-ic { width: 46px; height: 46px; border-radius: 15px; }
        .wl-mod b { font-size: 1.04rem; }
        .wl-mod span.t { color: var(--muted); font-size: .88rem; line-height: 1.45; }

        .wl-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
        .wl-card { display: flex; flex-direction: column; background: #fff; border: 1px solid var(--line); border-radius: 20px; overflow: hidden; text-decoration: none; box-shadow: 0 1px 2px rgba(18,18,43,.04), 0 10px 26px rgba(18,18,43,.05); transition: transform .15s; }
        .wl-card:hover { transform: translateY(-3px); }
        .wl-card__b { padding: 12px 14px 14px; display: grid; gap: 3px; }
        .wl-card__b small { color: var(--accent); font-weight: 800; font-size: .72rem; text-transform: uppercase; letter-spacing: .04em; }
        .wl-card__b b { font-size: .98rem; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.5em; }
        .wl-card__b span { color: var(--muted); font-size: .84rem; }
        .wl-tag { position: absolute; left: 10px; top: 10px; background: rgba(18,18,43,.7); color: #fff; font-size: .68rem; font-weight: 800; padding: 4px 10px; border-radius: 999px; z-index: 1; }

        .wl-steps { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        .wl-step { background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 20px; display: flex; gap: 14px; }
        .wl-step i { flex: none; width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; display: grid; place-items: center; font-style: normal; font-weight: 800; }
        .wl-step h3 { margin: 2px 0 4px; font-size: 1.02rem; }
        .wl-step p { margin: 0; color: var(--muted); font-size: .9rem; line-height: 1.45; }

        .wl-price { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
        .wl-plan { background: #fff; border: 1px solid var(--line); border-radius: 22px; padding: 20px; }
        .wl-plan b { display: block; font-size: 1.05rem; margin-bottom: 4px; }
        .wl-plan strong { font-size: 2rem; letter-spacing: -.03em; }
        .wl-plan small { color: var(--muted); }
        .wl-plan--hot { background: linear-gradient(145deg, #4f46e5, #8b5cf6); color: #fff; border: 0; box-shadow: 0 20px 40px rgba(79,70,229,.3); }
        .wl-plan--hot small { color: rgba(255,255,255,.8); }

        .wl-cta { max-width: 1120px; margin: 44px auto 0; padding: 0 20px; }
        .wl-cta__in { border-radius: 28px; padding: 36px 24px; text-align: center; color: #fff; background: radial-gradient(60% 120% at 100% 0%, rgba(236,72,153,.55), transparent 60%), linear-gradient(135deg, #4338ca, #7c3aed); box-shadow: 0 24px 50px rgba(67,56,202,.3); }
        .wl-cta__in h2 { margin: 0 0 8px; font-size: clamp(1.5rem, 3.4vw, 2.1rem); letter-spacing: -.02em; }
        .wl-cta__in p { margin: 0 auto 20px; max-width: 560px; opacity: .92; line-height: 1.5; }
        .wl-cta__in .wl-btn { border: 0; }
        .wl-err { background: #fdecea; color: #c62828; padding: 12px 16px; border-radius: 12px; margin: 0 auto 16px; max-width: 560px; }

        footer { text-align: center; color: var(--muted); font-size: .9rem; line-height: 1.9; padding: 32px 20px 40px; }
        footer a { color: var(--accent); text-decoration: none; font-weight: 600; }

        @media (max-width: 900px) {
            .wl-hero__in { grid-template-columns: 1fr; padding-top: 28px; text-align: center; }
            .wl-hero p.lead { margin-left: auto; margin-right: auto; }
            .wl-btns, .wl-trust { justify-content: center; }
            .wl-mods, .wl-cards { grid-template-columns: repeat(2, 1fr); }
            .wl-steps, .wl-price { grid-template-columns: 1fr; }
            .wl-phone { transform: none; }
        }
        @media (max-width: 520px) {
            .wl-btns .wl-btn { flex: 1 1 40%; }
            .wl-btns .wl-btn--main { flex-basis: 100%; }
            .wl-mod { padding: 14px; }
            .wl-mod span.t { display: none; }
            .wl-card__b b { font-size: .9rem; }
        }
    </style>
    @isset($jsonLd)
    <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endisset
@include('layouts.partials.ui')
</head>
<body>
@include('layouts.partials.public-nav')
@if (session('success'))
    <p class="wl-flash wl-flash--ok">{{ session('success') }}</p>
@endif
@if (request('checkout') === 'success')
    <p class="wl-flash wl-flash--ok">Pagamento ricevuto! Controlla la tua email per impostare la password e iniziare.</p>
@elseif (request('checkout') === 'cancelled')
    <p class="wl-flash wl-flash--warn">Pagamento non completato — puoi registrarti di nuovo quando vuoi.</p>
@endif

<section class="wl-hero">
    <div class="wl-hero__in">
        <div>
            <span class="wl-chip"><i>@include('layouts.partials.icon', ['name' => 'check', 'size' => 13])</i> Provala gratis, senza carta</span>
            <h1>Tutto per la tua attività, <em>in un'unica app</em></h1>
            <p class="lead">Promo, servizi, negozio, annunci e sito web. Per <strong>aziende</strong>, <strong>privati</strong> ed <strong>enti</strong> — semplice come le app del telefono.</p>
            <div class="wl-btns">
                <a class="wl-btn wl-btn--main" href="{{ route('registration.create') }}">Registrati gratis @include('layouts.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
                <a class="wl-btn" href="{{ route('admin.login') }}">Accedi</a>
                <a class="wl-btn" href="{{ route('pricing.show') }}">Prezzi</a>
            </div>
            <ul class="wl-trust">
                <li>@include('layouts.partials.icon', ['name' => 'check', 'size' => 16]) {{ config('services.hub_billing.free_days', 7) }} giorni gratis</li>
                <li>@include('layouts.partials.icon', ['name' => 'check', 'size' => 16]) Funziona da telefono</li>
                <li>@include('layouts.partials.icon', ['name' => 'check', 'size' => 16]) Disdici quando vuoi</li>
            </ul>
        </div>

        <div class="wl-phone-wrap" aria-hidden="true">
            <div class="wl-phone">
                <div class="wl-screen">
                    <div class="wl-screen__bar"><span>9:41</span><span>●●●</span></div>
                    <div class="wl-app-top"><span class="wl-av">D</span><div><b>Demo Attività</b><small>Cosa vuoi fare oggi?</small></div></div>
                    <div class="wl-kpis">
                        <div class="wl-kpi"><b>5</b><span>Promo</span></div>
                        <div class="wl-kpi"><b>12</b><span>Offerte</span></div>
                        <div class="wl-kpi"><b>3</b><span>Messaggi</span></div>
                    </div>
                    <div class="wl-tiles">
                        <div class="wl-tile"><span class="wl-ic" style="--c:#8b5cf6">@include('layouts.partials.icon', ['name' => 'promo', 'size' => 20])</span>Crea promo</div>
                        <div class="wl-tile"><span class="wl-ic" style="--c:#ec4899">@include('layouts.partials.icon', ['name' => 'services', 'size' => 20])</span>Servizi</div>
                        <div class="wl-tile"><span class="wl-ic" style="--c:#6366f1">@include('layouts.partials.icon', ['name' => 'products', 'size' => 20])</span>Prodotti</div>
                        <div class="wl-tile"><span class="wl-ic" style="--c:#f59e0b">@include('layouts.partials.icon', ['name' => 'classifieds', 'size' => 20])</span>Annunci</div>
                    </div>
                    <div class="wl-tabs">
                        <span class="on">@include('layouts.partials.icon', ['name' => 'home', 'size' => 20])Home</span>
                        <span>@include('layouts.partials.icon', ['name' => 'promo', 'size' => 20])Promo</span>
                        <span>@include('layouts.partials.icon', ['name' => 'services', 'size' => 20])Servizi</span>
                        <span>@include('layouts.partials.icon', ['name' => 'messages', 'size' => 20])Messaggi</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@php
    $modIcons = ['promo', 'services', 'products', 'calendar', 'home', 'globe', 'classifieds'];
@endphp
<section class="wl-sec" id="funzioni">
    <h2>Tutto sotto mano</h2>
    <p>Tocca una voce per iniziare: ogni attività vede solo ciò che le serve.</p>
    <div class="wl-mods">
        @foreach ($slides as $i => $slide)
            <a class="wl-mod" href="{{ $slide['cta_url'] }}">
                <span class="wl-ic" style="--c: {{ $slide['accent'] }}">@include('layouts.partials.icon', ['name' => $modIcons[$i] ?? 'promo', 'size' => 23])</span>
                <b>{{ $slide['title'] }}</b>
                <span class="t">{{ $slide['text'] }}</span>
            </a>
        @endforeach
    </div>
</section>

@if ($livePromos->isNotEmpty())
<section class="wl-sec">
    <div class="wl-head">
        <h2>Promozioni attive adesso</h2>
        <a class="wl-more" href="{{ route('promo.hub-archive') }}">Vedi tutte @include('layouts.partials.icon', ['name' => 'arrow', 'size' => 16])</a>
    </div>
    <div class="wl-cards">
        @foreach ($livePromos as $promo)
            <a class="wl-card" href="{{ route('promo.show', [$promo->tenant, $promo]) }}">
                <span class="ui-media"><img src="{{ $promo->imageUrl() }}" alt="{{ $promo->title }}" loading="lazy"></span>
                <span class="wl-card__b"><small>{{ $promo->tenant->name }}</small><b>{{ $promo->title }}</b></span>
            </a>
        @endforeach
    </div>
</section>
@endif

@if ($liveAds->isNotEmpty())
<section class="wl-sec">
    <div class="wl-head">
        <h2>Ultimi annunci</h2>
        <a class="wl-more" href="{{ route('classifieds.board') }}">Vedi tutti @include('layouts.partials.icon', ['name' => 'arrow', 'size' => 16])</a>
    </div>
    <div class="wl-cards">
        @foreach ($liveAds as $ad)
            <a class="wl-card" href="{{ $ad->publicUrl() }}">
                <span class="ui-media">
                    @if ($ad->coverUrl())<img src="{{ $ad->coverUrl() }}" alt="{{ $ad->title }}" loading="lazy">@else<span class="ui-media__ph">{{ $ad->categoryIcon() }}</span>@endif
                    <span class="wl-tag">{{ $ad->categoryLabel() }}</span>
                </span>
                <span class="wl-card__b">
                    @if ($ad->priceLabel())<small>{{ $ad->priceLabel() }}</small>@endif
                    <b>{{ $ad->title }}</b>
                    <span>{{ $ad->zone }}</span>
                </span>
            </a>
        @endforeach
    </div>
</section>
@endif

<section class="wl-sec">
    <h2>Come funziona</h2>
    <p>Tre passi, dal telefono.</p>
    <div class="wl-steps">
        <div class="wl-step"><i>1</i><div><h3>Scegli cosa ti serve</h3><p>Promo, servizi, negozio o annunci: attivi solo ciò che usi.</p></div></div>
        <div class="wl-step"><i>2</i><div><h3>Provalo gratis</h3><p>Demo completa da subito, nessuna carta per cominciare.</p></div></div>
        <div class="wl-step"><i>3</i><div><h3>Vai online</h3><p>Promo e servizi compaiono subito sul tuo sito, senza toccare codice.</p></div></div>
    </div>
</section>

<section class="wl-sec">
    <h2>Prezzi semplici</h2>
    <p>Niente sorprese: si comincia gratis.</p>
    <div class="wl-price">
        <div class="wl-plan"><b>Privato</b><strong>€0</strong> <small>per sempre</small></div>
        <div class="wl-plan wl-plan--hot"><b>Azienda ed ente</b><strong>{{ config('services.hub_billing.free_days', 7) }} giorni gratis</strong><br><small>poi solo €{{ config('services.hub_billing.launch_offer_price_eur', 1) }} per partire</small></div>
        <div class="wl-plan"><b>Poi</b><strong>€{{ config('services.hub_billing.monthly_price_eur', 29) }}</strong> <small>al mese · disdici quando vuoi</small></div>
    </div>
</section>

<section class="wl-cta" id="registrazione">
    <div class="wl-cta__in">
        <h2>Registrati su Hub Core</h2>
        <p>Privato: sempre gratis. Azienda/Ente: <strong>{{ config('services.hub_billing.free_days', 7) }} giorni gratis</strong>, senza carta; dopo la prima settimana basta <strong>€{{ config('services.hub_billing.launch_offer_price_eur', 1) }}</strong> (copre anche l'attivazione del primo modulo). Poi €{{ config('services.hub_billing.monthly_price_eur', 29) }}/mese, disdici quando vuoi.</p>
        @error('registration')
            <p class="wl-err">{{ $message }}</p>
        @enderror
        @error('guest')
            <p class="wl-err">{{ $message }}</p>
        @enderror
        <div class="wl-btns" style="justify-content:center">
            <a class="wl-btn" href="{{ route('registration.create') }}">Inizia la registrazione @include('layouts.partials.icon', ['name' => 'arrow', 'size' => 18])</a>
            <a class="wl-btn" href="{{ route('admin.login') }}" style="background:rgba(255,255,255,.16);color:#fff">Hai già un account? Accedi</a>
        </div>
    </div>
</section>

@include('layouts.partials.share-bar', ['shareTitle' => 'Hub Core — piattaforma multiservizi per aziende e privati', 'shareUrl' => route('welcome'), 'shareSection' => true, 'shareLabel' => 'Condividi Hub Core'])

<footer>
    Hub Core — piattaforma multiservizi per aziende e privati<br>
    <a href="{{ route('pricing.show') }}">Vedi i prezzi</a>
    ·
    <a href="{{ route('landing.web') }}">Siti web e app a Corigliano-Rossano</a>
    @if (config('landing.facebook_group.url'))
    ·
    <a href="{{ config('landing.facebook_group.url') }}" target="_blank" rel="noopener">{{ config('landing.facebook_group.name') }}</a>
    @endif
    ·
    <a href="{{ route('promo.hub-archive') }}">Guarda tutte le promozioni attive →</a>
    ·
    <a href="{{ route('search') }}">Cerca</a>
    @include('layouts.partials.company-footer')
</footer>

@include('partials.max-public-chat')
</body>
</html>
