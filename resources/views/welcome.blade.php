<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#6366f1">
    <title>Hub Core — La tua attività online, semplice</title>
    <style>
        :root {
            --accent: #6366f1;
            --accent2: #ec4899;
            --text: #0f172a;
            --muted: #64748b;
            --card-w: min(320px, 78vw);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", system-ui, -apple-system, sans-serif;
            color: var(--text);
            background: #fafbff;
            overflow-x: hidden;
        }
        .hero {
            display: grid;
            place-items: center;
            padding: 40px 20px 24px;
            text-align: center;
            background:
                radial-gradient(circle at 18% 22%, #eef2ff, transparent 42%),
                radial-gradient(circle at 82% 8%, #fce7f3, transparent 36%),
                #fafbff;
        }
        .hero h1 {
            font-size: clamp(2rem, 5vw, 3.4rem);
            margin: 0 0 14px;
            line-height: 1.08;
            letter-spacing: -.02em;
        }
        .hero p {
            color: var(--muted);
            font-size: clamp(1rem, 2.2vw, 1.15rem);
            max-width: 580px;
            margin: 0 auto 30px;
            line-height: 1.55;
        }
        .cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
        .btn {
            padding: 14px 26px;
            border-radius: 14px;
            font-weight: 700;
            text-decoration: none;
            font-size: 1rem;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            box-shadow: 0 10px 30px rgba(99, 102, 241, .35);
        }
        .btn-secondary {
            background: #fff;
            color: var(--text);
            border: 1px solid #e2e8f0;
        }
        .section-label {
            text-align: center;
            margin: 8px 0 20px;
            font-size: .85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: var(--muted);
        }
        .carousel-outer {
            position: relative;
            padding: 10px 0 50px;
            mask-image: linear-gradient(90deg, transparent, #000 8%, #000 92%, transparent);
        }
        .carousel-wrap {
            overflow: hidden;
            max-width: 100%;
        }
        .carousel-track {
            display: flex;
            gap: 22px;
            width: max-content;
            padding: 12px 24px 28px;
            animation: scroll 42s linear infinite;
        }
        .carousel-wrap:hover .carousel-track { animation-play-state: paused; }
        @media (prefers-reduced-motion: reduce) {
            .carousel-track { animation: none; }
        }
        @keyframes scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .flyer {
            width: var(--card-w);
            flex-shrink: 0;
            border-radius: 28px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 20px 50px rgba(15, 23, 42, .12);
            transform-style: preserve-3d;
            transition: transform .35s ease, box-shadow .35s ease;
            position: relative;
        }
        .flyer:hover {
            transform: perspective(900px) rotateY(-4deg) translateY(-6px) scale(1.02);
            box-shadow: 0 28px 60px rgba(15, 23, 42, .18);
            z-index: 2;
        }
        .flyer__img-wrap {
            width: 100%;
            aspect-ratio: 4 / 3;
            overflow: hidden;
            background: linear-gradient(160deg, #f1f5f9, #e2e8f0);
        }
        .flyer__img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center center;
            display: block;
        }
        .flyer__body {
            padding: 18px 18px 20px;
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
        }
        .flyer__body h3 {
            margin: 0 0 6px;
            font-size: 1.15rem;
        }
        .flyer__body p {
            margin: 0 0 16px;
            color: var(--muted);
            font-size: .9rem;
            line-height: 1.45;
            min-height: 2.9em;
        }
        .flyer__cta {
            display: block;
            text-align: center;
            padding: 12px 16px;
            border-radius: 12px;
            font-weight: 700;
            font-size: .92rem;
            text-decoration: none;
            color: #fff;
            box-shadow: 0 8px 20px rgba(0,0,0,.12);
            transition: filter .15s ease, transform .15s ease;
        }
        .flyer__cta:hover { filter: brightness(1.06); transform: translateY(-1px); }
        .register {
            max-width: 720px;
            margin: 0 auto 60px;
            padding: 0 20px;
            scroll-margin-top: 24px;
        }
        .register-card {
            background: #fff;
            border-radius: 28px;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 16px 48px rgba(15,23,42,.08);
            border: 1px solid #eef2ff;
        }
        .register-card h2 { margin: 0 0 10px; font-size: 1.6rem; }
        .register-card p { color: var(--muted); margin: 0 0 24px; line-height: 1.55; }
        .register-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 24px;
        }
        .register-type {
            background: #f8fafc;
            border-radius: 16px;
            padding: 18px 14px;
        }
        .register-type strong { display: block; margin-bottom: 4px; }
        .register-type span { font-size: .88rem; color: var(--muted); }
        footer {
            text-align: center;
            padding: 28px 20px 40px;
            color: var(--muted);
            font-size: .9rem;
        }
        @media (min-width: 1100px) {
            :root { --card-w: 300px; }
        }
        .app-preview {
            max-width: 720px;
            margin: 0 auto 8px;
            padding: 0 20px;
        }
        .app-preview-intro {
            text-align: center;
            max-width: 480px;
            margin: 0 auto 20px;
            color: var(--muted);
            font-size: .92rem;
        }
        .app-preview-intro strong { color: var(--text); }
        .app-preview-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }
        .app-preview-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: inherit;
            text-align: center;
        }
        .app-preview-icon {
            width: 60px;
            height: 60px;
            border-radius: 18px;
            display: grid;
            place-items: center;
            font-size: 1.6rem;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .1);
            transition: transform .15s ease;
        }
        .app-preview-tile:hover .app-preview-icon { transform: translateY(-3px); }
        .app-preview-label { font-size: .74rem; font-weight: 700; line-height: 1.2; }
        @media (min-width: 640px) {
            .app-preview-grid { grid-template-columns: repeat(7, 1fr); }
            .app-preview-icon { width: 68px; height: 68px; font-size: 1.9rem; }
        }

        .how-it-works {
            max-width: 720px;
            margin: 36px auto 8px;
            padding: 0 20px;
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
        }
        @media (min-width: 720px) {
            .how-it-works { grid-template-columns: repeat(3, 1fr); }
        }
        .how-step { display: flex; gap: 14px; align-items: flex-start; }
        .how-step-num {
            flex: 0 0 auto;
            width: 30px; height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff;
            display: grid; place-items: center;
            font-size: .85rem; font-weight: 700;
        }
        .how-step h3 { margin: 2px 0 4px; font-size: .95rem; }
        .how-step p { margin: 0; color: var(--muted); font-size: .85rem; line-height: 1.45; }

        .gmax-overlay {
            position: fixed; inset: 0; background: rgba(15,23,42,.5);
            display: flex; align-items: center; justify-content: center;
            padding: 20px; z-index: 80;
        }
        .gmax-overlay[hidden] { display: none; }
        .gmax-card {
            background: #fff; border-radius: 24px; padding: 28px 26px 26px;
            width: 100%; max-width: 380px; box-shadow: 0 30px 80px rgba(15,23,42,.3);
            position: relative; text-align: center;
        }
        .gmax-close {
            position: absolute; top: 14px; right: 14px; width: 28px; height: 28px;
            border-radius: 50%; border: 0; background: #f1f5f9; color: #475569;
            cursor: pointer; font-size: .85rem;
        }
        .gmax-avatar { display: flex; justify-content: center; margin-bottom: 14px; }
        .gmax-step[hidden] { display: none; }
        .gmax-step h3 { margin: 0 0 8px; font-size: 1.15rem; }
        .gmax-step p { margin: 0 0 16px; color: var(--muted); font-size: .9rem; }
        .gmax-choices { display: flex; flex-direction: column; gap: 10px; margin-top: 6px; }
        .gmax-chip {
            display: block; padding: 12px 16px; border-radius: 12px;
            background: color-mix(in srgb, var(--accent) 10%, #fff);
            border: 1px solid color-mix(in srgb, var(--accent) 28%, #fff);
            color: var(--text); font-weight: 700; font-size: .92rem;
            cursor: pointer; text-decoration: none;
        }
        .gmax-step input[type=text] {
            width: 100%; padding: 12px; margin-bottom: 14px; margin-top: 4px;
            border: 1px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: .95rem;
        }
        .gmax-submit {
            width: 100%; padding: 12px; border: 0; border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent2));
            color: #fff; font-weight: 700; cursor: pointer; font-size: .95rem;
        }
    </style>
