<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($brand = $tenant?->primary_color ?: '#4f46e5')
    <title>{{ $tenant ? 'Annunci — '.$tenant->name : 'Annunci immobili' }}</title>
    @include('layouts.partials.favicon')
    <meta name="description" content="Affitti, case vacanza e vendita immobili{{ $tenant ? ' di '.$tenant->name : '' }}.">
    <link rel="canonical" href="{{ $tenant ? route('classifieds.tenant-board', $tenant) : route('classifieds.board') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $tenant ? 'Annunci — '.$tenant->name : 'Annunci immobili' }}">
    <meta property="og:description" content="Affitti, case vacanza e vendita immobili{{ $tenant ? ' di '.$tenant->name : '' }}.">
    <meta property="og:url" content="{{ $tenant ? route('classifieds.tenant-board', $tenant) : route('classifieds.board') }}">
    <meta name="theme-color" content="{{ $brand }}">
    <style>
        :root { --primary: {{ $brand }}; --primary-dark: color-mix(in srgb, var(--primary) 75%, #000); --ink: #1b1b24; --muted: #6b6b7b; --line: #e8e8ef; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, "Segoe UI", system-ui, sans-serif; color: var(--ink); background: #f6f6fa; line-height: 1.45; }
        a { color: inherit; text-decoration: none; }
        .top { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: #fff; padding: 28px 16px 36px; text-align: center; }
        .top h1 { font-size: 1.7rem; }
        .top p { opacity: .9; margin-top: 4px; }
        .wrap { max-width: 1100px; margin: -22px auto 40px; padding: 0 16px; }
        .filters { background: #fff; border-radius: 14px; padding: 14px; display: flex; gap: 10px; flex-wrap: wrap; box-shadow: 0 6px 20px rgba(0,0,0,.08); }
        .filters input, .filters select { padding: 11px 12px; border: 1px solid var(--line); border-radius: 10px; font: inherit; flex: 1; min-width: 150px; }
        .filters button { background: var(--primary); color: #fff; border: 0; border-radius: 10px; padding: 11px 22px; font: inherit; font-weight: 700; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-top: 20px; }
        .card { background: #fff; border-radius: 14px; overflow: hidden; border: 1px solid var(--line); display: flex; flex-direction: column; transition: transform .15s; }
        .card:hover { transform: translateY(-3px); }
        .cover { aspect-ratio: 4/3; background: color-mix(in srgb, var(--primary) 15%, #fff) center/cover no-repeat; position: relative; display: flex; align-items: center; justify-content: center; font-size: 2.4rem; }
        .badge { position: absolute; top: 10px; left: 10px; background: rgba(0,0,0,.65); color: #fff; font-size: .72rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; text-transform: uppercase; letter-spacing: .03em; }
        .body { padding: 14px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .price { color: var(--primary-dark); font-weight: 800; font-size: 1.15rem; }
        .title { font-weight: 700; }
        .meta { color: var(--muted); font-size: .88rem; }
        .empty { text-align: center; color: var(--muted); padding: 50px 0; }
        .pager { margin-top: 24px; text-align: center; }
        .pager nav svg { height: 18px; }
        .pager a, .pager span { padding: 6px 10px; }
    </style>
    @isset($jsonLd)
    <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endisset
</head>
<body>
<div class="top">
    <h1>{{ $tenant ? $tenant->name : 'Annunci immobili' }}</h1>
    <p>Affitti, case vacanza e vendita immobili</p>
</div>

<div class="wrap">
    <form class="filters" method="GET">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cerca zona o parola chiave (es. Garda, Sirmione)">
        <select name="categoria">
            <option value="">Tutte le categorie</option>
            @foreach (\App\Models\ClassifiedAd::CATEGORIES as $key => $label)
                <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit">Cerca</button>
    </form>

    @if ($ads->isEmpty())
        <p class="empty">Nessun annuncio trovato.</p>
    @else
        <div class="grid">
            @foreach ($ads as $ad)
                <a class="card" href="{{ $ad->publicUrl() }}">
                    <div class="cover" style="{{ $ad->coverUrl() ? "background-image:url('".$ad->coverUrl()."')" : '' }}">
                        @unless ($ad->coverUrl())🏠@endunless
                        <span class="badge">{{ $ad->categoryLabel() }}</span>
                    </div>
                    <div class="body">
                        @if ($ad->priceLabel())<div class="price">{{ $ad->priceLabel() }}</div>@endif
                        <div class="title">{{ $ad->title }}</div>
                        <div class="meta">📍 {{ $ad->zone }}</div>
                        <div class="meta">
                            @foreach (\App\Models\ClassifiedAd::FEATURE_LABELS as $key => $label)
                                @if (! empty($ad->features[$key])){{ $ad->features[$key] }} {{ strtolower($label) }} &nbsp;@endif
                            @endforeach
                        </div>
                        @unless ($tenant)<div class="meta">{{ $ad->tenant->name }}</div>@endunless
                    </div>
                </a>
            @endforeach
        </div>
        <div class="pager">{{ $ads->links() }}</div>
    @endif
</div>
</body>
</html>
