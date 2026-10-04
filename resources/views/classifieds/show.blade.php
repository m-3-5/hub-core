<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $brand = $tenant->primary_color ?: '#4f46e5';
        $images = $ad->imageUrls();
        $contactSuccess = session('contact_success');
        $phoneDigits = preg_replace('/\D+/', '', (string) $ad->contact_phone);
    @endphp
    <title>{{ $ad->title }} — {{ $ad->zone }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($ad->description), 155) }}">
    <link rel="canonical" href="{{ $ad->publicUrl() }}">
    <meta name="theme-color" content="{{ $brand }}">
    <meta property="og:title" content="{{ $ad->title }}">
    <meta property="og:description" content="{{ $ad->categoryLabel() }} · {{ $ad->zone }}{{ $ad->priceLabel() ? ' · '.$ad->priceLabel() : '' }}">
    @if ($images)<meta property="og:image" content="{{ $images[0] }}">@endif
    <style>
        :root { --primary: {{ $brand }}; --primary-dark: color-mix(in srgb, var(--primary) 75%, #000); --ink: #1b1b24; --muted: #6b6b7b; --line: #e8e8ef; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, "Segoe UI", system-ui, sans-serif; color: var(--ink); background: #f6f6fa; line-height: 1.55; }
        a { color: var(--primary-dark); }
        .nav { max-width: 1000px; margin: 0 auto; padding: 14px 16px; font-size: .92rem; }
        .layout { max-width: 1000px; margin: 0 auto 40px; padding: 0 16px; display: grid; grid-template-columns: minmax(0, 1.7fr) minmax(280px, 1fr); gap: 20px; align-items: start; }
        @media (max-width: 800px) { .layout { grid-template-columns: 1fr; } }
        .panel { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 18px; }
        .gallery-main { aspect-ratio: 4/3; border-radius: 12px; background: color-mix(in srgb, var(--primary) 15%, #fff) center/cover no-repeat; display: flex; align-items: center; justify-content: center; font-size: 3rem; }
        .thumbs { display: flex; gap: 8px; margin-top: 8px; overflow-x: auto; }
        .thumbs button { border: 2px solid transparent; border-radius: 8px; padding: 0; width: 84px; height: 62px; flex: none; cursor: pointer; background: center/cover no-repeat; }
        .thumbs button.on { border-color: var(--primary); }
        .badge { display: inline-block; background: color-mix(in srgb, var(--primary) 14%, #fff); color: var(--primary-dark); font-size: .75rem; font-weight: 700; padding: 4px 12px; border-radius: 999px; text-transform: uppercase; letter-spacing: .03em; }
        h1 { font-size: 1.5rem; margin: 10px 0 4px; }
        .zone { color: var(--muted); }
        .price { font-size: 1.7rem; font-weight: 800; color: var(--primary-dark); margin: 12px 0; }
        .features { display: flex; flex-wrap: wrap; gap: 8px; margin: 12px 0; }
        .features span { background: #f1f1f6; border-radius: 10px; padding: 6px 12px; font-size: .9rem; }
        .desc { white-space: pre-line; margin-top: 8px; }
        .cta { display: block; text-decoration: none; text-align: center; padding: 13px; border-radius: 10px; font-weight: 700; margin-bottom: 10px; color: #fff; background: var(--primary); }
        .cta.wa { background: #25d366; }
        .cta.ghost { background: #fff; color: var(--primary-dark); border: 2px solid var(--primary); }
        .form input, .form textarea { width: 100%; padding: 11px 12px; border: 1px solid var(--line); border-radius: 10px; font: inherit; margin-bottom: 10px; }
        .form button { width: 100%; background: var(--primary); color: #fff; border: 0; border-radius: 10px; padding: 13px; font: inherit; font-weight: 700; cursor: pointer; }
        .hp { position: absolute; left: -9999px; }
        .ok { background: #e8f5e9; color: #1b5e20; padding: 12px; border-radius: 10px; }
        .err { color: #c62828; font-size: .85rem; margin: -6px 0 8px; }
        .sticky { position: sticky; top: 16px; }
    </style>
</head>
<body>
<div class="nav"><a href="{{ route('classifieds.tenant-board', $tenant) }}">← Tutti gli annunci di {{ $tenant->name }}</a></div>

<div class="layout">
    <div>
        <div class="panel">
            <div class="gallery-main" id="main" style="{{ $images ? "background-image:url('".$images[0]."')" : '' }}">@unless ($images)🏠@endunless</div>
            @if (count($images) > 1)
                <div class="thumbs">
                    @foreach ($images as $i => $url)
                        <button type="button" class="{{ $i === 0 ? 'on' : '' }}" data-src="{{ $url }}" style="background-image:url('{{ $url }}')" aria-label="Foto {{ $i + 1 }}"></button>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="panel" style="margin-top:16px">
            <span class="badge">{{ $ad->categoryLabel() }}</span>
            <h1>{{ $ad->title }}</h1>
            <div class="zone">📍 {{ $ad->zone }}</div>
            @if ($ad->priceLabel())<div class="price">{{ $ad->priceLabel() }}</div>@endif
            <div class="features">
                @foreach (\App\Models\ClassifiedAd::FEATURE_LABELS as $key => $label)
                    @if (! empty($ad->features[$key]))<span><strong>{{ $ad->features[$key] }}</strong> {{ $label }}</span>@endif
                @endforeach
            </div>
            <div class="desc">{{ $ad->description }}</div>
            <p class="zone" style="margin-top:14px;font-size:.85rem">Pubblicato {{ optional($ad->published_at)->format('d/m/Y') }} · {{ $tenant->name }}</p>
        </div>
    </div>

    <div class="sticky">
        <div class="panel">
            <h2 style="font-size:1.1rem;margin-bottom:12px">Contatta l'inserzionista{{ $ad->contact_name ? ': '.$ad->contact_name : '' }}</h2>
            @if ($ad->contact_phone)
                <a class="cta" href="tel:{{ $ad->contact_phone }}">📞 Chiama {{ $ad->contact_phone }}</a>
                @if ($phoneDigits)
                    <a class="cta wa" href="https://wa.me/{{ $phoneDigits }}?text={{ rawurlencode('Buongiorno, sono interessato all\'annuncio «'.$ad->title.'» '.$ad->publicUrl()) }}" target="_blank" rel="noopener">WhatsApp</a>
                @endif
            @endif

            @if ($contactSuccess)
                <p class="ok">Messaggio inviato! Ti risponderemo il prima possibile.</p>
            @else
                <form class="form" method="POST" action="{{ route('classifieds.contact', [$tenant, $ad]) }}">
                    @csrf
                    <input type="text" name="website" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
                    <input type="text" name="name" required maxlength="120" value="{{ old('name') }}" placeholder="Il tuo nome">
                    @error('name')<div class="err">{{ $message }}</div>@enderror
                    <input type="email" name="email" maxlength="190" value="{{ old('email') }}" placeholder="Email">
                    <input type="tel" name="phone" maxlength="30" value="{{ old('phone') }}" placeholder="Telefono">
                    @error('email')<div class="err">{{ $message }}</div>@enderror
                    @error('phone')<div class="err">{{ $message }}</div>@enderror
                    <textarea name="message" rows="4" required maxlength="2000" placeholder="Scrivi il tuo messaggio (date, numero di persone...)">{{ old('message') }}</textarea>
                    @error('message')<div class="err">{{ $message }}</div>@enderror
                    <button type="submit">Invia messaggio</button>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.thumbs button').forEach(function (b) {
    b.addEventListener('click', function () {
        document.getElementById('main').style.backgroundImage = "url('" + b.dataset.src + "')";
        document.querySelectorAll('.thumbs button').forEach(function (x) { x.classList.remove('on'); });
        b.classList.add('on');
    });
});
</script>
</body>
</html>
