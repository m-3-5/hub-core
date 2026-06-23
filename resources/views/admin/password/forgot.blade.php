<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recupera accesso — Hub Core</title>
    <style>
        body {
            font-family: "Segoe UI", system-ui, sans-serif;
            background: linear-gradient(160deg, #1e1b4b, #312e81 45%, #4c1d95);
            min-height: 100dvh; display: flex; align-items: center; justify-content: center;
            margin: 0; padding: 20px;
        }
        .card {
            background: #fff; padding: 36px 32px; border-radius: 24px;
            width: 100%; max-width: 420px; box-shadow: 0 24px 60px rgba(0,0,0,.25);
        }
        h1 { margin: 0 0 8px; font-size: 1.5rem; }
        .sub { color: #64748b; margin: 0 0 24px; font-size: .95rem; line-height: 1.5; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: .9rem; }
        input[type=email] {
            width: 100%; padding: 12px 14px; margin-bottom: 20px;
            border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem;
        }
        button {
            width: 100%; background: linear-gradient(135deg, #6366f1, #a855f7);
            color: #fff; border: 0; padding: 14px; border-radius: 12px;
            font-weight: 700; cursor: pointer; font-size: 1rem;
        }
        .error { color: #dc2626; font-size: .9rem; margin-bottom: 12px; }
        .success { color: #15803d; background: #f0fdf4; padding: 12px; border-radius: 10px; font-size: .9rem; margin-bottom: 16px; line-height: 1.45; }
        .back { display: block; text-align: center; margin-top: 20px; color: #64748b; font-size: .9rem; text-decoration: none; }
    </style>
</head>
<body>
<div class="card">
    <h1>Recupera accesso</h1>
    <p class="sub">Inserisci la tua email: ti invieremo un messaggio con il link per impostare o reimpostare la password.</p>

    @if (session('status'))
        <p class="success">{{ session('status') }}</p>
    @endif

    @error('email')
        <p class="error">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('admin.password.email') }}">
        @csrf
        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        <button type="submit">Invia istruzioni via email</button>
    </form>

    <a class="back" href="{{ route('admin.login') }}">← Torna al login</a>
</div>
</body>
</html>
