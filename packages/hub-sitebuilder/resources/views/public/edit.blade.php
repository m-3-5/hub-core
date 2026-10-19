<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#6366f1">
    <title>Modifica il tuo sito — {{ $tenant->name }}</title>
    @include('layouts.partials.favicon')
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f5fb; color: #12122b; }
        .top { background: linear-gradient(135deg, #4f46e5, #8b5cf6); color: #fff; padding: calc(18px + env(safe-area-inset-top)) 16px 26px; }
        .top h1 { margin: 0; font-size: 1.35rem; }
        .top p { margin: 4px 0 0; opacity: .9; font-size: .92rem; }
        main { max-width: 640px; margin: -14px auto 0; padding: 0 16px calc(100px + env(safe-area-inset-bottom)); }
        .card { background: #fff; border-radius: 22px; padding: 18px; box-shadow: 0 6px 24px rgba(18,18,43,.08); margin-bottom: 14px; }
        label { display: block; font-weight: 800; margin: 14px 0 6px; }
        label small { font-weight: 500; color: #667085; display: block; }
        input, textarea { width: 100%; font: inherit; font-size: 16px; padding: 12px; border: 1px solid #d9dcf0; border-radius: 14px; background: #fff; }
        textarea { min-height: 84px; resize: vertical; }
        .ok { background: #e8f5e9; color: #2e7d32; border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; font-weight: 700; }
        .err { background: #fdecea; color: #c62828; border-radius: 14px; padding: 12px 14px; margin-bottom: 14px; }
        .bar { position: fixed; left: 0; right: 0; bottom: 0; background: #fff; border-top: 1px solid rgba(18,18,43,.08); padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); display: flex; gap: 10px; }
        .bar button, .bar a { flex: 1; min-height: 52px; border-radius: 16px; border: 0; font: inherit; font-weight: 800; font-size: 1.02rem; display: flex; align-items: center; justify-content: center; text-decoration: none; cursor: pointer; }
        .bar button { background: #6366f1; color: #fff; }
        .bar a { background: #eef0fb; color: #12122b; }
        .note { color: #667085; font-size: .85rem; line-height: 1.5; }
    </style>
    @include('layouts.partials.ui')
</head>
<body>
<div class="top">
    <h1>Modifica il tuo sito</h1>
    <p>{{ $tenant->name }} · senza password: questo link è solo tuo.</p>
</div>
<main>
    @if (session('saved'))
        <div class="ok">Salvato! Il sito è già aggiornato.</div>
    @endif
    @if ($errors->any())
        <div class="err">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('site.edit.update', $token) }}" id="edit-form">
        @csrf
        <div class="card">
            <label for="tagline">La frase in prima pagina <small>Quella che colpisce subito chi arriva.</small></label>
            <input id="tagline" name="tagline" maxlength="200" required value="{{ old('tagline', $answers['tagline'] ?? '') }}">

            <label for="services">Servizi o prodotti principali <small>Separati da una virgola.</small></label>
            <textarea id="services" name="services" maxlength="1000" required>{{ old('services', implode(', ', $answers['services'] ?? [])) }}</textarea>

            <label for="cta_label">Cosa deve fare chi visita il sito? <small>Il testo del pulsante, es. «Chiamaci ora».</small></label>
            <input id="cta_label" name="cta_label" maxlength="60" value="{{ old('cta_label', $answers['cta_label'] ?? 'Contattaci') }}">

            <label for="extra">Altro da far sapere <small>Facoltativo.</small></label>
            <textarea id="extra" name="extra" maxlength="1000">{{ old('extra', $answers['extra'] ?? '') }}</textarea>
        </div>
        <p class="note">Controlliamo che i testi siano adatti a un sito serio: niente spam, volgarità o cose vietate. Non inoltrare questo link a nessuno.</p>
    </form>
</main>
<div class="bar">
    <a href="{{ route('site.public.show', $tenant) }}" target="_blank" rel="noopener">Vedi il sito</a>
    <button type="submit" form="edit-form">Salva</button>
</div>
</body>
</html>
