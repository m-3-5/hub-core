@extends('layouts.admin')

@section('title', 'Promozioni — '.$tenant->name)

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1>Promozioni — {{ $tenant->name }}</h1>
            <p style="color:#666;margin-top:6px">Attive, archivio scadute e bozze.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn" href="{{ route('admin.promos.create', $tenant) }}">+ Nuova promo</a>
            <a class="btn btn-secondary" href="{{ route('promo.archive', $tenant) }}" target="_blank">Vedi archivio pubblico ↗</a>
            <a class="btn btn-secondary" href="{{ route('app.home', $tenant) }}">← Home</a>
        </div>
    </div>

    <h2 style="font-size:1.1rem;margin:20px 0 12px;color:#e91e8c">Attive ({{ $active->count() }})</h2>
    @if ($active->isEmpty())
        <p style="color:#888">Nessuna promo attiva.</p>
    @else
        <ul class="promo-list" style="list-style:none;padding:0;margin:0 0 24px">
            @foreach ($active as $promo)
                <li style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #eee">
                    <div>
                        <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}"><strong>{{ $promo->title }}</strong></a>
                        @if ($promo->expiryLabel())
                            <br><small style="color:#666">{{ $promo->expiryLabel() }}</small>
                        @endif
                    </div>
                    <a class="btn btn-secondary" style="font-size:.85rem;padding:6px 12px" href="{{ route('promo.show', [$tenant, $promo]) }}" target="_blank">Pubblica ↗</a>
                </li>
            @endforeach
        </ul>
    @endif

    <h2 style="font-size:1.1rem;margin:20px 0 12px;color:#64748b">Archivio scadute ({{ $expired->count() }})</h2>
    @if ($expired->isEmpty())
        <p style="color:#888">Nessuna promo scaduta.</p>
    @else
        <ul class="promo-list" style="list-style:none;padding:0;margin:0 0 24px;opacity:.85">
            @foreach ($expired as $promo)
                <li style="display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid #eee">
                    <div>
                        <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}">{{ $promo->title }}</a>
                        <br><small style="color:#94a3b8">{{ $promo->expiryLabel() }}</small>
                    </div>
                    <a class="btn btn-secondary" style="font-size:.85rem;padding:6px 12px" href="{{ route('promo.show', [$tenant, $promo]) }}" target="_blank">Vedi ↗</a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($drafts->isNotEmpty())
        <h2 style="font-size:1.1rem;margin:20px 0 12px">Bozze ({{ $drafts->count() }})</h2>
        <ul class="promo-list" style="list-style:none;padding:0">
            @foreach ($drafts as $promo)
                <li style="padding:8px 0;border-bottom:1px solid #eee">
                    <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}">{{ $promo->title }}</a>
                    <span style="color:#f59e0b;font-size:.85rem"> · Bozza</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
