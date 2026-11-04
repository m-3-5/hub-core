<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $title = 'Crea il tuo sito con l\'IA, gratis per '.$freeDays.' giorni | Hub Core';
        $desc = 'Scrivi in due righe cosa fa la tua attività: l\'intelligenza artificiale prepara il sito, pronto per il telefono, con pulsante per chiamare e WhatsApp. Primi '.$freeDays.' giorni gratis, senza carta.';
    @endphp
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $desc }}">
    <link rel="canonical" href="{{ route('site.start') }}">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="it_IT">
    <meta property="og:title" content="Crea il tuo sito con l'IA">
    <meta property="og:description" content="{{ $desc }}">
    <meta property="og:url" content="{{ route('site.start') }}">
    <meta property="og:image" content="{{ asset('images/og-hub-core.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    @include('layouts.partials.favicon')
    <style>
        :root { --c: #6366f1; --c-dark: #4338ca; --c-soft: #eef0ff; --ink: #16162a; --muted: #5e6072; --paper: #ffffff; --card: #f6f6fb; --line: #e7e7f0; }
        @media (prefers-color-scheme: dark) {
            :root { --c: #818cf8; --c-dark: #a5b4fc; --c-soft: #23244a; --ink: #ececf4; --muted: #a6a8bb; --paper: #111118; --card: #1b1b26; --line: #2b2b3a; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: var(--ink); background: var(--paper); line-height: 1.5; }
        .ss { max-width: 960px; margin: 0 auto; padding: 0 16px 120px; }
        .ss-hero { text-align: center; padding: 28px 0 8px; }
        .ss-pill { display: inline-flex; gap: 6px; align-items: center; background: var(--c-soft); color: var(--c-dark); font-weight: 700; font-size: .85rem; padding: 7px 14px; border-radius: 99px; }
        .ss-hero h1 { font-size: clamp(1.9rem, 7.5vw, 3.2rem); line-height: 1.1; letter-spacing: -.02em; margin: 14px 0 10px; text-wrap: balance; }
        .ss-hero p { color: var(--muted); font-size: 1.08rem; margin: 0 auto 20px; max-width: 520px; }
        .ss-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 56px; padding: 0 26px; border-radius: 16px; background: var(--c); color: #fff; font-weight: 800; font-size: 1.1rem; text-decoration: none; box-shadow: 0 10px 24px rgba(99,102,241,.35); }
        .ss-btn:active { transform: scale(.98); }
        .ss-note { display: block; color: var(--muted); font-size: .9rem; margin-top: 10px; }

        .ss-phone { width: min(300px, 82vw); margin: 26px auto 6px; border: 10px solid #1d1d2b; border-radius: 34px; overflow: hidden; background: #fff; box-shadow: 0 24px 50px rgba(20,20,50,.22); color: #16162a; text-align: left; }
        .ss-phone__hero { background: linear-gradient(135deg, #ec4899, #8b5cf6); color: #fff; padding: 26px 16px 20px; }
        .ss-phone__hero small { font-weight: 700; opacity: .85; font-size: .72rem; letter-spacing: .05em; text-transform: uppercase; }
        .ss-phone__hero b { display: block; font-size: 1.2rem; line-height: 1.2; margin: 6px 0 12px; }
        .ss-phone__hero span { display: inline-block; background: #fff; color: #7c3aed; font-weight: 800; font-size: .82rem; padding: 8px 12px; border-radius: 10px; }
        .ss-phone__body { padding: 14px 16px 18px; display: flex; flex-wrap: wrap; gap: 6px; }
        .ss-phone__body i { font-style: normal; background: #f3e8ff; color: #6d28d9; font-weight: 600; font-size: .78rem; padding: 6px 10px; border-radius: 99px; }

        .ss h2 { font-size: clamp(1.35rem, 5vw, 1.8rem); text-align: center; margin: 44px 0 18px; letter-spacing: -.01em; }
        .ss-steps { display: grid; gap: 12px; }
        .ss-step { display: flex; gap: 14px; align-items: center; background: var(--card); border-radius: 18px; padding: 16px; }
        .ss-step .n { flex: none; width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; font-size: 1.6rem; background: var(--paper); border: 2px solid var(--line); }
        .ss-step b { display: block; font-size: 1.05rem; }
        .ss-step small { color: var(--muted); font-size: .93rem; }
        .ss-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
        .ss-tile { background: var(--card); border-radius: 18px; padding: 16px 12px; text-align: center; }
        .ss-tile .i { font-size: 1.9rem; display: block; }
        .ss-tile b { display: block; font-size: .98rem; margin-top: 6px; }
        .ss-price { background: var(--card); border: 2px solid var(--c); border-radius: 22px; padding: 20px 16px; text-align: center; }
        .ss-price .big { font-size: 2rem; font-weight: 800; }
        .ss-price .big small { font-size: 1rem; color: var(--muted); font-weight: 600; }
        .ss-price ul { list-style: none; padding: 0; margin: 12px 0 0; display: grid; gap: 8px; text-align: left; }
        .ss-price li { display: flex; gap: 10px; align-items: flex-start; }
        .ss-price li::before { content: '✓'; color: #16a34a; font-weight: 800; }
        details.ss-more { margin-top: 14px; text-align: left; }
        details.ss-more summary { cursor: pointer; font-weight: 700; color: var(--c-dark); min-height: 48px; display: flex; align-items: center; justify-content: center; }
        details.ss-more p { color: var(--muted); font-size: .93rem; margin: 6px 0; }
        .ss-alt { text-align: center; margin-top: 30px; color: var(--muted); }
        .ss-alt a { color: var(--c-dark); font-weight: 700; display: inline-flex; min-height: 48px; align-items: center; }

        .ss-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); background: linear-gradient(to top, var(--paper) 72%, transparent); }
        .ss-bar .ss-btn { width: 100%; max-width: 520px; margin: 0 auto; display: flex; }
        @media (min-width: 760px) {
            .ss-hero { padding-top: 54px; }
            .ss-steps { grid-template-columns: repeat(3, 1fr); }
            .ss-step { flex-direction: column; text-align: center; padding: 22px 16px; }
            .ss-grid { grid-template-columns: repeat(4, 1fr); }
            .ss-price { max-width: 520px; margin: 0 auto; }
            .ss-bar { display: none; }
            .ss { padding-bottom: 40px; }
        }
    </style>
    @include('layouts.partials.ui')
</head>
<body>
@include('layouts.partials.public-nav')

<main class="ss">
    <section class="ss-hero">
        <span class="ss-pill">✨ Con l'intelligenza artificiale</span>
        <h1>Il tuo sito, scritto dall'IA in 5 minuti</h1>
        <p>Racconti in due righe cosa fai. L'IA scrive i testi, tu controlli e lo metti online.</p>
        <a class="ss-btn" href="{{ $startUrl }}">{{ $loggedIn ? 'Crea il mio sito' : 'Crea il mio sito gratis' }} →</a>
        <span class="ss-note">{{ $freeDays }} giorni gratis · senza carta · disdici quando vuoi</span>

        <div class="ss-phone" aria-hidden="true">
            <div class="ss-phone__hero"><small>Salone Anna</small><b>Il tuo prossimo taglio, come lo hai sempre immaginato</b><span>Prenota ora</span></div>
            <div class="ss-phone__body"><i>Taglio</i><i>Colore</i><i>Sposa</i><i>Trattamenti</i></div>
        </div>
    </section>

    <h2>Come funziona</h2>
    <div class="ss-steps">
        <div class="ss-step"><span class="n">👤</span><div><b>1. Registrati</b><small>Nome, email: un minuto.</small></div></div>
        <div class="ss-step"><span class="n">✍️</span><div><b>2. Racconta cosa fai</b><small>L'IA scrive frase, servizi e «chi siamo».</small></div></div>
        <div class="ss-step"><span class="n">🚀</span><div><b>3. È online</b><small>Un link da mandare su WhatsApp e social.</small></div></div>
    </div>

    <h2>Cosa c'è dentro</h2>
    <div class="ss-grid">
        <div class="ss-tile"><span class="i">📱</span><b>Perfetto sul telefono</b></div>
        <div class="ss-tile"><span class="i">📞</span><b>Tasto Chiama e WhatsApp</b></div>
        <div class="ss-tile"><span class="i">✏️</span><b>Lo cambi quando vuoi</b></div>
        <div class="ss-tile"><span class="i">🛡️</span><b>Solo siti seri</b></div>
    </div>

    <h2>Quanto costa</h2>
    <div class="ss-price">
        <div class="big">{{ $premiumMonthly }} € <small>/mese · Premium</small></div>
        <ul>
            <li>Primi {{ $freeDays }} giorni gratis, senza carta</li>
            <li>Poi {{ $launchEur }} € per altri {{ $trialDays }} giorni di prova</li>
            <li>Dentro anche promo, servizi, prodotti e pagamenti</li>
        </ul>
        <details class="ss-more">
            <summary>Scopri di più</summary>
            <p>Il canone parte solo dopo la prova. Se non attivi, non succede niente e non paghi nulla.</p>
            <p>Il sito ha un indirizzo su inm35.it. Vuoi un dominio tuo e più pagine? Te lo facciamo noi su misura.</p>
        </details>
    </div>

    <p class="ss-alt">Preferisci che lo facciamo noi?<br><a href="{{ route('landing.web') }}">Sito su misura con preventivo gratuito →</a></p>
</main>

<div class="ss-bar"><a class="ss-btn" href="{{ $startUrl }}">{{ $loggedIn ? 'Crea il mio sito' : 'Crea il mio sito gratis' }} →</a></div>

@include('layouts.partials.app-footer')
</body>
</html>
