<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $tenant->primary_color ?? '#6366f1' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Hub Core">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/images/icon-192.png">
    <link rel="icon" href="/images/icon-192.png">
    <title>@yield('title', 'Hub') — {{ $tenant->name ?? 'Hub Core' }}</title>
    <style>
        :root {
            --accent: {{ $tenant->primary_color ?? '#6366f1' }};
            --bg: #f4f6fb;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --radius: 22px;
            --shadow: 0 8px 32px rgba(15, 23, 42, .08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100dvh;
            animation: app-fade-in .18s ease;
        }
        @keyframes app-fade-in { from { opacity: 0; } to { opacity: 1; } }
        .app-shell { max-width: 1200px; margin: 0 auto; padding: 20px 16px 40px; }
        .app-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 28px;
        }
        .app-top h1 { margin: 0; font-size: clamp(1.25rem, 2vw, 1.75rem); font-weight: 700; }
        .app-top p { margin: 4px 0 0; color: var(--muted); font-size: .95rem; }
        .app-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            padding: 10px 16px; border-radius: 12px; font-weight: 600;
            text-decoration: none; border: 0; cursor: pointer; font-size: .9rem;
        }
        .btn-ghost { background: #e2e8f0; color: var(--text); }
        .btn-accent { background: var(--accent); color: #fff; }
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 18px;
        }
        @media (min-width: 900px) {
            .module-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 24px; }
            .app-shell { padding: 32px 24px 48px; }
        }
        .module-tile {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 18px 12px 14px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            transition: transform .15s ease, box-shadow .15s ease;
            border: 1px solid rgba(255,255,255,.6);
        }
        .module-tile:hover:not(.is-disabled) { transform: translateY(-3px); box-shadow: 0 14px 40px rgba(15,23,42,.12); }
        .module-tile.is-disabled { opacity: .45; cursor: default; }
        .module-icon {
            width: 64px; height: 64px; margin: 0 auto 10px;
            border-radius: 18px;
            background: linear-gradient(145deg, color-mix(in srgb, var(--accent) 18%, #fff), #fff);
            display: grid; place-items: center;
            font-size: 1.75rem;
        }
        .module-tile.is-disabled .module-icon { background: #f1f5f9; }
        .module-label { font-weight: 700; font-size: .88rem; line-height: 1.2; }
        .module-desc { font-size: .72rem; color: var(--muted); margin-top: 4px; line-height: 1.3; }
        .badge-soon {
            display: inline-block; margin-top: 6px;
            font-size: .65rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: .04em; color: var(--muted);
        }
        .section-card {
            background: var(--card); border-radius: var(--radius);
            box-shadow: var(--shadow); padding: 20px; margin-top: 28px;
        }
        .section-card h2 { margin: 0 0 12px; font-size: 1.1rem; }
        .promo-list { list-style: none; padding: 0; margin: 0; }
        .promo-list li {
            display: flex; justify-content: space-between; align-items: center;
            gap: 12px; padding: 12px 0; border-top: 1px solid #f1f5f9;
            flex-wrap: wrap;
        }
        .promo-list li:first-child { border-top: 0; }
        .promo-list a { color: var(--text); font-weight: 600; text-decoration: none; }
        .status {
            font-size: .72rem; padding: 3px 8px; border-radius: 999px; font-weight: 700;
        }
        .status-published { background: #dcfce7; color: #166534; }
        .status-draft { background: #ffedd5; color: #9a3412; }
        .alert { background: #dcfce7; color: #166534; padding: 12px 16px; border-radius: 12px; margin-bottom: 16px; }
    </style>
</head>
<body class="@isset($tenant) has-bottom-nav @endisset">
<div class="app-shell">
    @if (session('success'))
        <div class="alert">{{ session('success') }}</div>
    @endif
    @yield('content')
</div>
@include('layouts.partials.bottom-nav')
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js'));
    }
</script>
</body>
</html>
