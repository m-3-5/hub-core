<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prezzi — Hub Core</title>
    <meta name="description" content="Listino prezzi di Hub Core: abbonamento, moduli attivi ed extra.">
    <meta name="theme-color" content="#6366f1">
    <style>
        :root { --accent: #6366f1; --accent2: #ec4899; --ink: #15131a; --muted: #767085; --line: #ece9f5; --bg: #f7f6fb; --card: #fff; }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        html { scroll-behavior: smooth; }
        body { font-family: -apple-system, "Segoe UI", system-ui, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.45; padding-bottom: 40px; }
        .top {
            position: sticky; top: 0; z-index: 5;
            background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff;
            padding: 22px 20px 26px; text-align: center;
        }
        .top h1 { font-size: 1.4rem; font-weight: 800; }
        .top p { font-size: .82rem; opacity: .9; margin-top: 4px; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 18px 14px 0; }

        .group-title { font-size: .78rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); margin: 26px 4px 10px; }
        .group-title:first-child { margin-top: 4px; }

        .stack { display: flex; flex-direction: column; gap: 10px; }

        .plan-card {
            background: var(--card); border-radius: 18px; padding: 16px 18px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            border: 1px solid var(--line);
        }
        .plan-card.is-best { border: 2px solid var(--accent); box-shadow: 0 6px 20px rgba(99,102,241,.15); }
        .plan-card .name { font-weight: 700; font-size: .95rem; }
        .plan-card .tag { display: inline-block; font-size: .68rem; font-weight: 800; color: var(--accent); background: color-mix(in srgb, var(--accent) 12%, transparent); padding: 2px 8px; border-radius: 999px; margin-top: 4px; }
        .plan-card .price { font-size: 1.3rem; font-weight: 800; text-align: right; white-space: nowrap; }
        .plan-card .price small { font-size: .72rem; font-weight: 600; color: var(--muted); display: block; }

        .module-card { background: var(--card); border-radius: 18px; padding: 18px; border: 1px solid var(--line); }
        .module-card .head { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .module-card .emoji { font-size: 1.4rem; }
        .module-card h3 { font-size: 1rem; font-weight: 800; }
        .stat-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .stat { background: var(--bg); border-radius: 12px; padding: 10px 12px; }
        .stat .n { font-weight: 800; font-size: 1.05rem; }
        .stat .l { font-size: .68rem; color: var(--muted); font-weight: 600; }

        .addon-card { background: linear-gradient(135deg, #fdf2ff, #fff); border: 1px dashed #e9c6f5; border-radius: 16px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: .85rem; }
        .addon-card b { font-size: 1rem; }

        .soon-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
        .soon-card { background: var(--card); border: 1px dashed var(--line); border-radius: 14px; padding: 14px; text-align: center; color: var(--muted); }
        .soon-card .emoji { font-size: 1.3rem; display: block; margin-bottom: 4px; }
        .soon-card strong { display: block; color: var(--ink); font-size: .82rem; }
        .soon-card span { font-size: .68rem; }

        .iva { text-align: center; color: var(--muted); font-size: .72rem; margin: 18px 0 4px; }
        footer { text-align: center; padding: 30px 20px 10px; color: var(--muted); font-size: .8rem; }
        footer a { color: var(--accent); text-decoration: none; font-weight: 700; }

        /* Tablet / desktop: comparison-style, denser, more columns */
        @media (min-width: 760px) {
            .top { padding: 34px 20px 40px; }
            .top h1 { font-size: 1.9rem; }
            .top p { font-size: .95rem; }
            .wrap { padding: 28px 24px 0; max-width: 980px; }
            .stack.plans { flex-direction: row; }
            .plan-card { flex-direction: column; align-items: flex-start; flex: 1; padding: 22px; }
            .plan-card .price { text-align: left; font-size: 1.7rem; }
            .stack.modules { flex-direction: row; align-items: stretch; }
            .module-card { flex: 1; }
            .stat-row { grid-template-columns: repeat(2, 1fr); }
            .soon-grid { grid-template-columns: repeat(4, 1fr); }
            .group-title { font-size: .82rem; }
        }
    </style>
</head>
<body>

<div class="top">
    <h1>Prezzi</h1>
    <p>Lo stesso listino che usiamo noi — sempre aggiornato.</p>
</div>

<div class="wrap">
    <div class="group-title">Abbonamento</div>
    <div class="stack plans">
        <div class="plan-card">
            <div>
                <div class="name">Mensile</div>
                <span class="tag">{{ $hubBilling['trial_days'] }} giorni gratis</span>
            </div>
            <div class="price">€{{ $hubBilling['monthly_price_eur'] }}<small>/mese</small></div>
        </div>
        <div class="plan-card is-best">
            <div>
                <div class="name">Annuale</div>
                <span class="tag">Il più conveniente</span>
            </div>
            <div class="price">€{{ $hubBilling['annual_price_eur'] }}<small>≈ €{{ number_format($hubBilling['annual_price_eur'] / 12, 0) }}/mese</small></div>
        </div>
        <div class="plan-card">
            <div>
                <div class="name">Privato</div>
                <span class="tag">Sempre gratis</span>
            </div>
            <div class="price">€0</div>
        </div>
    </div>

    <div class="group-title">Moduli attivi</div>
    <div class="stack modules">
        @foreach ($modulePricing as $key => $module)
            @continue($key === 'iva_percent')
            <div class="module-card">
                <div class="head"><h3>{{ $module['label'] }}</h3></div>
                <div class="stat-row">
                    <div class="stat"><div class="n">€{{ $module['activation_cents']/100 }}</div><div class="l">Attivazione</div></div>
                    <div class="stat"><div class="n">€{{ $module['monthly_cents']/100 }}<span style="font-size:.6em">/mese</span></div><div class="l">Canone</div></div>
                    <div class="stat"><div class="n">{{ $module['included_per_month'] }}</div><div class="l">Incluse/mese</div></div>
                    <div class="stat"><div class="n">€{{ $module['extra_self_cents']/100 }} · €{{ $module['extra_staff_cents']/100 }}</div><div class="l">Extra tu · noi</div></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="addon-card">
        <span>✨ Volantino con IA su misura</span>
        <b>€{{ number_format($aiFlyerPrice, 0) }}</b>
    </div>

    <p class="iva">Prezzi IVA esclusa (+{{ $modulePricing['iva_percent'] }}%)</p>

    @if ($comingSoon->isNotEmpty())
    <div class="group-title">In arrivo</div>
    <div class="soon-grid">
        @foreach ($comingSoon as $module)
            <div class="soon-card">
                <span class="emoji">{{ $module['emoji'] }}</span>
                <strong>{{ $module['label'] }}</strong>
                <span>da definire</span>
            </div>
        @endforeach
    </div>
    @endif
</div>

<footer><a href="{{ route('welcome') }}">← Home</a> · domande? scrivici da Max</footer>

</body>
</html>
