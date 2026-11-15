<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="robots" content="noindex, nofollow">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('layouts.partials.favicon')
    <title>Ultimo passo — Hub Core</title>
    <style>
        body { margin: 0; min-height: 100dvh; display: flex; flex-direction: column; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f5fb; color: #12122b; }
        .gc-top { display: flex; align-items: center; gap: 10px; padding: calc(10px + env(safe-area-inset-top)) 16px 10px; background: #fff; border-bottom: 1px solid rgba(18,18,43,.09); }
        .gc-back { width: 44px; height: 44px; border-radius: 50%; display: grid; place-items: center; background: #f1f1f4; color: #12122b; text-decoration: none; font-size: 1.2rem; flex: none; }
        .gc-top b { font-size: 1.05rem; }
        main { flex: 1; width: 100%; max-width: 460px; margin: 0 auto; padding: 22px 16px calc(24px + env(safe-area-inset-bottom)); }
        .gc-ic { width: 64px; height: 64px; border-radius: 22px; display: grid; place-items: center; font-size: 2rem; background: linear-gradient(135deg, #eef2ff, #fdf2f8); margin-bottom: 14px; }
        h1 { margin: 0 0 6px; font-size: 1.6rem; letter-spacing: -.02em; }
        .gc-sub { margin: 0 0 20px; color: #667085; }
        form { display: grid; gap: 12px; }
        label.gc-l { font-weight: 700; font-size: .92rem; }
        .gc-in { width: 100%; min-height: 52px; padding: 0 14px; border: 1px solid #d6d9e6; border-radius: 14px; font: inherit; font-size: 16px; background: #fff; }
        .gc-in:focus { outline: 2px solid #818cf8; border-color: transparent; }
        .gc-ok { display: flex; gap: 10px; align-items: flex-start; font-size: .88rem; color: #475467; }
        .gc-ok input { width: 22px; height: 22px; margin: 0; flex: none; }
        .gc-go { min-height: 54px; border: 0; border-radius: 16px; background: #6366f1; color: #fff; font: 800 1.05rem system-ui, -apple-system, "Segoe UI", sans-serif; cursor: pointer; }
        .gc-or { display: flex; align-items: center; gap: 10px; color: #98a2b3; font-size: .85rem; margin: 6px 0; }
        .gc-or::before, .gc-or::after { content: ""; flex: 1; height: 1px; background: #e4e7ec; }
        .gc-err { color: #c62828; font-size: .9rem; margin: -4px 0 0; }
        .gc-note { text-align: center; color: #98a2b3; font-size: .8rem; margin: 6px 0 0; }
    </style>
@include('layouts.partials.ui')
</head>
<body>
<div class="gc-top">
    <a class="gc-back" href="{{ $promo ? route('admin.promos.show', [$tenant, $promo]) : route('app.home', $tenant) }}" aria-label="Indietro">←</a>
    <b>Ultimo passo</b>
</div>
<main>
    <div class="gc-ic" aria-hidden="true">🚀</div>
    <h1>Registrati e pubblica</h1>
    <p class="gc-sub">Una volta sola. Niente carta.</p>

    @if ($googleEnabled)
        @include('layouts.partials.google-button', ['googleParams' => ['per' => 'pubblica']])
    @endif

    <form method="POST" action="{{ route('guest.claim.store') }}">
        @csrf
        <label class="gc-l" for="contact">Email o cellulare</label>
        <input class="gc-in" id="contact" name="contact" type="text" inputmode="email" autocomplete="username" maxlength="190" required value="{{ old('contact') }}" placeholder="nome@esempio.it o 333 123 4567">
        @error('contact')<p class="gc-err">{{ $message }}</p>@enderror

        <label class="gc-l" for="password">Scegli una password</label>
        <input class="gc-in" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required placeholder="Almeno 8 caratteri">
        @error('password')<p class="gc-err">{{ $message }}</p>@enderror

        <label class="gc-ok"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> <span>Accetto che l'IA controlli i contenuti prima di pubblicarli e che il contatto serva per il mio account.</span></label>
        @error('consent')<p class="gc-err">{{ $message }}</p>@enderror
        @error('moderation')<p class="gc-err">{{ $message }}</p>@enderror

        <button type="submit" class="gc-go" data-busy="Pubblico…">{{ $promo ? 'Registrati e pubblica' : 'Registrati' }}</button>
    </form>
    <p class="gc-note">Poi entri con email o cellulare e la tua password.</p>
</main>
</body>
</html>
