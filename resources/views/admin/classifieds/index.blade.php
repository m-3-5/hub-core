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
    <p style="color:#666;margin:0 0 20px">Affitti, case vacanza e vendita immobili. Ogni annuncio ha la sua pagina pubblica con foto e modulo di contatto.</p>

    @if (session('success'))
        <div style="background:#e8f5e9;color:#1b5e20;border-radius:10px;padding:10px 14px;margin-bottom:16px;word-break:break-all">{{ session('success') }}</div>
    @endif

    @if ($ads->isEmpty())
        <p style="color:#666">Nessun annuncio per ora. Crea il primo con «Nuovo annuncio».</p>
    @endif

    @foreach ($ads as $ad)
        <div style="border:1px solid #eee;border-radius:12px;padding:14px;margin-bottom:12px;display:flex;gap:14px;flex-wrap:wrap;align-items:center">
            <div style="width:96px;height:72px;border-radius:8px;background:#f1f1f5 center/cover no-repeat;flex:none;{{ $ad->coverUrl() ? "background-image:url('".$ad->coverUrl()."')" : '' }}"></div>
            <div style="flex:1;min-width:200px">
                <strong>{{ $ad->title }}</strong>
                <div style="font-size:13px;color:#666">{{ $ad->categoryLabel() }} · {{ $ad->zone }}@if ($ad->priceLabel()) · {{ $ad->priceLabel() }}@endif</div>
                <span style="display:inline-block;margin-top:6px;font-size:12px;font-weight:700;padding:3px 10px;border-radius:999px;{{ $ad->isPublished() ? 'background:#e8f5e9;color:#2e7d32' : 'background:#fff3e0;color:#e65100' }}">
                    {{ $ad->isPublished() ? 'Pubblicato' : 'Bozza' }}
                </span>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a href="{{ route('admin.classifieds.edit', [$tenant, $ad]) }}" class="btn btn-secondary">Modifica</a>
                @if ($ad->isPublished())
                    <a href="{{ $ad->publicUrl() }}" target="_blank" class="btn btn-secondary">Apri ↗</a>
                    <form method="POST" action="{{ route('admin.classifieds.unpublish', [$tenant, $ad]) }}">@csrf<button class="btn btn-secondary" type="submit">Ritira</button></form>
                @else
                    <form method="POST" action="{{ route('admin.classifieds.publish', [$tenant, $ad]) }}">@csrf<button class="btn" type="submit">Pubblica</button></form>
                @endif
            </div>
        </div>
    @endforeach
</div>
@endsection
