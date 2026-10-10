@php
    $eur = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
    $paid = $order->status === 'paid';
    $wa = preg_replace('/\D+/', '', (string) config('landing.whatsapp'));
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Il tuo ordine — {{ $tenant->name }}</title>
    @include('layouts.partials.favicon')
    <style>
        :root { --primary: {{ $tenant->primary_color ?: '#6366f1' }}; --text: #1f1a24; --muted: #5c5563; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: system-ui, sans-serif; color: var(--text); background: #faf8fb; line-height: 1.55; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 28px 18px 56px; }
        .card { background: #fff; border-radius: 22px; padding: 22px; box-shadow: 0 10px 30px rgba(0,0,0,.06); margin-bottom: 16px; }
        h1 { font-size: 1.5rem; margin-bottom: 6px; }
        .ok { background: #eefaf2; color: #166534; border-radius: 14px; padding: 12px 14px; font-weight: 600; margin-bottom: 14px; }
        .wait { background: #fff7ed; color: #9a3412; border-radius: 14px; padding: 12px 14px; font-weight: 600; margin-bottom: 14px; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f0edf3; }
        .row:last-child { border-bottom: 0; }
        .muted { color: var(--muted); font-size: .92rem; }
        .shield { display: grid; gap: 6px; }
        a.btn { display: inline-block; background: var(--primary); color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 999px; font-weight: 700; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>🛡️ Il tuo ordine da {{ $tenant->name }}</h1>

        @if (! $paid)
            <div class="wait">Stiamo aspettando la conferma del pagamento. Se hai appena pagato, ricarica la pagina tra qualche secondo.</div>
        @elseif ($order->payout_status === 'released')
            <div class="ok">✓ Tutto a posto: il pagamento è stato consegnato al venditore.</div>
        @elseif ($order->payout_status === 'frozen')
            <div class="wait">Abbiamo ricevuto la tua segnalazione: il pagamento resta fermo finché il problema non è risolto.</div>
        @elseif ($order->payout_status === 'refunded')
            <div class="ok">Il pagamento ti è stato rimborsato.</div>
        @else
            <div class="ok">✓ {{ $justPaid ? 'Grazie! Pagamento ricevuto.' : 'Pagamento ricevuto.' }}</div>
        @endif

        @foreach ($order->items as $item)
            <div class="row"><span>{{ ($item['quantity'] ?? 1) > 1 ? $item['quantity'].' × ' : '' }}{{ $item['title'] ?? 'Articolo' }}</span><strong>{{ $eur((int) ($item['unit_amount_cents'] ?? 0) * (int) ($item['quantity'] ?? 1)) }}</strong></div>
        @endforeach
        <div class="row"><span>Totale pagato</span><strong>{{ $eur($order->amount_cents) }}</strong></div>
    </div>

    @if ($paid && $order->isHeld())
        <div class="card shield">
            <strong>Come sei protetto</strong>
            <p class="muted">Il tuo pagamento è custodito da Hub Core: {{ $tenant->name }} lo riceve solo dopo che hai ricevuto quello che hai comprato.
            @if ($order->release_at)Se non ci segnali problemi, i soldi vengono consegnati al venditore il <strong>{{ $order->release_at->timezone(config('app.timezone'))->locale('it')->translatedFormat('d F Y') }}</strong>.@endif</p>
            <p class="muted">Se qualcosa non va, scrivici prima di quella data e blocchiamo il pagamento.</p>
            @if ($wa)<p><a class="btn" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Ciao, ho un problema con il mio ordine '.$tenant->name.' (codice '.substr($order->buyer_token, 0, 8).')') }}" rel="noopener">Scrivici su WhatsApp</a></p>@endif
        </div>
    @endif

    <p class="muted" style="text-align:center">Pagamento sicuro tramite Stripe · Pagamenti protetti Hub Core · <a href="{{ route('terms.economic') }}">condizioni</a></p>
</div>
</body>
</html>