</head>
<body>
<section class="hero">
    <div>
        <h1>Tutto per la tua attività,<br>in un'unica app</h1>
        <p>Promo, servizi, negozio, agenda, affitti e sito web. Per <strong>aziende</strong> e <strong>privati</strong> — semplice come le app del telefono.</p>
        <div class="cta">
            <a class="btn btn-primary" href="{{ route('admin.login') }}">Accedi</a>
            <a class="btn btn-secondary" href="#registrazione">Registrati</a>
        </div>
    </div>
</section>

<div class="app-preview">
    <p class="app-preview-intro">Così sarà la tua home appena entri — <strong>tocca una voce per iniziare</strong>, il resto lo trovi qui sotto spiegato per bene.</p>
    <div class="app-preview-grid">
        @foreach ($slides as $slide)
            <a class="app-preview-tile" href="{{ $slide['cta_url'] }}">
                <div class="app-preview-icon" style="background: linear-gradient(145deg, color-mix(in srgb, {{ $slide['accent'] }} 20%, #fff), #fff)">{{ $slide['emoji'] }}</div>
                <div class="app-preview-label">{{ $slide['title'] }}</div>
            </a>
        @endforeach
    </div>
</div>

<div class="how-it-works">
    <div class="how-step">
        <span class="how-step-num">1</span>
        <div>
            <h3>Scegli cosa ti serve</h3>
            <p>Promo, servizi, negozio o affitti — attivi solo i moduli utili alla tua attività.</p>
        </div>
    </div>
    <div class="how-step">
        <span class="how-step-num">2</span>
        <div>
            <h3>Provalo gratis</h3>
            <p>Demo completa da subito, nessuna carta richiesta per cominciare.</p>
        </div>
    </div>
    <div class="how-step">
        <span class="how-step-num">3</span>
        <div>
            <h3>Vai online quando sei pronto</h3>
            <p>Le tue promo e i tuoi servizi compaiono subito sul tuo sito, senza toccare codice.</p>
        </div>
    </div>
