@extends('layouts.admin')

@section('title', 'Servizi a pagamento — '.$tenant->name)

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1 style="margin:0 0 6px">Servizi a pagamento</h1>
            <p style="margin:0;color:#666">Crea link Stripe (carta + metodi extra attivi sul conto) per trattamenti e servizi del salone.</p>
        </div>
        @if ($stripeConfigured)
            <a class="btn" href="{{ route('admin.services.create', $tenant) }}">+ Nuovo servizio</a>
        @endif
    </div>

    @if (session('status'))
        <p class="alert">{{ session('status') }}</p>
    @endif

    <div class="alert" style="background:#eef2ff;color:#312e81;margin-bottom:20px">
        <strong>Demo inclusa:</strong>
        {{ $quota['remaining'] }} / {{ $quota['included'] }} servizi gratuiti rimasti.
        @if ($quota['remaining'] === 0)
            Per crearne altri: pacchetto da €{{ $quota['paid_price'] }} (pagamento hub in arrivo).
        @endif
    </div>

    <div class="card" style="background:#fafafa;margin-bottom:24px;padding:20px">
        <h2 style="margin:0 0 12px;font-size:1.1rem">Stripe del salone</h2>
        @if ($stripeConfigured)
            <p style="margin:0 0 12px;color:#2e7d32">✓ Collegato — chiave: <code>{{ $stripeMasked }}</code></p>
        @else
            <p style="margin:0 0 12px;color:#c62828">Inserisci la <strong>Secret key</strong> del conto Stripe Beauty (Dashboard → Sviluppatori → Chiavi API).</p>
        @endif
        <form method="POST" action="{{ route('admin.services.stripe-settings', $tenant) }}" style="display:grid;gap:12px;max-width:520px">
            @csrf
            <div>
                <label for="stripe_secret_key">Secret key</label>
                <input type="password" name="stripe_secret_key" id="stripe_secret_key" placeholder="sk_live_…" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px" autocomplete="off">
                @error('stripe_secret_key')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="stripe_publishable_key">Publishable key (opzionale)</label>
                <input type="text" name="stripe_publishable_key" id="stripe_publishable_key" placeholder="pk_live_…" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px">
            </div>
            <button type="submit" class="btn btn-secondary">Salva chiavi Stripe</button>
        </form>
    </div>

    @if ($services->isEmpty())
        <p style="color:#666">Nessun servizio ancora. Crea il primo link di pagamento per un trattamento.</p>
    @else
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="text-align:left;border-bottom:2px solid #eee">
                    <th style="padding:10px 8px">Servizio</th>
                    <th style="padding:10px 8px">Prezzo</th>
                    <th style="padding:10px 8px">Sito</th>
                    <th style="padding:10px 8px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($services as $service)
                    <tr style="border-bottom:1px solid #f0f0f0">
                        <td style="padding:12px 8px">
                            <a href="{{ route('admin.services.show', [$tenant, $service]) }}" style="color:#1a1a2e;font-weight:600;text-decoration:none">{{ $service->title }}</a>
                        </td>
                        <td style="padding:12px 8px">{{ $service->amountEuros() }} €</td>
                        <td style="padding:12px 8px">{{ $service->published_to_site ? 'Pubblicato' : '—' }}</td>
                        <td style="padding:12px 8px;text-align:right">
                            <a href="{{ route('admin.services.show', [$tenant, $service]) }}">Apri link →</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
