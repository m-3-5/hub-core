<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Hub Core Admin')</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, sans-serif; background: #f6f7fb; color: #1a1a2e; }
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
    </style>
</head>
<body>
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
    @yield('content')
</main>
</body>
</html>
