@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="app-top">
    <div>
        <h1>{{ $tenant->name }}</h1>
        <p>Cosa vuoi fare oggi?</p>
    </div>
    <div class="app-actions">
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
    @foreach ($hubModules as $module)
        @if ($module['active'] && $module['url'])
            <a class="module-tile" href="{{ $module['url'] }}">
                <div class="module-icon">{{ $module['emoji'] }}</div>
                <div class="module-label">{{ $module['label'] }}</div>
                <div class="module-desc">{{ $module['description'] }}</div>
            </a>
        @else
            <div class="module-tile is-disabled" aria-disabled="true">
                <div class="module-icon">{{ $module['emoji'] }}</div>
                <div class="module-label">{{ $module['label'] }}</div>
                <div class="module-desc">{{ $module['description'] }}</div>
                <span class="badge-soon">Prossimamente</span>
            </div>
        @endif
    @endforeach
</div>

@if ($recentPromos->isNotEmpty() || ($expiredCount ?? 0) > 0)
    <div class="section-card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px">
            <h2 style="margin:0">Promozioni</h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('admin.promos.index', $tenant) }}">Gestisci</a>
                <a href="{{ route('promo.archive', $tenant) }}" target="_blank">Archivio pubblico ↗</a>
            </div>
        </div>
        @if ($recentPromos->isNotEmpty())
            <ul class="promo-list">
                @foreach ($recentPromos as $promo)
                    <li>
                        <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}">{{ $promo->title }}</a>
                        @if ($promo->expiryLabel())
                            <small style="color:#666"> · {{ $promo->expiryLabel() }}</small>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if (($expiredCount ?? 0) > 0)
            <p style="color:#64748b;font-size:.9rem;margin-top:12px">{{ $expiredCount }} promo in archivio (scadute)</p>
        @endif
    </div>
@endif
@endsection
