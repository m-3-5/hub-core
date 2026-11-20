<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Codice buono · inm35.it</title>
    @include('layouts.partials.favicon')
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#4f46e5">
    @include('classifieds.partials.mobile-css')
@include('layouts.partials.ui')
</head>
<body>
@include('layouts.partials.public-nav')
<main class="m-wrap m-center">
    <div class="m-big">🎁</div>
    <h1>Hai un codice buono?</h1>
    <p class="m-muted">Il premio dei giochi di piramide35.com diventa credito qui.</p>

    @if ($errors->any())<div class="m-error" style="margin-top:14px">{{ $errors->first() }}</div>@endif

    @guest
        @if ($code !== '')
            <p style="margin-top:14px;font-family:ui-monospace,monospace;font-weight:700;word-break:break-all">{{ $code }}</p>
        @endif
        <p class="m-muted" style="margin-top:14px">Prima entra: poi il buono si carica sul tuo credito.</p>
        <form method="POST" action="{{ route('email-login.send') }}" style="text-align:left;margin-top:14px">@csrf
            <input type="hidden" name="next" value="{{ $back }}">
            <label class="m-field"><span>La tua email</span>
                <input type="email" name="email" required maxlength="190" autocomplete="email" inputmode="email" value="{{ old('email') }}">
            </label>
            <button class="m-btn" type="submit">Mandami il link per entrare</button>
        </form>
        @if (session('sent'))<div class="m-ok" style="margin-top:14px">📩 Link mandato a {{ session('sent') }}: aprilo dal telefono.</div>@endif
        <p class="m-muted m-small" style="margin-top:16px">Hai la password? <a href="{{ route('admin.login') }}">Accedi</a> · <a href="{{ route('google.login') }}">Google</a></p>
    @else
        @if ($check && $check['ok'])
            <div class="m-ok" style="margin-top:14px">Buono da <b>{{ \App\Support\PayPerUse::euro($check['cents']) }}</b> · valido fino al {{ $check['expires_on']->format('d/m/Y') }}</div>
        @elseif ($check)
            <div class="m-error" style="margin-top:14px">{{ $check['error'] }}</div>
        @endif
        <form method="POST" action="{{ route('voucher.redeem') }}" style="text-align:left;margin-top:14px">@csrf
            @if ($tenant)<input type="hidden" name="attivita" value="{{ $tenant->slug }}">@endif
            <label class="m-field"><span>Codice</span>
                <input type="text" name="codice" required maxlength="80" autocapitalize="characters" autocomplete="off" spellcheck="false" value="{{ old('codice', $code) }}" placeholder="M35G-…" style="font-family:ui-monospace,monospace">
            </label>
            <button class="m-btn" type="submit">Usa il buono</button>
        </form>
        @if ($tenant)
            <p class="m-muted m-small" style="margin-top:16px">Va sul credito di <b>{{ $tenant->name }}</b> · <a href="{{ route('admin.credit.show', $tenant) }}">Pagamenti</a></p>
        @endif
    @endguest
    <p class="m-muted m-small" style="margin-top:10px">Ogni buono vale una volta · massimo {{ \App\Support\GameVoucher::monthlyLimit() }} al mese</p>
</main>
</body>
</html>
