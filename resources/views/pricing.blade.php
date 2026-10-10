<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Prezzi — Hub Core</title>
    @include('layouts.partials.favicon')
    <meta name="description" content="Prezzi di Hub Core: prima settimana gratis, poi {{ $hubBilling['launch_offer_price_eur'] }} € di lancio e il canone solo dopo la prova. Privati sempre gratis.">
    <link rel="canonical" href="{{ route('pricing.show') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Prezzi — Hub Core">
    <meta property="og:description" content="Prima settimana gratis, poi {{ $hubBilling['launch_offer_price_eur'] }} € di lancio. Privati sempre gratis.">
    <meta property="og:url" content="{{ route('pricing.show') }}">
    <meta property="og:image" content="{{ asset('images/og-hub-core.png') }}">
    <meta name="theme-color" content="#6366f1">
    @php
        $free = (int) $hubBilling['free_days'];
        $launch = (int) $hubBilling['launch_offer_price_eur'];
        $trial = (int) $hubBilling['trial_days'];
        $monthly = (int) $hubBilling['monthly_price_eur'];
        $annual = (int) $hubBilling['annual_price_eur'];
        $moduleImages = ['promo' => 'welcome-promo.png', 'servizi' => 'welcome-services.png'];
    @endphp
    <style>
        :root { --accent: #6366f1; --accent2: #ec4899; --ink: #15131a; --muted: #767085; --line: #ece9f5; --bg: #f7f6fb; --card: #fff; }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        html { scroll-behavior: smooth; }
        body { font-family: -apple-system, "Segoe UI", system-ui, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.45; padding-bottom: calc(96px + env(safe-area-inset-bottom)); }
        a { color: inherit; }

        .top { position: sticky; top: 0; z-index: 20; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; padding: calc(12px + env(safe-area-inset-top)) 14px 16px; display: grid; grid-template-columns: auto 1fr auto; align-items: center; gap: 10px; }
        .top h1 { font-size: 1.25rem; font-weight: 800; text-align: center; }
        .top .sub { grid-column: 1 / -1; text-align: center; font-size: .82rem; opacity: .92; margin-top: -4px; }
        .nav-btn { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,255,255,.2); color: #fff; border: 0; border-radius: 999px; padding: 9px 14px; font: inherit; font-weight: 700; font-size: .9rem; cursor: pointer; text-decoration: none; min-height: 40px; }
        .nav-btn:hover { background: rgba(255,255,255,.32); }
        .nav-spacer { width: 1px; }

        .wrap { max-width: 980px; margin: 0 auto; padding: 18px 14px 0; }
        .group-title { font-size: .8rem; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin: 30px 4px 12px; }

        /* Come funziona il pagamento: 3 tappe a colpo d'occhio */
        .journey { display: grid; gap: 12px; }
        .tap { background: var(--card); border: 1px solid var(--line); border-radius: 20px; padding: 16px 18px; display: flex; gap: 14px; align-items: center; }
        .tap .ico { flex: none; width: 58px; height: 58px; border-radius: 18px; display: grid; place-items: center; font-size: 1.9rem; background: linear-gradient(145deg, #eef2ff, #fdf2f8); }
        .tap b { display: block; font-size: 1.02rem; }
        .tap span { color: var(--muted); font-size: .9rem; }
        .tap .big { color: var(--accent); font-weight: 800; }

        .stack { display: grid; gap: 12px; }
        .plan { background: var(--card); border-radius: 22px; padding: 20px; border: 1px solid var(--line); display: flex; flex-direction: column; gap: 10px; position: relative; }
        .plan.best { border: 2px solid var(--accent); box-shadow: 0 10px 30px rgba(99,102,241,.18); }
        .plan .tag { position: absolute; top: -11px; left: 18px; background: var(--accent); color: #fff; font-size: .7rem; font-weight: 800; padding: 3px 10px; border-radius: 999px; }
        .plan h3 { font-size: 1.05rem; }
        .plan .price { font-size: 2rem; font-weight: 800; line-height: 1; }
        .plan .price small { font-size: .8rem; font-weight: 600; color: var(--muted); }
        .plan ul { list-style: none; display: grid; gap: 7px; font-size: .92rem; flex: 1; }
        .plan li::before { content: '✓'; color: #16a34a; font-weight: 800; margin-right: 8px; }
        .cta { display: block; text-align: center; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; text-decoration: none; font-weight: 800; border-radius: 14px; padding: 14px; min-height: 48px; }
        .cta.ghost { background: #fff; color: var(--accent); border: 2px solid var(--accent); }

        .module { background: var(--card); border: 1px solid var(--line); border-radius: 22px; overflow: hidden; }
        .module .img { aspect-ratio: 16/9; background: linear-gradient(160deg, #eef2ff, #fdf2f8); display: grid; place-items: center; }
        .module .img img { width: 100%; height: 100%; object-fit: contain; display: block; }
        .module .body { padding: 16px 18px 18px; }
        .module h3 { font-size: 1.02rem; margin-bottom: 10px; }
        .stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .stat { background: var(--bg); border-radius: 12px; padding: 10px 12px; }
        .stat .n { font-weight: 800; font-size: 1.05rem; }
        .stat .l { font-size: .7rem; color: var(--muted); font-weight: 600; }

        .addon { background: linear-gradient(135deg, #fdf2ff, #fff); border: 1px dashed #e9c6f5; border-radius: 16px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; }
        .addon b { font-size: 1.05rem; }
        .iva { text-align: center; color: var(--muted); font-size: .76rem; margin: 18px 0 4px; }

        .soon { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .soon div { background: var(--card); border: 1px dashed var(--line); border-radius: 14px; padding: 14px; text-align: center; color: var(--muted); font-size: .8rem; }
        .soon .e { display: block; font-size: 1.6rem; margin-bottom: 4px; }
        .soon strong { display: block; color: var(--ink); font-size: .85rem; }

        details { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 14px 16px; margin-bottom: 10px; }
        summary { font-weight: 700; cursor: pointer; list-style: none; display: flex; justify-content: space-between; gap: 10px; }
        summary::after { content: '+'; color: var(--accent); font-weight: 800; }
        details[open] summary::after { content: '−'; }
        details p { color: var(--muted); margin-top: 8px; font-size: .92rem; }

        footer { text-align: center; padding: 30px 20px 10px; color: var(--muted); font-size: .85rem; }
        footer a { color: var(--accent); text-decoration: none; font-weight: 700; }

        .dock { position: fixed; left: 0; right: 0; bottom: 0; z-index: 30; background: rgba(255,255,255,.96); backdrop-filter: blur(10px); border-top: 1px solid var(--line); padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); display: flex; gap: 10px; }
        .dock .back { flex: none; background: #f1f1f5; color: var(--ink); border: 0; border-radius: 14px; padding: 0 18px; font: inherit; font-weight: 700; cursor: pointer; min-height: 48px; text-decoration: none; display: grid; place-items: center; }
        .dock .cta { flex: 1; }

        @media (min-width: 760px) {
            .top { padding-top: 18px; padding-bottom: 22px; }
            .top h1 { font-size: 1.7rem; }
            .journey { grid-template-columns: repeat(3, 1fr); }
            .tap { flex-direction: column; text-align: center; align-items: center; padding: 22px 18px; }
            .stack.plans { grid-template-columns: repeat(3, 1fr); }
            .stack.modules { grid-template-columns: repeat(2, 1fr); }
            .soon { grid-template-columns: repeat(4, 1fr); }
            .dock { justify-content: center; }
            .dock .cta { flex: 0 1 360px; }
        }
    </style>
</head>
<body>

<header class="top">
    <a class="nav-btn" href="{{ route('welcome') }}" id="back-top" aria-label="Torna indietro">← Indietro</a>
    <h1>Prezzi</h1>
    <a class="nav-btn" href="{{ route('welcome') }}">🏠 Home</a>
    <p class="sub">Prima settimana gratis. Nessuna carta per cominciare.</p>
</header>

<div class="wrap">
    <div class="group-title" style="margin-top:6px">Come si paga, in 3 tappe</div>
    <div class="journey">
        <div class="tap">
            <span class="ico">🆓</span>
            <div><b>1 · Prima settimana gratis</b><span>Ti registri e usi tutto per <span class="big">{{ $free }} giorni</span>, senza carta e senza pagare nulla.</span></div>
        </div>
        <div class="tap">
            <span class="ico">🎉</span>
            <div><b>2 · Poi solo {{ $launch }} €</b><span>Per continuare attivi l'<span class="big">offerta di lancio da {{ $launch }} €</span>: copre anche il primo modulo.</span></div>
        </div>
        <div class="tap">
            <span class="ico">💳</span>
            <div><b>3 · Altri {{ $trial }} giorni di prova</b><span>Solo dopo parte il canone da <span class="big">{{ $monthly }} €/mese</span>. Disdici quando vuoi.</span></div>
        </div>
    </div>

    <div class="group-title">Scegli come usarlo</div>
    <div class="stack plans">
        <div class="plan">
            <h3>👤 Privato</h3>
            <div class="price">€0 <small>per sempre</small></div>
            <ul><li>Annunci e affitti</li><li>Promo incluse ogni mese</li><li>Nessun canone</li></ul>
            <a class="cta ghost" href="{{ route('registration.create') }}">Registrati gratis</a>
        </div>
        <div class="plan best">
            <span class="tag">Per iniziare</span>
            <h3>🏢 Azienda · Mensile</h3>
            <div class="price">€{{ $monthly }} <small>/mese</small></div>
            <ul><li>{{ $free }} giorni gratis, poi {{ $launch }} € di lancio</li><li>Promo, servizi e prodotti con pagamento online</li><li>Tutto pubblicato sul tuo sito</li></ul>
            <a class="cta" href="{{ route('registration.create') }}">Inizia gratis</a>
        </div>
        <div class="plan">
            <h3>🏛️ Azienda · Annuale</h3>
            <div class="price">€{{ $annual }} <small>/anno · ≈ €{{ number_format($annual / 12, 0) }}/mese</small></div>
            <ul><li>Come il mensile, ma risparmi</li><li>Ideale se hai già deciso</li><li>Un solo pagamento l'anno</li></ul>
            <a class="cta ghost" href="{{ route('registration.create') }}">Inizia gratis</a>
        </div>
    </div>

    <div class="group-title">Moduli attivi</div>
    <div class="stack modules">
        @foreach ($modulePricing as $key => $module)
            @continue($key === 'iva_percent')
            <div class="module">
                @if (isset($moduleImages[$key]))
                    <div class="img"><img src="{{ asset('images/welcome/'.$moduleImages[$key]) }}" alt="{{ $module['label'] }}" loading="lazy"></div>
                @endif
                <div class="body">
                    <h3>{{ $module['label'] }}</h3>
                    <div class="stats">
                        <div class="stat"><div class="n">€{{ $module['activation_cents']/100 }}</div><div class="l">Attivazione (coperta dall'offerta)</div></div>
                        <div class="stat"><div class="n">€{{ $module['monthly_cents']/100 }}<span style="font-size:.6em">/mese</span></div><div class="l">Canone</div></div>
                        <div class="stat"><div class="n">{{ $module['included_per_month'] }}</div><div class="l">Incluse al mese</div></div>
                        <div class="stat"><div class="n">€{{ $module['extra_self_cents']/100 }} · €{{ $module['extra_staff_cents']/100 }}</div><div class="l">Extra: fai tu · facciamo noi</div></div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="addon">
        <span>✨ Volantino con IA su misura</span>
        <b>€{{ number_format($aiFlyerPrice, 0) }}</b>
    </div>

    <p class="iva">Prezzi IVA esclusa (+{{ $modulePricing['iva_percent'] }}%)</p>

    @php $feeEx = \M35\HubPayments\Support\StripeFees::example(); @endphp
    <div class="group-title">Se vendi: pagamenti protetti e trattenute</div>
    <div class="journey">
        <div class="tap"><span class="ico">🛍️</span><div><b>Il cliente paga tramite Hub Core</b><span>I soldi sono al sicuro: chi compra è tutelato e chi vende sa di essere pagato.</span></div></div>
        <div class="tap"><span class="ico">📦</span><div><b>Dopo la consegna ti arrivano</b><span>Quando il cliente conferma, o dopo <span class="big">7 giorni</span> senza problemi, i soldi vanno sul tuo conto.</span></div></div>
        <div class="tap"><span class="ico">💶</span><div><b>Trattenute di Stripe</b><span>Stripe trattiene circa <span class="big">{{ \M35\HubPayments\Support\StripeFees::rateLabel() }}</span> su ogni pagamento con carta: è un suo costo, sottratto dall'incasso.</span></div></div>
    </div>
    <p class="iva" style="margin-top:10px">Esempio: vendita da {{ $feeEx['amount'] }} € → trattenuta circa {{ $feeEx['fee'] }} € → ricevi circa {{ $feeEx['net'] }} €. Le tariffe le decide Stripe e possono variare (carte extra-UE, bonifici): ti mostriamo sempre l'importo netto. Puoi sempre vendere anche direttamente sul tuo sito con il tuo conto Stripe, con PayPal o con bonifico.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
        <a class="cta ghost" style="flex:1;min-width:220px" href="{{ route('guides.index') }}">📖 Guide: come ricevere i soldi</a>
        <a class="cta ghost" style="flex:1;min-width:220px" href="{{ route('terms.economic') }}">📄 Condizioni economiche</a>
    </div>

    @if ($comingSoon->isNotEmpty())
        <div class="group-title">In arrivo</div>
        <div class="soon">
            @foreach ($comingSoon as $module)
                <div><span class="e">{{ $module['emoji'] }}</span><strong>{{ $module['label'] }}</strong>da definire</div>
            @endforeach
        </div>
    @endif

    <div class="group-title">Domande frequenti</div>
    <details><summary>Devo pagare per registrarmi?</summary><p>No. Ti registri e usi tutto gratis per {{ $free }} giorni, senza carta. Se non attivi nulla, non succede niente e non ti addebitiamo nulla.</p></details>
    <details><summary>Cosa succede dopo la prima settimana?</summary><p>Ti chiediamo l'offerta di lancio da {{ $launch }} € per continuare. Ti avvisiamo con una email e nell'app: decidi tu quando.</p></details>
    <details><summary>Quando parte il canone da {{ $monthly }} €?</summary><p>Solo dopo altri {{ $trial }} giorni di prova dall'attivazione. Puoi disdire in qualsiasi momento.</p></details>
    <details><summary>Quanto mi trattiene Stripe quando vendo?</summary><p>Su ogni pagamento con carta Stripe applica una commissione, indicativamente {{ \M35\HubPayments\Support\StripeFees::rateLabel() }} (di più per carte extra-UE). È un costo di Stripe e viene sottratto dall'incasso: nel pannello vedi sempre quanto ricevi netto.</p></details>
    <details><summary>Posso vendere anche direttamente, senza Hub Core?</summary><p>Sì. Sul tuo sito puoi continuare a incassare con il tuo conto Stripe. I pagamenti protetti valgono per le vendite fatte tramite inm35.it, dove chi compra e chi vende sono tutelati.</p></details>
    <details><summary>Sono un privato: pago qualcosa?</summary><p>No, i privati usano Hub Core sempre gratis. Le funzioni extra oltre la quota mensile si pagano solo se le usi.</p></details>
</div>

<footer>
    <a href="{{ route('welcome') }}">🏠 Home</a> · <a href="{{ route('landing.web') }}">Siti web e app</a> · domande? chiedi a Max qui in basso a destra
</footer>

<div class="dock">
    <a class="back" href="{{ route('welcome') }}" id="back-dock">← Indietro</a>
    <a class="cta" href="{{ route('registration.create') }}">Registrati gratis</a>
</div>

@include('partials.max-public-chat', ['autoOpen' => false, 'lift' => 72])

<script>
// «Indietro» torna alla pagina da cui arrivi (anche dentro il sito); se non c'è, porta alla home.
(function () {
    function goBack(e) {
        var internal = document.referrer && document.referrer.indexOf(location.origin) === 0;
        if (internal && history.length > 1) { e.preventDefault(); history.back(); }
    }
    ['back-top', 'back-dock'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener('click', goBack); });
})();
</script>
</body>
</html>
