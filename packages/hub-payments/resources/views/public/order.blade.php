@php
    $eur = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
    $paid = $order->status === 'paid';
    $wa = preg_replace('/\D+/', '', (string) config('landing.whatsapp'));
    $held = $paid && $order->isHeld();
    $waLink = $wa ? 'https://wa.me/'.$wa.'?text='.rawurlencode('Ciao, ho un problema con il mio ordine '.$tenant->name.' (codice '.substr($order->buyer_token, 0, 8).')') : null;

    // Stato in una sola schermata: icona grande, titolo, una riga di spiegazione.
    if (! $paid) {
        [$icon, $tone, $title, $sub] = ['⏳', 'wait', 'Stiamo aspettando la conferma del pagamento', 'Se hai appena pagato, ricarica la pagina tra qualche secondo.'];
    } elseif ($order->payout_status === 'released') {
        [$icon, $tone, $title, $sub] = ['✓', 'ok', 'Tutto a posto: il pagamento è stato consegnato al venditore', 'Se hai ancora un problema scrivici: valutiamo ogni caso.'];
    } elseif ($order->payout_status === 'refunded') {
        [$icon, $tone, $title, $sub] = ['↩', 'ok', 'Il pagamento ti è stato rimborsato', 'I tempi di accredito dipendono dalla tua banca (qualche giorno lavorativo).'];
    } elseif ($order->payout_status === 'frozen') {
        [$icon, $tone, $title, $sub] = ['⏸', 'warn', 'Segnalazione ricevuta', 'Il pagamento è fermo finché il problema non è risolto. Se non si risolve, ti rimborsiamo.'];
    } else {
        [$icon, $tone, $title, $sub] = ['🛡️', 'ok', ($justPaid ? 'Grazie! Pagamento ricevuto' : 'Pagamento ricevuto'), $tenant->name.' lo riceve dopo la tua conferma'.($order->release_at ? ' o il '.$order->release_at->timezone(config('app.timezone'))->locale('it')->translatedFormat('d F Y').' se non segnali problemi.' : '.')];
    }
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="{{ $tenant->primary_color ?: '#6366f1' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Il tuo ordine — {{ $tenant->name }}</title>
    @include('layouts.partials.favicon')
    <style>
        :root { --c: {{ $tenant->primary_color ?: '#6366f1' }}; --bg: #f4f4f8; --card: #fff; --ink: #17171f; --muted: #6a6a7a; --line: #e9e9f0; --ok: #15803d; --warn: #b45309; }
        @media (prefers-color-scheme: dark) { :root { --bg: #101014; --card: #1b1b22; --ink: #f2f2f6; --muted: #a2a2b2; --line: #2c2c36; } }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, "Segoe UI", system-ui, sans-serif; background: var(--bg); color: var(--ink); line-height: 1.45; min-height: 100vh; padding-bottom: calc(96px + env(safe-area-inset-bottom)); }
        .bar { position: sticky; top: 0; z-index: 5; display: flex; align-items: center; gap: 12px; background: var(--c); color: #fff; padding: calc(12px + env(safe-area-inset-top)) 16px 12px; }
        .bar .logo { width: 38px; height: 38px; border-radius: 12px; background: rgba(255,255,255,.2); display: grid; place-items: center; font-size: 1.2rem; flex: none; }
        .bar strong { display: block; font-size: 1.02rem; line-height: 1.2; }
        .bar small { opacity: .85; font-size: .8rem; }
        main { max-width: 560px; margin: 0 auto; padding: 16px; display: grid; gap: 12px; }
        .card { background: var(--card); border-radius: 18px; padding: 16px; border: 1px solid var(--line); }
        .hero { text-align: center; padding: 24px 16px; }
        .hero .ico { width: 72px; height: 72px; border-radius: 50%; display: grid; place-items: center; margin: 0 auto 12px; font-size: 2rem; font-weight: 800; background: color-mix(in srgb, var(--ok) 14%, var(--card)); color: var(--ok); }
        .hero.warn .ico { background: color-mix(in srgb, var(--warn) 14%, var(--card)); color: var(--warn); }
        .hero.wait .ico { background: color-mix(in srgb, var(--muted) 16%, var(--card)); color: var(--muted); }
        .hero h1 { font-size: 1.25rem; line-height: 1.25; }
        .hero p { color: var(--muted); margin-top: 6px; font-size: .95rem; }
        .row { display: flex; justify-content: space-between; gap: 12px; padding: 10px 0; border-bottom: 1px solid var(--line); font-size: .98rem; }
        .row:last-child { border-bottom: 0; padding-bottom: 0; }
        .row:first-child { padding-top: 0; }
        .row b { white-space: nowrap; }
        .steps { list-style: none; display: grid; gap: 12px; }
        .steps li { display: flex; gap: 12px; align-items: flex-start; font-size: .95rem; }
        .steps .n { width: 26px; height: 26px; border-radius: 50%; background: color-mix(in srgb, var(--c) 16%, var(--card)); color: var(--c); font-weight: 800; display: grid; place-items: center; flex: none; font-size: .85rem; }
        h2 { font-size: 1rem; margin-bottom: 10px; }
        .muted { color: var(--muted); font-size: .9rem; }
        textarea { width: 100%; min-height: 110px; font: inherit; font-size: 1rem; padding: 12px; border: 1px solid var(--line); border-radius: 14px; background: var(--bg); color: var(--ink); margin: 10px 0; resize: vertical; }
        .btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: 52px; border: 0; border-radius: 16px; font: inherit; font-size: 1.02rem; font-weight: 700; background: var(--c); color: #fff; cursor: pointer; text-decoration: none; padding: 0 18px; }
        .btn.line { background: transparent; color: var(--c); border: 2px solid var(--c); }
        .err { color: #b91c1c; font-weight: 600; font-size: .9rem; }
        .dock { position: fixed; left: 0; right: 0; bottom: 0; z-index: 10; background: var(--card); border-top: 1px solid var(--line); padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); display: flex; gap: 10px; }
        .dock form { flex: 2; }
        .dock .btn { min-height: 50px; }
        .dock a.btn { flex: 1; }
        .toast { position: fixed; left: 16px; right: 16px; top: calc(70px + env(safe-area-inset-top)); z-index: 20; background: var(--ink); color: var(--bg); border-radius: 14px; padding: 12px 16px; font-weight: 600; text-align: center; animation: fade 4.5s forwards; }
        @keyframes fade { 0%, 80% { opacity: 1; transform: none; } 100% { opacity: 0; transform: translateY(-8px); visibility: hidden; } }
        footer { text-align: center; color: var(--muted); font-size: .82rem; padding: 8px 16px 0; }
        footer a { color: inherit; }
        #problema { scroll-margin-top: 80px; }
    </style>
@include('layouts.partials.ui')
</head>
<body>
<header class="bar">
    <span class="logo">🛡️</span>
    <div><strong>{{ $tenant->name }}</strong><small>Ordine protetto · #{{ $order->id }}</small></div>
</header>

@if (session('status') && ! $order->isFrozen())<div class="toast" role="status">{{ session('status') }}</div>@endif

<main>
    <section class="card hero {{ $tone }}">
        <div class="ico">{{ $icon }}</div>
        <h1>{{ $title }}</h1>
        <p>{{ $sub }}</p>
    </section>

    <section class="card">
        @foreach ($order->items as $item)
            <div class="row"><span>{{ ($item['quantity'] ?? 1) > 1 ? $item['quantity'].' × ' : '' }}{{ $item['title'] ?? 'Articolo' }}</span><b>{{ $eur((int) ($item['unit_amount_cents'] ?? 0) * (int) ($item['quantity'] ?? 1)) }}</b></div>
        @endforeach
        <div class="row"><span>Totale pagato</span><b>{{ $eur($order->amount_cents) }}</b></div>
    </section>

    @if ($held)
        <section class="card">
            <h2>Come sei protetto</h2>
            <ol class="steps">
                <li><span class="n">1</span><span>Il tuo pagamento è <strong>custodito da Hub Core</strong>: il venditore non lo riceve subito.</span></li>
                <li><span class="n">2</span><span>Quando hai ricevuto tutto premi <strong>«Ho ricevuto tutto»</strong>. Il venditore viene pagato.</span></li>
                <li><span class="n">3</span><span>Se qualcosa non va premi <strong>«Segnala un problema»</strong>: il pagamento si ferma e, se non si risolve, ti rimborsiamo.</span></li>
            </ol>
        </section>

        <section class="card" id="problema">
            <h2>Qualcosa non va? Segnala un problema</h2>
            <form method="POST" action="{{ route('protected.order.report', $order->buyer_token) }}">
                @csrf
                <label class="muted" for="message">Scrivi cosa non va: non l'ho ricevuto, è diverso, è danneggiato…</label>
                <textarea id="message" name="message" maxlength="1000" required>{{ old('message') }}</textarea>
                @error('message')<p class="err">{{ $message }}</p>@enderror
                <button class="btn line" type="submit">Segnala e ferma il pagamento</button>
            </form>
        </section>
    @elseif ($paid && $order->isFrozen() && $order->problem_message)
        <section class="card">
            <h2>La tua segnalazione</h2>
            <p class="muted">«{{ $order->problem_message }}»</p>
        </section>
    @endif

    @if ($paid && $waLink)
        <a class="btn line" href="{{ $waLink }}" rel="noopener">💬 Scrivici su WhatsApp</a>
    @endif

    <footer>Pagamento sicuro tramite Stripe · <a href="{{ route('terms.buyer') }}">Condizioni di acquisto</a>
        @include('layouts.partials.company-footer')
    </footer>
</main>

@if ($held)
    <nav class="dock" aria-label="Azioni sull'ordine">
        <form method="POST" action="{{ route('protected.order.confirm', $order->buyer_token) }}" onsubmit="return confirm('Confermi di aver ricevuto quanto hai acquistato? I soldi verranno consegnati al venditore.')">
            @csrf
            <button class="btn" type="submit">✓ Ho ricevuto tutto</button>
        </form>
        <a class="btn line" href="#problema">Problema</a>
    </nav>
@endif
</body>
</html>
