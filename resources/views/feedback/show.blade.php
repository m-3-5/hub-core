<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Il tuo feedback — Hub Core</title>
    <meta name="robots" content="noindex">
    <style>
        :root { --primary: {{ $tenant->primary_color ?: '#6366f1' }}; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, sans-serif; background: #f6f7fb; color: #1f1a24; margin: 0; padding: 40px 16px; }
        .wrap { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 20px; padding: 32px; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
        h1 { font-size: 1.4rem; margin: 0 0 6px; }
        .intro { color: #666; font-size: .95rem; margin: 0 0 28px; }
        .q { margin-bottom: 24px; }
        .q label { display: block; font-weight: 600; margin-bottom: 10px; font-size: .95rem; }
        .scale { display: flex; gap: 8px; }
        .scale label { display: flex; flex-direction: column; align-items: center; gap: 4px; font-weight: 400; font-size: .8rem; color: #666; cursor: pointer; }
        .scale input { width: 20px; height: 20px; accent-color: var(--primary); }
        textarea { width: 100%; padding: 11px 12px; border: 1px solid #ddd; border-radius: 8px; font-family: inherit; font-size: .95rem; resize: vertical; }
        button { background: var(--primary); color: #fff; border: 0; padding: 13px 26px; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: .95rem; }
        .success { background: #e8f5e9; color: #2e7d32; padding: 16px; border-radius: 12px; }
    </style>
</head>
<body>
<div class="wrap">
    @if (session('feedback_sent'))
        <h1>Grazie! 🙏</h1>
        <p class="success">Il tuo feedback ci aiuta a migliorare Hub Core — grazie del tempo che ci hai dedicato.</p>
    @elseif ($already)
        <h1>Grazie, ci hai già risposto!</h1>
        <p class="intro">Il tuo feedback è già stato registrato per questa richiesta. Se vuoi aggiungere altro, scrivici da Max nella tua area.</p>
    @else
        <h1>Ciao {{ $tenant->name }} 👋</h1>
        <p class="intro">Qualche domanda veloce per capire come migliorare Hub Core in base a quello che hai già creato — bastano due minuti.</p>

        @error('answers')<p style="color:#c62828;font-size:.9rem;margin-bottom:16px">{{ $message }}</p>@enderror

        <form method="POST" action="{{ url()->full() }}">
            @csrf
            @foreach ($questions as $q)
                <div class="q">
                    <label>{{ $q['label'] }}</label>
                    @if ($q['type'] === 'scale')
                        <div class="scale">
                            @for ($i = 1; $i <= 5; $i++)
                                <label>
                                    <input type="radio" name="answers[{{ $q['key'] }}]" value="{{ $i }}">
                                    {{ $i }}
                                </label>
                            @endfor
                        </div>
                    @else
                        <textarea name="answers[{{ $q['key'] }}]" rows="2"></textarea>
                    @endif
                </div>
            @endforeach
            <button type="submit">Invia feedback</button>
        </form>
    @endif
</div>
</body>
</html>
