<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $answers = $site->answers ?? [];
        $extras = $answers['extras'] ?? [];
        $tagline = $answers['tagline'] ?? $tenant->name;
        $services = \M35\HubSitebuilder\Services\SiteContent::servicesFor($answers);
        $strengths = $extras['strengths'] ?? [];
        $faq = $extras['faq'] ?? [];
        $closing = $extras['closing'] ?? '';
        $ctaLabel = $answers['cta_label'] ?? 'Contattaci';
        $about = $answers['extra'] ?? null;

        // Stile: quello scelto dall'attività nel profilo, altrimenti quello proposto dall'IA.
        $styleKey = isset($tenant->settings['brand']['font']) ? \App\Support\BrandFonts::keyForTenant($tenant) : ($extras['style'] ?? \App\Support\BrandFonts::keyForTenant($tenant));
        $brandFont = \App\Support\BrandFonts::get($styleKey);
        $dark = $styleKey === 'digitale';
        $primary = $tenant->primary_color ?: '#6366f1';

        $heroVisual = $site->hero_video_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($site->hero_video_path)
            : ($site->hero_svg_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($site->hero_svg_path) : null);
        $tel = $tenant->phone ? preg_replace('/[^0-9+]/', '', $tenant->phone) : null;
        // WhatsApp solo per i cellulari italiani (3xx) o numeri già con prefisso internazionale.
        $waNumber = $tel ? ltrim(preg_match('/^3\d{8,9}$/', $tel) ? '39'.$tel : (str_starts_with($tel, '+') ? $tel : ''), '+') : '';
        $waUrl = $waNumber ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode('Ciao '.$tenant->name.', vi scrivo dal vostro sito') : null;
        $ctaUrl = $waUrl && str_contains(strtolower($ctaLabel), 'whatsapp') ? $waUrl : ($tel ? 'tel:'.$tel : '#contatti');
        $hasContacts = $tel || $tenant->address;
        $mapsUrl = $tenant->address ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($tenant->address) : null;
        $initial = mb_strtoupper(mb_substr(trim($tenant->name), 0, 1));
        $lead = $about ? \Illuminate\Support\Str::limit(\Illuminate\Support\Str::before($about.' ', '. ').'.', 140) : null;
        $siteUrl = route('site.public.show', $tenant);
        $description = \Illuminate\Support\Str::limit($tagline.($about ? ' — '.$about : ''), 160);
    @endphp
    <title>{{ $tenant->name }} — {{ $tagline }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $siteUrl }}">
    <meta property="og:title" content="{{ $tenant->name }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="it_IT">
    <meta property="og:url" content="{{ $siteUrl }}">
    <meta property="og:image" content="{{ asset('images/og-hub-core.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $tenant->name }}">
    <meta name="twitter:image" content="{{ asset('images/og-hub-core.png') }}">
    <meta name="theme-color" content="{{ $primary }}">
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@'.'context' => 'https://schema.org',
        '@type' => $tenant->type === 'ente' ? 'Organization' : 'LocalBusiness',
        'name' => $tenant->name,
        'description' => $tagline,
        'url' => $siteUrl,
        'telephone' => $tenant->phone,
        'address' => $tenant->address,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?{{ $brandFont['google_fonts'] }}&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: {{ $primary }};
            --primary-dark: color-mix(in srgb, var(--primary) 70%, #000);
            --primary-soft: color-mix(in srgb, var(--primary) 12%, {{ $dark ? '#0d0f14' : '#fff' }});
            --ink: {{ $dark ? '#eef0f5' : '#14161a' }};
            --paper: {{ $dark ? '#0d0f14' : '#ffffff' }};
            --soft: {{ $dark ? '#161922' : '#f7f7f4' }};
            --card: {{ $dark ? '#1b1f2a' : '#ffffff' }};
            --muted: {{ $dark ? '#a4a9b6' : '#5b6169' }};
            --line: {{ $dark ? '#2a2f3c' : '#ecece8' }};
            --radius: {{ in_array($styleKey, ['amichevole'], true) ? '26px' : (in_array($styleKey, ['classico', 'tech'], true) ? '10px' : '18px') }};
            --font-display: {!! $brandFont['display'] !!};
            --font-body: {!! $brandFont['body'] !!};
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-body), system-ui, sans-serif; color: var(--ink); background: var(--paper); line-height: 1.6; -webkit-font-smoothing: antialiased; }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        h1, h2, h3 { font-family: var(--font-display), sans-serif; letter-spacing: -.01em; text-wrap: balance; line-height: 1.15; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 0 16px; }
        @media (min-width: 700px) { .wrap { padding: 0 28px; } }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 52px; padding: 0 26px; border-radius: 999px; font-weight: 700; font-size: 1rem; text-decoration: none; background: var(--primary); color: #fff; border: 2px solid transparent; transition: transform .15s, box-shadow .15s; box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 35%, transparent); }
        .btn:hover { transform: translateY(-2px); }
        .btn.ghost { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.55); box-shadow: none; backdrop-filter: blur(6px); }
        .btn.wa { background: #1fa855; box-shadow: 0 10px 24px rgba(31,168,85,.3); }
        .btn.light { background: #fff; color: var(--primary-dark); box-shadow: none; }

        /* Barra in alto */
        .top { position: sticky; top: 0; z-index: 30; background: color-mix(in srgb, var(--paper) 88%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
        .top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 60px; }
        .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; text-decoration: none; font-size: 1.05rem; min-width: 0; }
        .brand span:last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mono { flex: none; width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; background: var(--primary); color: #fff; font-weight: 800; }
        .top nav { display: none; gap: 22px; font-weight: 600; font-size: .95rem; }
        .top nav a { text-decoration: none; color: var(--muted); }
        .top nav a:hover { color: var(--ink); }
        .top .btn { min-height: 42px; padding: 0 18px; font-size: .9rem; box-shadow: none; white-space: nowrap; flex: none; }
        /* Sul telefono i tasti sono già nella barra in basso. */
        @media (max-width: 759px) { body.has-dock .top .btn { display: none; } }
        @media (min-width: 860px) { .top nav { display: flex; } }

        /* Hero */
        .hero { position: relative; overflow: hidden; color: #fff; background: linear-gradient(135deg, var(--primary-dark), #0d0f14); }
        .hero-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .9; }
        .hero-scrim { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.25), rgba(0,0,0,.65)), radial-gradient(circle at 15% 85%, color-mix(in srgb, var(--primary) 55%, transparent), transparent 55%); }
        .hero-inner { position: relative; z-index: 2; padding-top: 72px; padding-bottom: 120px; }
        .hero-inner > * { max-width: 720px; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.3); padding: 7px 14px; border-radius: 99px; margin-bottom: 18px; }
        .hero h1 { font-size: clamp(2.1rem, 6.4vw, 3.6rem); margin-bottom: 16px; }
        .hero p.lead { font-size: 1.08rem; opacity: .92; max-width: 560px; margin-bottom: 26px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .wave { position: absolute; left: 0; right: 0; bottom: -1px; width: 100%; height: 70px; z-index: 3; display: block; }

        /* Sezioni */
        .section { padding: 64px 0; }
        .section.soft { background: var(--soft); }
        .head { text-align: center; max-width: 640px; margin: 0 auto 34px; }
        .kicker { color: var(--primary); font-weight: 800; letter-spacing: .1em; text-transform: uppercase; font-size: .78rem; display: block; margin-bottom: 8px; }
        .head h2 { font-size: clamp(1.6rem, 4.5vw, 2.3rem); }
        @media (min-width: 700px) { .section { padding: 88px 0; } }

        .strengths { display: grid; gap: 16px; margin-top: -20px; }
        .strength { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 24px; box-shadow: 0 14px 34px rgba(16,18,24,.06); }
        .ico { width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; font-size: 1.7rem; background: var(--primary-soft); margin-bottom: 14px; }
        .strength h3 { font-size: 1.15rem; margin-bottom: 6px; }
        .strength p { color: var(--muted); }
        @media (min-width: 760px) { .strengths { grid-template-columns: repeat(3, 1fr); } }

        .services { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(min(290px, 100%), 1fr)); }
        .service { position: relative; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 22px; display: flex; gap: 16px; align-items: flex-start; transition: transform .2s, box-shadow .2s, border-color .2s; overflow: hidden; }
        .service::after { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--primary); opacity: 0; transition: opacity .2s; }
        .service:hover { transform: translateY(-3px); box-shadow: 0 16px 34px rgba(16,18,24,.08); border-color: color-mix(in srgb, var(--primary) 35%, var(--line)); }
        .service:hover::after { opacity: 1; }
        .service .ico { flex: none; margin: 0; width: 50px; height: 50px; font-size: 1.5rem; }
        .service h3 { font-size: 1.08rem; margin-bottom: 4px; }
        .service p { color: var(--muted); font-size: .95rem; }

        .about { display: grid; gap: 28px; align-items: center; }
        .about-art { position: relative; aspect-ratio: 1; max-width: min(340px, 60vw); width: 100%; margin: 0 auto; border-radius: 32px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); display: grid; place-items: center; color: #fff; overflow: hidden; }
        .about-art b { font-family: var(--font-display); font-size: clamp(6rem, 22vw, 9rem); line-height: 1; opacity: .95; }
        .about-art::before, .about-art::after { content: ''; position: absolute; border-radius: 50%; border: 2px solid rgba(255,255,255,.25); }
        .about-art::before { width: 70%; height: 70%; top: -18%; right: -18%; }
        .about-art::after { width: 50%; height: 50%; bottom: -12%; left: -12%; background: rgba(255,255,255,.08); }
        .about-text h2 { font-size: clamp(1.6rem, 4.5vw, 2.3rem); margin-bottom: 14px; }
        .about-text p { color: var(--muted); font-size: 1.08rem; }
        @media (min-width: 820px) { .about { grid-template-columns: 1fr 1.2fr; gap: 56px; } }

        .faq { max-width: 760px; margin: 0 auto; display: grid; gap: 12px; }
        .faq details { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 0 20px; }
        .faq summary { list-style: none; cursor: pointer; font-weight: 700; min-height: 60px; display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .faq summary::-webkit-details-marker { display: none; }
        .faq summary::after { content: '+'; flex: none; width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; background: var(--primary-soft); color: var(--primary); font-size: 1.2rem; transition: transform .2s; }
        .faq details[open] summary::after { transform: rotate(45deg); }
        .faq details p { color: var(--muted); padding-bottom: 18px; }

        .band { position: relative; overflow: hidden; border-radius: 28px; padding: 44px 24px; text-align: center; color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .band::before { content: ''; position: absolute; width: 320px; height: 320px; border-radius: 50%; right: -90px; top: -120px; background: rgba(255,255,255,.1); }
        .band::after { content: ''; position: absolute; width: 220px; height: 220px; border-radius: 50%; left: -70px; bottom: -110px; border: 2px solid rgba(255,255,255,.2); }
        .band h2 { position: relative; font-size: clamp(1.5rem, 4.4vw, 2.2rem); margin-bottom: 22px; }
        .band .hero-actions { position: relative; justify-content: center; }

        .contacts { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .contact { display: flex; align-items: center; gap: 14px; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 18px; text-decoration: none; min-height: 76px; }
        .contact .ico { margin: 0; width: 48px; height: 48px; font-size: 1.35rem; flex: none; }
        .contact small { display: block; color: var(--muted); font-size: .82rem; }
        .contact b { overflow-wrap: anywhere; }

        /* Footer */
        footer { background: #0f1117; color: #c8ccd6; padding: 56px 0 calc(28px + env(safe-area-inset-bottom)); }
        .foot { display: grid; gap: 32px; }
        .foot .brand { color: #fff; margin-bottom: 12px; }
        .foot p { color: #9aa0ad; font-size: .95rem; max-width: 340px; }
        .foot h4 { color: #fff; font-size: .82rem; letter-spacing: .1em; text-transform: uppercase; margin-bottom: 12px; }
        .foot ul { list-style: none; display: grid; gap: 8px; }
        .foot a { text-decoration: none; color: #c8ccd6; }
        .foot a:hover { color: #fff; }
        .legal { margin-top: 40px; padding-top: 20px; border-top: 1px solid #262a35; display: flex; flex-wrap: wrap; gap: 8px 18px; justify-content: space-between; font-size: .85rem; color: #8a90a0; }
        .legal a { color: #c8ccd6; }
        .made { display: inline-flex; align-items: center; gap: 6px; }
        .made b { color: #fff; }
        @media (min-width: 760px) { .foot { grid-template-columns: 1.4fr 1fr 1fr; } }

        /* Barra fissa su telefono */
        .dock { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; display: flex; gap: 10px; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: color-mix(in srgb, var(--paper) 92%, transparent); backdrop-filter: blur(10px); border-top: 1px solid var(--line); }
        .dock .btn { flex: 1; min-height: 50px; padding: 0 12px; box-shadow: none; }
        body.has-dock { padding-bottom: 76px; }
        @media (min-width: 760px) { .dock { display: none; } body.has-dock { padding-bottom: 0; } }

        /* Comparsa morbida scorrendo (solo con JavaScript e senza «riduci movimento») */
        .js .rv { opacity: 0; transform: translateY(22px); transition: opacity .6s ease, transform .6s ease; }
        .js .rv.in { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) { .js .rv { opacity: 1; transform: none; transition: none; } html { scroll-behavior: auto; } }
    </style>
    <script>document.documentElement.classList.add('js');</script>
@include('layouts.partials.ui')
</head>
<body class="{{ $tel ? 'has-dock' : '' }}">

<header class="top">
    <div class="wrap">
        <a class="brand" href="#top"><span class="mono">{{ $initial }}</span><span>{{ $tenant->name }}</span></a>
        <nav aria-label="Sezioni">
            @if (count($services))<a href="#servizi">Servizi</a>@endif
            @if ($about)<a href="#chi-siamo">Chi siamo</a>@endif
            @if (count($faq))<a href="#domande">Domande</a>@endif
            @if ($hasContacts)<a href="#contatti">Contatti</a>@endif
        </nav>
        @if ($hasContacts)<a class="btn" href="{{ $ctaUrl }}">{{ \Illuminate\Support\Str::limit($ctaLabel, 22) }}</a>@endif
    </div>
</header>

<section class="hero" id="top">
    @if ($heroVisual)
        <img class="hero-bg" src="{{ $heroVisual }}" alt="">
    @endif
    <div class="hero-scrim"></div>
    <div class="wrap hero-inner">
        <span class="eyebrow">{{ $tenant->name }}</span>
        <h1>{{ $tagline }}</h1>
        @if ($lead)<p class="lead">{{ $lead }}</p>@endif
        <div class="hero-actions">
            @if ($hasContacts)<a class="btn" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a>@endif
            @if ($waUrl && $ctaUrl !== $waUrl)<a class="btn ghost" href="{{ $waUrl }}" target="_blank" rel="noopener">WhatsApp</a>@endif
            @if (count($services))<a class="btn ghost" href="#servizi">Cosa offriamo</a>@endif
        </div>
    </div>
    <svg class="wave" viewBox="0 0 1440 70" preserveAspectRatio="none" aria-hidden="true"><path fill="{{ count($strengths) ? ($dark ? '#0d0f14' : '#ffffff') : ($dark ? '#0d0f14' : '#ffffff') }}" d="M0,40 C240,80 480,0 720,24 C960,48 1200,70 1440,30 L1440,70 L0,70 Z"/></svg>
</section>

@if (count($strengths))
<section class="section" style="padding-top:40px" aria-label="Perché sceglierci">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Perché sceglierci</span></div>
        <div class="strengths">
            @foreach ($strengths as $s)
                <div class="strength rv"><div class="ico" aria-hidden="true">{{ $s['icon'] }}</div><h3>{{ $s['title'] }}</h3><p>{{ $s['text'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if (count($services))
<section class="section soft" id="servizi">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Servizi</span><h2>Cosa offriamo</h2></div>
        <div class="services">
            @foreach ($services as $service)
                <div class="service rv">
                    <div class="ico" aria-hidden="true">{{ $service['icon'] }}</div>
                    <div><h3>{{ $service['name'] }}</h3>@if ($service['line'])<p>{{ $service['line'] }}</p>@endif</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($about)
<section class="section" id="chi-siamo">
    <div class="wrap about">
        <div class="about-art rv" aria-hidden="true"><b>{{ $initial }}</b></div>
        <div class="about-text rv"><span class="kicker">Chi siamo</span><h2>{{ $tenant->name }}</h2><p>{{ $about }}</p></div>
    </div>
</section>
@endif

@if (count($faq))
<section class="section soft" id="domande">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Domande frequenti</span><h2>Le risposte che cerchi</h2></div>
        <div class="faq">
            @foreach ($faq as $i => $f)
                <details class="rv" @if ($i === 0) open @endif><summary>{{ $f['q'] }}</summary><p>{{ $f['a'] }}</p></details>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($hasContacts)
<section class="section" id="contatti">
    <div class="wrap">
        <div class="band rv">
            <h2>{{ $closing ?: 'Scrivici o chiamaci: ti rispondiamo presto' }}</h2>
            <div class="hero-actions">
                @if ($waUrl)<a class="btn light" href="{{ $waUrl }}" target="_blank" rel="noopener">Scrivici su WhatsApp</a>@endif
                @if ($tel)<a class="btn ghost" href="tel:{{ $tel }}">Chiama {{ $tenant->phone }}</a>@endif
            </div>
        </div>

        <div class="contacts" style="margin-top:22px">
            @if ($tel)<a class="contact rv" href="tel:{{ $tel }}"><span class="ico" aria-hidden="true">📞</span><span><small>Telefono</small><b>{{ $tenant->phone }}</b></span></a>@endif
            @if ($waUrl)<a class="contact rv" href="{{ $waUrl }}" target="_blank" rel="noopener"><span class="ico" aria-hidden="true">💬</span><span><small>WhatsApp</small><b>Scrivici ora</b></span></a>@endif
            @if ($tenant->address)<a class="contact rv" href="{{ $mapsUrl }}" target="_blank" rel="noopener"><span class="ico" aria-hidden="true">📍</span><span><small>Dove siamo</small><b>{{ $tenant->address }}</b></span></a>@endif
        </div>
    </div>
</section>
@endif

@include('layouts.partials.share-bar', ['shareTitle' => $tenant->name, 'shareUrl' => $siteUrl, 'shareSection' => true, 'shareLabel' => 'Condividi questo sito'])

<footer>
    <div class="wrap">
        <div class="foot">
            <div>
                <a class="brand" href="#top"><span class="mono">{{ $initial }}</span><span>{{ $tenant->name }}</span></a>
                <p>{{ $tagline }}</p>
            </div>
            @if ($hasContacts)
            <div>
                <h4>Contatti</h4>
                <ul>
                    @if ($tel)<li><a href="tel:{{ $tel }}">{{ $tenant->phone }}</a></li>@endif
                    @if ($waUrl)<li><a href="{{ $waUrl }}" target="_blank" rel="noopener">WhatsApp</a></li>@endif
                    @if ($tenant->address)<li><a href="{{ $mapsUrl }}" target="_blank" rel="noopener">{{ $tenant->address }}</a></li>@endif
                </ul>
            </div>
            @endif
            <div>
                <h4>Pagina</h4>
                <ul>
                    <li><a href="#top">Inizio</a></li>
                    @if (count($services))<li><a href="#servizi">Servizi</a></li>@endif
                    @if ($about)<li><a href="#chi-siamo">Chi siamo</a></li>@endif
                    @if (count($faq))<li><a href="#domande">Domande frequenti</a></li>@endif
                    @if ($hasContacts)<li><a href="#contatti">Contatti</a></li>@endif
                </ul>
            </div>
        </div>
        <div class="legal">
            <span>© {{ now()->year }} {{ $tenant->name }} · <a href="{{ route('privacy') }}">Privacy</a></span>
            <a class="made" href="{{ route('site.start', ['ref' => 'sito-'.$tenant->slug]) }}">Sito creato con l'IA di <b>Hub Core</b> · crea il tuo</a>
        </div>
    </div>
</footer>

@if ($tel)
<div class="dock">
    <a class="btn" href="tel:{{ $tel }}">📞 Chiama</a>
    @if ($waUrl)<a class="btn wa" href="{{ $waUrl }}" target="_blank" rel="noopener">💬 WhatsApp</a>@endif
</div>
@endif

<script>
(function () {
    var items = document.querySelectorAll('.rv');
    var showAll = function () { items.forEach(function (el) { el.classList.add('in'); }); };
    // Sicurezza: nessuna sezione deve mai restare nascosta (vecchi browser, anteprime, stampa).
    setTimeout(showAll, 1500);
    window.addEventListener('beforeprint', showAll);
    if (!('IntersectionObserver' in window)) { showAll(); return; }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    items.forEach(function (el) { io.observe(el); });
})();
</script>
</body>
</html>
