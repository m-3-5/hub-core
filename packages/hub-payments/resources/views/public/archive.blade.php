<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Servizi — {{ $tenant->name }}</title>
    <meta name="description" content="Trattamenti e servizi prenotabili online di {{ $tenant->name }}.">
    <meta name="theme-color" content="{{ $tenant->primary_color }}">
    <link rel="canonical" href="{{ url()->current() }}">
    @php $ogImage = optional($services->first(fn ($s) => $s->coverImageUrl()))?->coverImageUrl() ?? asset('images/og-hub-core.png'); @endphp
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $tenant->name }}">
    <meta property="og:title" content="Servizi — {{ $tenant->name }}">
    <meta property="og:description" content="Trattamenti e servizi prenotabili online di {{ $tenant->name }}.">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="it_IT">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Servizi — {{ $tenant->name }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <style>
        :root { --primary: {{ $tenant->primary_color }}; --text: #1f1a24; --muted: #5c5563; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; color: var(--text); background: #faf8fb; line-height: 1.5; }
        .wrap { max-width: 1000px; margin: 0 auto; padding: 32px 20px 48px; }
        h1 { font-size: clamp(1.8rem, 4vw, 2.4rem); margin-bottom: 8px; }
        .lead { color: var(--muted); margin-bottom: 28px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
        .empty { color: var(--muted); font-size: .95rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Servizi — {{ $tenant->name }}</h1>
    <p class="lead">Scegli un trattamento e prenota pagando subito online.</p>

    @if ($services->isEmpty())
        <p class="empty">Nessun servizio disponibile al momento.</p>
    @else
        <div class="grid">
            @foreach ($services as $service)
                @include('hub-payments::public._card', ['service' => $service, 'tenant' => $tenant])
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
