@extends('layouts.admin')

@section('title', 'Annunci — '.$tenant->name)

@section('content')
<div class="card">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;align-items:center;margin-bottom:8px">
        <h1 style="margin:0">Annunci — {{ $tenant->name }}</h1>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="{{ route('admin.classifieds.create', $tenant) }}" class="btn">+ Nuovo annuncio</a>
            <a href="{{ route('classifieds.tenant-board', $tenant) }}" target="_blank" class="btn btn-secondary">Vedi bacheca pubblica ↗</a>
            <a href="{{ route('app.home', $tenant) }}" class="btn btn-secondary">← Home</a>
        </div>
    </div>
    <p style="color:#666;margin:0 0 20px">Affitti, case vacanza e vendita immobili. Tocca ✎ per modificare, ✕ per eliminare.</p>

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
                'badge' => $ad->isPublished() ? 'Pubblicato' : 'Bozza',
                'tone' => $ad->isPublished() ? 'ok' : 'warn',
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
