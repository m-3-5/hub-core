<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Servizi e prodotti — {{ $tenant->name }}</title>
    <meta name="description" content="Servizi e prodotti acquistabili online di {{ $tenant->name }}.">
    <meta name="theme-color" content="{{ $tenant->primary_color }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @php $ogImage = optional($services->first(fn ($s) => $s->coverImageUrl()))?->coverImageUrl() ?? asset('images/og-hub-core.png'); @endphp
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $tenant->name }}">
    <meta property="og:title" content="Servizi e prodotti — {{ $tenant->name }}">
    <meta property="og:description" content="Servizi e prodotti acquistabili online di {{ $tenant->name }}.">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="it_IT">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Servizi e prodotti — {{ $tenant->name }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <style>
        :root { --primary: {{ $tenant->primary_color }}; --text: #1f1a24; --muted: #5c5563; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; color: var(--text); background: #faf8fb; line-height: 1.5; }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 32px 20px 48px; }
        h1 { font-size: clamp(1.8rem, 4vw, 2.4rem); margin-bottom: 8px; }
        .lead { color: var(--muted); margin-bottom: 28px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
        .section-title { font-family: 'Cormorant Garamond', Georgia, serif; font-size: 1.6rem; margin: 8px 0 4px; }
        .empty { color: var(--muted); font-size: .95rem; }
    </style>
    @isset($jsonLd)
    <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endisset
</head>
<body>
<div class="wrap">
    <h1>{{ $tenant->name }}</h1>

    @if ($services->isEmpty() && $products->isEmpty())
        <p class="empty">Nessun servizio o prodotto disponibile al momento.</p>
    @endif

    @if ($services->isNotEmpty())
        <section id="servizi">
            <h2 class="section-title">Servizi</h2>
            <p class="lead">Scegli un trattamento e prenota pagando subito online.</p>
            <div class="grid">
                @foreach ($services as $service)
                    @include('hub-payments::public._card', ['service' => $service, 'tenant' => $tenant])
                @endforeach
            </div>
        </section>
    @endif

    @if ($products->isNotEmpty())
        <section id="prodotti" style="margin-top:36px">
            <h2 class="section-title">Prodotti</h2>
            <p class="lead">Acquista online, pagamento sicuro.</p>
            <div class="grid">
                @foreach ($products as $service)
                    @include('hub-payments::public._card', ['service' => $service, 'tenant' => $tenant])
                @endforeach
            </div>
        </section>
    @endif
</div>
</body>
</html>
