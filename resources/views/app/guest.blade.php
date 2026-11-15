<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Hub Core: sito web, promo, annunci, servizi e prodotti della tua attività in un'app.">
    @include('layouts.partials.favicon')
    <title>Hub Core — la tua attività in un'app</title>
    <style>
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; background: #f4f5fb; color: #12122b; min-height: 100dvh; }
        .gw { max-width: 720px; margin: 0 auto; padding: 18px 16px calc(110px + env(safe-area-inset-bottom)); }
        .gw h1 { margin: 4px 0 16px; font-size: 1.5rem; letter-spacing: -.02em; }
        .gw-bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; display: flex; gap: 10px; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: #fff; border-top: 1px solid rgba(18,18,43,.09); box-shadow: 0 -8px 24px rgba(18,18,43,.07); }
        .gw-bar a { flex: 1; min-height: 52px; display: flex; align-items: center; justify-content: center; border-radius: 16px; font-weight: 800; text-decoration: none; }
        .gw-bar .ghost { background: #eef0fb; color: #12122b; }
        .gw-bar .main { background: #6366f1; color: #fff; }
        @media (min-width: 720px) { .gw-bar { position: static; max-width: 720px; margin: 8px auto 0; background: none; border: 0; box-shadow: none; } }
    </style>
@include('layouts.partials.ui')
</head>
<body>
@include('layouts.partials.public-nav')
<main class="gw">
    <h1>Cosa vuoi fare oggi?</h1>

    @include('layouts.partials.install-app')

    @include('app.partials.site-hero', ['siteUrl' => route('site.start'), 'hasSite' => false, 'launchEur' => $launchEur])

    <section class="ah-sec">
        <h2 class="ah-h">Tutti i servizi</h2>
        @include('app.partials.service-grid', ['tiles' => $tiles, 'canRequest' => false])
    </section>
</main>

<div class="gw-bar">
    <a class="ghost" href="{{ route('admin.login') }}">Accedi</a>
    <a class="main" href="{{ route('registration.create') }}">Registrati</a>
</div>

{{-- Scheda «inizia senza account»: due risposte e si entra, la registrazione si chiede solo per pubblicare --}}
<div class="gs-over" data-guest-sheet hidden>
    <form class="gs-card" method="POST" action="{{ route('guest.start') }}">
        @csrf
        <input type="hidden" name="vai" value="">
        <button type="button" class="gs-x" data-guest-close aria-label="Chiudi">✕</button>
        <h3 data-guest-title>Inizia subito</h3>
        <div class="gs-types" role="radiogroup" aria-label="Per chi è?">
            <label><input type="radio" name="type" value="azienda" checked><span>🏢 Azienda</span></label>
            <label><input type="radio" name="type" value="privato"><span>🙂 Privato</span></label>
            <label><input type="radio" name="type" value="ente"><span>🏛️ Ente</span></label>
        </div>
        <input type="text" name="company_name" required maxlength="120" placeholder="Come si chiama?" autocomplete="organization" aria-label="Come si chiama?">
        <button type="submit" class="gs-go" data-busy="Preparo l'app…">Inizia</button>
        <small>Senza account. Ti chiediamo email o cellulare solo per pubblicare.</small>
    </form>
</div>
<script>
(function () {
    var sheet = document.querySelector('[data-guest-sheet]');
    if (!sheet) return;
    var vai = sheet.querySelector('input[name=vai]');
    var title = sheet.querySelector('[data-guest-title]');
    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-guest-start]');
        if (t) {
            vai.value = t.getAttribute('data-guest-start');
            title.textContent = (t.querySelector('b') || {}).textContent || 'Inizia subito';
            sheet.hidden = false;
            setTimeout(function () { sheet.querySelector('input[name=company_name]').focus(); }, 50);
            return;
        }
        if (e.target === sheet || e.target.closest('[data-guest-close]')) { sheet.hidden = true; }
    });
})();
</script>
</body>
</html>
