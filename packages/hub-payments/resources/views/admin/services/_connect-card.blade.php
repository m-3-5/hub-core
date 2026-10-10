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
                <p style="margin:0;color:#444">Il cliente paga tramite Hub Core e i soldi ti arrivano <strong>dopo la consegna</strong>: tu sei sicuro di essere pagato, il cliente è sicuro di ricevere quello che ha comprato.</p>
            @endif
            @php $feeEx = \M35\HubPayments\Support\StripeFees::example(); @endphp
            <div style="margin:12px 0 0;background:#fff;border:1px solid #e0e7ff;border-radius:12px;padding:12px 14px;font-size:.88rem;color:#374151">
                <strong>💶 Trattenute di Stripe</strong> — su ogni pagamento con carta Stripe trattiene una commissione, indicativamente <strong>{{ \M35\HubPayments\Support\StripeFees::rateLabel() }}</strong> (di più per carte extra-UE). È un costo di Stripe, non nostro: viene sottratta dall'incasso e ti mostriamo sempre quanto ricevi.
                <div style="margin-top:6px;color:#4b5563">Esempio: vendita da {{ $feeEx['amount'] }} € → trattenuta circa {{ $feeEx['fee'] }} € → ricevi circa <strong>{{ $feeEx['net'] }} €</strong>. Per i bonifici verso il tuo conto possono esserci piccoli costi di Stripe, che vedi nella tua pagina Stripe.</div>
            </div>
            <p style="margin:8px 0 0;color:#666;font-size:.88rem">Sul tuo sito puoi continuare a vendere anche direttamente, con le tue chiavi Stripe qui sotto. I pagamenti protetti valgono per le vendite fatte tramite inm35.it.</p>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;min-width:200px">
            @if (! $connectAvailable)
                <span style="color:#888;font-size:.85rem">Non ancora attivi sul sistema.</span>
            @else
                <form method="POST" action="{{ route('admin.connect.start', $tenant) }}">
                    @csrf
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
