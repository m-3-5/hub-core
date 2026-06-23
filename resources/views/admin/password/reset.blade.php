<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Imposta password — Hub Core</title>
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
        .sub { color: #64748b; margin: 0 0 24px; font-size: .95rem; }
        label { display: block; margin-bottom: 6px; font-weight: 600; font-size: .9rem; }
        input {
            width: 100%; padding: 12px 14px; margin-bottom: 16px;
            border: 1px solid #e2e8f0; border-radius: 12px; font-size: 1rem;
        }
        button {
            width: 100%; background: linear-gradient(135deg, #6366f1, #a855f7);
            color: #fff; border: 0; padding: 14px; border-radius: 12px;
            font-weight: 700; cursor: pointer; font-size: 1rem; margin-top: 8px;
        }
        .error { color: #dc2626; font-size: .9rem; margin-bottom: 12px; }
        .hint { font-size: .82rem; color: #94a3b8; margin: -8px 0 12px; }
    </style>
</head>
<body>
<div class="card">
    <h1>Imposta la password</h1>
    <p class="sub">Scegli una password sicura per accedere a Hub Core.</p>

    @error('email')
        <p class="error">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('admin.password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="{{ old('email', $email) }}" required readonly>

        <label for="password">Nuova password</label>
        <p class="hint">Minimo 8 caratteri</p>
        <input type="password" name="password" id="password" required autofocus autocomplete="new-password">

        <label for="password_confirmation">Conferma password</label>
        <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password">

        <button type="submit">Salva e accedi</button>
    </form>
</div>
</body>
</html>
