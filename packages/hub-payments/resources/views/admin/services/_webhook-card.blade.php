@php
    $webhookConfigured = \M35\HubPayments\Support\TenantStripeWebhook::isConfigured($tenant);
    $webhookUrl = \M35\HubPayments\Support\TenantStripeWebhook::url($tenant);
    $lastEvent = \M35\HubPayments\Support\TenantStripeWebhook::lastEventAt($tenant);
@endphp
<div class="card" style="background:#fafafa;margin-bottom:24px;padding:20px">
    <h2 style="margin:0 0 8px;font-size:1.1rem">Notifica pagamenti (webhook Stripe)</h2>
    <p style="margin:0 0 12px;color:#555;font-size:.92rem">
        Serve perché i pagamenti risultino <strong>pagati</strong> (preventivi e carrello) e per ricevere l'email con articoli, importo e dati del cliente.
    </p>

    @if ($webhookConfigured)
        <p style="margin:0 0 12px;color:#2e7d32">
            ✓ Collegato.
            @if ($lastEvent)
                Ultimo evento ricevuto: {{ \Illuminate\Support\Carbon::parse($lastEvent)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}.
            @else
                Nessun evento ricevuto ancora: arriverà con il prossimo pagamento.
            @endif
        </p>
    @else
        <p style="margin:0 0 12px;color:#c62828">Non ancora collegato: senza questo passo i pagamenti restano "in attesa".</p>
    @endif

    @error('webhook')
        <p class="error">{{ $message }}</p>
    @enderror
    @error('webhook_secret')
        <p class="error">{{ $message }}</p>
    @enderror

    @if ($stripeConfigured)
        <form method="POST" action="{{ route('admin.services.stripe-webhook.create', $tenant) }}" style="margin:0 0 12px">
            @csrf
            <button type="submit" class="btn btn-secondary">{{ $webhookConfigured ? 'Ricrea il webhook su Stripe' : 'Crea il webhook su Stripe' }}</button>
        </form>
    @endif

    <details @if (session('webhook_manual') || $errors->has('webhook_secret')) open @endif>
        <summary style="cursor:pointer;font-weight:600">Preferisco farlo a mano su Stripe</summary>
        <ol style="margin:12px 0 12px 18px;padding:0;line-height:1.7;font-size:.92rem">
            <li>Su Stripe apri <strong>Sviluppatori → Webhook → Aggiungi endpoint</strong>.</li>
            <li>Indirizzo dell'endpoint: <code style="word-break:break-all">{{ $webhookUrl }}</code></li>
            <li>Eventi da selezionare: <code>checkout.session.completed</code> e <code>checkout.session.async_payment_succeeded</code>.</li>
            <li>Salva, poi copia il <strong>Segreto di firma</strong> (inizia con <code>whsec_</code>) e incollalo qui sotto.</li>
        </ol>
        <form method="POST" action="{{ route('admin.services.stripe-webhook.secret', $tenant) }}" style="display:grid;gap:10px;max-width:520px">
            @csrf
            <input type="password" name="webhook_secret" placeholder="whsec_…" autocomplete="off" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px">
            <button type="submit" class="btn btn-secondary">Salva segreto di firma</button>
        </form>
    </details>
</div>
