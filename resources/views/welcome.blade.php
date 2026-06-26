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
            min-height: 88dvh;
            display: grid;
            place-items: center;
            padding: 48px 20px 32px;
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
            .hero { min-height: 78dvh; }
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
        <h2>Registrazione in arrivo</h2>
        <p>Stiamo aprendo l'accesso a nuove attività e privati. Scegli il profilo che fa per te — ti avviseremo appena è attivo.</p>
        <div class="register-types">
            <div class="register-type">
                <strong>🏢 Azienda</strong>
                <span>Centri estetici, negozi, B&B, professionisti</span>
            </div>
            <div class="register-type">
                <strong>👤 Privato</strong>
                <span>Vendi prodotti o pubblica annunci in bakeca</span>
            </div>
        </div>
        <div class="cta">
            <a class="btn btn-primary" href="{{ route('admin.login') }}">Hai già un account? Accedi</a>
            <a class="btn btn-secondary" href="mailto:startupm3.5@gmail.com?subject=Registrazione%20Hub%20Core">Richiedi accesso anticipato</a>
        </div>
    </div>
</section>

<footer>Hub Core — piattaforma multiservizi per aziende e privati</footer>
</body>
</html>
