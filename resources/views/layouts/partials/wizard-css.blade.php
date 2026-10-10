{{-- Stile condiviso delle procedure guidate a schermo intero (creazione e registrazione). Richiede $color. --}}
    <style>
        :root { --c: {{ $color }}; --c-dark: color-mix(in srgb, var(--c) 78%, #000); --c-soft: color-mix(in srgb, var(--c) 10%, #fff); --ink: #1a1a2e; --muted: #6b6b7b; }
        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        html, body { margin: 0; height: 100%; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; color: var(--ink); background: #fff; }
        .wz { min-height: 100dvh; display: flex; flex-direction: column; padding: env(safe-area-inset-top) 0 env(safe-area-inset-bottom); }
        .wz-top { display: flex; align-items: center; gap: 14px; padding: 14px 18px 10px; max-width: 760px; width: 100%; margin: 0 auto; }
        .wz-x { width: 40px; height: 40px; border-radius: 50%; border: 0; background: #f1f1f5; color: #333; font-size: 20px; line-height: 1; cursor: pointer; text-decoration: none; display: grid; place-items: center; flex: none; }
        .wz-progress { flex: 1; height: 6px; background: #eceef3; border-radius: 99px; overflow: hidden; }
        .wz-progress > i { display: block; height: 100%; width: 0; background: var(--c); border-radius: 99px; transition: width .35s ease; }
        .wz-count { font-size: .8rem; color: var(--muted); min-width: 34px; text-align: right; }
        .wz-stage { flex: 1; width: 100%; max-width: 760px; margin: 0 auto; padding: 8px 22px 130px; position: relative; }
        .wz-step { display: none; animation: wz-in .28s ease both; }
        .wz-step.on { display: block; }
        @keyframes wz-in { from { opacity: 0; transform: translateX(22px); } to { opacity: 1; transform: none; } }
        .wz-step.back { animation-name: wz-in-back; }
        @keyframes wz-in-back { from { opacity: 0; transform: translateX(-22px); } to { opacity: 1; transform: none; } }
        h1 { font-size: clamp(1.7rem, 6vw, 2.5rem); line-height: 1.15; margin: 14px 0 8px; letter-spacing: -.02em; }
        .sub { color: var(--muted); font-size: 1.02rem; margin: 0 0 22px; line-height: 1.45; }
        .choices { display: grid; gap: 14px; margin-top: 8px; }
        .choice { display: flex; align-items: center; gap: 16px; padding: 20px; border: 2px solid #e6e7ee; border-radius: 20px; background: #fff; cursor: pointer; text-align: left; font: inherit; color: inherit; width: 100%; transition: border-color .15s, background .15s, transform .1s; }
        .choice:hover, .choice.on { border-color: var(--c); background: var(--c-soft); }
        .choice:active { transform: scale(.985); }
        .choice .ico { font-size: 2rem; flex: none; }
        .choice b { display: block; font-size: 1.15rem; }
        .choice small { display: block; color: var(--muted); font-size: .92rem; margin-top: 2px; }
        .sr { position: absolute; width: 1px; height: 1px; opacity: 0; overflow: hidden; pointer-events: none; }
        .field { width: 100%; font: inherit; font-size: 1.3rem; padding: 16px 18px; border: 2px solid #e6e7ee; border-radius: 16px; background: #fff; color: var(--ink); outline: none; }
        .field:focus { border-color: var(--c); box-shadow: 0 0 0 4px var(--c-soft); }
        textarea.field { min-height: 190px; resize: vertical; font-size: 1.1rem; line-height: 1.5; }
        .hint { color: var(--muted); font-size: .88rem; margin-top: 8px; }
        .err { color: #c62828; font-size: .95rem; margin-top: 10px; min-height: 1.2em; }
        .ai { display: inline-flex; align-items: center; gap: 6px; background: var(--c-soft); color: var(--c-dark); font-weight: 600; font-size: .85rem; padding: 6px 12px; border-radius: 99px; margin-bottom: 12px; }
        .preview-photo { position: relative; border-radius: 20px; overflow: hidden; background: #f1f1f5; margin: 10px 0 0; }
        .preview-photo img { width: 100%; max-height: 46vh; object-fit: cover; display: block; }
        .yesno { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 18px; }
        .yesno .choice { justify-content: center; text-align: center; padding: 18px 12px; font-weight: 700; font-size: 1.05rem; }
        .chips { display: flex; flex-wrap: wrap; gap: 10px; }
        .chip { border: 2px solid #e6e7ee; border-radius: 99px; padding: 12px 18px; background: #fff; font: inherit; font-size: 1.05rem; font-weight: 600; cursor: pointer; }
        .chip.on { border-color: var(--c); background: var(--c-soft); color: var(--c-dark); }
        .money { position: relative; }
        .money span { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); font-size: 1.3rem; color: var(--muted); }
        .money .field { padding-right: 44px; }
        .sum { border: 2px solid #eceef3; border-radius: 22px; overflow: hidden; }
        .sum-img { position: relative; overflow: hidden; aspect-ratio: 16/10; background: linear-gradient(135deg, var(--c), var(--c-dark)); display: grid; place-items: center; color: #fff; text-align: center; padding: 18px; font-weight: 800; font-size: 1.5rem; }
        .sum-img img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
        .sum-body { padding: 18px 20px; }
        .sum-body h2 { margin: 0 0 6px; font-size: 1.35rem; }
        .sum-body p { margin: 0; color: #444; line-height: 1.5; white-space: pre-line; }
        .rows { margin-top: 14px; border-top: 1px solid #eee; }
        .row { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 13px 0; border-bottom: 1px solid #f1f1f5; font-size: 1rem; }
        .row span { color: var(--muted); }
        .row b { flex: 1; text-align: right; font-weight: 600; font-size: .95rem; overflow-wrap: anywhere; }
        .row a { color: var(--c-dark); font-weight: 700; font-size: .9rem; cursor: pointer; text-decoration: none; padding: 6px 4px; }
        .toggle { display: flex; align-items: center; gap: 12px; margin-top: 16px; font-size: 1.02rem; cursor: pointer; }
        .toggle input { width: 22px; height: 22px; accent-color: var(--c); }
        .banner { background: #fff3e0; color: #b45309; padding: 12px 16px; border-radius: 14px; margin: 6px 0 10px; font-size: .95rem; }
        .banner.bad { background: #fdecea; color: #b71c1c; }
        .wz-bar { position: sticky; bottom: 0; background: linear-gradient(to top, #fff 70%, rgba(255,255,255,0)); padding: 18px 18px calc(16px + env(safe-area-inset-bottom)); }
        .wz-bar-in { max-width: 760px; margin: 0 auto; display: flex; gap: 12px; }
        .btn { font: inherit; font-weight: 700; font-size: 1.1rem; border: 0; border-radius: 16px; padding: 17px 22px; cursor: pointer; }
        .btn.main { flex: 1; background: var(--c); color: #fff; box-shadow: 0 8px 22px color-mix(in srgb, var(--c) 40%, transparent); }
        .btn.main:disabled { opacity: .45; box-shadow: none; }
        .btn.ghost { background: #f1f1f5; color: #333; }
        .btn.alt { background: #fff; color: var(--c-dark); border: 2px solid var(--c); }
        .adv { display: block; text-align: center; margin: 18px 0 4px; color: var(--muted); font-size: .85rem; }
        .veil { position: fixed; inset: 0; background: rgba(255,255,255,.94); display: none; place-items: center; text-align: center; padding: 30px; z-index: 50; }
        .veil.on { display: grid; }
        .spin { width: 54px; height: 54px; border: 5px solid var(--c-soft); border-top-color: var(--c); border-radius: 50%; animation: sp 1s linear infinite; margin: 0 auto 18px; }
        @keyframes sp { to { transform: rotate(360deg); } }
        @media (min-width: 900px) { .wz-stage { padding-top: 28px; } h1 { margin-top: 28px; } }
    </style>
