{{-- Stato della prova in una riga corta, senza prezzi (i costi stanno nella pagina Abbonamento). --}}
@isset($tenant)
    @if ($tenant->isGuestPending() && auth()->check() && ! auth()->user()->isSuperAdmin())
        <a class="ui-trial" href="{{ route('guest.claim', ['torna' => '/'.ltrim(request()->path(), '/')]) }}">🚀 Vuoi andare online? <b>Registrati</b></a>
    @elseif ($tenant->trialExpired())
        <a class="ui-trial ui-trial--end" href="{{ route('admin.billing.show', $tenant) }}">⏳ Prova finita <b>Attiva</b></a>
    @elseif ($tenant->onTrial() && $tenant->trialDaysRemaining() <= 7)
        <a class="ui-trial" href="{{ route('admin.billing.show', $tenant) }}">⏳ Prova: {{ $tenant->trialDaysRemaining() }} {{ $tenant->trialDaysRemaining() === 1 ? 'giorno' : 'giorni' }} <b>Attiva</b></a>
    @endif
@endisset
