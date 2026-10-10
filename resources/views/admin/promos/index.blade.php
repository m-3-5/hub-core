@extends('layouts.admin')

@section('title', 'Promozioni — '.$tenant->name)

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1>Promozioni — {{ $tenant->name }}</h1>
            <p style="color:#666;margin-top:6px">Tocca una promo per aprirla, ✎ per modificarla, ✕ per eliminarla.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="{{ route('admin.wizard.show', [$tenant, 'promo']) }}">+ Nuova promo</a>
            <a class="btn btn-secondary" href="{{ route('promo.archive', $tenant) }}" target="_blank">Vedi archivio pubblico ↗</a>
            <a class="btn btn-secondary" href="{{ route('app.home', $tenant) }}">← Home</a>
        </div>
    </div>

    <h2 style="font-size:1.1rem;margin:20px 0 12px;color:#e91e8c">Attive ({{ $active->count() }})</h2>
    <div class="mgrid">
        @foreach ($active as $promo)
            @include('layouts.partials.media-card', [
                'title' => $promo->title,
                'href' => route('admin.promos.show', [$tenant, $promo]),
                'image' => $promo->imageUrl(),
                'meta' => $promo->expiryLabel(),
                'badge' => 'Attiva',
                'tone' => 'ok',
                'editUrl' => route('admin.promos.edit', [$tenant, $promo]),
                'deleteUrl' => route('admin.promos.destroy', [$tenant, $promo]),
                'deleteConfirm' => 'Eliminare la promo «'.$promo->title.'»? Sparirà anche dal sito.',
                'openUrl' => route('promo.show', [$tenant, $promo]),
            ])
        @endforeach
        <div class="mcard mcard--new"><a href="{{ route('admin.wizard.show', [$tenant, 'promo']) }}"><span><b>＋</b>Nuova promo</span></a></div>
    </div>

    @if ($expired->isNotEmpty())
        <h2 style="font-size:1.1rem;margin:20px 0 12px;color:#64748b">Archivio scadute ({{ $expired->count() }})</h2>
        <div class="mgrid">
            @foreach ($expired as $promo)
                @include('layouts.partials.media-card', [
                    'title' => $promo->title,
                    'href' => route('admin.promos.show', [$tenant, $promo]),
                    'image' => $promo->imageUrl(),
                    'meta' => $promo->expiryLabel(),
                    'badge' => 'Scaduta',
                    'tone' => 'off',
                    'muted' => true,
                    'editUrl' => route('admin.promos.edit', [$tenant, $promo]),
                    'deleteUrl' => route('admin.promos.destroy', [$tenant, $promo]),
                    'deleteConfirm' => 'Eliminare la promo scaduta «'.$promo->title.'»?',
                    'openUrl' => route('promo.show', [$tenant, $promo]),
                ])
            @endforeach
        </div>
    @endif

    @if ($drafts->isNotEmpty())
        <h2 style="font-size:1.1rem;margin:20px 0 12px">Bozze ({{ $drafts->count() }})</h2>
        <div class="mgrid">
            @foreach ($drafts as $promo)
                @include('layouts.partials.media-card', [
                    'title' => $promo->title,
                    'href' => route('admin.promos.show', [$tenant, $promo]),
                    'image' => $promo->imageUrl(),
                    'meta' => 'Non ancora pubblicata',
                    'badge' => 'Bozza',
                    'tone' => 'warn',
                    'editUrl' => route('admin.promos.edit', [$tenant, $promo]),
                    'deleteUrl' => route('admin.promos.destroy', [$tenant, $promo]),
                    'deleteConfirm' => 'Eliminare la bozza «'.$promo->title.'»?',
                ])
            @endforeach
        </div>
    @endif
</div>
@endsection
