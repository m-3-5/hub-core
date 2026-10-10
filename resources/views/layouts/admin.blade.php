<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/images/icon-192.png">
    <link rel="icon" href="/images/icon-192.png">
    <title>@yield('title', 'Hub Core Admin')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: #f6f7fb; color: #1a1a2e; animation: app-fade-in .18s ease; }
        @keyframes app-fade-in { from { opacity: 0; } to { opacity: 1; } }
        header { background: #1a1a2e; color: #fff; padding: 14px 24px; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #fff; text-decoration: none; }
        main { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
        .card { background: #fff; border-radius: 12px; padding: 24px; box-shadow: 0 2px 12px rgba(0,0,0,.06); }
        .btn { display: inline-block; background: #e91e8c; color: #fff; border: 0; padding: 10px 18px; border-radius: 8px; text-decoration: none; cursor: pointer; font-weight: 600; }
        .btn-secondary { background: #eee; color: #333; }
        .btn-danger { background: #c62828; color: #fff; }
        .admin-grid { display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 20px; }
        @media (max-width: 900px) { .admin-grid { grid-template-columns: 1fr; } }
        .preview-frame { width: 100%; min-height: 720px; border: 1px solid #e0e0e0; border-radius: 12px; background: #fff; }
        input[type=text], input[type=datetime-local], textarea { font-family: inherit; }
        .alert { background: #e8f5e9; color: #2e7d32; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        .alert-warning { background: #fff3e0; color: #e65100; }
        .error { color: #c62828; font-size: 14px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input[type=file], input[type=password] { width: 100%; margin-bottom: 16px; }
        /* Schede con immagine (promo, annunci…): più foto che testo, X per eliminare e matita per modificare */
        .mgrid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 18px; margin: 0 0 26px; }
        .mcard { position: relative; background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid rgba(0,0,0,.06); box-shadow: 0 6px 20px rgba(0,0,0,.07); display: flex; flex-direction: column; transition: transform .15s, box-shadow .15s; }
        .mcard:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(0,0,0,.12); }
        .mcard--muted .mcard__media img { filter: grayscale(.55); opacity: .85; }
        .mcard__media { position: relative; display: block; aspect-ratio: 4/5; background: linear-gradient(135deg, #fdf2f8, #eef2ff); }
        .mcard__media img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .mcard__ph { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; font-size: 2.2rem; color: #b8879e; }
        .mcard__ph small { font-size: .72rem; }
        .mcard__badge { position: absolute; left: 10px; bottom: 10px; font-size: .72rem; font-weight: 700; padding: 4px 10px; border-radius: 999px; backdrop-filter: blur(6px); }
        .mcard__badge--ok { background: rgba(232,245,233,.95); color: #2e7d32; }
        .mcard__badge--warn { background: rgba(255,243,224,.95); color: #e65100; }
        .mcard__badge--off { background: rgba(241,241,244,.95); color: #555; }
        .mcard__tools { position: absolute; top: 10px; right: 10px; display: flex; gap: 8px; z-index: 2; }
        .mcard__tools form { margin: 0; }
        .mcard__tool { width: 38px; height: 38px; border-radius: 50%; border: 0; background: rgba(255,255,255,.95); color: #1a1a2e; font-size: 1.1rem; font-weight: 700; display: grid; place-items: center; cursor: pointer; text-decoration: none; box-shadow: 0 3px 10px rgba(0,0,0,.2); font-family: inherit; line-height: 1; }
        .mcard__tool:hover { background: #fff; transform: scale(1.08); }
        .mcard__tool--del { color: #c62828; }
        .mcard__tool--del:hover { background: #c62828; color: #fff; }
        .mcard__body { padding: 12px 14px 14px; display: flex; flex-direction: column; gap: 4px; }
        .mcard__body h3 { margin: 0; font-size: 1rem; line-height: 1.25; }
        .mcard__body h3 a { color: #1a1a2e; text-decoration: none; }
        .mcard__body p { margin: 0; font-size: .82rem; color: #666; }
        .mcard__open { font-size: .8rem; color: #e91e8c; text-decoration: none; font-weight: 600; margin-top: 2px; }
        .mcard__action { width: 100%; border: 0; border-radius: 999px; padding: 8px 12px; font: inherit; font-size: .85rem; font-weight: 700; cursor: pointer; background: #f4f4f8; color: #333; }
        .mcard__action:hover { background: #e9e9f0; }
        .mcard--new { border: 2px dashed #e91e8c; background: #fff7fb; box-shadow: none; min-height: 220px; }
        .mcard--new a { flex: 1; display: grid; place-items: center; align-content: center; color: #e91e8c; text-align: center; font-weight: 700; text-decoration: none; padding: 20px; }
        .mcard--new b { display: block; font-size: 2.6rem; line-height: 1; }
        @media (max-width: 560px) { .mgrid { grid-template-columns: repeat(2, 1fr); gap: 12px; } .mcard__tool { width: 36px; height: 36px; } }
    </style>
</head>
<body class="@isset($tenant) has-bottom-nav @endisset">
<header>
    <div>
        <strong>Hub Core</strong>
        @auth
            <span style="opacity:.75;font-size:14px;margin-left:12px">{{ auth()->user()->name }}</span>
        @endauth
    </div>
    <div style="display:flex;gap:8px;align-items:center">
        @auth
            @if (auth()->user()->accessibleTenants()->count() === 1)
                <a href="{{ route('app.home', auth()->user()->accessibleTenants()->first()) }}" class="btn btn-secondary" style="padding:8px 14px">Home app</a>
            @elseif (auth()->user()->accessibleTenants()->isNotEmpty())
                <a href="{{ route('app.index') }}" class="btn btn-secondary" style="padding:8px 14px">Home app</a>
            @endif
        @endauth
        <form method="POST" action="{{ route('admin.logout') }}" style="display:inline">
            @csrf
            <button type="submit" class="btn btn-secondary" style="border:0">Esci</button>
        </form>
    </div>
</header>
<main>
    @if (session('success'))
        <div class="alert">{{ session('success') }}</div>
    @endif
    @if (session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @isset($tenant)
        @if ($tenant->trialExpired())
            <div class="alert alert-warning">
                La demo gratuita di {{ $tenant->name }} è scaduta —
                <a href="{{ route('admin.billing.show', $tenant) }}">abbonati per continuare</a>.
            </div>
        @elseif ($tenant->onTrial() && $tenant->trialDaysRemaining() <= 7)
            <div class="alert alert-warning">
                Demo gratuita in scadenza tra {{ $tenant->trialDaysRemaining() }} giorni —
                <a href="{{ route('admin.billing.show', $tenant) }}">abbonati ora</a>.
            </div>
        @endif
    @endisset
    @yield('content')
</main>
@include('layouts.partials.bottom-nav')
</body>
</html>
