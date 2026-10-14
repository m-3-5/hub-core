@extends('layouts.admin')

@section('title', 'Promozioni — '.$tenant->name)

@section('content')
<div class="card">
    <div class="ph-row">
        <div>
            <h1>Promozioni — {{ $tenant->name }}</h1>
            <p style="color:#666;margin-top:6px">Tocca una promo per aprirla, ✎ per modificarla, ✕ per eliminarla.</p>
        </div>
        <div class="ph-act">
            <div class="chips">
                <a class="btn btn-secondary" href="{{ route('promo.archive', $tenant) }}" target="_blank">Vedi archivio pubblico ↗</a>
                <a class="btn btn-secondary" href="{{ route('app.home', $tenant) }}">← Home</a>
            </div>
            <div class="fab-bar"><a class="btn" href="{{ route('admin.wizard.show', [$tenant, 'promo']) }}">+ Nuova promo</a></div>
        </div>
    </div>

    @if ($toApprove->isNotEmpty())
        <style>
            .appr { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }
            .appr__card { border: 2px solid #f59e0b; background: #fffbeb; border-radius: 16px; padding: 12px; display: flex; flex-direction: column; gap: 10px; }
            .appr__top { display: flex; gap: 12px; align-items: flex-start; }
            .appr__top img { width: 84px; height: 84px; object-fit: cover; border-radius: 12px; flex: none; background: #fff; }
            .appr__top h3 { font-size: 1rem; margin: 0 0 4px; }
            .appr__tag { display: inline-block; background: #1877F2; color: #fff; font-size: .72rem; font-weight: 700; border-radius: 999px; padding: 2px 10px; }
            .appr__top p { font-size: .85rem; color: #6b6b7b; margin: 4px 0 0; }
            .appr__btns { display: flex; flex-wrap: wrap; gap: 8px; }
            .appr__btns form { margin: 0; }
            .appr__btns .btn { min-height: 48px; display: inline-flex; align-items: center; justify-content: center; }
        </style>
        <h2 style="font-size:1.1rem;margin:20px 0 12px;color:#b45309">Da approvare ({{ $toApprove->count() }})</h2>
        <div class="appr">
            @foreach ($toApprove as $promo)
                <article class="appr__card">
                    <div class="appr__top">
                        @if ($promo->imageUrl())<img src="{{ $promo->imageUrl() }}" alt="" loading="lazy">@endif
                        <div>
                            <span class="appr__tag">{{ $promo->importedFromLabel() }}</span>
                            <h3><a href="{{ route('admin.promos.show', [$tenant, $promo]) }}">{{ $promo->title }}</a></h3>
                            @if ($promo->sourceUrl())<p><a href="{{ $promo->sourceUrl() }}" target="_blank" rel="noopener">Vedi il post originale ↗</a></p>@endif
                            <p>{{ $promo->ends_at ? 'Scade il '.$promo->ends_at->format('d/m/Y') : 'Se approvata, vale un mese' }}</p>
                        </div>
                    </div>
                    <div class="appr__btns">
                        <form method="POST" action="{{ route('admin.promos.publish', [$tenant, $promo]) }}">@csrf<button type="submit" class="btn">Approva e pubblica</button></form>
                        <a class="btn btn-secondary" href="{{ route('admin.promos.edit', [$tenant, $promo]) }}">Modifica</a>
                        <form method="POST" action="{{ route('admin.promos.destroy', [$tenant, $promo]) }}" onsubmit="return confirm('Scartare questa promo? Lo stesso post non tornerà.')">@csrf @method('DELETE')<button type="submit" class="btn btn-danger">Elimina</button></form>
                    </div>
                </article>
            @endforeach
        </div>
    @endif

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
