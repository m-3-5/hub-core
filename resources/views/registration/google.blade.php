<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#6366f1">
    <meta name="robots" content="noindex">
    <title>{{ $forSite ? 'Crea il tuo sito' : 'Registrati' }} — Hub Core</title>
    @include('layouts.partials.favicon')
    @include('layouts.partials.wizard-css', ['color' => '#6366f1'])
    <style>
        .wz-stage { padding-left: 16px; padding-right: 16px; }
        .mail { display: inline-flex; gap: 8px; align-items: center; background: #e8f5e9; color: #2e7d32; font-weight: 600; padding: 8px 14px; border-radius: 99px; font-size: .92rem; overflow-wrap: anywhere; }
        label.q { display: block; font-weight: 700; margin: 22px 0 10px; font-size: 1.05rem; }
        .choices .choice { min-height: 64px; }
        .choice input { position: absolute; opacity: 0; pointer-events: none; }
        .choice:has(input:checked) { border-color: var(--c); background: var(--c-soft); }
    </style>
    @include('layouts.partials.ui')
</head>
<body>
<div class="wz">
    <div class="wz-top">
        <a class="wz-x" href="{{ route('welcome') }}" aria-label="Esci">✕</a>
        <div class="wz-progress"><i style="width:90%"></i></div>
        <div class="wz-count"></div>
    </div>

    <form class="wz-stage" method="POST" action="{{ route('google.signup.store') }}">
        @csrf
        @if ($errors->any())
            <div class="banner bad">{{ $errors->first() }}</div>
        @endif

        <span class="mail">✓ {{ $email }}</span>
        <h1>{{ $forSite ? 'Ultimo passo, poi il sito' : 'Ultimo passo' }}</h1>
        <p class="sub">Google ha confermato la tua email. Mancano due risposte.</p>

        <label class="q">Per chi è?</label>
        <div class="choices">
            <label class="choice"><input type="radio" name="type" value="azienda" @checked(old('type', 'azienda') === 'azienda')><span class="ico">🏢</span><span><b>Azienda</b><small>Negozio, studio, attività con clienti</small></span></label>
            <label class="choice"><input type="radio" name="type" value="ente" @checked(old('type') === 'ente')><span class="ico">🏛️</span><span><b>Ente o associazione</b><small>Eventi, comunicazioni, iniziative</small></span></label>
            @unless ($forSite)
                <label class="choice"><input type="radio" name="type" value="privato" @checked(old('type') === 'privato')><span class="ico">👤</span><span><b>Privato</b><small>Sempre gratis: annunci, affitti, idee</small></span></label>
            @endunless
        </div>

        <label class="q" for="company">Come si chiama?</label>
        <input class="field" id="company" name="company_name" type="text" maxlength="120" required autocomplete="organization" value="{{ old('company_name') }}" placeholder="Es. Salone Anna">

        <label class="q" for="contact">E tu come ti chiami? <small style="font-weight:400;color:var(--muted)">(facoltativo)</small></label>
        <input class="field" id="contact" name="contact_name" type="text" maxlength="120" autocomplete="name" value="{{ old('contact_name') }}" placeholder="Es. Anna Rossi">

        <p class="hint" style="margin-top:18px">{{ $forSite ? 'I primi giorni sono gratis, senza carta.' : 'La prova è gratis, senza carta.' }} Usiamo i tuoi dati solo per gestire il tuo account.</p>

        <div class="wz-bar" style="margin:0 -16px">
            <div class="wz-bar-in"><button type="submit" class="btn main">{{ $forSite ? 'Crea il mio spazio e il sito' : 'Entra' }}</button></div>
        </div>
    </form>
</div>
</body>
</html>
