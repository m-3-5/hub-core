@extends('layouts.admin')

@section('title', 'Abbonamento — '.$tenant->name)

@section('content')
<div class="card" style="max-width:720px">
    <h1 style="margin:0 0 8px">Abbonamento Hub Core</h1>
    <p style="margin:0 0 20px;color:#666">Gestisci l'abbonamento di {{ $tenant->name }} per continuare a usare Hub Core dopo la demo gratuita.</p>

    @error('billing')
        <p class="error">{{ $message }}</p>
    @enderror

    @if (request('checkout') === 'success')
        <p class="alert">Pagamento ricevuto! L'abbonamento sarà attivo a breve (di solito pochi secondi).</p>
    @elseif (request('checkout') === 'cancelled')
        <p class="alert alert-warning">Pagamento annullato — nessun addebito effettuato.</p>
    @endif

    <div style="background:#f6f7fb;border-radius:12px;padding:20px;margin-bottom:24px">
        @if ($tenant->hasActiveSubscription())
            <p style="margin:0;color:#2e7d32;font-weight:600">✓ Abbonamento attivo ({{ $tenant->billing_interval === 'year' ? 'annuale' : 'mensile' }})</p>
        @elseif ($tenant->onTrial())
            <p style="margin:0;font-weight:600">Demo gratuita — {{ $tenant->trial_ends_at->diffInDays(now()) }} giorni rimasti (scade il {{ $tenant->trial_ends_at->format('d/m/Y') }})</p>
        @else
            <p style="margin:0;color:#c62828;font-weight:600">Demo scaduta — abbonati per continuare a usare Hub Core</p>
        @endif
    </div>

    @if (! $configured)
        <p style="color:#666">La fatturazione non è ancora attiva — contatta il team Hub Core.</p>
    @elseif (! $tenant->hasActiveSubscription())
        <div style="display:grid;gap:12px;grid-template-columns:1fr 1fr">
            <form method="POST" action="{{ route('admin.billing.checkout', $tenant) }}">
                @csrf
                <input type="hidden" name="interval" value="month">
                <button type="submit" class="btn" style="width:100%;border:0;cursor:pointer">
                    Mensile — €{{ $monthlyPrice }}/mese
                </button>
            </form>
            <form method="POST" action="{{ route('admin.billing.checkout', $tenant) }}">
                @csrf
                <input type="hidden" name="interval" value="year">
                <button type="submit" class="btn btn-secondary" style="width:100%;border:0;cursor:pointer">
                    Annuale — €{{ $annualPrice }}/anno
                </button>
            </form>
        </div>
        <p style="margin-top:12px;font-size:.85rem;color:#666">Pagamento sicuro tramite Stripe. Puoi disdire in qualsiasi momento.</p>
    @endif

    <h2 style="margin:32px 0 6px;font-size:1.1rem">Moduli</h2>
    <p style="margin:0 0 16px;color:#666;font-size:.9rem">Cosa è attivo per {{ $tenant->name }}, e cosa manca ancora da costruire.</p>

    <table style="width:100%;border-collapse:collapse">
        <thead>
            <tr style="text-align:left;border-bottom:2px solid #eee">
                <th style="padding:8px"></th>
                <th style="padding:8px">Modulo</th>
                <th style="padding:8px">Stato</th>
                <th style="padding:8px">Attivazione</th>
                <th style="padding:8px"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($modules as $module)
                <tr style="border-bottom:1px solid #f0f0f0">
                    <td style="padding:8px;font-size:1.2rem">{{ $module['emoji'] }}</td>
                    <td style="padding:8px">
                        <strong>{{ $module['label'] }}</strong>
                        <div style="color:#666;font-size:.82rem">{{ $module['description'] }}</div>
                    </td>
                    <td style="padding:8px">
                        @if (! $module['buildable'])
                            <span style="color:#999">—</span>
                        @elseif ($module['enabled'])
                            <span style="color:#2e7d32;font-weight:600">● Attivo</span>
                        @else
                            <span style="color:#999;font-weight:600">○ Disattivato</span>
                        @endif
                    </td>
                    <td style="padding:8px">
                        @if (! $module['buildable'])
                            <span style="color:#999">Prossimamente</span>
                        @elseif ($module['activation_paid'] === true)
                            <span style="color:#2e7d32;font-weight:600">✓ Pagato</span>
                        @elseif ($module['activation_paid'] === false)
                            <span style="color:#c62828;font-weight:600">Non pagato</span>
                        @else
                            <span style="color:#999">—</span>
                        @endif
                    </td>
                    <td style="padding:8px;text-align:right">
                        @if ($module['buildable'])
                            <form method="POST" action="{{ route('admin.billing.modules.toggle', [$tenant, $module['key']]) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn-secondary">
                                    {{ $module['enabled'] ? 'Disattiva' : 'Attiva' }}
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin-top:12px"><a href="{{ route('admin.module-billing.show', $tenant) }}">Vedi registro costi e pagamenti dettagliato →</a></p>
</div>
@endsection
