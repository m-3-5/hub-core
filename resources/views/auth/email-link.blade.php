<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Entra con l'email · inm35.it</title>
    @include('layouts.partials.favicon')
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#4f46e5">
    @include('classifieds.partials.mobile-css')
@include('layouts.partials.ui')
</head>
<body>
@include('layouts.partials.public-nav')
<main class="m-wrap m-center">
    @if (session('sent'))
        <div class="m-big">📩</div>
        <h1>Controlla la tua email</h1>
        <p class="m-muted">Abbiamo scritto a <b>{{ session('sent') }}</b>.<br>Tocca il link per entrare.</p>
        <p class="m-muted m-small" style="margin-top:18px">Non arriva? Guarda nella posta indesiderata.</p>
    @else
        <div class="m-big">✉️</div>
        <h1>Entra con l'email</h1>
        <p class="m-muted">Niente password: ti mandiamo un link.</p>
        @if ($errors->any())<div class="m-error" style="margin-top:14px">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('email-login.send') }}" style="text-align:left;margin-top:18px">@csrf
            <input type="hidden" name="next" value="{{ $next }}">
            <label class="m-field"><span>Email</span>
                <input type="email" name="email" required maxlength="190" autocomplete="email" inputmode="email" value="{{ old('email') }}" autofocus>
            </label>
            <button class="m-btn" type="submit">Mandami il link</button>
        </form>
        <p class="m-muted m-small" style="margin-top:18px">Hai la password? <a href="{{ route('admin.login') }}">Accedi</a></p>
    @endif
</main>
</body>
</html>
