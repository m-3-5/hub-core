<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#6366f1">
    <meta name="robots" content="noindex">
    <title>Come vuoi ricevere i soldi? — {{ $tenant->name }}</title>
    @include('layouts.partials.favicon')
    @include('layouts.partials.wizard-css', ['color' => '#6366f1'])
    <style>
        .opt { border: 2px solid #e6e7ee; border-radius: 22px; padding: 18px; background: #fff; display: grid; gap: 8px; }
        .opt.rec { border-color: var(--c); background: var(--c-soft); }
        .opt .row1 { display: flex; gap: 14px; align-items: center; }
        .opt .ico { font-size: 2rem; flex: none; }
        .opt h2 { font-size: 1.12rem; margin: 0; }
        .opt .badge { display: inline-block; font-size: .7rem; font-weight: 800; background: var(--c); color: #fff; border-radius: 999px; padding: 2px 9px; margin-bottom: 3px; }
        .opt .badge.ok { background: #16a34a; }
        .opt p { margin: 0; color: #4b4b5b; font-size: .95rem; line-height: 1.45; }
        .opt .acts { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; align-items: center; }
        .opt .acts form { margin: 0; }
        .btn-sm { font: inherit; font-weight: 700; border: 0; border-radius: 12px; padding: 12px 16px; cursor: pointer; text-decoration: none; display: inline-block; font-size: .95rem; }
        .btn-sm.main { background: var(--c); color: #fff; }
        .btn-sm.line { background: #fff; color: var(--c-dark); border: 2px solid var(--c); }
        .guide { color: var(--c-dark); font-weight: 700; font-size: .92rem; }
        .stack { display: grid; gap: 14px; margin-top: 14px; }
    </style>
</head>
<body>
<div class="wz">
    <div class="wz-top">
        <a class="wz-x" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('app.home', $tenant) }}" aria-label="Indietro">←</a>
        <div class="wz-progress"><i style="width:100%"></i></div>
    </div>

    <main class="wz-stage" style="padding-bottom:40px">
        @if (session('status'))<div class="banner" style="background:#eefaf2;color:#166534">{{ session('status') }}</div>@endif
        @error('connect')<div class="banner bad">{{ $message }}</div>@enderror

        <h1>Come vuoi ricevere i soldi?</h1>
        <p class="sub">Scegli la strada che preferisci: puoi cambiarla o usarne più d'una. Ogni opzione ha la sua guida passo passo.</p>

        <div class="stack">
            <section class="opt rec">
                <div class="row1"><span class="ico">🛡️</span>
                    <div>
                        <span class="badge">Consigliato</span>@if ($connectState === 'ready')<span class="badge ok">✓ Collegato</span>@endif
                        <h2>Pagamenti protetti Hub Core</h2>
                    </div>
                </div>
                <p>Il cliente paga tramite Hub Core e tu ricevi i soldi <strong>dopo la consegna</strong>. Tutela chi compra e chi vende. Ti serve un IBAN e un documento.</p>
                <p style="font-size:.85rem;color:#6b6b7b">Stripe trattiene indicativamente {{ \M35\HubPayments\Support\StripeFees::rateLabel() }} su ogni pagamento (vedi <a href="{{ route('terms.economic') }}" target="_blank">condizioni economiche</a>).</p>
                <div class="acts">
                    <form method="POST" action="{{ route('admin.connect.start', $tenant) }}" style="display:grid;gap:10px;flex:1 1 100%">@csrf
                        @include('hub-payments::admin.partials.seller-terms', ['tenant' => $tenant])
                        <button class="btn-sm main" type="submit">{{ $connectState === 'none' ? 'Collega ora' : ($connectState === 'incomplete' ? 'Completa i dati' : 'Modifica i dati') }}</button>
                    </form>
                    <a class="guide" href="{{ route('guides.show', 'pagamenti-protetti') }}" target="_blank">Come funziona →</a>
                </div>
            </section>

            <section class="opt">
                <div class="row1"><span class="ico">💳</span>
                    <div>@if ($ownStripe)<span class="badge ok">✓ Collegato</span>@endif<h2>Il mio conto Stripe</h2></div>
                </div>
                <p>Vendi direttamente sul tuo sito e incassi sul tuo conto Stripe. Gestisci tu rimborsi e contestazioni (nessuna protezione Hub Core).</p>
                <div class="acts">
                    <a class="btn-sm line" href="{{ route('admin.services.index', $tenant) }}#stripe-keys">Inserisci le mie chiavi</a>
                    <a class="guide" href="{{ route('guides.show', 'stripe') }}" target="_blank">Come aprire un conto Stripe →</a>
                </div>
            </section>

            @foreach ([['paypal', '🅿️', 'PayPal', 'Ricevi con PayPal usando il tuo link PayPal.me. Molto conosciuto, ma gestisci tu rimborsi e verifiche.', 'Come aprire un conto PayPal →'], ['bonifico', '🏦', 'Bonifico bancario', 'Il cliente ti paga sul tuo IBAN. Semplice, ma controlli tu che i soldi siano arrivati prima di consegnare.', 'Come ricevere con bonifico →']] as [$key, $emoji, $name, $text, $guideLabel])
                <section class="opt">
                    <div class="row1"><span class="ico">{{ $emoji }}</span><div><h2>{{ $name }}</h2></div></div>
                    <p>{{ $text }}</p>
                    <div class="acts">
                        <form method="POST" action="{{ route('admin.payout.interest', $tenant) }}">@csrf<input type="hidden" name="method" value="{{ $key }}">
                            <button class="btn-sm line" type="submit">Mi interessa, attivatelo</button>
                        </form>
                        <a class="guide" href="{{ route('guides.show', $key === 'paypal' ? 'paypal' : 'bonifico') }}" target="_blank">{{ $guideLabel }}</a>
                    </div>
                </section>
            @endforeach

            <section class="opt">
                <div class="row1"><span class="ico">🪪</span><div><h2>Non ho ancora un conto per le vendite</h2></div></div>
                <p>Nessun problema: ti spieghiamo come avere in pochi minuti un IBAN o una carta dedicati alle vendite.</p>
                <div class="acts"><a class="btn-sm line" href="{{ route('guides.show', 'carte-e-conti-online') }}" target="_blank">Leggi la guida</a></div>
            </section>
        </div>

        <p class="hint" style="margin-top:18px">Tutte le guide: <a href="{{ route('guides.index') }}" target="_blank">come ricevere i soldi</a> · <a href="{{ route('terms.economic') }}" target="_blank">condizioni economiche</a></p>
    </main>
</div>
</body>
</html>
