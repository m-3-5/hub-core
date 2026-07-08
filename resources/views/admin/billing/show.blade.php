@extends('layouts.admin')

@section('title', 'Abbonamento — '.$tenant->name)

@section('content')
<div class="card" style="max-width:640px">
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
</div>
@endsection
