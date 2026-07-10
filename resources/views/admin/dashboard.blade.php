@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <h1 style="margin:0">Clienti</h1>
        <a class="btn btn-secondary" href="{{ route('admin.tickets.index') }}">
            🎫 Ticket clienti
            @if ($openTicketsCount > 0)
                <span style="background:#c62828;color:#fff;border-radius:999px;padding:1px 8px;font-size:12px;margin-left:6px">{{ $openTicketsCount }}</span>
            @endif
        </a>
    </div>
    <p style="color:#666">Crea promo in bozza, controlla anteprima, poi pubblica su Beauty of Image.</p>
    @foreach ($tenants as $t)
        <div style="padding:20px 0;border-bottom:1px solid #eee">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px">
                <div>
                    <strong>{{ $t->name }}</strong>
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#666;background:#f1f1f4;padding:2px 8px;border-radius:999px;margin-left:6px">{{ ucfirst($t->type ?? 'azienda') }}</span>
                    <div style="color:#888;font-size:14px">{{ $t->website }} · {{ $t->promos_count }} promo</div>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <a class="btn" href="{{ route('admin.promos.create', $t) }}">Nuova promo</a>
                    <form method="POST" action="{{ route('admin.tenants.destroy', $t) }}"
                          onsubmit="return confirm('Eliminare definitivamente {{ $t->name }}? Verranno cancellati anche servizi, promo, registro pagamenti e gli utenti collegati solo a questo tenant. Azione irreversibile.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Elimina</button>
                    </form>
                </div>
            </div>
            @if ($t->promos->isNotEmpty())
                <ul style="list-style:none;padding:0;margin:0">
                    @foreach ($t->promos as $promo)
                        <li style="display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-top:1px solid #f0f0f0;gap:12px;flex-wrap:wrap">
                            <div>
                                <a href="{{ route('admin.promos.show', [$t, $promo]) }}" style="color:#1a1a2e;font-weight:600;text-decoration:none">{{ $promo->title }}</a>
                                <span style="font-size:12px;margin-left:8px;padding:3px 8px;border-radius:999px;background:{{ $promo->isPublished() ? '#e8f5e9' : '#fff3e0' }};color:{{ $promo->isPublished() ? '#2e7d32' : '#e65100' }}">
                                    {{ $promo->isPublished() ? 'Pubblicata' : 'Bozza' }}
                                </span>
                            </div>
                            <a href="{{ route('admin.promos.show', [$t, $promo]) }}" style="font-size:14px">Gestisci →</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</div>
@endsection
