@extends('layouts.admin')

@section('title', 'Servizi a pagamento — '.$tenant->name)

@section('content')
<style>
    .svc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
    .svc-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 6px 20px rgba(0,0,0,.06); display: flex; flex-direction: column; }
    .svc-card__media { display: block; aspect-ratio: 4/3; background: linear-gradient(135deg,#fdf2f8,#fff); }
    .svc-card__media img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .svc-card__placeholder { width: 100%; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 6px; color: #b8879e; }
    .svc-card__placeholder span { font-size: .75rem; }
    .svc-card__body { padding: 16px; display: flex; flex-direction: column; gap: 6px; flex: 1; }
    .svc-card h3 { margin: 0; font-size: 1.05rem; }
    .svc-card h3 a { color: #1a1a2e; text-decoration: none; }
    .svc-card__price { margin: 0 0 4px; font-weight: 700; color: #444; }
    .svc-badge { display: inline-block; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; width: fit-content; }
    .svc-badge--live { background: #e8f5e9; color: #2e7d32; }
    .svc-badge--off { background: #f1f1f4; color: #777; }
    .svc-card__actions { display: flex; flex-wrap: wrap; gap: 6px; margin-top: auto; padding-top: 10px; }
    .svc-inline { display: contents; }
    .svc-btn { background: #f4f4f8; color: #333; border: 0; border-radius: 999px; padding: 7px 12px; font-size: .82rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .svc-btn:hover { background: #e9e9f0; }
    .svc-btn--danger { background: #fdecea; color: #c62828; }
    .svc-btn--danger:hover { background: #fbdedb; }
    @media (max-width: 480px) {
        .svc-grid { grid-template-columns: 1fr; }
        .svc-card__actions { justify-content: space-between; }
        .svc-btn { flex: 1; text-align: center; }
    }
</style>

<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1 style="margin:0 0 6px">Servizi a pagamento</h1>
            <p style="margin:0;color:#666">Crea link Stripe (carta + metodi extra attivi sul conto) per trattamenti e servizi del salone.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a class="btn btn-secondary" href="{{ route('admin.module-billing.show', $tenant) }}">Costi e pagamenti</a>
            @if ($stripeConfigured)
                <a class="btn btn-secondary" href="{{ route('admin.services.payment-links', $tenant) }}">Link di pagamento</a>
                <a class="btn" href="{{ route('admin.services.create', $tenant) }}">+ Nuovo servizio</a>
            @endif
        </div>
    </div>

    @if (session('status'))
        <p class="alert">{{ session('status') }}</p>
    @endif
    @error('stripe')
        <p class="error">{{ $message }}</p>
    @enderror

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
        <div class="svc-grid">
            @foreach ($services as $service)
                @include('hub-payments::admin.services._service-card', ['tenant' => $tenant, 'service' => $service])
            @endforeach
        </div>
    @endif
</div>
@endsection
