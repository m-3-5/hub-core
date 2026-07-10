<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Promozioni — {{ $tenant->name }}</title>
    <meta name="description" content="Promozioni attive e archivio offerte di {{ $tenant->name }}.">
    <meta name="theme-color" content="{{ $tenant->primary_color }}">
    <link rel="canonical" href="{{ route('promo.archive', $tenant) }}">
    @php $ogImage = optional($active->first())->variantUrl('og') ?? asset('images/og-hub-core.png'); @endphp
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $tenant->name }}">
    <meta property="og:title" content="Promozioni — {{ $tenant->name }}">
    <meta property="og:description" content="Promozioni attive e archivio offerte di {{ $tenant->name }}.">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:url" content="{{ route('promo.archive', $tenant) }}">
    <meta property="og:locale" content="it_IT">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Promozioni — {{ $tenant->name }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    <style>
        :root { --primary: {{ $tenant->primary_color }}; --text: #1f1a24; --muted: #5c5563; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; color: var(--text); background: #faf8fb; line-height: 1.5; }
        .wrap { max-width: 960px; margin: 0 auto; padding: 32px 20px 48px; }
        h1 { font-size: clamp(1.8rem, 4vw, 2.4rem); margin-bottom: 8px; }
        .lead { color: var(--muted); margin-bottom: 28px; }
        h2 { font-size: 1.2rem; margin: 28px 0 14px; color: var(--primary); }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
        .promo-card { background: #fff; border-radius: 14px; overflow: hidden; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 8px 24px rgba(0,0,0,.07); transition: transform .15s; }
        .promo-card:hover { transform: translateY(-2px); }
        .promo-card--expired { opacity: .72; box-shadow: 0 4px 14px rgba(0,0,0,.05); filter: grayscale(.15); }
        .promo-card__media { display: block; background: linear-gradient(180deg, #fdf8fb, #fff); padding: 12px 14px 8px; }
        .promo-card__media img { width: 100%; height: auto; max-height: 150px; object-fit: contain; display: block; margin: 0 auto; }
        .promo-card__body { padding: 14px 16px 16px; }
        .promo-card__badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 4px 10px; border-radius: 999px; margin-bottom: 8px; }
        .promo-card__badge--active { background: color-mix(in srgb, var(--primary) 14%, #fff); color: var(--primary); }
        .promo-card__badge--expired { background: #f1f5f9; color: #64748b; }
        .promo-card h3 { font-size: 1.05rem; margin-bottom: 6px; }
        .promo-card h3 a { color: inherit; text-decoration: none; }
        .promo-card p { color: var(--muted); font-size: .88rem; margin-bottom: 12px; }
        .promo-card__cta { display: inline-block; background: var(--primary); color: #fff !important; text-decoration: none; padding: 8px 14px; border-radius: 999px; font-size: .82rem; font-weight: 600; }
        .promo-card--expired .promo-card__cta { background: #94a3b8; }
        .empty { color: var(--muted); font-size: .95rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Promozioni — {{ $tenant->name }}</h1>
    <p class="lead">Offerte attive e archivio promozioni passate.</p>

    <h2>Promo attive ({{ $active->count() }})</h2>
    @if ($active->isEmpty())
        <p class="empty">Nessuna promozione attiva al momento.</p>
    @else
        <div class="grid">
            @foreach ($active as $promo)
                @include('promo._card', ['promo' => $promo, 'tenant' => $tenant, 'expired' => false])
            @endforeach
        </div>
    @endif

    <h2>Archivio — promo scadute ({{ $expired->count() }})</h2>
    @if ($expired->isEmpty())
        <p class="empty">Nessuna promo scaduta in archivio.</p>
    @else
        <div class="grid">
            @foreach ($expired as $promo)
                @include('promo._card', ['promo' => $promo, 'tenant' => $tenant, 'expired' => true])
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
