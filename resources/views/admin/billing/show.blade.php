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
            <p style="margin:0;color:#2e7d32;font-weight:600">✓ Abbonamento {{ $tiers[$tenant->tier()]['label'] }} attivo ({{ $tenant->billing_interval === 'year' ? 'annuale' : 'mensile' }})</p>
        @elseif ($tenant->isFreeTier())
            <p style="margin:0;color:#2e7d32;font-weight:600">✓ Piano gratuito Privato — nessuna scadenza</p>
            <p style="margin:8px 0 0;font-size:.9rem;color:#666">
                Incluse {{ \App\Support\TenantPromoQuota::includedLimit($tenant) }} promo al mese gratis
                ({{ \App\Support\TenantPromoQuota::remaining($tenant) }} rimaste questo mese) —
                oltre la quota, ogni promo extra è a pagamento (vedi registro sotto).
            </p>
        @elseif ($tenant->onTrial())
            <p style="margin:0;font-weight:600">Prova gratuita — {{ $tenant->trialDaysRemaining() }} giorni rimasti (fino al {{ $tenant->trial_ends_at->format('d/m/Y') }})</p>
            <p style="margin:6px 0 0;font-size:.9rem;color:#666">Non devi fare nulla ora. Alla fine ti chiediamo solo l'euro di lancio per continuare.</p>
        @else
            <p style="margin:0;color:#c62828;font-weight:600">La prova gratuita è finita — attiva con {{ config('services.hub_billing.launch_offer_price_eur') }} € per continuare</p>
        @endif
    </div>

    @if ($tenant->isFreeTier())
        {{-- Niente pulsanti abbonamento hub per i privati: il loro modello è a quota, non a canone fisso --}}
    @elseif (! $configured)
        <p style="color:#666">La fatturazione non è ancora attiva — contatta il team Hub Core.</p>
    @elseif (! $tenant->hasActiveSubscription())
        <div style="background:linear-gradient(135deg,#eef2ff,#fdf2f8);border:2px solid #c7d2fe;border-radius:16px;padding:20px;margin-bottom:18px">
            <strong style="font-size:1.1rem">🎉 Offerta di lancio: attiva con {{ config('services.hub_billing.launch_offer_price_eur') }} €</strong>
            <p style="margin:6px 0 14px;color:#444;font-size:.92rem">Paghi {{ config('services.hub_billing.launch_offer_price_eur') }} € adesso (tutti i moduli inclusi). Poi hai {{ config('services.hub_billing.trial_days') }} giorni di prova e solo dopo parte il canone (da {{ $monthlyPrice }} €/mese). Disdici quando vuoi.</p>
            <form method="POST" action="{{ route('admin.billing.launch', $tenant) }}">
                @csrf
                <div style="display:grid;gap:10px;grid-template-columns:1fr 1fr;margin-bottom:12px">
                    @foreach ($tiers as $key => $t)
                        <label style="display:block;background:#fff;border:2px solid #c7d2fe;border-radius:14px;padding:12px;cursor:pointer">
                            <input type="radio" name="tier" value="{{ $key }}" @checked($tenant->tier() === $key) style="width:auto;margin:0 6px 0 0">
                            <strong>{{ $t['label'] }}</strong>
                            <span style="display:block;font-size:.85rem;color:#555;margin-top:4px">€{{ $t['monthly'] }}/mese · €{{ $t['annual'] }}/anno</span>
                            <span style="display:block;font-size:.8rem;color:#777;margin-top:2px">{{ $key === 'premium' ? 'In più: sito web con Max, '.$t['promo_quota'].' promo, assistenza prioritaria' : 'Tutto il necessario: '.$t['promo_quota'].' promo, servizi, annunci' }}</span>
                        </label>
                    @endforeach
                </div>
                <button type="submit" class="btn" style="width:100%;border:0;cursor:pointer;font-size:1.05rem;padding:14px">Attiva con {{ config('services.hub_billing.launch_offer_price_eur') }} €</button>
            </form>
        </div>
        <p style="margin:0 0 10px;color:#666;font-size:.88rem">Oppure scegli direttamente il canone:</p>
        <form method="POST" action="{{ route('admin.billing.checkout', $tenant) }}">
            @csrf
            <div style="display:grid;gap:10px;grid-template-columns:1fr 1fr;margin-bottom:12px">
                @foreach ($tiers as $key => $t)
                    <label style="display:block;background:#f6f7fb;border:2px solid #e5e7f5;border-radius:14px;padding:10px;cursor:pointer">
                        <input type="radio" name="tier" value="{{ $key }}" @checked($tenant->tier() === $key) style="width:auto;margin:0 6px 0 0"><strong>{{ $t['label'] }}</strong>
                    </label>
                @endforeach
            </div>
            <div style="display:grid;gap:12px;grid-template-columns:1fr 1fr">
                <button type="submit" name="interval" value="month" class="btn" style="width:100%;border:0;cursor:pointer">Mensile</button>
                <button type="submit" name="interval" value="year" class="btn btn-secondary" style="width:100%;border:0;cursor:pointer">Annuale (risparmi)</button>
            </div>
        </form>
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
