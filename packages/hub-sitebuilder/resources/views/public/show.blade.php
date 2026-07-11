<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $answers = $site->answers ?? [];
        $tagline = $answers['tagline'] ?? $tenant->name;
        $services = $answers['services'] ?? [];
        $ctaLabel = $answers['cta_label'] ?? 'Contattaci';
        $extra = $answers['extra'] ?? null;
        $brandFontKey = $tenant->settings['brand']['font'] ?? \App\Support\BrandFonts::default();
        $brandFont = \App\Support\BrandFonts::get($brandFontKey);
        $heroVisual = $site->hero_video_path
            ? \Illuminate\Support\Facades\Storage::disk('public')->url($site->hero_video_path)
            : ($site->hero_svg_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($site->hero_svg_path) : null);
    @endphp
    <title>{{ $tenant->name }} — {{ $tagline }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($tagline, 160) }}">
    <link rel="canonical" href="{{ route('site.public.show', $tenant) }}">
    <meta property="og:title" content="{{ $tenant->name }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($tagline, 160) }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="it_IT">
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
        .wrap { max-width: 1080px; margin: 0 auto; padding: 0 24px; }
        h1, h2 { font-family: var(--font-display), sans-serif; letter-spacing: -.01em; text-wrap: balance; }
        a { color: inherit; }

        .hero { position: relative; min-height: 78vh; display: flex; align-items: center; overflow: hidden; color: #fff; background: var(--ink); }
        .hero-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .85; }
        .hero-scrim { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.35), rgba(0,0,0,.6)); }
        .hero-inner { position: relative; z-index: 2; max-width: 680px; padding: 60px 24px; }
        .hero h1 { font-size: clamp(2rem, 5vw, 3.2rem); line-height: 1.12; margin-bottom: 22px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 14px 26px; border-radius: 10px; font-weight: 700; font-size: .95rem; text-decoration: none; background: var(--primary); color: #fff; }

        .section { padding: 70px 0; }
        .section h2 { font-size: clamp(1.5rem, 3vw, 2rem); margin-bottom: 32px; text-align: center; }
        .services-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; }
        .service-card { background: #fafaf9; border: 1px solid var(--line); border-radius: 16px; padding: 24px; text-align: center; font-weight: 600; }

        .extra { background: #fafaf9; }
        .extra p { max-width: 680px; margin: 0 auto; color: var(--muted); text-align: center; font-size: 1.05rem; }

        .contact { text-align: center; }
        .contact p { color: var(--muted); margin: 6px 0; }

        footer { text-align: center; padding: 30px 20px; color: var(--muted); font-size: .82rem; }
        footer a { color: var(--primary); font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>

<section class="hero">
    @if ($heroVisual)
        <img class="hero-bg" src="{{ $heroVisual }}" alt="">
    @endif
    <div class="hero-scrim"></div>
    <div class="wrap hero-inner">
        <h1>{{ $tagline }}</h1>
        <a class="btn" href="{{ $tenant->phone ? 'tel:'.preg_replace('/\s+/', '', $tenant->phone) : '#contatti' }}">{{ $ctaLabel }}</a>
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
    <div class="wrap"><p>{{ $extra }}</p></div>
</section>
@endif

<section class="section contact" id="contatti">
    <div class="wrap">
        <h2>Contattaci</h2>
        @if ($tenant->phone)<p><a href="tel:{{ preg_replace('/\s+/', '', $tenant->phone) }}">{{ $tenant->phone }}</a></p>@endif
        @if ($tenant->address)<p>{{ $tenant->address }}</p>@endif
    </div>
</section>

<footer>{{ $tenant->name }} · sito creato con <a href="{{ url('/') }}">Max</a></footer>
</body>
</html>
