<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Accedi — Hub Core</title>
    <style>
        body {
            font-family: "Segoe UI", system-ui, sans-serif;
            background: linear-gradient(160deg, #1e1b4b, #312e81 45%, #4c1d95);
            min-height: 100dvh; display: flex; align-items: center; justify-content: center;
            margin: 0; padding: 20px;
        }
        .card {
            background: #fff; padding: 36px 32px; border-radius: 24px;
            width: 100%; max-width: 400px; box-shadow: 0 24px 60px rgba(0,0,0,.25);
        }
        h1 { margin: 0 0 8px; font-size: 1.6rem; }
        .sub { color: #64748b; margin: 0 0 24px; font-size: .95rem; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: .9rem; }
        input[type=email], input[type=password] {
            width: 100%; padding: 12px 14px; margin-bottom: 16px;
            border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem;
        }
        input:focus { outline: 2px solid #818cf8; border-color: transparent; }
        .remember {
            display: flex; align-items: flex-start; gap: 10px;
            font-weight: 500; font-size: .9rem; margin-bottom: 20px; color: #334155;
        }
        .remember input { width: auto; margin: 3px 0 0; }
        .remember small { display: block; color: #94a3b8; font-weight: 400; margin-top: 2px; }
        button {
            width: 100%; background: linear-gradient(135deg, #6366f1, #a855f7);
            color: #fff; border: 0; padding: 14px; border-radius: 12px;
            font-weight: 700; cursor: pointer; font-size: 1rem;
        }
        .error { color: #dc2626; font-size: .9rem; margin-bottom: 12px; }
        .success { color: #15803d; background: #f0fdf4; padding: 12px; border-radius: 10px; font-size: .9rem; margin-bottom: 16px; }
        .links { margin-top: 20px; text-align: center; font-size: .9rem; }
        .links a { color: #6366f1; text-decoration: none; font-weight: 600; }
        .links a:hover { text-decoration: underline; }
        .back { display: block; margin-top: 14px; color: #64748b; }
    </style>
</head>
<body>
<div class="card">
    <h1>Hub Core</h1>
    <p class="sub">Accedi alla tua attività</p>

    @if (session('status'))
        <p class="success">{{ session('status') }}</p>
    @endif

    @error('email')
        <p class="error">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('admin.login.submit') }}">
        @csrf
        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username">

        <label for="password">Password</label>
        <input type="password" name="password" id="password" required autocomplete="current-password">

        <label class="remember">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            <span>
                Ricordami su questo dispositivo
                <small>Resta connesso anche dopo la chiusura del browser</small>
            </span>
        </label>

        <button type="submit">Accedi</button>
    </form>

    <div class="links">
        <a href="{{ route('admin.password.request') }}">Password dimenticata? Ricevi istruzioni via email</a>
        <a class="back" href="{{ route('welcome') }}">← Torna alla home</a>
    </div>
</div>
</body>
</html>
