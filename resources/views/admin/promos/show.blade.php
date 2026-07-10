@extends('layouts.admin')

@section('title', $promo->title)

@section('content')
@php
    $previewUrl = route('admin.promos.preview', [$tenant, $promo]);
    $statusLabel = $promo->isPublished() ? 'Pubblicata' : 'Bozza';
    $statusColor = $promo->isPublished() ? '#2e7d32' : '#e65100';
@endphp

<div class="card" style="margin-bottom:20px">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:16px">
        <div>
            <h1 style="margin:0 0 8px">{{ $promo->title }}</h1>
            <p style="color:#666;margin:0">{{ $tenant->name }}</p>
            <span style="display:inline-block;margin-top:10px;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;background:{{ $statusColor }}20;color:{{ $statusColor }}">
                {{ $statusLabel }}
            </span>
            @if ($promo->always_active)
                <span style="font-size:13px;color:#888;margin-left:8px">· Sempre attiva</span>
            @elseif ($promo->ends_at)
                <span style="font-size:13px;color:#888;margin-left:8px">· Scade {{ $promo->ends_at->format('d/m/Y H:i') }}</span>
            @endif
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px">
            @if ($promo->isDraft())
                <form method="POST" action="{{ route('admin.promos.publish', [$tenant, $promo]) }}">
                    @csrf
                    <button type="submit" class="btn">Pubblica su Beauty of Image</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.promos.publish', [$tenant, $promo]) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Risincronizza WordPress</button>
                </form>
            @endif
            <a class="btn btn-secondary" href="{{ route('admin.promos.edit', [$tenant, $promo]) }}">Modifica</a>
            <a class="btn btn-secondary" href="{{ $previewUrl }}" target="_blank">Anteprima a schermo intero</a>
            <form method="POST" action="{{ route('admin.promos.destroy', [$tenant, $promo]) }}" onsubmit="return confirm('Eliminare questa promo?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Elimina</button>
            </form>
        </div>
    </div>
</div>

<div class="admin-grid">
    <div class="card">
        <h2 style="margin:0 0 12px;font-size:1.1rem">Anteprima cliente</h2>
        <p style="color:#666;font-size:14px;margin:0 0 16px">Così vedrà la promo su hub-core. Su WordPress comparirà nelle card dopo <strong>Pubblica</strong>.</p>
        <iframe src="{{ $previewUrl }}" title="Anteprima promo" class="preview-frame"></iframe>
    </div>

    <div class="card">
        <h2 style="margin:0 0 16px;font-size:1.1rem">Contenuto</h2>
        @if ($promo->imageUrl())
            <img src="{{ $promo->imageUrl() }}" alt="" style="max-width:100%;border-radius:8px;margin-bottom:8px">
            <details style="margin-bottom:16px">
                <summary style="cursor:pointer;color:#666;font-size:13px">Il volantino non ti convince? Scrivi a Max</summary>
                <form method="POST" action="{{ route('admin.tickets.store', $tenant) }}" style="margin-top:10px">
                    @csrf
                    <input type="hidden" name="context_type" value="promo">
                    <input type="hidden" name="context_id" value="{{ $promo->id }}">
                    <input type="hidden" name="context_label" value="Promo: {{ $promo->title }}">
                    <textarea name="message" rows="3" maxlength="2000" required placeholder="Cosa vorresti cambiare?" style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-family:inherit"></textarea>
                    <button type="submit" class="btn btn-secondary" style="margin-top:8px">Invia a Max — risposta entro 24 ore</button>
                </form>
            </details>
        @endif
        <p style="color:#444;line-height:1.6">{{ $promo->description }}</p>

        @if ($promo->offers)
            <h3 style="font-size:1rem;margin:20px 0 10px">Offerte</h3>
            <ul style="padding-left:18px;line-height:1.7">
                @foreach ($promo->offers as $offer)
                    <li>
                        <strong>{{ $offer['name'] ?? '' }}</strong>
                        @if (!empty($offer['price'])) — {{ $offer['price'] }} @endif
                        @if (!empty($offer['detail']))<br><span style="color:#666;font-size:14px">{{ $offer['detail'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($promo->isPublished())
            <p style="margin-top:20px;font-size:14px">
                <a href="{{ $promo->publicUrl() }}" target="_blank">Apri pagina pubblica →</a>
            </p>
        @else
            <p style="margin-top:20px;font-size:14px;color:#e65100;background:#fff3e0;padding:12px;border-radius:8px">
                In bozza: non visibile su beautyofimage.com finché non clicchi <strong>Pubblica</strong>.
            </p>
        @endif
    </div>
</div>

<div class="card" style="margin-top:20px">
    <h3>Integrazione WordPress</h3>
    <p style="font-size:14px;color:#666">Popup footer:</p>
    <pre style="background:#1a1a2e;color:#fff;padding:12px;border-radius:8px;overflow:auto;font-size:13px">&lt;script src="{{ route('embed.script', ['tenantSlug' => $tenant->slug]) }}" defer&gt;&lt;/script&gt;</pre>
    <p style="font-size:14px;color:#666;margin-top:16px">API sync: <code>{{ route('api.promos.index', ['tenantSlug' => $tenant->slug]) }}</code></p>
</div>
@endsection
