<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Le tue attività — Hub Core</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f6fb; color: #0f172a; min-height: 100dvh; }
        .wrap { max-width: 720px; margin: 0 auto; padding: 32px 16px; }
        h1 { margin: 0 0 8px; }
        p { color: #64748b; }
        .top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 12px; }
        .btn { display: inline-block; padding: 10px 16px; border-radius: 10px; text-decoration: none; font-weight: 600; border: 0; cursor: pointer; }
        .btn-ghost { background: #e2e8f0; color: #0f172a; }
        .tenant-grid { display: grid; gap: 14px; }
        .tenant-card {
            display: flex; justify-content: space-between; align-items: center; gap: 16px;
            background: #fff; padding: 20px; border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15,23,42,.06); text-decoration: none; color: inherit;
        }
        .tenant-card:hover { box-shadow: 0 12px 32px rgba(15,23,42,.1); }
        .tenant-card strong { display: block; font-size: 1.1rem; }
        .tenant-card small { color: #64748b; }
        .dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1>Ciao, {{ $user->name }}</h1>
            <p>Scegli l'attività da gestire</p>
        </div>
        <div style="display:flex;gap:8px">
            @if ($user->isSuperAdmin())
                <a class="btn btn-ghost" href="{{ route('admin.dashboard') }}">Pannello admin</a>
            @endif
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn btn-ghost">Esci</button>
            </form>
        </div>
    </div>

    <div class="tenant-grid">
        @foreach ($tenants as $tenant)
            <a class="tenant-card" href="{{ route('app.home', $tenant) }}">
                <div style="display:flex;align-items:center;gap:14px">
                    <span class="dot" style="background:{{ $tenant->primary_color }}"></span>
                    <div>
                        <strong>{{ $tenant->name }}</strong>
                        <small>{{ $tenant->website }}</small>
                    </div>
                </div>
                <span>→</span>
            </a>
        @endforeach
    </div>
</div>
</body>
</html>
