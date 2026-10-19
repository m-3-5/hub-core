@php $isProduct = $service->type === 'product'; @endphp
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
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="it_IT">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $service->title }} — {{ $tenant->name }}">
    @if ($service->coverImageUrl())
        <meta name="twitter:image" content="{{ $service->coverImageUrl() }}">
    @endif
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
        /* Cellulare: aspetto da app. Immagine a tutta larghezza, acquisto in una barra fissa in basso con tasto grande. */
        @media (max-width: 800px) {
            body { padding-bottom: calc(150px + env(safe-area-inset-bottom)); }
            .wrap { padding: 0 0 24px; }
            .breadcrumb { padding: 14px 16px 0; margin-bottom: 10px; }
            .layout { gap: 0; }
            .layout > div:last-child { padding: 16px; }
            .media { border-radius: 0; box-shadow: none; }
            h1 { font-size: 1.7rem; }
            .price { font-size: 1.6rem; margin-bottom: 12px; }
            .cta-group { position: fixed; left: 0; right: 0; bottom: 0; z-index: 30; background: #fff; border-top: 1px solid #eee; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); box-shadow: 0 -8px 24px rgba(0,0,0,.06); gap: 8px; }
            .cta-group form { width: 100%; gap: 8px; }
            .cta-group .btn-primary { width: 100%; text-align: center; min-height: 54px; padding: 16px; font-size: 1.05rem; }
            .btn-whatsapp { display: none; }
            .trust-note { margin-top: 16px; }
            .back-link { margin: 24px 16px 0; }
        }
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
@include('layouts.partials.ui')
</head>
<body>
<div class="wrap">
    @unless (!empty($embedMode))
        <p class="breadcrumb"><a href="{{ route('services.public.archive', $tenant) }}">{{ $tenant->name }}</a> · {{ $isProduct ? 'Prodotti' : 'Servizi' }}</p>
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
            @if ($service->onPromo())
                <p style="display:inline-block;background:#fef3c7;color:#92400e;font-size:.8rem;font-weight:700;padding:4px 12px;border-radius:999px;margin-bottom:10px">In promo fino al {{ $service->promoUntil()->format('d/m/Y') }}</p>
            @endif
            <h1>{{ $service->title }}</h1>
            <div class="price">{{ $service->amountEuros() }} €@if ($service->durationLabel()) <span style="font-size:1rem;font-weight:500;color:var(--muted)">· {{ $service->durationLabel() }}</span>@endif</div>
            @if ($service->description)
                <p class="description">{{ $service->description }}</p>
            @endif
            @php $protectedBuy = ! empty($protectedAvailable) && empty($embedMode); @endphp
            @error('buy')<p style="color:#b91c1c;font-weight:600;margin-bottom:12px">{{ $message }}</p>@enderror
            <div class="cta-group">
                @if ($protectedBuy)
                    <form method="POST" action="{{ route('services.public.buy', [$tenant, $service]) }}" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center">
                        @csrf
                        @if ($isProduct)
                            <label style="font-size:.9rem;color:var(--muted)">Quantità <input type="number" name="quantity" value="1" min="1" max="20" style="width:64px;padding:10px;border:1px solid #ddd;border-radius:10px;font:inherit"></label>
                        @endif
                        <label style="flex:1 1 100%;display:flex;gap:8px;align-items:flex-start;font-size:.88rem;color:var(--muted);line-height:1.4;cursor:pointer">
                            <input type="checkbox" name="accept_buyer_terms" value="1" required style="margin-top:3px;width:18px;height:18px;flex:none">
                            <span>Ho letto e accetto le <a href="{{ route('terms.buyer') }}" target="_blank" rel="noopener">condizioni di acquisto protetto</a>: il pagamento resta custodito da Hub Core fino alla mia conferma di ricezione (o {{ \M35\HubPayments\Support\ProtectedCheckout::holdDays() }} giorni) e posso segnalare un problema.</span>
                        </label>
                        <button type="submit" class="btn-primary" style="border:0;cursor:pointer;font:inherit;font-weight:700">{{ $isProduct ? 'Acquista ora' : 'Prenota e paga ora' }}</button>
                    </form>
                @else
                    <a class="btn-primary" href="{{ !empty($embedMode) ? $service->payment_url : $service->hubPaymentUrl() }}" target="_top">{{ $isProduct ? 'Acquista ora' : 'Prenota e paga ora' }}</a>
                @endif
                @if ($tenant->settings['whatsapp'] ?? null)
                    <a class="btn-whatsapp" target="_blank" rel="noopener"
                       href="https://wa.me/{{ $tenant->settings['whatsapp'] }}?text={{ rawurlencode('Ciao! Vorrei info su «'.$service->title.'» ('.$service->amountEuros().' €)') }}">WhatsApp</a>
                @endif
            </div>
            @if ($protectedBuy)
                <p class="trust-note"><a href="{{ route('terms.buyer') }}" target="_blank" rel="noopener">Leggi termini e condizioni</a></p>
            @else
                <p class="trust-note">Pagamento sicuro tramite Stripe · {{ $tenant->name }}</p>
            @endif

        </div>
    </div>

    @unless (!empty($embedMode))
        <a class="back-link" href="{{ route('services.public.archive', $tenant) }}#{{ $isProduct ? 'prodotti' : 'servizi' }}">← {{ $isProduct ? 'Tutti i prodotti' : 'Tutti i servizi' }} di {{ $tenant->name }}</a>
    @endunless
</div>
@unless (!empty($embedMode))
    @include('layouts.partials.share-bar', ['shareTitle' => $service->title.' — '.$tenant->name, 'shareUrl' => url()->current(), 'shareSection' => true, 'shareLabel' => $isProduct ? 'Condividi questo prodotto' : 'Condividi questo servizio'])
@endunless
</body>
</html>
