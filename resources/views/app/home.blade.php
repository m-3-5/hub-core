@extends('layouts.app')

@section('title', 'Home')

@section('content')
@php
    $typeLabels = ['azienda' => 'Azienda', 'privato' => 'Privato', 'ente' => 'Ente'];
    $tenantType = $tenant->type ?: 'azienda';
    $ownModules = collect($hubModules)->filter(fn ($m) => $m['fits_type']);
    $otherModules = collect($hubModules)->reject(fn ($m) => $m['fits_type']);
@endphp

<div class="app-top">
    <div>
        <h1>{{ $tenant->name }}</h1>
        <p>{{ $typeLabels[$tenantType] ?? 'Azienda' }} · Cosa vuoi fare oggi?</p>
    </div>
    <div class="app-actions">
        <a class="btn btn-ghost" href="{{ route('admin.profile.edit', $tenant) }}">🪪 Profilo</a>
        @if (auth()->user()->isSuperAdmin())
            <a class="btn btn-ghost" href="{{ route('app.index') }}">Attività</a>
            <a class="btn btn-ghost" href="{{ route('admin.dashboard') }}">Admin</a>
        @endif
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost">Esci</button>
        </form>
    </div>
</div>

<div class="module-grid">
    @foreach ($ownModules as $module)
        @if ($module['active'] && $module['url'])
            <a class="module-tile" href="{{ $module['url'] }}">
                <div class="module-icon">{{ $module['emoji'] }}</div>
                <div class="module-label">{{ $module['label'] }}</div>
                <div class="module-desc">{{ $module['description'] }}</div>
            </a>
        @else
            <button type="button" class="module-tile is-disabled" data-request-module="{{ $module['label'] }}">
                <div class="module-icon">{{ $module['emoji'] }}</div>
                <div class="module-label">{{ $module['label'] }}</div>
                <div class="module-desc">{{ $module['description'] }}</div>
                <span class="badge-soon">Prossimamente</span>
            </button>
        @endif
    @endforeach
</div>

@if ($otherModules->isNotEmpty())
    <div class="section-card" style="margin-top:24px">
        <h2 style="margin:0 0 4px">Altre possibilità su Hub Core</h2>
        <p style="margin:0 0 16px;color:#666;font-size:.9rem">Funzioni pensate per altri tipi di account — puoi vedere cosa offrono, non sono attivabili da qui.</p>
        <div class="module-grid">
            @foreach ($otherModules as $module)
                <div class="module-tile is-disabled" aria-disabled="true">
                    <div class="module-icon">{{ $module['emoji'] }}</div>
                    <div class="module-label">{{ $module['label'] }}</div>
                    <div class="module-desc">{{ $module['description'] }}</div>
                    <span class="badge-soon">Solo per {{ implode('/', array_map(fn ($t) => $typeLabels[$t] ?? $t, $module['for_types'] ?? [])) }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if ($recentServices->isNotEmpty())
    <div class="section-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
            <h2 style="margin:0">I tuoi servizi</h2>
            <a href="{{ route('admin.services.index', $tenant) }}">Gestisci</a>
        </div>
        <div class="services-scroll">
            @foreach ($recentServices as $service)
                <a href="{{ route('admin.services.show', [$tenant, $service]) }}" class="service-scroll-card">
                    <div class="service-scroll-cover">
                        @if ($service->coverImageUrl())
                            <img src="{{ $service->coverImageUrl() }}" alt="{{ $service->title }}" loading="lazy">
                        @else
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.5rem">💆</div>
                        @endif
                    </div>
                    <div class="service-scroll-title">{{ $service->title }}</div>
                    <div class="service-scroll-price">{{ $service->amountEuros() }} €</div>
                </a>
            @endforeach
        </div>
    </div>
@endif

@if ($recentPromos->isNotEmpty() || $archivedPromos->isNotEmpty())
    <div class="section-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
            <h2 style="margin:0">Promozioni</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('admin.promos.index', $tenant) }}">Gestisci</a>
                <a href="{{ route('promo.archive', $tenant) }}" target="_blank">Archivio pubblico ↗</a>
            </div>
        </div>
        <div class="services-scroll">
            @foreach ($recentPromos as $promo)
                <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}" class="service-scroll-card">
                    <div class="service-scroll-cover">
                        @if ($promo->imageUrl())
                            <img src="{{ $promo->imageUrl() }}" alt="{{ $promo->title }}" loading="lazy">
                        @else
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.5rem">✨</div>
                        @endif
                    </div>
                    <div class="service-scroll-title">{{ $promo->title }}</div>
                    <div class="service-scroll-price">{{ $promo->expiryLabel() ?? 'Attiva' }}</div>
                </a>
            @endforeach
            @foreach ($archivedPromos as $promo)
                <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}" class="service-scroll-card">
                    <div class="service-scroll-cover" style="position:relative">
                        @if ($promo->imageUrl())
                            <img src="{{ $promo->imageUrl() }}" alt="{{ $promo->title }}" loading="lazy" style="filter:grayscale(.55);opacity:.75">
                        @else
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;opacity:.6">✨</div>
                        @endif
                        <span style="position:absolute;top:6px;left:6px;background:rgba(15,23,42,.75);color:#fff;font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:2px 8px;border-radius:999px">Scaduta</span>
                    </div>
                    <div class="service-scroll-title">{{ $promo->title }}</div>
                    <div class="service-scroll-price">{{ $promo->expiryLabel() }}</div>
                </a>
            @endforeach
        </div>
    </div>
@endif

@include('app.partials.max-assistant')
@include('app.partials.module-request-modal')
@endsection
