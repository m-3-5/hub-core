<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $service->title }} — {{ $tenant->name }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($service->description ?? $service->title), 160) }}">
    <meta property="og:title" content="{{ $service->title }} — {{ $tenant->name }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($service->description ?? $service->title), 160) }}">
    @if ($service->coverImageUrl())
        <meta property="og:image" content="{{ $service->coverImageUrl() }}">
    @endif
    <meta property="og:type" content="product">
    <meta property="og:site_name" content="{{ $tenant->name }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="{{ $tenant->primary_color }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,600;0,700&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: {{ $tenant->primary_color }};
            --primary-dark: color-mix(in srgb, var(--primary) 75%, #000);
            --text: #1f1a24;
            --muted: #5c5563;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Outfit', system-ui, sans-serif; color: var(--text); background: #fdf8fb; line-height: 1.6; }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 40px 24px 64px; }
        .breadcrumb { font-size: .85rem; color: var(--muted); margin-bottom: 24px; }
        .breadcrumb a { color: var(--primary-dark); text-decoration: none; }
        .layout { display: grid; grid-template-columns: 1.1fr 1fr; gap: 40px; align-items: start; }
        @media (max-width: 800px) { .layout { grid-template-columns: 1fr; } }
        .media { border-radius: 20px; overflow: hidden; background: linear-gradient(135deg, color-mix(in srgb, var(--primary) 18%, #fff), #fff); box-shadow: 0 20px 50px rgba(0,0,0,.08); }
        .media img { width: 100%; height: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; }
        .media--placeholder { aspect-ratio: 4/3; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: .9rem; }
        h1 { font-family: 'Cormorant Garamond', Georgia, serif; font-size: clamp(2rem, 5vw, 2.8rem); color: var(--primary-dark); margin-bottom: 12px; }
        .price { font-size: 2rem; font-weight: 700; color: var(--primary-dark); margin-bottom: 16px; }
        .description { color: var(--muted); font-size: 1.02rem; margin-bottom: 28px; white-space: pre-line; }
        .cta-group { display: flex; flex-wrap: wrap; gap: 12px; }
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: #fff; text-decoration: none; padding: 16px 32px; border-radius: 999px; font-weight: 700;
            display: inline-block;
        }
        .btn-whatsapp { background: #25D366; color: #fff; text-decoration: none; padding: 16px 24px; border-radius: 999px; font-weight: 600; display: inline-block; }
        .trust-note { margin-top: 20px; font-size: .85rem; color: var(--muted); }
        .back-link { display: inline-block; margin-top: 40px; color: var(--primary-dark); text-decoration: none; font-size: .9rem; }
    </style>
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $service->title,
        'description' => \Illuminate\Support\Str::limit(strip_tags($service->description ?? ''), 200),
        'image' => $service->coverImageUrl(),
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => strtoupper($service->currency),
            'price' => number_format($service->amount_cents / 100, 2, '.', ''),
            'availability' => 'https://schema.org/InStock',
            'url' => $service->payment_url,
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
</head>
<body>
<div class="wrap">
    @unless (!empty($embedMode))
        <p class="breadcrumb"><a href="{{ route('services.public.archive', $tenant) }}">{{ $tenant->name }}</a> · Servizi</p>
    @endunless

    <div class="layout">
        <div class="media">
            @if ($service->coverImageUrl())
                <img src="{{ $service->coverImageUrl() }}" alt="{{ $service->title }}">
            @else
                <div class="media--placeholder">{{ $tenant->name }}</div>
            @endif
        </div>
        <div>
            <h1>{{ $service->title }}</h1>
            <div class="price">{{ $service->amountEuros() }} €</div>
            @if ($service->description)
                <p class="description">{{ $service->description }}</p>
            @endif
            <div class="cta-group">
                <a class="btn-primary" href="{{ $service->payment_url }}" target="_top">Prenota e paga ora</a>
                @if ($tenant->settings['whatsapp'] ?? null)
                    <a class="btn-whatsapp" target="_blank" rel="noopener"
                       href="https://wa.me/{{ $tenant->settings['whatsapp'] }}?text={{ rawurlencode('Ciao! Vorrei info su «'.$service->title.'» ('.$service->amountEuros().' €)') }}">WhatsApp</a>
                @endif
            </div>
            <p class="trust-note">Pagamento sicuro tramite Stripe · {{ $tenant->name }}</p>
        </div>
    </div>

    @unless (!empty($embedMode))
        <a class="back-link" href="{{ route('services.public.archive', $tenant) }}">← Tutti i servizi di {{ $tenant->name }}</a>
    @endunless
</div>
</body>
</html>
