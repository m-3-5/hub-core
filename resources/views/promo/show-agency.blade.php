<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $promo->seo_title ?? $promo->title }} — {{ $tenant->name }}</title>
    <meta name="description" content="{{ $promo->seo_description }}">
    <meta property="og:title" content="{{ $promo->seo_title ?? $promo->title }}">
    <meta property="og:description" content="{{ $promo->seo_description }}">
    <meta property="og:image" content="{{ $promo->variantUrl('og') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $tenant->name }}">
    <meta property="og:url" content="{{ $promo->publicUrl() }}">
    <meta property="og:locale" content="it_IT">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $promo->seo_title ?? $promo->title }}">
    <meta name="twitter:description" content="{{ $promo->seo_description }}">
    <meta name="twitter:image" content="{{ $promo->variantUrl('og') }}">
    <link rel="canonical" href="{{ $promo->publicUrl() }}">
    <meta name="theme-color" content="{{ $tenant->primary_color }}">
    @php
        $brandFontKey = $tenant->settings['brand']['font'] ?? \App\Support\BrandFonts::default();
        $brandFont = \App\Support\BrandFonts::get($brandFontKey);
        $tagline = $tenant->settings['tagline'] ?? null;
        $flyer = $promo->variantUrl('flyer') ?? $promo->imageUrl();
    @endphp
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?{{ $brandFont['google_fonts'] }}&display=swap" rel="stylesheet">
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'ProfessionalService',
        'name' => $tenant->name,
        'url' => $tenant->website,
        'address' => $tenant->address,
        'telephone' => $tenant->phone,
        'makesOffer' => [
            '@type' => 'Offer',
            'name' => $promo->title,
            'description' => \Illuminate\Support\Str::limit(strip_tags($promo->description ?? ''), 200),
            'url' => $promo->publicUrl(),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <style>
        :root {
            --primary: {{ $tenant->primary_color }};
            --bg: #081014;
            --bg-elevated: #0f172a;
            --text: #eef4f2;
            --muted: #8fa0a8;
            --line: rgba(255,255,255,.09);
            --font-display: {!! $brandFont['display'] !!};
            --font-body: {!! $brandFont['body'] !!};
            --font-accent: {!! $brandFont['accent'] !!};
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-body), system-ui, sans-serif; color: var(--text); background: var(--bg); line-height: 1.6; }
        img { max-width: 100%; display: block; }
        .wrap { max-width: 1120px; margin: 0 auto; padding: 0 24px; }
        h1, h2, h3 { font-family: var(--font-display), sans-serif; letter-spacing: -.01em; text-wrap: balance; }
        a { color: inherit; }

        /* triangle motif */
        .tri { position: absolute; width: 0; height: 0; pointer-events: none; z-index: 1; }
        .tri--fill { border-style: solid; }

        /* network / constellation motif */
        .network { position: absolute; top: 0; right: 0; width: 62%; max-width: 640px; height: auto; opacity: .55; pointer-events: none; z-index: 1; }

        /* HERO */
        .hero {
            position: relative; overflow: hidden;
            background:
                radial-gradient(ellipse 60% 50% at 85% 0%, color-mix(in srgb, var(--primary) 16%, transparent), transparent 70%),
                var(--bg);
            color: var(--text);
            padding: 100px 0 90px;
        }
        .hero .tri1 { top: -60px; right: 8%; border-width: 0 90px 150px 90px; border-color: transparent transparent color-mix(in srgb, var(--primary) 55%, transparent) transparent; transform: rotate(12deg); }
        .hero .tri2 { bottom: -40px; left: 4%; border-width: 0 60px 100px 60px; border-color: transparent transparent color-mix(in srgb, var(--primary) 30%, transparent) transparent; transform: rotate(-18deg); }
        .hero .tri3 { top: 30%; left: 46%; border-width: 0 24px 40px 24px; border-color: transparent transparent color-mix(in srgb, var(--primary) 70%, transparent) transparent; }
        .hero-inner { position: relative; z-index: 2; max-width: 720px; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            font-family: var(--font-accent), monospace;
            font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .12em;
            color: var(--primary); margin-bottom: 20px;
        }
        .eyebrow::before { content: ''; width: 0; height: 0; border-style: solid; border-width: 0 5px 8px 5px; border-color: transparent transparent var(--primary) transparent; }
        .hero h1 { font-size: clamp(2.2rem, 5.2vw, 3.6rem); line-height: 1.08; margin-bottom: 20px; }
        .hero h1 em { color: var(--primary); font-style: normal; }
        .hero p { font-size: 1.1rem; color: var(--muted); max-width: 54ch; margin-bottom: 32px; }
        .cta-row { display: flex; gap: 14px; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            padding: 14px 24px; border-radius: 10px; font-weight: 700; font-size: .95rem;
            text-decoration: none; cursor: pointer; border: 0;
        }
        .btn-primary { background: var(--primary); color: #04140d; }
        .btn-ghost { background: transparent; color: var(--text); border: 1px solid rgba(255,255,255,.22); }

        /* SECTIONS */
        .section { padding: 84px 0; }
        .section-head { max-width: 620px; margin: 0 auto 48px; text-align: center; }
        .section-head .eyebrow { justify-content: center; }
        .section-head h2 { font-size: clamp(1.7rem, 3.4vw, 2.3rem); margin-bottom: 12px; }
        .section-head p { color: var(--muted); font-size: 1.02rem; }

        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 20px; }
        .service-card {
            background: var(--bg-elevated); border: 1px solid var(--line); border-radius: 18px; padding: 28px 24px;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .service-card:hover { transform: translateY(-4px); box-shadow: 0 16px 36px color-mix(in srgb, var(--primary) 16%, transparent); border-color: color-mix(in srgb, var(--primary) 40%, var(--line)); }
        .service-card .tri-icon { width: 0; height: 0; border-style: solid; border-width: 0 14px 24px 14px; border-color: transparent transparent var(--primary) transparent; margin-bottom: 18px; }
        .service-card h3 { font-size: 1.1rem; margin-bottom: 8px; }
        .service-card p { color: var(--muted); font-size: .9rem; }

        .process { background: var(--bg-elevated); }
        .process-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 28px; counter-reset: step; }
        .process-step { position: relative; padding-left: 4px; }
        .process-step .num {
            display: inline-flex; align-items: center; justify-content: center;
            width: 40px; height: 40px; margin-bottom: 16px;
            background: color-mix(in srgb, var(--primary) 16%, transparent);
            border: 1px solid color-mix(in srgb, var(--primary) 40%, transparent);
            border-radius: 10px; color: var(--primary); font-weight: 700; font-family: var(--font-accent), monospace;
        }
        .process-step h3 { font-size: 1.02rem; margin-bottom: 6px; }
        .process-step p { color: var(--muted); font-size: .88rem; }

        .locations { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }
        .location-card { background: var(--bg-elevated); border: 1px solid var(--line); border-radius: 18px; padding: 26px; }
        .location-card .tag { display: inline-block; font-family: var(--font-accent), monospace; font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--primary); margin-bottom: 10px; }
        .location-card h3 { font-size: 1.1rem; margin-bottom: 6px; }
        .location-card p { color: var(--muted); font-size: .9rem; }

        .flyer-preview { max-width: 720px; margin: 0 auto; }
        .flyer-preview img { border-radius: 20px; border: 1px solid var(--line); box-shadow: 0 24px 60px rgba(0,0,0,.45); }

        .closing {
            background: linear-gradient(135deg, var(--primary), color-mix(in srgb, var(--primary) 55%, #04140d));
            color: #04140d; text-align: center; border-radius: 28px;
            padding: 56px 32px; position: relative; overflow: hidden;
        }
        .closing h2 { font-size: clamp(1.7rem, 3.6vw, 2.4rem); margin-bottom: 14px; }
        .closing p { color: color-mix(in srgb, #04140d 78%, transparent); margin-bottom: 26px; }
        .closing .btn-primary { background: #04140d; color: var(--primary); }
        .contact-line { margin-top: 22px; font-size: .92rem; color: color-mix(in srgb, #04140d 70%, transparent); }
        .contact-line a { text-decoration: underline; }

        footer { text-align: center; padding: 32px 20px; color: var(--muted); font-size: .85rem; border-top: 1px solid var(--line); }

        .expired-banner { background: #fff3e0; color: #9a3412; text-align: center; padding: 12px 16px; font-size: .9rem; font-weight: 600; }
        @media (max-width: 640px) {
            .hero { padding: 70px 0 60px; }
            .section { padding: 60px 0; }
            .network { width: 85%; opacity: .35; }
        }
    </style>
</head>
<body>
@if (!empty($previewMode))
<div style="background:#e65100;color:#fff;text-align:center;padding:14px 16px;font-weight:600;font-family:system-ui,sans-serif;position:sticky;top:0;z-index:100">
    ANTEPRIMA — Questa promo non è ancora visibile al pubblico
</div>
@elseif ($promo->isExpired())
<div class="expired-banner">Questa promozione è scaduta.</div>
@endif

<section class="hero">
    <svg class="network" viewBox="0 0 600 400" aria-hidden="true" focusable="false">
        <g stroke="var(--primary)" stroke-width="1" opacity=".4">
            <line x1="40" y1="60" x2="180" y2="120"></line>
            <line x1="180" y1="120" x2="320" y2="40"></line>
            <line x1="180" y1="120" x2="260" y2="220"></line>
            <line x1="260" y1="220" x2="420" y2="180"></line>
            <line x1="420" y1="180" x2="520" y2="80"></line>
            <line x1="320" y1="40" x2="420" y2="180"></line>
            <line x1="260" y1="220" x2="140" y2="300"></line>
            <line x1="420" y1="180" x2="540" y2="300"></line>
        </g>
        <g fill="var(--primary)">
            <circle cx="40" cy="60" r="3"></circle>
            <circle cx="180" cy="120" r="4"></circle>
            <circle cx="320" cy="40" r="3"></circle>
            <circle cx="260" cy="220" r="4"></circle>
            <circle cx="420" cy="180" r="3.5"></circle>
            <circle cx="520" cy="80" r="3"></circle>
            <circle cx="140" cy="300" r="3"></circle>
            <circle cx="540" cy="300" r="3"></circle>
        </g>
    </svg>
    <span class="tri tri--fill tri1"></span>
    <span class="tri tri--fill tri2"></span>
    <span class="tri tri--fill tri3"></span>
    <div class="wrap hero-inner">
        <span class="eyebrow">{{ $tenant->name }}</span>
        <h1>{!! preg_replace('/(con\s+M\s*3\.5\s*S\.R\.L\.)/i', '<em>$1</em>', e($promo->title)) !!}</h1>
        <p>{{ $tagline ?? 'Progettiamo e sviluppiamo soluzioni digitali su misura: siti web, applicazioni e piattaforme, con un approccio da ingegneri, non solo da agenzia.' }}</p>
        <div class="cta-row">
            <a class="btn btn-primary" href="{{ $tenant->phone ? 'tel:'.preg_replace('/\s+/', '', $tenant->phone) : '#contatti' }}">📞 Chiamaci ora</a>
            <a class="btn btn-ghost" href="#contatti">{{ $promo->cta_label ?? 'Richiedi un preventivo' }}</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Cosa facciamo</span>
            <h2>Un partner tecnico, non solo un fornitore</h2>
            <p>Dal primo schizzo al codice in produzione — restiamo con te oltre il lancio.</p>
        </div>
        <div class="services-grid">
            <div class="service-card">
                <span class="tri-icon"></span>
                <h3>Siti web</h3>
                <p>Design moderno, veloce, pensato per convertire — non solo per esistere online.</p>
            </div>
            <div class="service-card">
                <span class="tri-icon"></span>
                <h3>Applicazioni su misura</h3>
                <p>Piattaforme e strumenti costruiti attorno al tuo modo di lavorare, non al contrario.</p>
            </div>
            <div class="service-card">
                <span class="tri-icon"></span>
                <h3>Consulenza tecnologica</h3>
                <p>Scegliamo insieme la soluzione giusta, senza venderti complessità che non ti serve.</p>
            </div>
            <div class="service-card">
                <span class="tri-icon"></span>
                <h3>Manutenzione &amp; crescita</h3>
                <p>Il progetto non finisce al lancio: monitoriamo, aggiorniamo, miglioriamo nel tempo.</p>
            </div>
        </div>
    </div>
</section>

@if ($flyer)
<section class="section" style="padding-top:0">
    <div class="wrap flyer-preview">
        <img src="{{ $flyer }}" alt="{{ $promo->title }}" loading="lazy">
    </div>
</section>
@endif

<section class="section process">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Come lavoriamo</span>
            <h2>Un percorso chiaro, dall'inizio alla fine</h2>
            <p>Niente sorprese: sai sempre a che punto siamo.</p>
        </div>
        <div class="process-grid">
            <div class="process-step"><span class="num">1</span><h3>Ascolto</h3><p>Capiamo il tuo business prima ancora di parlare di tecnologia.</p></div>
            <div class="process-step"><span class="num">2</span><h3>Design</h3><p>Prototipi concreti, non solo idee — vedi il progetto prendere forma.</p></div>
            <div class="process-step"><span class="num">3</span><h3>Sviluppo</h3><p>Codice solido, testato, costruito per durare e crescere con te.</p></div>
            <div class="process-step"><span class="num">4</span><h3>Lancio &amp; oltre</h3><p>Andiamo online insieme, e restiamo al tuo fianco dopo.</p></div>
        </div>
    </div>
</section>

<section class="section">
    <div class="wrap">
        <div class="section-head">
            <span class="eyebrow">Dove siamo</span>
            <h2>Due sedi, un solo team</h2>
        </div>
        <div class="locations">
            <div class="location-card">
                <span class="tag">Sede legale</span>
                <h3>Senise</h3>
                <p>{{ $tenant->address ?? 'Via Soldato Belfi Giuseppe 11, Senise (PZ)' }}</p>
            </div>
            <div class="location-card">
                <span class="tag">Sede operativa</span>
                <h3>Roma</h3>
                <p>Press-Oil — Tecnopolo Tiburtino</p>
            </div>
        </div>
    </div>
</section>

<section class="section" id="contatti">
    <div class="wrap">
        <div class="closing">
            <h2>Parliamo del tuo progetto</h2>
            <p>{{ \Illuminate\Support\Str::limit(strip_tags($promo->description ?? ''), 180) }}</p>
            <a class="btn btn-primary" href="{{ $tenant->phone ? 'tel:'.preg_replace('/\s+/', '', $tenant->phone) : ($tenant->website ?? '#') }}">
                {{ $promo->cta_label ?? 'Richiedi un preventivo gratuito' }}
            </a>
            <div class="contact-line">
                @if ($tenant->phone)<a href="tel:{{ preg_replace('/\s+/', '', $tenant->phone) }}">{{ $tenant->phone }}</a>@endif
                @if ($tenant->website) · <a href="{{ $tenant->website }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', $tenant->website) }}</a>@endif
            </div>
        </div>
    </div>
</section>

<footer>{{ $tenant->name }} — {{ $tenant->address ?? 'Senise (PZ) · Roma' }}</footer>
</body>
</html>
