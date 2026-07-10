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
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $promo->seo_title ?? $promo->title }}">
    <meta name="twitter:description" content="{{ $promo->seo_description }}">
    <meta name="twitter:image" content="{{ $promo->variantUrl('og') }}">
    <meta property="og:url" content="{{ $promo->publicUrl() }}">
    <meta property="og:locale" content="it_IT">
    <link rel="canonical" href="{{ $promo->publicUrl() }}">
    <meta name="theme-color" content="{{ $tenant->primary_color }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700&family=Great+Vibes&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    @php
        $heroVisual = $promo->variantUrl('hero');
        $flyerUrl = $promo->variantUrl('flyer');
        $decorImages = $decorImages ?? $promo->decorImages();
        $shareLinks = $shareLinks ?? \App\Support\PromoShareLinks::for($promo);
        $isExpiredPromo = $isExpiredPromo ?? $promo->isExpired();
        $expiryLabel = $promo->expiryLabel();
    @endphp
    <style>
        :root {
            --primary: {{ $tenant->primary_color }};
            --primary-dark: color-mix(in srgb, var(--primary) 75%, #000);
            --text: #1f1a24;
            --muted: #5c5563;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', system-ui, sans-serif; color: var(--text); background: #fdf8fb; line-height: 1.65; }
        .promo-hero {
            position: relative;
            min-height: 88vh;
            display: grid;
            align-items: center;
            overflow: hidden;
        }
        .promo-hero__visual { position: absolute; inset: 0; background: var(--primary) center/cover; }
        .promo-hero__visual img { width: 100%; height: 100%; object-fit: cover; }
        .promo-hero__overlay {
            position: absolute; inset: 0;
            background: linear-gradient(105deg, rgba(0,0,0,.78) 0%, rgba(0,0,0,.5) 50%, rgba(0,0,0,.25) 100%);
        }
        .promo-hero__grid {
            position: relative; z-index: 2;
            max-width: 1180px; margin: 0 auto; padding: 48px 24px;
            display: grid; grid-template-columns: 0.95fr 1.15fr; gap: 32px; align-items: center;
        }
        @media (min-width: 901px) {
            .flyer-card { padding: 20px; }
            .flyer-card img { max-height: 580px; }
        }
        @media (max-width: 900px) {
            .promo-hero__grid { grid-template-columns: 1fr; }
            .flyer-card img { max-height: 420px; }
        }
        .promo-hero__text { color: #fff; }
        .promo-badge {
            display: inline-block; background: rgba(255,255,255,.18);
            border: 1px solid rgba(255,255,255,.35); padding: 8px 16px;
            border-radius: 999px; font-size: 12px; font-weight: 600;
            letter-spacing: .08em; text-transform: uppercase; margin-bottom: 16px;
        }
        .promo-hero h1 {
            font-family: 'Cormorant Garamond', Georgia, serif;
            font-size: clamp(2.2rem, 5vw, 3.6rem); line-height: 1.05; margin-bottom: 16px;
        }
        .promo-hero__lead { font-size: 1.1rem; opacity: .94; max-width: 46ch; margin-bottom: 20px; }
        .hero-icons { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
        .hero-icon {
            display: flex; align-items: center; gap: 10px;
            background: rgba(255,255,255,.14); padding: 10px 16px; border-radius: 999px;
            font-size: 13px; font-weight: 500;
            border: 1px solid rgba(255,255,255,.2);
        }
        .hero-icon img {
            width: 36px; height: 36px; border-radius: 50%; object-fit: cover;
            flex-shrink: 0; border: 2px solid rgba(255,255,255,.5);
        }
        .flyer-card {
            background: #fff; border-radius: 20px; padding: 16px;
            box-shadow: 0 24px 60px rgba(0,0,0,.35);
        }
        .flyer-card img { width: 100%; height: auto; display: block; border-radius: 12px; object-fit: contain; }
        .flyer-card p { text-align: center; font-size: 13px; color: var(--muted); margin-top: 12px; }
        .section { max-width: 1100px; margin: 0 auto; padding: 56px 24px; }
        .section--alt { background: #fff; border-radius: 32px 32px 0 0; margin-top: -24px; position: relative; }
        .section h2 {
            font-family: 'Cormorant Garamond', serif;
            font-size: clamp(1.8rem, 4vw, 2.4rem);
            color: var(--primary-dark); margin-bottom: 12px;
        }
        .section .intro { color: var(--muted); max-width: 65ch; margin-bottom: 32px; font-size: 1.05rem; }
        .offers-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px; }
        .offer-card {
            background: #fdf8fb; border-radius: 20px; padding: 28px 24px;
            border: 1px solid color-mix(in srgb, var(--primary) 20%, transparent);
            position: relative; overflow: hidden;
        }
        .offer-card__visual {
            width: 100%; aspect-ratio: 4/3; border-radius: 14px;
            object-fit: cover; margin-bottom: 18px; display: block;
            background: color-mix(in srgb, var(--primary) 12%, #fff);
        }
        .offer-card__placeholder {
            width: 100%; aspect-ratio: 4/3; border-radius: 14px; margin-bottom: 18px;
            background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 20%, #fff), #fff);
        }
        .themes-row img {
            width: 72px; height: 72px; border-radius: 50%; object-fit: cover;
            margin: 0 auto 12px; display: block; border: 3px solid color-mix(in srgb, var(--primary) 25%, transparent);
        }
        .deco-img {
            position: absolute; border-radius: 20px; object-fit: cover;
            opacity: .22; pointer-events: none; box-shadow: 0 12px 40px rgba(0,0,0,.15);
        }
        .deco-img--1 { top: 80px; right: 3%; width: 160px; height: 160px; transform: rotate(6deg); }
        .deco-img--2 { bottom: 40px; left: 2%; width: 130px; height: 130px; transform: rotate(-8deg); }
        .offer-card h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.65rem; color: var(--primary); margin-bottom: 8px; }
        .offer-card .price { font-size: 2.1rem; font-weight: 700; color: var(--primary-dark); margin: 8px 0; }
        .offer-card p { color: var(--muted); font-size: .98rem; }
        .steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-top: 8px; }
        .step {
            padding: 24px; background: #fdf8fb; border-radius: 16px;
            border-left: 4px solid var(--primary);
        }
        .step strong { display: block; color: var(--primary); margin-bottom: 8px; font-size: 1.05rem; }
        .step span { color: var(--muted); font-size: .95rem; }
        .themes-row {
            display: flex; flex-wrap: wrap; justify-content: center; gap: 32px;
            padding: 32px 0; color: var(--primary);
        }
        .themes-row .theme-item { text-align: center; max-width: 100px; }
        .themes-row small { font-size: 12px; color: var(--muted); display: block; font-weight: 500; }
        .contact-card {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; border-radius: 24px; padding: 40px 32px; text-align: center;
        }
        .contact-card h2 {
            font-family: 'Great Vibes', 'Brush Script MT', cursive;
            font-size: clamp(2.8rem, 7vw, 4.2rem);
            font-weight: 400;
            color: #fff;
            line-height: 1.15;
            margin-bottom: 12px;
            letter-spacing: 0.02em;
            text-shadow: 0 2px 12px rgba(0,0,0,.15);
        }
        .contact-card .contact-tagline {
            font-family: 'Outfit', system-ui, sans-serif;
            font-size: 1.05rem;
            opacity: .95;
            margin-bottom: 12px;
        }
        .contact-card p { opacity: .92; margin-bottom: 8px; color: #fff; }
        .contact-card a { color: #fff; font-weight: 600; }
        .cta-bar {
            position: sticky; bottom: 0; background: rgba(255,255,255,.96);
            backdrop-filter: blur(12px); border-top: 1px solid rgba(0,0,0,.06);
            padding: 16px 24px; z-index: 10;
        }
        .cta-bar__inner {
            max-width: 1100px; margin: 0 auto;
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 16px;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; text-decoration: none; padding: 16px 32px; border-radius: 999px; font-weight: 700;
        }
        .btn-outline {
            background: #fff; color: var(--primary-dark); text-decoration: none; padding: 14px 24px;
            border-radius: 999px; font-weight: 600; border: 2px solid var(--primary);
        }
        .btn-whatsapp {
            background: #25D366; color: #fff; text-decoration: none; padding: 14px 24px;
            border-radius: 999px; font-weight: 600;
        }
        .btn-website {
            background: rgba(255,255,255,.18); color: #fff; text-decoration: none; padding: 14px 24px;
            border-radius: 999px; font-weight: 600; border: 2px solid rgba(255,255,255,.55);
        }
        .btn-soon {
            display: inline-block; padding: 14px 24px; border-radius: 999px; font-weight: 600;
            background: rgba(255,255,255,.12); color: rgba(255,255,255,.65); border: 2px dashed rgba(255,255,255,.35);
            font-size: .95rem; cursor: not-allowed;
        }
        .contact-actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 24px; }
        .cta-bar__actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .expiry-pill {
            display: inline-block; margin-bottom: 12px; padding: 6px 14px; border-radius: 999px;
            font-size: .78rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
        }
        .expiry-pill--active { background: rgba(255,255,255,.2); border: 1px solid rgba(255,255,255,.45); color: #fff; }
        .expiry-pill--expired { background: #fef3c7; color: #92400e; }
        .expired-banner {
            background: #fef3c7; color: #92400e; text-align: center; padding: 12px 16px;
            font-weight: 600; font-size: .92rem;
        }
        .share-bar {
            display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px;
        }
        .share-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 999px; font-size: .82rem; font-weight: 600;
            text-decoration: none; border: 1px solid rgba(255,255,255,.35); color: #fff;
            background: rgba(255,255,255,.12);
        }
        .share-btn:hover { background: rgba(255,255,255,.22); }
        .deco-img--2 { bottom: 40px; left: 2%; width: 130px; height: 130px; transform: rotate(-8deg); }
    </style>
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'LocalBusiness',
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
</head>
<body>
@if (!empty($previewMode))
<div style="background:#e65100;color:#fff;text-align:center;padding:14px 16px;font-weight:600;font-family:system-ui,sans-serif;position:sticky;top:0;z-index:100">
    ANTEPRIMA — Questa promo non è ancora visibile al pubblico
</div>
@elseif ($isExpiredPromo)
<div class="expired-banner">Questa promozione è scaduta — consulta le <a href="{{ route('promo.archive', $tenant) }}" style="color:#92400e">offerte attive</a></div>
@endif
<div class="promo-page">
    <header class="promo-hero">
        <div class="promo-hero__visual">
            @if ($heroVisual)<img src="{{ $heroVisual }}" alt="">@endif
        </div>
        <div class="promo-hero__overlay"></div>
        <div class="promo-hero__grid">
            <div class="promo-hero__text">
                <span class="promo-badge">{{ $tenant->name }} · {{ $isExpiredPromo ? 'Promo archivio' : 'Promo attiva' }}</span>
                @if ($expiryLabel)
                    <span class="expiry-pill {{ $isExpiredPromo ? 'expiry-pill--expired' : 'expiry-pill--active' }}">{{ $expiryLabel }}</span>
                @endif
                <h1>{{ $promo->title }}</h1>
                @if ($promo->description)
                    <p class="promo-hero__lead">{{ $promo->description }}</p>
                @endif
                @if (count($decorImages) > 0)
                    <div class="hero-icons">
                        @foreach (array_slice($decorImages, 0, 3) as $decor)
                            <span class="hero-icon">
                                <img src="{{ $decor['url'] }}" alt="{{ $decor['label'] }}">
                                {{ $decor['label'] }}
                            </span>
                        @endforeach
                    </div>
                @endif
                <div class="share-bar" aria-label="Condividi promo">
                    <a class="share-btn" href="{{ $shareLinks['whatsapp'] }}" target="_blank" rel="noopener">WhatsApp</a>
                    <a class="share-btn" href="{{ $shareLinks['facebook'] }}" target="_blank" rel="noopener">Facebook</a>
                    <a class="share-btn" href="{{ $shareLinks['twitter'] }}" target="_blank" rel="noopener">X</a>
                    <button type="button" class="share-btn" data-copy="{{ $shareLinks['copy'] }}">Copia link</button>
                </div>
            </div>
            @if ($flyerUrl)
                <div class="flyer-card">
                    <img src="{{ $flyerUrl }}" alt="Volantino {{ $promo->title }}">
                    <p>Volantino originale · consulta tutti i dettagli</p>
                </div>
            @endif
        </div>
    </header>

    <section class="section section--alt">
        <h2>Le nostre offerte</h2>
        <p class="intro">
            {{ $tenant->name }} ti propone trattamenti selezionati per valorizzare il tuo look e il benessere del corpo.
            Prenota ora per approfittare delle condizioni promozionali: posti limitati e promozione
            {{ $promo->always_active ? 'sempre valida' : 'per tempo limitato' }}.
        </p>

        @if ($promo->offers)
            <div class="offers-grid">
                @foreach ($promo->offers as $index => $offer)
                    @php $decorUrl = $promo->decorUrlForOffer($index); @endphp
                    <article class="offer-card">
                        @if ($decorUrl)
                            <img class="offer-card__visual" src="{{ $decorUrl }}" alt="{{ $offer['name'] ?? 'Offerta' }}">
                        @else
                            <div class="offer-card__placeholder" aria-hidden="true"></div>
                        @endif
                        <h3>{{ $offer['name'] ?? 'Offerta speciale' }}</h3>
                        @if (!empty($offer['price']))
                            <div class="price">{{ $offer['price'] }}</div>
                        @endif
                        @if (!empty($offer['detail']))
                            <p>{{ $offer['detail'] }}</p>
                        @else
                            <p>Trattamento professionale con prodotti selezionati e consulenza personalizzata.</p>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif

        @if (count($decorImages) > 0)
            <div class="themes-row" aria-hidden="true">
                @foreach ($decorImages as $decor)
                    <div class="theme-item">
                        <img src="{{ $decor['url'] }}" alt="">
                        <small>{{ $decor['label'] }}</small>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    <section class="section" style="position:relative">
        @if (count($decorImages) >= 2)
            <img class="deco-img deco-img--1" src="{{ $decorImages[0]['url'] }}" alt="" aria-hidden="true">
            <img class="deco-img deco-img--2" src="{{ $decorImages[1]['url'] }}" alt="" aria-hidden="true">
        @endif

        <h2>Come prenotare</h2>
        <p class="intro">
            Siamo a {{ $tenant->address ?? 'Senise' }}. Contattaci per fissare il tuo appuntamento
            o passa in salone negli orari di apertura. Il team di {{ $tenant->name }} è a disposizione
            per consigliarti il trattamento più adatto a te.
        </p>
        @php
            $offerNames = collect($promo->offers ?? [])->pluck('name')->filter()->implode(', ');
        @endphp
        <div class="steps">
            <div class="step">
                <strong>1. Scegli l'offerta</strong>
                <span>{{ $offerNames ?: 'Indica cosa desideri' }}: fatti sentire al telefono o via sito.</span>
            </div>
            <div class="step">
                <strong>2. Prenota</strong>
                <span>Chiama {{ $tenant->phone ?? 'il centro' }} o compila il form contatti sul sito ufficiale.</span>
            </div>
            <div class="step">
                <strong>3. Vieni in salone</strong>
                <span>Ti accogliamo in {{ $tenant->address ?? 'salone' }} e iniziamo il tuo percorso di bellezza.</span>
            </div>
        </div>
    </section>

    <section class="section" style="padding-top:0">
        @php
            $landingLinks = \App\Support\PromoLinks::forLanding($tenant, $promo);
            $whatsappLink = collect($landingLinks)->firstWhere('key', 'whatsapp');
        @endphp
        <div class="contact-card">
            <h2>{{ $tenant->name }}</h2>
            <p class="contact-tagline">Il tuo corpo, la nostra immagine.</p>
            @if ($tenant->address)<p>{{ $tenant->address }}</p>@endif
            @if ($tenant->phone)<p>Tel. {{ $tenant->phone }}</p>@endif
            <div class="contact-actions">
                @foreach ($landingLinks as $link)
                    @if (!empty($link['disabled']))
                        <span class="btn-soon" title="Agenda prenotazioni in arrivo">{{ $link['label'] }} — prossimamente</span>
                    @elseif ($link['key'] === 'whatsapp')
                        <a class="btn-whatsapp" href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
                    @elseif ($link['key'] === 'website')
                        <a class="btn-website" href="{{ $link['url'] }}" target="_blank" rel="noopener">{{ $link['label'] }}</a>
                    @elseif ($link['key'] === 'book')
                        <a class="btn-primary" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                    @else
                        <a class="btn-website" href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    @php
        $promoLinks = $landingLinks;
    @endphp
    <div class="cta-bar">
        <div class="cta-bar__inner">
            <div>
                <strong>{{ $promo->title }}</strong>
                <span style="color:var(--muted);font-size:.9rem"> · {{ $promo->always_active ? 'Sempre attiva' : 'Offerta limitata' }}</span>
            </div>
            <div class="cta-bar__actions">
                @foreach ($promoLinks as $link)
                    @if (!empty($link['disabled']))
                        @continue
                    @endif
                    @php
                        $class = match ($link['key']) {
                            'whatsapp' => 'btn-whatsapp',
                            'all_promos', 'website' => 'btn-outline',
                            default => 'btn-primary',
                        };
                    @endphp
                    <a class="{{ $class }}" href="{{ $link['url'] }}" @if(in_array($link['key'], ['whatsapp', 'website'], true)) target="_blank" rel="noopener" @endif>{{ $link['label'] }}</a>
                @endforeach
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var url = btn.getAttribute('data-copy');
        if (navigator.clipboard && url) {
            navigator.clipboard.writeText(url).then(function () {
                btn.textContent = 'Link copiato!';
                setTimeout(function () { btn.textContent = 'Copia link'; }, 2000);
            });
        }
    });
});
</script>
</body>
</html>
