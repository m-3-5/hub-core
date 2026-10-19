<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, follow">
    <title>{{ $q !== '' ? 'Cerca «'.$q.'»' : 'Cerca' }} — Hub Core</title>
    <meta name="theme-color" content="#4f46e5">
    <link rel="search" type="application/opensearchdescription+xml" title="Hub Core" href="{{ route('opensearch') }}">
    @include('layouts.partials.favicon')
    <style>
        :root { --c: #4f46e5; --ink: #17171f; --muted: #6a6a7a; --line: #e8e8f0; --bg: #f6f6fa; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--ink); line-height: 1.5; }
        header { background: var(--c); color: #fff; padding: calc(14px + env(safe-area-inset-top)) 16px 18px; }
        .wrap { max-width: 860px; margin: 0 auto; padding: 0 16px; }
        header .wrap { padding: 0; }
        header a { color: #fff; font-weight: 700; text-decoration: none; }
        form { display: flex; gap: 8px; margin-top: 12px; }
        input[type=search] { flex: 1; min-width: 0; min-height: 48px; border: 0; border-radius: 14px; padding: 0 14px; font: inherit; font-size: 1rem; }
        button { min-height: 48px; border: 0; border-radius: 14px; padding: 0 18px; background: #17171f; color: #fff; font: inherit; font-weight: 800; cursor: pointer; }
        h2 { font-size: 1.05rem; margin: 22px 0 8px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 10px; }
        a.r { display: flex; gap: 12px; align-items: center; background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 10px; text-decoration: none; color: inherit; }
        a.r:hover { border-color: #c7d2fe; }
        .th { width: 56px; height: 56px; border-radius: 12px; background: #eef2ff center/cover no-repeat; display: grid; place-items: center; font-size: 1.5rem; flex: none; }
        .r b { display: block; font-size: .95rem; line-height: 1.25; }
        .r small { color: var(--muted); font-size: .8rem; }
        .empty { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px; color: var(--muted); margin-top: 18px; }
        footer { text-align: center; color: var(--muted); font-size: .8rem; padding: 28px 16px calc(28px + env(safe-area-inset-bottom)); }
    </style>
@include('layouts.partials.ui')
</head>
<body>
@include('layouts.partials.public-nav')
<header>
    <div class="wrap">
        <a href="{{ route('welcome') }}">← Hub Core</a>
        <form method="GET" action="{{ route('search') }}" role="search">
            <input type="search" name="q" value="{{ $q }}" placeholder="Cerca promo, servizi, prodotti, annunci…" autofocus maxlength="80">
            <button type="submit">Cerca</button>
        </form>
    </div>
</header>

<main class="wrap">
    @if ($q === '')
        <p class="empty">Scrivi cosa cerchi: una promozione, un servizio, un prodotto, un annuncio o una zona.</p>
    @elseif (mb_strlen($q) < 2)
        <p class="empty">Scrivi almeno due lettere.</p>
    @elseif ($total === 0)
        <p class="empty">Nessun risultato per «{{ $q }}». Prova con una parola più semplice o guarda tutti gli <a href="{{ route('classifieds.board') }}">annunci</a> e le <a href="{{ route('promo.hub-archive') }}">promozioni</a>.</p>
    @else
        @if ($results['promos']->isNotEmpty())
            <h2>✨ Promozioni</h2>
            <div class="grid">
                @foreach ($results['promos'] as $promo)
                    <a class="r" href="{{ $promo->publicUrl() }}">
                        <span class="th" style="{{ $promo->imageUrl() ? "background-image:url('".$promo->imageUrl()."')" : '' }}">@unless ($promo->imageUrl())✨@endunless</span>
                        <span><b>{{ $promo->title }}</b><small>{{ $promo->tenant?->name }}@if ($promo->expiryLabel()) · {{ $promo->expiryLabel() }}@endif</small></span>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($results['offers']->isNotEmpty())
            <h2>🛍️ Servizi e prodotti</h2>
            <div class="grid">
                @foreach ($results['offers'] as $item)
                    <a class="r" href="{{ route('services.public.show', [$item->tenant, $item]) }}">
                        <span class="th" style="{{ $item->coverImageUrl() ? "background-image:url('".$item->coverImageUrl()."')" : '' }}">@unless ($item->coverImageUrl()){{ $item->type === 'product' ? '🛍️' : '💆' }}@endunless</span>
                        <span><b>{{ $item->title }}</b><small>{{ $item->tenant?->name }} · {{ $item->amountEuros() }} €</small></span>
                    </a>
                @endforeach
            </div>
        @endif

        @if ($results['ads']->isNotEmpty())
            <h2>🏷️ Annunci</h2>
            <div class="grid">
                @foreach ($results['ads'] as $ad)
                    <a class="r" href="{{ $ad->publicUrl() }}">
                        <span class="th" style="{{ $ad->coverUrl() ? "background-image:url('".$ad->coverUrl()."')" : '' }}">@unless ($ad->coverUrl()){{ $ad->categoryIcon() }}@endunless</span>
                        <span><b>{{ $ad->title }}</b><small>{{ $ad->categoryLabel() }} · {{ $ad->zone }}{{ $ad->priceLabel() ? ' · '.$ad->priceLabel() : '' }}</small></span>
                    </a>
                @endforeach
            </div>
        @endif
    @endif
</main>

<footer>@include('layouts.partials.company-footer')</footer>
</body>
</html>