</div>

<p class="section-label" id="funzioni">Sfoglia le possibilità</p>

<div class="carousel-outer">
    <div class="carousel-wrap">
        <div class="carousel-track" aria-label="Anteprima funzioni Hub Core">
            @foreach ($loopSlides as $slide)
                <article class="flyer">
                    <div class="flyer__img-wrap" style="background: linear-gradient(160deg, color-mix(in srgb, {{ $slide['accent'] }} 18%, #fff), color-mix(in srgb, {{ $slide['accent'] }} 8%, #f8fafc));">
                        <img
                            class="flyer__img"
                            src="{{ $slide['image_url'] }}"
                            alt="{{ $slide['title'] }} — Hub Core"
                            loading="lazy"
                            width="320"
                            height="240"
                        >
                    </div>
                    <div class="flyer__body">
                        <h3>{{ $slide['title'] }}</h3>
                        <p>{{ $slide['text'] }}</p>
                        <a
                            class="flyer__cta"
                            href="{{ $slide['cta_url'] }}"
                            style="background: linear-gradient(135deg, {{ $slide['accent'] }}, color-mix(in srgb, {{ $slide['accent'] }} 70%, #1e1b4b))"
                        >{{ $slide['cta'] }}</a>
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</div>

<section class="register" id="registrazione">
    <div class="register-card">
        <h2>Registrati su Hub Core</h2>
        <p>Prova gratis con {{ config('hub-payments.services_included_quota', 3) }} servizi a pagamento inclusi — poi solo €{{ config('hub-payments.services_paid_price', 9) }}/mese per continuare. Nessuna carta richiesta ora.</p>

        @if (session('success'))
            <p style="background:#e8f5e9;color:#2e7d32;padding:12px 16px;border-radius:12px;margin-bottom:20px">{{ session('success') }}</p>
        @endif
        @error('registration')
            <p style="background:#fdecea;color:#c62828;padding:12px 16px;border-radius:12px;margin-bottom:20px">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('registration.store') }}" style="text-align:left;display:grid;gap:14px;margin-bottom:24px">
            @csrf
            <div>
                <label style="display:block;font-weight:600;margin-bottom:6px;font-size:.9rem">Tipo di registrazione *</label>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
                    @foreach (['azienda' => '🏢 Azienda', 'privato' => '👤 Privato', 'ente' => '🏛️ Ente'] as $value => $label)
                        <label style="display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;border:2px solid #e2e8f0;border-radius:10px;cursor:pointer;font-size:.88rem;font-weight:600;has-[:checked]:border-color:var(--accent)">
                            <input type="radio" name="type" value="{{ $value }}" @checked(old('type', 'azienda') === $value) required style="accent-color:var(--accent)">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('type')<p style="color:#c62828;font-size:.85rem;margin:4px 0 0">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="company_name" style="display:block;font-weight:600;margin-bottom:6px;font-size:.9rem">Nome / Ragione sociale *</label>
                <input type="text" name="company_name" id="company_name" value="{{ old('company_name') }}" required maxlength="120"
                       style="width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:10px">
                @error('company_name')<p style="color:#c62828;font-size:.85rem;margin:4px 0 0">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="contact_name" style="display:block;font-weight:600;margin-bottom:6px;font-size:.9rem">Il tuo nome *</label>
                <input type="text" name="contact_name" id="contact_name" value="{{ old('contact_name') }}" required maxlength="120"
                       style="width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:10px">
                @error('contact_name')<p style="color:#c62828;font-size:.85rem;margin:4px 0 0">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" style="display:block;font-weight:600;margin-bottom:6px;font-size:.9rem">Email *</label>
                <input type="email" name="email" id="email" value="{{ old('email') }}" required maxlength="190"
                       style="width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:10px">
                @error('email')<p style="color:#c62828;font-size:.85rem;margin:4px 0 0">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone" style="display:block;font-weight:600;margin-bottom:6px;font-size:.9rem">Telefono (opzionale)</label>
                <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" maxlength="30"
                       style="width:100%;padding:12px;border:1px solid #e2e8f0;border-radius:10px">
            </div>
            <button type="submit" class="btn btn-primary" style="border:0;cursor:pointer">Registrati — ti mandiamo un'email di conferma</button>
        </form>
        <div class="cta">
            <a class="btn btn-secondary" href="{{ route('admin.login') }}">Hai già un account? Accedi</a>
        </div>
    </div>
