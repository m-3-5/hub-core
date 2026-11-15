@extends('layouts.app')

@section('title', 'Home')

@section('content')
@php
    $typeLabels = ['azienda' => 'Azienda', 'privato' => 'Privato', 'ente' => 'Ente'];
    $tenantType = $tenant->type ?: 'azienda';
    $ownModules = collect($hubModules)->filter(fn ($m) => $m['fits_type']); // usato da Max (max-assistant)
    $logo = app(\App\Services\TenantBrandManager::class)->logoUrl($tenant);
    $todo = [];
    if (($overview['messages_new'] ?? 0) > 0) $todo[] = ['messages', $overview['messages_new'].' '.($overview['messages_new'] === 1 ? 'messaggio nuovo' : 'messaggi nuovi'), 'Rispondi ai tuoi clienti', route('admin.customer-tickets.index', $tenant), '#e65100'];
    if (($overview['promos_draft'] ?? 0) > 0) $todo[] = ['promo', $overview['promos_draft'].' '.($overview['promos_draft'] === 1 ? 'promo in bozza' : 'promo in bozza'), 'Rivedile e pubblicale', route('admin.promos.index', $tenant), '#8b5cf6'];
    if (($overview['frozen_count'] ?? 0) > 0) $todo[] = ['shield', $overview['frozen_count'].' '.($overview['frozen_count'] === 1 ? 'pagamento bloccato' : 'pagamenti bloccati'), 'C\'è un problema da risolvere', route('admin.orders.index', $tenant), '#dc2626'];
@endphp

<header class="ah-top">
    <span class="ah-av" style="--av: {{ $tenant->primary_color ?: '#6366f1' }}">@if ($logo)<img src="{{ $logo }}" alt="">@else{{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}@endif</span>
    <div class="ah-top__t">
        <h1>{{ $tenant->name }}</h1>
        <p>{{ $typeLabels[$tenantType] ?? 'Azienda' }} · Cosa vuoi fare oggi?</p>
    </div>
    <div class="app-actions only-mobile">
        <a class="btn btn-ghost" href="{{ route('admin.customer-tickets.index', $tenant) }}">Messaggi clienti
            @if ($newCustomerTicketsCount)<span class="ah-pill">{{ $newCustomerTicketsCount }}</span>@endif
        </a>
        <a class="btn btn-ghost" href="{{ route('admin.profile.edit', $tenant) }}">Profilo</a>
        @if (auth()->user()->isSuperAdmin())
            <a class="btn btn-ghost" href="{{ route('app.index') }}">Attività</a>
            <a class="btn btn-ghost" href="{{ route('admin.dashboard') }}">Admin</a>
        @endif
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost">Esci</button>
        </form>
    </div>
</header>

@include('layouts.partials.install-app')

@if ($showSite)
    @include('app.partials.site-hero', ['siteUrl' => $siteUrl, 'hasSite' => $hasSite, 'launchEur' => $launchEur])
@endif

@if (! empty($todo))
<section class="ah-sec">
    <h2 class="ah-h">Da fare adesso</h2>
    <div class="ah-todo">
        @foreach ($todo as $t)
            <a class="ah-todo__i" href="{{ $t[3] }}" style="--c: {{ $t[4] }}">
                <span class="ah-ic">@include('layouts.partials.icon', ['name' => $t[0], 'size' => 21])</span>
                <span><b>{{ $t[1] }}</b><small>{{ $t[2] }}</small></span>
                <span class="ah-go">@include('layouts.partials.icon', ['name' => 'arrow', 'size' => 18])</span>
            </a>
        @endforeach
    </div>
</section>
@endif

<section class="ah-sec">
    <h2 class="ah-h">Cosa vuoi fare?</h2>
    @include('app.partials.service-grid', ['tiles' => $tiles])
</section>

<section class="ah-sec">
    <h2 class="ah-h">Il tuo lavoro in breve</h2>
    @include('admin.partials.tenant-kpis', ['tenant' => $tenant, 's' => $overview])
</section>

@if ($recentPromos->isNotEmpty() || $archivedPromos->isNotEmpty())
<section class="ah-sec">
    <div class="ah-row">
        <h2 class="ah-h">Promozioni</h2>
        <span class="ah-links"><a href="{{ route('admin.promos.index', $tenant) }}">Gestisci</a><a href="{{ route('promo.archive', $tenant) }}" target="_blank" rel="noopener">Archivio pubblico ↗</a></span>
    </div>
    <div class="ah-scroll">
        @foreach ($recentPromos as $promo)
            <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}" class="ah-card">
                <span class="ui-media">@if ($promo->imageUrl())<img src="{{ $promo->imageUrl() }}" alt="{{ $promo->title }}" loading="lazy">@else<span class="ui-media__ph">@include('layouts.partials.icon', ['name' => 'promo', 'size' => 28])</span>@endif</span>
                <b>{{ $promo->title }}</b><small>{{ $promo->expiryLabel() ?? 'Attiva' }}</small>
            </a>
        @endforeach
        @foreach ($archivedPromos as $promo)
            <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}" class="ah-card ah-card--off">
                <span class="ui-media">@if ($promo->imageUrl())<img src="{{ $promo->imageUrl() }}" alt="{{ $promo->title }}" loading="lazy">@else<span class="ui-media__ph">@include('layouts.partials.icon', ['name' => 'promo', 'size' => 28])</span>@endif<span class="ah-badge">Scaduta</span></span>
                <b>{{ $promo->title }}</b><small>{{ $promo->expiryLabel() }}</small>
            </a>
        @endforeach
    </div>
</section>
@endif

@if ($recentServices->isNotEmpty())
<section class="ah-sec">
    <div class="ah-row">
        <h2 class="ah-h">I tuoi servizi</h2>
        <span class="ah-links"><a href="{{ route('admin.services.index', $tenant) }}">Gestisci</a></span>
    </div>
    <div class="ah-scroll">
        @foreach ($recentServices as $service)
            <a href="{{ route('admin.services.show', [$tenant, $service]) }}" class="ah-card">
                <span class="ui-media">@if ($service->coverImageUrl())<img src="{{ $service->coverImageUrl() }}" alt="{{ $service->title }}" loading="lazy">@else<span class="ui-media__ph">@include('layouts.partials.icon', ['name' => 'services', 'size' => 28])</span>@endif</span>
                <b>{{ $service->title }}</b><small>{{ $service->amountEuros() }} €</small>
            </a>
        @endforeach
    </div>
</section>
@endif

@include('app.partials.max-assistant')
@include('app.partials.module-request-modal')
@endsection
