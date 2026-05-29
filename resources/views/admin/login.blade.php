<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — Hub Core</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #1a1a2e; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .card { background: #fff; padding: 32px; border-radius: 12px; width: 100%; max-width: 380px; }
        h1 { margin: 0 0 24px; font-size: 22px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; }
        input { width: 100%; padding: 10px; margin-bottom: 16px; border: 1px solid #ddd; border-radius: 8px; }
        button { width: 100%; background: #e91e8c; color: #fff; border: 0; padding: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .error { color: #c62828; font-size: 14px; margin-bottom: 12px; }
    </style>
</head>
<body>
<div class="card">
    <h1>Hub Core Admin</h1>
    @error('password')
        <p class="error">{{ $message }}</p>
    @enderror
    <form method="POST" action="{{ route('admin.login.submit') }}">
        @csrf
        <label for="password">Password</label>
        <input type="password" name="password" id="password" required autofocus>
        <button type="submit">Accedi</button>
    </form>
</div>
</body>
</html>