</section>

<footer>Hub Core — piattaforma multiservizi per aziende e privati</footer>

<div class="gmax-overlay" id="gmax-overlay" hidden>
    <div class="gmax-card">
        <button type="button" class="gmax-close" id="gmax-close" aria-label="Chiudi">✕</button>
        <div class="gmax-avatar">@include('app.partials.max-avatar', ['size' => 64, 'animated' => true])</div>

        <div class="gmax-step" data-gstep="greeting">
            <h3>Ciao! Cosa vuoi fare?</h3>
            <p>Sono Max — ti aiuto a orientarti su Hub Core.</p>
            <div class="gmax-choices">
                <a href="{{ route('admin.login') }}" class="gmax-chip">Accedi</a>
                <a href="#registrazione" class="gmax-chip" id="gmax-goto-register">Registrati</a>
                <button type="button" class="gmax-chip" data-gmax-goto="type">👋 Continua come ospite</button>
            </div>
        </div>

        <form method="POST" action="{{ route('guest.start') }}" id="gmax-guest-form">
            @csrf
            <div class="gmax-step" data-gstep="type" hidden>
                <h3>Sei un'azienda, un privato o un ente?</h3>
                <p>Ti creo subito uno spazio di prova — puoi decidere dopo se tenerlo.</p>
                <div class="gmax-choices">
                    <button type="button" class="gmax-chip" data-gmax-type="azienda">🏢 Azienda</button>
                    <button type="button" class="gmax-chip" data-gmax-type="privato">👤 Privato</button>
                    <button type="button" class="gmax-chip" data-gmax-type="ente">🏛️ Ente</button>
                </div>
                <input type="hidden" name="type" id="gmax-type">
            </div>
            <div class="gmax-step" data-gstep="name" hidden>
                <h3>Come si chiama la tua attività?</h3>
                <p>La useremo per personalizzare quello che crei.</p>
                <input type="text" name="company_name" maxlength="120" required placeholder="Es. Salone Anna">
                <button type="submit" class="gmax-submit">Inizia →</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const overlay = document.getElementById('gmax-overlay');
    const close = document.getElementById('gmax-close');
    const steps = Array.from(overlay.querySelectorAll('.gmax-step'));
    const typeInput = document.getElementById('gmax-type');
    const registerLink = document.getElementById('gmax-goto-register');

    function showStep(name) {
        steps.forEach(s => { s.hidden = s.dataset.gstep !== name; });
    }

    overlay.querySelectorAll('[data-gmax-goto]').forEach(btn => {
        btn.addEventListener('click', () => showStep(btn.dataset.gmaxGoto));
    });

    overlay.querySelectorAll('[data-gmax-type]').forEach(btn => {
        btn.addEventListener('click', () => {
            typeInput.value = btn.dataset.gmaxType;
            showStep('name');
        });
    });

    close.addEventListener('click', () => {
        overlay.hidden = true;
        sessionStorage.setItem('gmaxDismissed', '1');
    });

    registerLink.addEventListener('click', () => { overlay.hidden = true; });

    if (!sessionStorage.getItem('gmaxDismissed')) {
        setTimeout(() => {
            overlay.hidden = false;
            showStep('greeting');
        }, 1200);
    }
})();
</script>
</body>
</html>
