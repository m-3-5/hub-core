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
        a.btn, button.btn { display: inline-block; background: var(--primary); color: #fff; text-decoration: none; padding: 12px 20px; border-radius: 999px; font: inherit; font-weight: 700; border: 0; cursor: pointer; }
        button.btn.line, a.btn.line { background: #fff; color: var(--primary); border: 2px solid var(--primary); }
        textarea { width: 100%; font: inherit; padding: 10px; border: 1px solid #ddd; border-radius: 12px; min-height: 90px; margin: 8px 0; }
        .err { color: #b91c1c; font-weight: 600; }
        details summary { cursor: pointer; font-weight: 700; padding: 6px 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <h1>🛡️ Il tuo ordine da {{ $tenant->name }}</h1>

        @if (session('status'))<div class="ok">{{ session('status') }}</div>@endif

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

            <form method="POST" action="{{ route('protected.order.confirm', $order->buyer_token) }}" onsubmit="return confirm('Confermi di aver ricevuto quanto hai acquistato? I soldi verranno consegnati al venditore.')">
                @csrf
                <button class="btn" type="submit">✓ Ho ricevuto tutto</button>
                <p class="muted" style="margin-top:6px">Premi solo quando hai ricevuto il prodotto o il servizio e sei soddisfatto.</p>
            </form>

            <details @if ($errors->has('message')) open @endif>
                <summary>Qualcosa non va? Segnala un problema</summary>
                <form method="POST" action="{{ route('protected.order.report', $order->buyer_token) }}">
                    @csrf
                    <textarea name="message" maxlength="1000" placeholder="Descrivi cosa non va (non ho ricevuto, è diverso da come descritto, è danneggiato…)" required>{{ old('message') }}</textarea>
                    @error('message')<p class="err">{{ $message }}</p>@enderror
                    <button class="btn line" type="submit">Segnala e ferma il pagamento</button>
                </form>
            </details>
            @if ($wa)<p class="muted">Preferisci parlarci? <a href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Ciao, ho un problema con il mio ordine '.$tenant->name.' (codice '.substr($order->buyer_token, 0, 8).')') }}" rel="noopener">Scrivici su WhatsApp</a>.</p>@endif
        </div>
    @elseif ($paid && $order->isFrozen())
        <div class="card shield">
            <strong>Segnalazione in corso</strong>
            @if ($order->problem_message)<p class="muted">Hai scritto: «{{ $order->problem_message }}»</p>@endif
            <p class="muted">Il pagamento è fermo. Ti contatteremo noi o il venditore; se il problema non si risolve, ti rimborsiamo.</p>
            @if ($wa)<p><a class="btn line" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Ciao, scrivo per la mia segnalazione sull\'ordine '.$tenant->name.' (codice '.substr($order->buyer_token, 0, 8).')') }}" rel="noopener">Scrivici su WhatsApp</a></p>@endif
        </div>
    @elseif ($paid && $order->payout_status === 'released')
        <div class="card shield">
            <p class="muted">Il pagamento è stato consegnato al venditore. Se hai ancora un problema scrivici: valutiamo ogni caso.</p>
            @if ($wa)<p><a class="btn line" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Ciao, ho un problema con il mio ordine '.$tenant->name.' (codice '.substr($order->buyer_token, 0, 8).')') }}" rel="noopener">Scrivici su WhatsApp</a></p>@endif
        </div>
    @endif

    <p class="muted" style="text-align:center">Pagamento sicuro tramite Stripe · Pagamenti protetti Hub Core · <a href="{{ route('terms.buyer') }}">condizioni di acquisto</a></p>
</div>
</body>
</html>
