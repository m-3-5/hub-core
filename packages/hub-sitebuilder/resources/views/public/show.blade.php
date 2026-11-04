<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $answers = $site->answers ?? [];
        $tagline = $answers['tagline'] ?? $tenant->name;
        $services = $answers['services'] ?? [];
        $ctaLabel = $answers['cta_label'] ?? 'Contattaci';
        $extra = $answers['extra'] ?? null;
        $brandFontKey = \App\Support\BrandFonts::keyForTenant($tenant);
        $brandFont = \App\Support\BrandFonts::get($brandFontKey);
        $heroVisual = $site->hero_video_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($site->hero_video_path)
            : ($site->hero_svg_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($site->hero_svg_path) : null);
        $tel = $tenant->phone ? preg_replace('/[^0-9+]/', '', $tenant->phone) : null;
        // WhatsApp solo per i cellulari italiani (3xx) o numeri già con prefisso internazionale.
        $waNumber = $tel ? ltrim(preg_match('/^3\d{8,9}$/', $tel) ? '39'.$tel : (str_starts_with($tel, '+') ? $tel : ''), '+') : '';
        $waUrl = $waNumber ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode('Ciao '.$tenant->name.', vi scrivo dal vostro sito') : null;
        $ctaUrl = $waUrl && str_contains(strtolower($ctaLabel), 'whatsapp') ? $waUrl : ($tel ? 'tel:'.$tel : '#contatti');
        $hasContacts = $tel || $tenant->address;
    @endphp
    <title>{{ $tenant->name }} — {{ $tagline }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($tagline, 160) }}">
    <link rel="canonical" href="{{ route('site.public.show', $tenant) }}">
    <meta property="og:title" content="{{ $tenant->name }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($tagline, 160) }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="it_IT">
    <meta property="og:url" content="{{ route('site.public.show', $tenant) }}">
    <meta property="og:image" content="{{ asset('images/og-hub-core.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $tenant->name }}">
    <meta name="twitter:image" content="{{ asset('images/og-hub-core.png') }}">
    <meta name="theme-color" content="{{ $tenant->primary_color }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?{{ $brandFont['google_fonts'] }}&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: {{ $tenant->primary_color }};
            --ink: #14161a;
            --paper: #ffffff;
            --muted: #5b6169;
            --line: #ecece8;
            --font-display: {!! $brandFont['display'] !!};
            --font-body: {!! $brandFont['body'] !!};
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-body), system-ui, sans-serif; color: var(--ink); background: var(--paper); line-height: 1.6; }
        img { max-width: 100%; display: block; }
        .wrap { max-width: 1080px; margin: 0 auto; padding: 0 16px; }
        @media (min-width: 700px) { .wrap { padding: 0 24px; } }
        h1, h2 { font-family: var(--font-display), sans-serif; letter-spacing: -.01em; text-wrap: balance; }
        a { color: inherit; }

        .hero { position: relative; min-height: 70vh; display: flex; align-items: center; overflow: hidden; color: #fff; background: var(--ink); }
        .hero-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .85; }
        .hero-scrim { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.35), rgba(0,0,0,.6)); }
        .hero-inner { position: relative; z-index: 2; max-width: 680px; padding: 56px 16px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 10px; }
        .hero h1 { font-size: clamp(2rem, 5vw, 3.2rem); line-height: 1.12; margin-bottom: 22px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 52px; padding: 14px 26px; border-radius: 10px; font-weight: 700; font-size: .95rem; text-decoration: none; background: var(--primary); color: #fff; }

        .section { padding: 70px 0; }
        .section h2 { font-size: clamp(1.5rem, 3vw, 2rem); margin-bottom: 32px; text-align: center; }
        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; }
        .service-card { background: #fafaf9; border: 1px solid var(--line); border-radius: 16px; padding: 24px; text-align: center; font-weight: 600; }

        .extra { background: #fafaf9; }
        .extra p { max-width: 680px; margin: 0 auto; color: var(--muted); text-align: center; font-size: 1.05rem; }

        .contact { text-align: center; }
        .contact p { color: var(--muted); margin: 6px 0; }
        .contact .btns { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 14px; }
        .btn.wa { background: #1fa855; }
        .btn.light { background: rgba(255,255,255,.16); border: 1px solid rgba(255,255,255,.5); }
        .extra h2 { margin-bottom: 16px; }
        .section { padding: 56px 0; }
        @media (min-width: 700px) { .section { padding: 70px 0; } }

        footer { text-align: center; padding: 30px 20px; color: var(--muted); font-size: .82rem; }
        footer a { color: var(--primary); font-weight: 600; text-decoration: none; }
    </style>
@include('layouts.partials.ui')
</head>
<body>

<section class="hero">
    @if ($heroVisual)
        <img class="hero-bg" src="{{ $heroVisual }}" alt="">
    @endif
    <div class="hero-scrim"></div>
    <div class="wrap hero-inner">
        <h1>{{ $tagline }}</h1>
        <div class="hero-actions">
            @if ($hasContacts)<a class="btn" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a>@endif
            @if ($waUrl && $ctaUrl !== $waUrl)<a class="btn light" href="{{ $waUrl }}" target="_blank" rel="noopener">WhatsApp</a>@endif
        </div>
    </div>
</section>

@if (count($services))
<section class="section">
    <div class="wrap">
        <h2>Cosa offriamo</h2>
        <div class="services-grid">
            @foreach ($services as $service)
                <div class="service-card">{{ $service }}</div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($extra)
<section class="section extra">
    <div class="wrap"><h2>Chi siamo</h2><p>{{ $extra }}</p></div>
</section>
@endif

@if ($hasContacts)
<section class="section contact" id="contatti">
    <div class="wrap">
        <h2>Contattaci</h2>
        @if ($tel)<p><a href="tel:{{ $tel }}">{{ $tenant->phone }}</a></p>@endif
        @if ($tenant->address)<p>{{ $tenant->address }}</p>@endif
        <div class="btns">
            @if ($tel)<a class="btn" href="tel:{{ $tel }}">Chiama</a>@endif
            @if ($waUrl)<a class="btn wa" href="{{ $waUrl }}" target="_blank" rel="noopener">WhatsApp</a>@endif
            @if ($tenant->address)<a class="btn" style="background:var(--ink)" href="https://www.google.com/maps/search/?api=1&query={{ rawurlencode($tenant->address) }}" target="_blank" rel="noopener">Come arrivare</a>@endif
        </div>
    </div>
</section>
@endif

@include('layouts.partials.share-bar', ['shareTitle' => $tenant->name, 'shareUrl' => route('site.public.show', $tenant), 'shareSection' => true, 'shareLabel' => 'Condividi questo sito'])
<footer>{{ $tenant->name }} · sito creato con <a href="{{ url('/') }}">Max</a></footer>
</body>
</html>
