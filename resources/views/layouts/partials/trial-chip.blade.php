{{-- Stato in una riga corta, senza prezzi. Col sistema «paghi quello che pubblichi» non c'è prova:
     si avvisa solo se l'ultimo addebito non è andato (serve un'altra carta). --}}
@isset($tenant)
    @if ($tenant->isGuestPending() && auth()->check() && ! auth()->user()->isSuperAdmin())
        <a class="ui-trial" href="{{ route('guest.claim', ['torna' => '/'.ltrim(request()->path(), '/')]) }}">🚀 Vuoi andare online? <b>Registrati</b></a>
    @elseif (\App\Support\PayPerUse::enabled())
        @if (! $tenant->hasActiveSubscription() && ! empty($tenant->settings['ppu_card']['failed']))
            <a class="ui-trial ui-trial--end" href="{{ route('admin.credit.show', $tenant) }}">💳 Carta non passata <b>Cambia</b></a>
        @endif
    @elseif ($tenant->trialExpired())
        <a class="ui-trial ui-trial--end" href="{{ route('admin.billing.show', $tenant) }}">⏳ Prova finita <b>Attiva</b></a>
    @elseif ($tenant->onTrial() && $tenant->trialDaysRemaining() <= 7)
        <a class="ui-trial" href="{{ route('admin.billing.show', $tenant) }}">⏳ Prova: {{ $tenant->trialDaysRemaining() }} {{ $tenant->trialDaysRemaining() === 1 ? 'giorno' : 'giorni' }} <b>Attiva</b></a>
    @endif
@endisset
