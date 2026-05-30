@extends('layouts.admin')

@section('title', $promo->title)

@section('content')
<div class="card">
    <h1>{{ $promo->title }}</h1>
    <p style="color:#666">{{ $tenant->name }} · {{ $promo->always_active ? 'Sempre attiva' : 'A tempo' }}</p>

    @if ($promo->imageUrl())
        <img src="{{ $promo->imageUrl() }}" alt="" style="max-width:100%;border-radius:8px;margin:16px 0">
    @endif

    <p>{{ $promo->description }}</p>

    @if ($promo->offers)
        <ul>
            @foreach ($promo->offers as $offer)
                <li><strong>{{ $offer['name'] ?? '' }}</strong>
                    @if (!empty($offer['price'])) — {{ $offer['price'] }} @endif
                    @if (!empty($offer['detail'])) <em>({{ $offer['detail'] }})</em> @endif
                </li>
            @endforeach
        </ul>
    @endif

    <div style="margin-top:24px;display:flex;flex-wrap:gap:12px">
        <a class="btn" href="{{ $promo->publicUrl() }}" target="_blank">Apri pagina promo</a>
        <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">Dashboard</a>
    </div>

    <div style="margin-top:32px;padding:16px;background:#f9f9f9;border-radius:8px">
        <h3>Popup home — {{ $tenant->website }}</h3>
        <p style="font-size:14px;color:#666">Footer WordPress (Snippet / Header &amp; Footer):</p>
        <pre style="background:#1a1a2e;color:#fff;padding:12px;border-radius:8px;overflow:auto;font-size:13px">&lt;script src="{{ route('embed.script', ['tenantSlug' => $tenant->slug]) }}" defer&gt;&lt;/script&gt;</pre>

        <h3 style="margin-top:24px">API promo (sync automatico WordPress)</h3>
        <p style="font-size:14px;color:#666">JSON pubblico per mu-plugin / griglia nativa:</p>
        <pre style="background:#1a1a2e;color:#fff;padding:12px;border-radius:8px;overflow:auto;font-size:12px">{{ route('api.promos.index', ['tenantSlug' => $tenant->slug]) }}</pre>

        <h3 style="margin-top:24px">WordPress — sync automatico (consigliato)</h3>
        <ol style="font-size:14px;color:#666;line-height:1.7">
            <li>Copia <code>resources/wordpress/beauty-hub-core.php</code> in <code>wp-content/mu-plugins/</code></li>
            <li>Imposta lo stesso <code>BEAUTY_HUB_WEBHOOK_SECRET</code> nel mu-plugin e in hub <code>HUB_WEBHOOK_SECRET</code></li>
            <li>In hub <code>.env</code> aggiungi (stesso secret del mu-plugin):<br>
                <code>HUB_WEBHOOK_URL=https://beautyofimage.com/wp-json/beauty-hub/v1/sync</code><br>
                <code>HUB_WEBHOOK_SECRET=un_token_lungo_e_casuale</code>
            </li>
            <li>Crea pagina WP <strong>Promozioni</strong> con shortcode: <code>[beauty_promos]</code></li>
        </ol>
        <p style="font-size:13px;color:#888">Alla pubblicazione promo, hub invia webhook → WordPress aggiorna la griglia senza iframe.</p>

        <p style="font-size:13px;color:#888;margin-top:12px">Landing completa: <a href="{{ $promo->publicUrl() }}" target="_blank">{{ $promo->publicUrl() }}</a></p>
    </div>
</div>
@endsection
