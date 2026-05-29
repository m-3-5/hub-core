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
        <p style="font-size:14px;color:#666">Incolla prima di <code>&lt;/body&gt;</code> sulla homepage:</p>
        <pre style="background:#1a1a2e;color:#fff;padding:12px;border-radius:8px;overflow:auto;font-size:13px">&lt;script src="{{ route('embed.script', ['tenantSlug' => $tenant->slug]) }}" defer&gt;&lt;/script&gt;</pre>
        <p style="font-size:12px;color:#888;margin-top:8px">URL script: <code>{{ route('embed.script', ['tenantSlug' => $tenant->slug]) }}</code></p>

        <h3 style="margin-top:24px">WordPress — pagina su {{ parse_url($tenant->website, PHP_URL_HOST) }}</h3>
        <p style="font-size:14px;color:#666"><strong>Opzione A (veloce):</strong> nuova pagina WP → blocco HTML personalizzato → incolla iframe:</p>
        <pre style="background:#1a1a2e;color:#fff;padding:12px;border-radius:8px;overflow:auto;font-size:12px;white-space:pre-wrap">&lt;iframe src="{{ route('client.promo.embed', [$tenant, $promo]) }}" title="{{ $promo->title }}" style="width:100%;min-height:920px;border:0" loading="lazy"&gt;&lt;/iframe&gt;</pre>
        <p style="font-size:14px;color:#666;margin-top:16px"><strong>Opzione B (tema PHP):</strong> copia <code>resources/wordpress/page-promo-hub.php</code> nel tema attivo, sostituisci <code>HUB_PROMO_URL</code> con:</p>
        <pre style="background:#1a1a2e;color:#fff;padding:12px;border-radius:8px;overflow:auto;font-size:12px">{{ route('client.promo.embed', [$tenant, $promo]) }}</pre>
        <p style="font-size:13px;color:#888;margin-top:12px">Link hub (condivisione): <a href="{{ $promo->publicUrl() }}" target="_blank">{{ $promo->publicUrl() }}</a></p>
    </div>
</div>
@endsection
