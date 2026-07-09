@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="card">
    <h1>Clienti</h1>
    <p style="color:#666">Crea promo in bozza, controlla anteprima, poi pubblica su Beauty of Image.</p>
    @foreach ($tenants as $tenant)
        <div style="padding:20px 0;border-bottom:1px solid #eee">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px">
                <div>
                    <strong>{{ $tenant->name }}</strong>
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#666;background:#f1f1f4;padding:2px 8px;border-radius:999px;margin-left:6px">{{ ucfirst($tenant->type ?? 'azienda') }}</span>
                    <div style="color:#888;font-size:14px">{{ $tenant->website }} · {{ $tenant->promos_count }} promo</div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn" href="{{ route('admin.promos.create', $tenant) }}">Nuova promo</a>
                    <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}"
                          onsubmit="return confirm('Eliminare definitivamente {{ $tenant->name }}? Verranno cancellati anche servizi, promo, registro pagamenti e gli utenti collegati solo a questo tenant. Azione irreversibile.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Elimina</button>
                    </form>
                </div>
            </div>
            @if ($tenant->promos->isNotEmpty())
                <ul style="list-style:none;padding:0;margin:0">
                    @foreach ($tenant->promos as $promo)
                        <li style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid #f0f0f0;gap:12px;flex-wrap:wrap">
                            <div>
                                <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}" style="color:#1a1a2e;font-weight:600;text-decoration:none">{{ $promo->title }}</a>
                                <span style="font-size:12px;margin-left:8px;padding:3px 8px;border-radius:999px;background:{{ $promo->isPublished() ? '#e8f5e9' : '#fff3e0' }};color:{{ $promo->isPublished() ? '#2e7d32' : '#e65100' }}">
                                    {{ $promo->isPublished() ? 'Pubblicata' : 'Bozza' }}
                                </span>
                            </div>
                            <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}" style="font-size:14px">Gestisci →</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</div>
@endsection
