@php
    $connectState = \M35\HubPayments\Support\TenantConnect::state($tenant);
    $connectAvailable = \M35\HubPayments\Services\StripeConnectService::isAvailable();
@endphp
<div class="card" style="background:linear-gradient(135deg,#eef2ff,#fdf2f8);margin-bottom:24px;padding:20px;border:2px solid #c7d2fe">
    <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap;justify-content:space-between">
        <div style="flex:1;min-width:240px">
            <h2 style="margin:0 0 6px;font-size:1.1rem">🛡️ Pagamenti protetti Hub Core</h2>
            @if ($connectState === 'ready')
                <p style="margin:0;color:#2e7d32;font-weight:600">✓ Collegato: ricevi i soldi delle vendite fatte su inm35.it.</p>
            @elseif ($connectState === 'incomplete')
                <p style="margin:0;color:#e65100;font-weight:600">Quasi fatto: Stripe ha ancora bisogno di alcuni dati.</p>
            @else
                <p style="margin:0;color:#444">Ricevi i soldi delle vendite fatte su inm35.it.</p>
            @endif
            <p style="margin:10px 0 0">@include('layouts.partials.costs-link')</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;min-width:200px">
            @if (! $connectAvailable)
                <span style="color:#888;font-size:.85rem">Non ancora attivi sul sistema.</span>
            @else
                <form method="POST" action="{{ route('admin.connect.start', $tenant) }}" style="display:grid;gap:8px">
                    @csrf
                    @include('hub-payments::admin.partials.seller-terms', ['tenant' => $tenant])
                    <button type="submit" class="btn" style="width:100%;border:0;cursor:pointer">
                        {{ $connectState === 'none' ? 'Collega i pagamenti protetti' : ($connectState === 'incomplete' ? 'Completa i dati' : 'Modifica i dati di pagamento') }}
                    </button>
                </form>
                @if ($connectState !== 'none')
                    <form method="POST" action="{{ route('admin.connect.sync', $tenant) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary" style="width:100%;border:0;cursor:pointer">Aggiorna stato</button>
                    </form>
                @endif
                <a class="btn btn-secondary" style="text-align:center" href="{{ route('admin.payout.setup', $tenant) }}">Altri modi di incasso</a>
                <a href="{{ route('guides.index') }}" target="_blank" style="font-size:.85rem;text-align:center">📖 Guide: come ricevere i soldi</a>
                @if ($connectState === 'ready')
                    <form method="POST" action="{{ route('admin.connect.dashboard', $tenant) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary" style="width:100%;border:0;cursor:pointer">Vedi i miei bonifici</button>
                    </form>
                @endif
            @endif
        </div>
    </div>
    @error('connect')
        <p class="error" style="margin-top:10px">{{ $message }}</p>
    @enderror
</div>
