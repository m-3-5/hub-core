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

@if ($recentPromos->isNotEmpty())
    <div class="section-card">
        <h2>Promo recenti</h2>
        <ul class="promo-list">
            @foreach ($recentPromos as $promo)
                <li>
                    <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}">{{ $promo->title }}</a>
                    <span class="status {{ $promo->isPublished() ? 'status-published' : 'status-draft' }}">
                        {{ $promo->isPublished() ? 'Pubblicata' : 'Bozza' }}
                    </span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
