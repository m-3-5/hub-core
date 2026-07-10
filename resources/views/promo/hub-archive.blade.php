<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tutte le promozioni — Hub Core</title>
    <meta name="description" content="Le promozioni attive di tutte le attività su Hub Core.">
    <meta name="theme-color" content="#e91e8c">
    <link rel="canonical" href="{{ route('promo.hub-archive') }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Hub Core">
    <meta property="og:title" content="Tutte le promozioni — Hub Core">
    <meta property="og:description" content="Le promozioni attive di tutte le attività su Hub Core, in un unico posto.">
    <meta property="og:image" content="{{ asset('images/og-hub-core.png') }}">
    <meta property="og:url" content="{{ route('promo.hub-archive') }}">
    <meta property="og:locale" content="it_IT">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Tutte le promozioni — Hub Core">
    <meta name="twitter:image" content="{{ asset('images/og-hub-core.png') }}">
    <style>
        :root { --primary: #e91e8c; --text: #1f1a24; --muted: #5c5563; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; color: var(--text); background: #faf8fb; line-height: 1.5; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 32px 20px 48px; }
        h1 { font-size: clamp(1.8rem, 4vw, 2.4rem); margin-bottom: 8px; }
        .lead { color: var(--muted); margin-bottom: 28px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px; }
        .promo-card { background: #fff; border-radius: 14px; overflow: hidden; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 8px 24px rgba(0,0,0,.07); transition: transform .15s; }
        .promo-card:hover { transform: translateY(-2px); }
        .promo-card__media { display: block; background: linear-gradient(180deg, #fdf8fb, #fff); padding: 12px 14px 8px; }
        .promo-card__media img { width: 100%; height: auto; max-height: 150px; object-fit: contain; display: block; margin: 0 auto; }
        .promo-card__body { padding: 14px 16px 16px; }
        .promo-card__tenant { font-size: .74rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--primary); margin-bottom: 6px; }
        .promo-card__badge { display: inline-block; font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 4px 10px; border-radius: 999px; margin-bottom: 8px; background: color-mix(in srgb, var(--primary) 14%, #fff); color: var(--primary); }
        .promo-card h3 { font-size: 1.05rem; margin-bottom: 6px; }
        .promo-card h3 a { color: inherit; text-decoration: none; }
        .promo-card p { color: var(--muted); font-size: .88rem; margin-bottom: 12px; }
        .promo-card__cta { display: inline-block; background: var(--primary); color: #fff !important; text-decoration: none; padding: 8px 14px; border-radius: 999px; font-size: .82rem; font-weight: 600; }
        .empty { color: var(--muted); font-size: .95rem; }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Tutte le promozioni</h1>
    <p class="lead">Le offerte attive di tutte le attività su Hub Core, in un unico posto.</p>

    @if ($promos->isEmpty())
        <p class="empty">Nessuna promozione attiva al momento.</p>
    @else
        <div class="grid">
            @foreach ($promos as $promo)
                @include('promo._card', ['promo' => $promo, 'tenant' => $promo->tenant, 'expired' => false, 'showTenant' => true])
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
