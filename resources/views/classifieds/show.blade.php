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
    @include('layouts.partials.favicon')
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
        .videos { display: flex; flex-wrap: wrap; gap: 10px; }
        .videos video { flex: 1 1 220px; max-width: 100%; max-height: 420px; border-radius: 10px; background: #000; }
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
        .buybar { display: none; }
        /* Cellulare: aspetto da app, con barra fissa «prezzo + Compra» in basso. */
        @media (max-width: 800px) {
            body { padding-bottom: calc(84px + env(safe-area-inset-bottom)); }
            .nav { padding: 12px 16px; }
            .layout { padding: 0 12px; gap: 12px; }
            .sticky { position: static; }
            .form input, .form textarea { font-size: 16px; }
            .form button, .cta { min-height: 52px; font-size: 1.02rem; }
            #acquista { scroll-margin-top: 12px; }
            .buybar { display: flex; align-items: center; justify-content: space-between; gap: 12px; position: fixed; left: 0; right: 0; bottom: 0; z-index: 30; background: #fff; border-top: 1px solid var(--line); padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); box-shadow: 0 -8px 24px rgba(0,0,0,.06); }
            .buybar b { font-size: 1.35rem; color: var(--primary-dark); }
            .buybar a { background: var(--primary); color: #fff; text-decoration: none; font-weight: 800; border-radius: 14px; padding: 0 22px; min-height: 50px; display: inline-flex; align-items: center; }
        }
    </style>
    @isset($jsonLd)
    <script type="application/ld+json">{!! $jsonLd !!}</script>
    @endisset
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

        @if ($ad->videoUrls())
            <div class="panel videos" style="margin-top:16px">
                @foreach ($ad->videoUrls() as $videoUrl)
                    <video src="{{ $videoUrl }}" controls playsinline preload="metadata"></video>
                @endforeach
            </div>
        @endif

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
        @if (! empty($protectedBuy))
            <div class="panel" id="acquista" style="margin-bottom:16px;border-color:#c7d2fe;background:#f5f7ff">
                <h2 style="font-size:1.1rem;margin-bottom:6px">🛡️ Compra con pagamento protetto</h2>
                <p style="font-size:.9rem;color:var(--muted);margin-bottom:10px">Paghi online {{ $ad->priceLabel() }}: i soldi restano custoditi da Hub Core e l'inserzionista li riceve solo dopo la consegna (tua conferma, o dopo {{ \M35\HubPayments\Support\ProtectedCheckout::holdDays() }} giorni se non segnali problemi).
                @if ($tenant->type === 'privato')Il venditore è un <strong>privato</strong>: non valgono garanzia legale e recesso da consumatore, ma il pagamento è protetto e, se non ricevi l'oggetto, ti rimborsiamo.@endif</p>
                @error('buy')<p class="err" style="margin:0 0 8px">{{ $message }}</p>@enderror
                <form class="form" method="POST" action="{{ route('classifieds.buy', [$tenant, $ad]) }}">
                    @csrf
                    <input type="email" name="customer_email" maxlength="190" placeholder="La tua email (per la ricevuta)" value="{{ old('customer_email') }}">
                    <label style="display:flex;gap:8px;align-items:flex-start;font-size:.85rem;color:var(--muted);line-height:1.4;margin-bottom:10px;cursor:pointer">
                        <input type="checkbox" name="accept_buyer_terms" value="1" required style="width:18px;height:18px;margin:2px 0 0;flex:none">
                        <span>Ho letto e accetto le <a href="{{ route('terms.buyer') }}" target="_blank" rel="noopener">condizioni di acquisto protetto</a>.</span>
                    </label>
                    <button type="submit">Compra ora · {{ $ad->priceLabel() }}</button>
                </form>
            </div>
        @elseif ($errors->has('buy'))
            <div class="panel" style="margin-bottom:16px"><p class="err" style="margin:0">{{ $errors->first('buy') }}</p></div>
        @endif

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

@if (! empty($protectedBuy))
    <div class="buybar"><b>{{ $ad->priceLabel() }}</b><a href="#acquista">🛡️ Compra ora</a></div>
@endif

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
