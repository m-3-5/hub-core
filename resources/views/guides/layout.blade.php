<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title') — Hub Core</title>
    <meta name="description" content="@yield('description')">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="@yield('title') — Hub Core">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:image" content="{{ asset('images/og-hub-core.png') }}">
    <meta name="theme-color" content="#6366f1">
    @include('layouts.partials.favicon')
    <style>
        :root { --accent: #6366f1; --accent2: #ec4899; --ink: #15131a; --muted: #6b667a; --line: #ece9f5; --bg: #f7f6fb; }
        * { box-sizing: border-box; margin: 0; padding: 0; -webkit-tap-highlight-color: transparent; }
        body { font-family: -apple-system, "Segoe UI", system-ui, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.55; padding-bottom: calc(90px + env(safe-area-inset-bottom)); }
        a { color: var(--accent); }
        .top { position: sticky; top: 0; z-index: 20; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; padding: calc(12px + env(safe-area-inset-top)) 14px 14px; display: flex; align-items: center; gap: 10px; }
        .top a.btn { background: rgba(255,255,255,.2); color: #fff; text-decoration: none; border-radius: 999px; padding: 9px 14px; font-weight: 700; font-size: .9rem; min-height: 40px; display: inline-flex; align-items: center; cursor: pointer; border: 0; font: inherit; font-weight: 700; }
        .top b { flex: 1; text-align: center; font-size: 1.05rem; }
        .wrap { max-width: 760px; margin: 0 auto; padding: 20px 16px; }
        h1 { font-size: clamp(1.6rem, 6vw, 2.2rem); line-height: 1.15; margin-bottom: 10px; }
        h2 { font-size: 1.2rem; margin: 26px 0 8px; }
        p, li { font-size: 1rem; }
        p { margin: 8px 0; }
        ul, ol { margin: 8px 0 8px 22px; display: grid; gap: 6px; }
        .lead { color: var(--muted); font-size: 1.05rem; }
        .cards { display: grid; gap: 12px; margin-top: 18px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 20px; padding: 18px; display: flex; gap: 14px; align-items: center; text-decoration: none; color: inherit; }
        .card .ico { flex: none; width: 56px; height: 56px; border-radius: 18px; display: grid; place-items: center; font-size: 1.8rem; background: linear-gradient(145deg, #eef2ff, #fdf2f8); }
        .card b { display: block; font-size: 1.05rem; }
        .card span { color: var(--muted); font-size: .92rem; }
        .tag { display: inline-block; font-size: .7rem; font-weight: 800; color: var(--accent); background: #eef2ff; border-radius: 999px; padding: 2px 9px; margin-bottom: 4px; }
        .box { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px 16px; margin: 14px 0; }
        .box.warn { background: #fff8e8; border-color: #f3dca0; }
        .box.ok { background: #eefaf2; border-color: #bfe6cc; }
        .btn-main { display: inline-block; background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; text-decoration: none; font-weight: 800; border-radius: 14px; padding: 13px 20px; margin: 6px 6px 6px 0; }
        .btn-ghost { display: inline-block; background: #fff; color: var(--accent); border: 2px solid var(--accent); text-decoration: none; font-weight: 800; border-radius: 14px; padding: 11px 18px; margin: 6px 6px 6px 0; }
        .dock { position: fixed; left: 0; right: 0; bottom: 0; z-index: 30; background: rgba(255,255,255,.96); border-top: 1px solid var(--line); padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); display: flex; gap: 10px; justify-content: center; }
        .dock a { flex: 1; max-width: 360px; text-align: center; text-decoration: none; font-weight: 800; border-radius: 14px; padding: 13px; min-height: 48px; }
        .dock .b { background: #f1f1f5; color: var(--ink); flex: none; padding: 13px 20px; }
        .dock .m { background: linear-gradient(135deg, var(--accent), var(--accent2)); color: #fff; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: .95rem; background: #fff; border-radius: 12px; overflow: hidden; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { background: #f1f0fa; }
        .small { color: var(--muted); font-size: .85rem; }
    </style>
</head>
<body>
<header class="top">
    <a class="btn" href="@yield('back_url', route('guides.index'))" id="g-back">← Indietro</a>
    <b>@yield('top', 'Guide')</b>
    <a class="btn" href="{{ route('welcome') }}">🏠</a>
</header>
<main class="wrap">
    @yield('content')
</main>
<div class="dock">
    <a class="b" href="@yield('back_url', route('guides.index'))" id="g-back2">← Indietro</a>
    <a class="m" href="{{ route('registration.create') }}">Registrati gratis</a>
</div>
@include('partials.max-public-chat', ['autoOpen' => false, 'lift' => 72])
<script>
(function () {
    function back(e) { var i = document.referrer && document.referrer.indexOf(location.origin) === 0; if (i && history.length > 1) { e.preventDefault(); history.back(); } }
    ['g-back', 'g-back2'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener('click', back); });
})();
</script>
</body>
</html>
