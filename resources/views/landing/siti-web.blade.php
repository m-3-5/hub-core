<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $city = config('landing.city');
        $place = config('landing.place');
        $title = 'Siti web e app a '.$city.' — preventivo gratuito | M 3.5';
        $desc = 'Realizziamo siti web e app per attività di '.$city.' e dintorni. Siamo a '.$place.': vetrina, sito aziendale o progetto su misura'.($offerActive ? ', con prezzi in offerta fino al '.$endsAt->format('d/m/Y') : '').'. Chiedi un preventivo gratuito.';
        $url = route('landing.web');
        $minPrice = $plans->min('price');
    @endphp
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $desc }}">
    <link rel="canonical" href="{{ $url }}">
    <meta name="theme-color" content="#14142b">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="it_IT">
    <meta property="og:title" content="Siti web e app a {{ $city }} — preventivo gratuito">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:url" content="{{ $url }}">
    <meta property="og:site_name" content="M 3.5">
    <meta name="twitter:card" content="summary">
    <meta name="geo.region" content="IT-CS">
    <meta name="geo.placename" content="{{ $city }}">
    <link rel="icon" href="/images/icon-192.png">

    <script type="application/ld+json">
    {!! json_encode([
        '@'.'context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'ProfessionalService',
                '@id' => $url.'#azienda',
                'name' => 'M 3.5 — Siti web e app',
                'url' => $url,
                'description' => 'Realizzazione di siti web vetrina, siti aziendali e app su misura per attività di '.$city.' e della Sibaritide.',
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => $city, 'addressRegion' => 'CS', 'addressCountry' => 'IT'],
                'areaServed' => collect($zones)->map(fn ($z) => ['@type' => 'Place', 'name' => $z])->values()->all(),
                'priceRange' => '€'.$minPrice.' - €'.$plans->max('price'),
                'telephone' => $phone,
                'hasOfferCatalog' => [
                    '@type' => 'OfferCatalog',
                    'name' => 'Siti web',
                    'itemListElement' => $plans->map(fn ($p) => array_filter([
                        '@type' => 'Offer',
                        'name' => 'Sito '.$p['name'],
                        'description' => $p['tagline'],
                        'price' => (string) $p['price'],
                        'priceCurrency' => 'EUR',
                        'priceValidUntil' => $offerActive ? $endsAt->toDateString() : null,
                        'availability' => 'https://schema.org/InStock',
                    ]))->values()->all(),
                ],
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => [
                    ['@type' => 'Question', 'name' => 'Quanto costa un sito web a '.$city.'?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Si parte da '.$minPrice.' euro per un sito vetrina. Il sito aziendale e quello professionale su misura hanno prezzi diversi in base a pagine e funzioni: il preventivo è gratuito e senza impegno.']],
                    ['@type' => 'Question', 'name' => 'Il sito si vede bene da smartphone?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Sì, ogni sito nasce prima per lo smartphone e poi si adatta a tablet e computer.']],
                    ['@type' => 'Question', 'name' => 'Lavorate anche fuori da '.$city.'?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Sì, lavoriamo in tutta la Sibaritide e anche a distanza. Se sei di zona puoi passare a trovarci a '.$place.'.']],
                    ['@type' => 'Question', 'name' => 'Posso modificare io testi e foto?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Possiamo pensarci noi oppure darti un pannello semplice per farlo da solo, a seconda del pacchetto: lo decidiamo insieme nel preventivo.']],
                ],
            ],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>

    <style>
        :root {
            --bg: #ffffff; --ink: #14142b; --muted: #5d5d73; --line: #e8e8f0; --soft: #f6f6fb;
            --brand: #e91e8c; --brand-dark: #c2177a; --navy: #14142b; --navy2: #23234a; --ok: #1a9d6b; --warn: #b45309;
            --radius: 20px;
        }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html { scroll-behavior: smooth; scroll-padding-top: 70px; }
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.55; -webkit-font-smoothing: antialiased; padding-bottom: 84px; }
        a { color: inherit; }
        .wrap { width: 100%; max-width: 1080px; margin: 0 auto; padding: 0 20px; }
        h1, h2, h3 { line-height: 1.12; letter-spacing: -.02em; margin: 0; }
        h2 { font-size: clamp(1.6rem, 5.5vw, 2.3rem); }
        section { padding: 54px 0; }
        .eyebrow { display: inline-block; font-size: .8rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--brand); margin-bottom: 10px; }
        .lead { color: var(--muted); font-size: 1.08rem; margin: 12px 0 0; max-width: 62ch; }

        /* barra in alto */
        .top { position: sticky; top: 0; z-index: 30; background: rgba(255,255,255,.92); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); padding-top: env(safe-area-inset-top); }
        .top .wrap { display: flex; align-items: center; justify-content: space-between; height: 58px; gap: 12px; }
        .logo { font-weight: 800; font-size: 1.15rem; text-decoration: none; letter-spacing: -.02em; }
        .logo b { color: var(--brand); }
        .top-cta { display: flex; gap: 8px; }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; font: inherit; font-weight: 700; font-size: 1rem; text-decoration: none; border: 0; border-radius: 14px; padding: 14px 22px; cursor: pointer; transition: transform .1s, box-shadow .2s; min-height: 48px; }
        .btn:active { transform: scale(.98); }
        .btn-brand { background: var(--brand); color: #fff; box-shadow: 0 10px 24px rgba(233,30,140,.32); }
        .btn-brand:hover { background: var(--brand-dark); }
        .btn-ghost { background: #fff; color: var(--ink); border: 2px solid var(--line); }
        .btn-wa { background: #25d366; color: #063; }
        .btn-sm { padding: 9px 16px; min-height: 40px; font-size: .92rem; border-radius: 12px; }

        /* hero */
        .hero { background: radial-gradient(900px 500px at 85% -10%, rgba(233,30,140,.35), transparent 60%), linear-gradient(160deg, var(--navy), var(--navy2)); color: #fff; padding: 44px 0 54px; overflow: hidden; }
        .hero h1 { font-size: clamp(2.1rem, 8.4vw, 3.6rem); font-weight: 800; }
        .hero h1 em { font-style: normal; color: #ff8ecb; }
        .hero p { color: #d9d9ee; font-size: 1.12rem; max-width: 52ch; margin: 16px 0 0; }
        .pin { display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); border-radius: 99px; padding: 8px 14px; font-size: .92rem; font-weight: 600; margin-bottom: 18px; }
        .hero-cta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 26px; }
        .hero .btn-ghost { background: transparent; color: #fff; border-color: rgba(255,255,255,.35); }
        .ticks { display: flex; flex-wrap: wrap; gap: 8px 18px; margin: 24px 0 0; padding: 0; list-style: none; color: #e9e9f7; font-size: .95rem; }
        .ticks li::before { content: '✓'; color: #7ee2b8; font-weight: 800; margin-right: 6px; }

        /* offerta */
        .offer { background: #fff7e6; border: 2px dashed #f5b942; border-radius: var(--radius); padding: 18px 20px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; margin-top: -30px; position: relative; z-index: 2; box-shadow: 0 12px 30px rgba(20,20,43,.12); }
        .offer b { font-size: 1.05rem; }
        .offer small { display: block; color: var(--warn); font-weight: 600; }
        .count { display: flex; gap: 8px; }
        .count div { background: var(--navy); color: #fff; border-radius: 12px; min-width: 56px; text-align: center; padding: 8px 6px; }
        .count strong { display: block; font-size: 1.35rem; line-height: 1; }
        .count span { font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; opacity: .8; }

        /* pacchetti */
        .plans { display: grid; gap: 18px; margin-top: 30px; }
        .plan { position: relative; border: 2px solid var(--line); border-radius: var(--radius); padding: 24px 22px; background: #fff; display: flex; flex-direction: column; }
        .plan.featured { border-color: var(--brand); box-shadow: 0 18px 40px rgba(233,30,140,.16); }
        .badge { position: absolute; top: -13px; left: 20px; background: var(--brand); color: #fff; font-size: .74rem; font-weight: 800; padding: 5px 12px; border-radius: 99px; letter-spacing: .04em; text-transform: uppercase; }
        .plan h3 { font-size: 1.35rem; }
        .plan .tag { color: var(--muted); margin: 4px 0 14px; }
        .price { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px 10px; }
        .price .now { font-size: 2.5rem; font-weight: 800; letter-spacing: -.03em; }
        .price .now small { font-size: 1rem; font-weight: 600; color: var(--muted); letter-spacing: 0; }
        .price .was { color: #9a9ab0; text-decoration: line-through; font-size: 1.1rem; }
        .price .off { background: #e7f8f0; color: var(--ok); font-weight: 800; font-size: .8rem; padding: 3px 9px; border-radius: 99px; }
        .plan ul { list-style: none; padding: 0; margin: 18px 0 22px; display: grid; gap: 9px; flex: 1; }
        .plan li { padding-left: 26px; position: relative; font-size: .98rem; }
        .plan li::before { content: '✓'; position: absolute; left: 0; color: var(--ok); font-weight: 800; }
        .note { color: var(--muted); font-size: .9rem; text-align: center; margin-top: 20px; }
        .custom { margin-top: 18px; background: var(--soft); border-radius: var(--radius); padding: 18px 20px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; }

        /* passi */
        .steps { display: grid; gap: 14px; margin-top: 28px; counter-reset: s; }
        .step { background: var(--soft); border-radius: var(--radius); padding: 20px; position: relative; padding-left: 74px; }
        .step::before { counter-increment: s; content: counter(s); position: absolute; left: 20px; top: 18px; width: 38px; height: 38px; border-radius: 50%; background: var(--navy); color: #fff; font-weight: 800; display: grid; place-items: center; }
        .step b { display: block; font-size: 1.08rem; margin-bottom: 2px; }
        .step p { margin: 0; color: var(--muted); }

        /* zone */
        .zones { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 22px; }
        .zones span { background: #fff; border: 1px solid var(--line); border-radius: 99px; padding: 8px 14px; font-weight: 600; font-size: .94rem; }
        .zones span.here { background: var(--brand); border-color: var(--brand); color: #fff; }

        /* faq */
        details { border-bottom: 1px solid var(--line); padding: 16px 0; }
        summary { cursor: pointer; font-weight: 700; font-size: 1.05rem; list-style: none; display: flex; justify-content: space-between; gap: 12px; }
        summary::-webkit-details-marker { display: none; }
        summary::after { content: '+'; font-size: 1.4rem; line-height: 1; color: var(--brand); }
        details[open] summary::after { content: '–'; }
        details p { color: var(--muted); margin: 10px 0 0; }

        /* modulo */
        .contact { background: linear-gradient(160deg, var(--navy), var(--navy2)); color: #fff; }
        .contact .lead { color: #d9d9ee; }
        .form-card { background: #fff; color: var(--ink); border-radius: 24px; padding: 24px 20px; margin-top: 26px; box-shadow: 0 24px 60px rgba(0,0,0,.28); }
        .grid2 { display: grid; gap: 14px; }
        label.l { display: block; font-weight: 700; font-size: .92rem; margin: 0 0 6px; }
        .in { width: 100%; font: inherit; font-size: 1.05rem; padding: 14px 15px; border: 2px solid var(--line); border-radius: 14px; background: #fff; color: var(--ink); outline: none; min-height: 52px; }
        .in:focus { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(233,30,140,.14); }
        textarea.in { min-height: 100px; resize: vertical; }
        .chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .chips input { position: absolute; opacity: 0; pointer-events: none; }
        .chips label { border: 2px solid var(--line); border-radius: 99px; padding: 10px 15px; font-weight: 600; font-size: .95rem; cursor: pointer; user-select: none; }
        .chips input:checked + label { border-color: var(--brand); background: #fdeaf5; color: var(--brand-dark); }
        .chips input:focus-visible + label { box-shadow: 0 0 0 4px rgba(233,30,140,.2); }
        .check { display: flex; gap: 10px; align-items: flex-start; font-size: .9rem; color: var(--muted); }
        .check input { width: 22px; height: 22px; accent-color: var(--brand); flex: none; margin-top: 2px; }
        .err { color: #c62828; font-size: .9rem; margin: 6px 0 0; }
        .errbox { background: #fdecea; color: #b71c1c; border-radius: 12px; padding: 12px 14px; margin-bottom: 14px; font-weight: 600; }
        .ok-card { text-align: center; padding: 18px 6px; }
        .ok-card .big { font-size: 3rem; }
        .hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
        .priv { margin-top: 12px; font-size: .84rem; color: var(--muted); }
        .priv summary { font-size: .86rem; font-weight: 600; color: var(--muted); justify-content: flex-start; }
        .priv summary::after { content: none; }

        footer { padding: 30px 0 20px; color: var(--muted); font-size: .9rem; text-align: center; }
        footer a { color: var(--ink); font-weight: 600; }

        /* barra fissa su telefono */
        .sticky { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); background: rgba(255,255,255,.96); backdrop-filter: blur(10px); border-top: 1px solid var(--line); display: flex; gap: 10px; transition: transform .25s; }
        .sticky .btn { flex: 1; }
        .sticky.hide { transform: translateY(110%); }

        @media (min-width: 760px) {
            body { padding-bottom: 0; }
            .plans { grid-template-columns: repeat(3, 1fr); align-items: stretch; }
            .steps { grid-template-columns: repeat(3, 1fr); }
            .step { padding: 74px 20px 20px; } .step::before { top: 20px; }
            .grid2 { grid-template-columns: 1fr 1fr; }
            .grid2 .full { grid-column: 1 / -1; }
            .form-card { padding: 34px; max-width: 760px; }
            .sticky { display: none; }
            .hero { padding: 80px 0 90px; }
        }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } .btn, .sticky { transition: none; } }
    </style>
</head>
<body>

<header class="top">
    <div class="wrap">
        <a class="logo" href="{{ route('welcome') }}">M<b> 3.5</b></a>
        <div class="top-cta">
            @if ($phone)<a class="btn btn-ghost btn-sm" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">Chiama</a>@endif
            <a class="btn btn-brand btn-sm" href="#contatti">Preventivo gratis</a>
        </div>
    </div>
</header>

<main>
    <div class="hero">
        <div class="wrap">
            <span class="pin">📍 Ora siamo a {{ $place }}, {{ $city }}</span>
            <h1>Il tuo <em>sito web</em> o la tua <em>app</em>, qui vicino a te.</h1>
            <p>Realizziamo siti e app per negozi, professionisti e aziende di {{ $city }} e dintorni. Vieni a trovarci a {{ $place }} o scrivici: ti diciamo subito cosa serve e quanto costa.</p>
            <div class="hero-cta">
                <a class="btn btn-brand" href="#contatti">Chiedi un preventivo gratuito</a>
                <a class="btn btn-ghost" href="#prezzi">Guarda i prezzi</a>
            </div>
            <ul class="ticks">
                <li>Perfetto da smartphone</li>
                <li>Ti troviamo su Google</li>
                <li>Preventivo senza impegno</li>
            </ul>
        </div>
    </div>

    <div class="wrap">
        @if ($offerActive)
            <div class="offer" id="offerBox" data-end="{{ $endsAt->toIso8601String() }}">
                <div>
                    <b>🔥 Offerta di lancio fino al {{ $endsAt->format('d/m/Y') }}</b>
                    <small>Prezzi scontati per le prime attività della zona</small>
                </div>
                <div class="count" aria-live="off">
                    <div><strong id="cd-d">--</strong><span>giorni</span></div>
                    <div><strong id="cd-h">--</strong><span>ore</span></div>
                    <div><strong id="cd-m">--</strong><span>min</span></div>
                </div>
            </div>
        @endif
    </div>

    <section id="prezzi">
        <div class="wrap">
            <span class="eyebrow">Tre modi per partire</span>
            <h2>Scegli il sito giusto per la tua attività</h2>
            <p class="lead">Tre pacchetti chiari. Se non sai quale scegliere, raccontaci cosa fai: ti consigliamo noi.</p>

            <div class="plans">
                @foreach ($plans as $plan)
                    <article class="plan {{ $plan['featured'] ? 'featured' : '' }}">
                        @if ($plan['featured'])<span class="badge">Il più scelto</span>@endif
                        <h3>{{ $plan['name'] }}</h3>
                        <p class="tag">{{ $plan['tagline'] }}</p>
                        <div class="price">
                            <span class="now">{{ $plan['prefix'] }}{{ number_format($plan['price'], 0, ',', '.') }}<small> €</small></span>
                            @if ($offerActive)
                                <span class="was">{{ number_format($plan['price_regular'], 0, ',', '.') }} €</span>
                                @if ($plan['discount'] > 0)<span class="off">−{{ $plan['discount'] }}%</span>@endif
                            @endif
                        </div>
                        <ul>
                            @foreach ($plan['features'] as $feature)<li>{{ $feature }}</li>@endforeach
                        </ul>
                        <a class="btn {{ $plan['featured'] ? 'btn-brand' : 'btn-ghost' }}" href="#contatti" data-pick="{{ $plan['key'] }}">Voglio il sito {{ $plan['name'] }}</a>
                    </article>
                @endforeach
            </div>
            <p class="note">{{ config('landing.price_note') }}</p>

            <div class="custom">
                <div><b>Ti serve anche un'app o un gestionale?</b><br><span style="color:var(--muted)">App per prenotazioni, cataloghi, pagamenti: preventivo su misura.</span></div>
                <a class="btn btn-ghost btn-sm" href="#contatti" data-pick="app">Parlaci della tua idea</a>
            </div>
        </div>
    </section>

    <section style="background:var(--soft)">
        <div class="wrap">
            <span class="eyebrow">Come funziona</span>
            <h2>Semplice, senza giri di parole</h2>
            <div class="steps">
                <div class="step"><b>Ci racconti la tua attività</b><p>Una telefonata, un messaggio o una visita a {{ $place }}: bastano pochi minuti.</p></div>
                <div class="step"><b>Disegniamo il tuo sito</b><p>Ti mostriamo la grafica e la sistemiamo insieme finché ti convince.</p></div>
                <div class="step"><b>Si va online</b><p>Ti aiutiamo con tutto e il sito è visibile da telefono e computer.</p></div>
            </div>
        </div>
    </section>

    <section>
        <div class="wrap">
            <span class="eyebrow">Dove lavoriamo</span>
            <h2>A {{ $city }} e in tutta la zona</h2>
            <p class="lead">Siamo a {{ $place }}: se sei di zona ci vediamo di persona, altrimenti ci sentiamo a distanza senza problemi.</p>
            <div class="zones">
                @foreach ($zones as $zone)<span class="{{ $zone === $place ? 'here' : '' }}">{{ $zone === $place ? '📍 ' : '' }}{{ $zone }}</span>@endforeach
            </div>
        </div>
    </section>

    <section style="background:var(--soft)">
        <div class="wrap">
            <span class="eyebrow">Domande frequenti</span>
            <h2>Hai un dubbio?</h2>
            <div style="margin-top:18px">
                <details><summary>Quanto costa un sito web a {{ $city }}?</summary><p>Si parte da {{ $minPrice }} € per il sito vetrina. Aziendale e professionale su misura dipendono da pagine e funzioni: il preventivo è gratuito e senza impegno.</p></details>
                <details><summary>Il sito si vede bene da smartphone?</summary><p>Sì: ogni sito nasce prima per lo smartphone, poi si adatta a tablet e computer.</p></details>
                <details><summary>Lavorate anche fuori da {{ $city }}?</summary><p>Sì, in tutta la Sibaritide e anche a distanza. Se sei di zona puoi passare a trovarci a {{ $place }}.</p></details>
                <details><summary>Posso modificare io testi e foto?</summary><p>Possiamo pensarci noi oppure darti un pannello semplice per farlo da solo, a seconda del pacchetto. Lo decidiamo insieme nel preventivo.</p></details>
            </div>
        </div>
    </section>

    <section class="contact" id="contatti">
        <div class="wrap">
            <span class="eyebrow" style="color:#ff8ecb">Preventivo gratuito</span>
            <h2>Raccontaci cosa ti serve</h2>
            <p class="lead">Lascia nome e telefono: ti richiamiamo noi, di solito entro la giornata lavorativa.</p>

            <div class="form-card" id="formCard">
                @if (session('lead_sent'))
                    <div class="ok-card">
                        <div class="big">✅</div>
                        <h3 style="font-size:1.5rem;margin:8px 0">Richiesta ricevuta!</h3>
                        <p style="color:var(--muted);margin:0 0 16px">Grazie, ti ricontattiamo al più presto. Se hai fretta scrivici direttamente.</p>
                        @if ($whatsapp)<a class="btn btn-wa" href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Ciao! Ho appena inviato una richiesta dal sito per un sito web.') }}" target="_blank" rel="noopener">Scrivici su WhatsApp</a>@endif
                    </div>
                @else
                    <form method="POST" action="{{ route('landing.web.lead') }}" id="leadForm" novalidate>
                        @csrf
                        <input type="hidden" name="rendered_at" value="{{ $renderedAt }}">
                        <div class="hp" aria-hidden="true"><label>Non compilare<input type="text" name="company" tabindex="-1" autocomplete="off"></label></div>

                        @if ($errors->any())
                            <div class="errbox">{{ $errors->first() }}</div>
                        @endif

                        <div class="grid2">
                            <div>
                                <label class="l" for="name">Il tuo nome *</label>
                                <input class="in" id="name" name="name" type="text" autocomplete="name" value="{{ old('name') }}" required maxlength="80">
                            </div>
                            <div>
                                <label class="l" for="phone">Telefono *</label>
                                <input class="in" id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="es. 333 1234567" value="{{ old('phone') }}" required maxlength="25">
                            </div>
                            <div class="full">
                                <label class="l" for="email">Email (facoltativa)</label>
                                <input class="in" id="email" name="email" type="email" autocomplete="email" value="{{ old('email') }}" maxlength="190">
                            </div>
                            <div class="full">
                                <span class="l">Cosa ti interessa?</span>
                                <div class="chips" id="pkgChips">
                                    @foreach ($plans as $plan)
                                        <span><input type="radio" name="package" id="pk-{{ $plan['key'] }}" value="{{ $plan['key'] }}" @checked(old('package') === $plan['key'])><label for="pk-{{ $plan['key'] }}">{{ $plan['name'] }}</label></span>
                                    @endforeach
                                    <span><input type="radio" name="package" id="pk-app" value="app" @checked(old('package') === 'app')><label for="pk-app">App su misura</label></span>
                                    <span><input type="radio" name="package" id="pk-altro" value="altro" @checked(old('package') === 'altro')><label for="pk-altro">Non so ancora</label></span>
                                </div>
                            </div>
                            <div class="full">
                                <label class="l" for="message">Vuoi dirci qualcosa? (facoltativo)</label>
                                <textarea class="in" id="message" name="message" maxlength="800" placeholder="Che attività hai? Hai già un sito?">{{ old('message') }}</textarea>
                            </div>
                            <div class="full">
                                <label class="check"><input type="checkbox" name="consent" value="1" @checked(old('consent')) required>
                                    <span>Acconsento a essere ricontattato per questa richiesta. *</span></label>
                                <details class="priv"><summary>Come usiamo i tuoi dati</summary>
                                    <p style="margin:8px 0 0">I dati inseriti (nome, telefono, email, messaggio) sono usati da M 3.5 S.R.L. solo per rispondere alla tua richiesta di preventivo e non vengono ceduti a terzi. Puoi chiedere in qualsiasi momento di consultarli o cancellarli scrivendoci.</p></details>
                            </div>
                            <div class="full">
                                <button class="btn btn-brand" style="width:100%;font-size:1.1rem;padding:17px" type="submit" id="sendBtn">Invia la richiesta</button>
                            </div>
                        </div>
                    </form>
                @endif
            </div>

            @if ($whatsapp || $phone)
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:20px">
                    @if ($whatsapp)<a class="btn btn-wa" href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Ciao! Vorrei un preventivo per un sito web.') }}" target="_blank" rel="noopener">WhatsApp</a>@endif
                    @if ($phone)<a class="btn btn-ghost" style="color:#fff;border-color:rgba(255,255,255,.35);background:transparent" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">Chiama {{ $phone }}</a>@endif
                </div>
            @endif
        </div>
    </section>
</main>

<footer>
    <div class="wrap">
        M 3.5 S.R.L. · {{ $place }}, {{ $city }} (CS)<br>
        <a href="{{ route('welcome') }}">Hub Core</a> · <a href="{{ route('pricing.show') }}">Prezzi Hub</a> · <a href="{{ route('promo.hub-archive') }}">Promozioni</a>
    </div>
</footer>

<div class="sticky" id="stickyBar">
    @if ($whatsapp)<a class="btn btn-wa" href="https://wa.me/{{ $whatsapp }}?text={{ rawurlencode('Ciao! Vorrei un preventivo per un sito web.') }}" target="_blank" rel="noopener">WhatsApp</a>
    @elseif ($phone)<a class="btn btn-ghost" href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">Chiama</a>@endif
    <a class="btn btn-brand" href="#contatti">Preventivo gratis</a>
</div>

<script>
(function () {
    // conto alla rovescia dell'offerta
    var box = document.getElementById('offerBox');
    if (box) {
        var end = new Date(box.dataset.end).getTime();
        var d = document.getElementById('cd-d'), h = document.getElementById('cd-h'), m = document.getElementById('cd-m');
        var pad = function (n) { return String(n).padStart(2, '0'); };
        var tick = function () {
            var left = Math.max(0, end - Date.now());
            d.textContent = Math.floor(left / 864e5);
            h.textContent = pad(Math.floor(left % 864e5 / 36e5));
            m.textContent = pad(Math.floor(left % 36e5 / 6e4));
        };
        tick(); setInterval(tick, 30000);
    }

    // «Voglio il sito X» precompila la scelta nel modulo
    document.querySelectorAll('[data-pick]').forEach(function (a) {
        a.addEventListener('click', function () {
            var r = document.getElementById('pk-' + a.dataset.pick);
            if (r) r.checked = true;
        });
    });

    // la barra fissa sparisce quando il modulo è visibile
    var bar = document.getElementById('stickyBar'), card = document.getElementById('formCard');
    if (bar && card && 'IntersectionObserver' in window) {
        new IntersectionObserver(function (e) { bar.classList.toggle('hide', e[0].isIntersecting); }, { threshold: .15 }).observe(card);
    }

    // evita doppi invii
    var form = document.getElementById('leadForm'), btn = document.getElementById('sendBtn');
    if (form) form.addEventListener('submit', function () { btn.disabled = true; btn.textContent = 'Invio in corso…'; });
})();
</script>
</body>
</html>
