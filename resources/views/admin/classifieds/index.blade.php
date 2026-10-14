@extends('layouts.admin')

@section('title', 'Annunci — '.$tenant->name)

@section('content')
<div class="card">
    <div class="ph-row" style="align-items:center;margin-bottom:8px">
        <h1 style="margin:0">Annunci — {{ $tenant->name }}</h1>
        <div class="ph-act">
            <div class="chips">
                <a href="{{ route('classifieds.tenant-board', $tenant) }}" target="_blank" class="btn btn-secondary">Vedi bacheca pubblica ↗</a>
                <a href="{{ route('app.home', $tenant) }}" class="btn btn-secondary">← Home</a>
            </div>
            <div class="fab-bar"><a href="{{ route('admin.classifieds.create', $tenant) }}" class="btn">+ Nuovo annuncio</a></div>
        </div>
    </div>
    <p style="color:#666;margin:0 0 20px">Affitti, case vacanza, immobili e oggetti in vendita. Tocca ✎ per modificare, ✕ per eliminare.</p>

    @if (\M35\HubPayments\Services\StripeConnectService::isAvailable())
        @include('hub-payments::admin.services._connect-card', ['tenant' => $tenant])
        <p style="margin:-8px 0 20px"><a href="{{ route('admin.orders.index', $tenant) }}">🧾 Ordini e pagamenti ricevuti →</a></p>
    @endif

    @if (session('success'))
        <div style="background:#e8f5e9;color:#1b5e20;border-radius:10px;padding:10px 14px;margin-bottom:16px;word-break:break-all">{{ session('success') }}</div>
    @endif

    <div class="mgrid">
        @foreach ($ads as $ad)
            @include('layouts.partials.media-card', [
                'title' => $ad->title,
                'href' => route('admin.classifieds.edit', [$tenant, $ad]),
                'image' => $ad->coverUrl(),
                'meta' => collect([$ad->categoryLabel(), $ad->zone, $ad->priceLabel()])->filter()->implode(' · '),
                'badge' => $ad->isSold() ? 'Venduto' : ($ad->isPublished() ? 'Pubblicato' : 'Bozza'),
                'tone' => $ad->isSold() ? 'ok' : ($ad->isPublished() ? 'ok' : 'warn'),
                'editUrl' => route('admin.classifieds.edit', [$tenant, $ad]),
                'deleteUrl' => route('admin.classifieds.destroy', [$tenant, $ad]),
                'deleteConfirm' => 'Eliminare l\'annuncio «'.$ad->title.'»?',
                'openUrl' => $ad->isPublished() ? $ad->publicUrl() : null,
                'actionUrl' => $ad->isPublished() ? route('admin.classifieds.unpublish', [$tenant, $ad]) : route('admin.classifieds.publish', [$tenant, $ad]),
                'actionLabel' => $ad->isPublished() ? 'Ritira dal sito' : 'Pubblica',
            ])
        @endforeach
        <div class="mcard mcard--new"><a href="{{ route('admin.classifieds.create', $tenant) }}"><span><b>＋</b>Nuovo annuncio</span></a></div>
    </div>
</div>
@endsection
