<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $answers = $site->answers ?? [];
        $extras = $answers['extras'] ?? [];
        $tagline = $answers['tagline'] ?? $tenant->name;
        $services = \M35\HubSitebuilder\Services\SiteContent::servicesFor($answers);
        $strengths = $extras['strengths'] ?? [];
        $faq = $extras['faq'] ?? [];
        $closing = $extras['closing'] ?? '';
        $ctaLabel = $answers['cta_label'] ?? 'Contattaci';
        $about = $answers['extra'] ?? null;

        // Stile: quello scelto dall'attività nel profilo, altrimenti quello proposto dall'IA.
        // Lo stile scelto nel riepilogo della creazione guidata vince; altrimenti quello del profilo.
        $styleKey = $extras['style'] ?? \App\Support\BrandFonts::keyForTenant($tenant);
        $brandFont = \App\Support\BrandFonts::get($styleKey);
        $dark = $styleKey === 'digitale';
        $primary = $tenant->primary_color ?: '#6366f1';

        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $info = $answers['info'] ?? [];
        $media = $answers['media'] ?? [];
        $photos = array_map(fn ($p) => $disk->url($p), $media['photos'] ?? []);
        $logo = ! empty($media['logo']) ? $disk->url($media['logo']) : null;
        $reviews = $info['reviews'] ?? [];
        $hours = $info['hours'] ?? null;
        $socials = array_filter(['Facebook' => $info['facebook'] ?? null, 'Instagram' => $info['instagram'] ?? null]);
        $vat = $info['vat'] ?? null;
        // Una foto vera batte qualsiasi grafica: se c'è, la prima diventa lo sfondo in alto.
        $heroVisual = $photos[0] ?? ($site->hero_video_path
            ? $disk->url($site->hero_video_path)
            : ($site->hero_svg_path ? $disk->url($site->hero_svg_path) : null));
        $aboutPhoto = $photos[1] ?? null;
        $schedule = $info['schedule'] ?? null;
        $hoursText = trim(implode(' · ', array_filter([\M35\HubSitebuilder\Services\SiteContent::scheduleText($schedule), $hours])));
        // Quello che l'attività pubblica già su Hub Core entra da solo nel sito.
        $promos = \App\Models\Promo::query()->where('tenant_id', $tenant->id)->active()->latest('published_at')->take(6)->get();
        $shop = class_exists(\M35\HubPayments\Models\PayableService::class)
            ? \M35\HubPayments\Models\PayableService::query()->where('tenant_id', $tenant->id)->where('status', 'active')->where('published_to_site', true)->latest()->take(6)->get()
            : collect();
        $tel = $tenant->phone ? preg_replace('/[^0-9+]/', '', $tenant->phone) : null;
        // WhatsApp solo per i cellulari italiani (3xx) o numeri già con prefisso internazionale.
        $waNumber = $tel ? ltrim(preg_match('/^3\d{8,9}$/', $tel) ? '39'.$tel : (str_starts_with($tel, '+') ? $tel : ''), '+') : '';
        $waUrl = $waNumber ? 'https://wa.me/'.$waNumber.'?text='.rawurlencode('Ciao '.$tenant->name.', vi scrivo dal vostro sito') : null;
        $ctaUrl = $waUrl && str_contains(strtolower($ctaLabel), 'whatsapp') ? $waUrl : ($tel ? 'tel:'.$tel : '#contatti');
        $hasContacts = $tel || $tenant->address;
        $mapsUrl = $tenant->address ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($tenant->address) : null;
        $initial = mb_strtoupper(mb_substr(trim($tenant->name), 0, 1));
        $lead = $about ? \Illuminate\Support\Str::limit(\Illuminate\Support\Str::before($about.' ', '. ').'.', 140) : null;
        $siteUrl = route('site.public.show', $tenant);
        $description = \Illuminate\Support\Str::limit($tagline.($about ? ' — '.$about : ''), 160);
    @endphp
    <title>{{ $tenant->name }} — {{ $tagline }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $siteUrl }}">
    <meta property="og:title" content="{{ $tenant->name }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="it_IT">
    <meta property="og:url" content="{{ $siteUrl }}">
    <meta property="og:image" content="{{ $photos[0] ?? asset('images/og-hub-core.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $tenant->name }}">
    <meta name="twitter:image" content="{{ $photos[0] ?? asset('images/og-hub-core.png') }}">
    <meta name="theme-color" content="{{ $primary }}">
    <script type="application/ld+json">{!! json_encode(array_filter([
        '@'.'context' => 'https://schema.org',
        '@type' => $tenant->type === 'ente' ? 'Organization' : 'LocalBusiness',
        'name' => $tenant->name,
        'description' => $tagline,
        'url' => $siteUrl,
        'telephone' => $tenant->phone,
        'address' => $tenant->address,
        'image' => $photos[0] ?? $logo,
        'logo' => $logo,
        'vatID' => $vat,
        'sameAs' => array_values($socials) ?: null,
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?{{ $brandFont['google_fonts'] }}&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: {{ $primary }};
            --primary-dark: color-mix(in srgb, var(--primary) 70%, #000);
            --primary-soft: color-mix(in srgb, var(--primary) 12%, {{ $dark ? '#0d0f14' : '#fff' }});
            --ink: {{ $dark ? '#eef0f5' : '#14161a' }};
            --paper: {{ $dark ? '#0d0f14' : '#ffffff' }};
            --soft: {{ $dark ? '#161922' : '#f7f7f4' }};
            --card: {{ $dark ? '#1b1f2a' : '#ffffff' }};
            --muted: {{ $dark ? '#a4a9b6' : '#5b6169' }};
            --line: {{ $dark ? '#2a2f3c' : '#ecece8' }};
            --radius: {{ in_array($styleKey, ['amichevole'], true) ? '26px' : (in_array($styleKey, ['classico', 'tech'], true) ? '10px' : '18px') }};
            --font-display: {!! $brandFont['display'] !!};
            --font-body: {!! $brandFont['body'] !!};
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body { font-family: var(--font-body), system-ui, sans-serif; color: var(--ink); background: var(--paper); line-height: 1.6; -webkit-font-smoothing: antialiased; }
        img { max-width: 100%; display: block; }
        a { color: inherit; }
        h1, h2, h3 { font-family: var(--font-display), sans-serif; letter-spacing: -.01em; text-wrap: balance; line-height: 1.15; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 0 16px; }
        @media (min-width: 700px) { .wrap { padding: 0 28px; } }

        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 52px; padding: 0 26px; border-radius: 999px; font-weight: 700; font-size: 1rem; text-decoration: none; background: var(--primary); color: #fff; border: 2px solid transparent; transition: transform .15s, box-shadow .15s; box-shadow: 0 10px 24px color-mix(in srgb, var(--primary) 35%, transparent); }
        .btn:hover { transform: translateY(-2px); }
        .btn.ghost { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.55); box-shadow: none; backdrop-filter: blur(6px); }
        .btn.wa { background: #1fa855; box-shadow: 0 10px 24px rgba(31,168,85,.3); }
        .btn.light { background: #fff; color: var(--primary-dark); box-shadow: none; }

        /* Barra in alto */
        .top { position: sticky; top: 0; z-index: 30; background: color-mix(in srgb, var(--paper) 88%, transparent); backdrop-filter: blur(10px); border-bottom: 1px solid var(--line); }
        .top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 60px; }
        .brand { display: flex; align-items: center; gap: 10px; font-family: var(--font-display); font-weight: 700; text-decoration: none; font-size: 1.05rem; min-width: 0; }
        .brand span:last-child { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mono { flex: none; width: 38px; height: 38px; border-radius: 12px; display: grid; place-items: center; background: var(--primary); color: #fff; font-weight: 800; }
        .top nav { display: none; gap: 22px; font-weight: 600; font-size: .95rem; }
        .top nav a { text-decoration: none; color: var(--muted); }
        .top nav a:hover { color: var(--ink); }
        .top .btn { min-height: 42px; padding: 0 18px; font-size: .9rem; box-shadow: none; white-space: nowrap; flex: none; }
        /* Sul telefono i tasti sono già nella barra in basso. */
        @media (max-width: 759px) { body.has-dock .top .btn { display: none; } }
        @media (min-width: 860px) { .top nav { display: flex; } }

        /* Hero */
        .hero { position: relative; overflow: hidden; color: #fff; background: linear-gradient(135deg, var(--primary-dark), #0d0f14); }
        .hero-bg { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: .9; }
        .hero-scrim { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.25), rgba(0,0,0,.65)), radial-gradient(circle at 15% 85%, color-mix(in srgb, var(--primary) 55%, transparent), transparent 55%); }
        .hero-inner { position: relative; z-index: 2; padding-top: 72px; padding-bottom: 120px; }
        .hero-inner > * { max-width: 720px; }
        .eyebrow { display: inline-flex; align-items: center; gap: 8px; font-size: .78rem; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.3); padding: 7px 14px; border-radius: 99px; margin-bottom: 18px; }
        .hero h1 { font-size: clamp(2.1rem, 6.4vw, 3.6rem); margin-bottom: 16px; }
        .hero p.lead { font-size: 1.08rem; opacity: .92; max-width: 560px; margin-bottom: 26px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; }
        .wave { position: absolute; left: 0; right: 0; bottom: -1px; width: 100%; height: 70px; z-index: 3; display: block; }

        /* Sezioni */
        .section { padding: 64px 0; }
        .section.soft { background: var(--soft); }
        .head { text-align: center; max-width: 640px; margin: 0 auto 34px; }
        .kicker { color: var(--primary); font-weight: 800; letter-spacing: .1em; text-transform: uppercase; font-size: .78rem; display: block; margin-bottom: 8px; }
        .head h2 { font-size: clamp(1.6rem, 4.5vw, 2.3rem); }
        @media (min-width: 700px) { .section { padding: 88px 0; } }

        .strengths { display: grid; gap: 16px; margin-top: -20px; }
        .strength { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 24px; box-shadow: 0 14px 34px rgba(16,18,24,.06); }
        .ico { width: 56px; height: 56px; border-radius: 16px; display: grid; place-items: center; font-size: 1.7rem; background: var(--primary-soft); margin-bottom: 14px; }
        .strength h3 { font-size: 1.15rem; margin-bottom: 6px; }
        .strength p { color: var(--muted); }
        @media (min-width: 760px) { .strengths { grid-template-columns: repeat(3, 1fr); } }

        .services { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(min(290px, 100%), 1fr)); }
        .service { position: relative; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 22px; display: flex; gap: 16px; align-items: flex-start; transition: transform .2s, box-shadow .2s, border-color .2s; overflow: hidden; }
        .service::after { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px; background: var(--primary); opacity: 0; transition: opacity .2s; }
        .service:hover { transform: translateY(-3px); box-shadow: 0 16px 34px rgba(16,18,24,.08); border-color: color-mix(in srgb, var(--primary) 35%, var(--line)); }
        .service:hover::after { opacity: 1; }
        .service .ico { flex: none; margin: 0; width: 50px; height: 50px; font-size: 1.5rem; }
        .service h3 { font-size: 1.08rem; margin-bottom: 4px; }
        .service p { color: var(--muted); font-size: .95rem; }

        .about { display: grid; gap: 28px; align-items: center; }
        .about-art { position: relative; aspect-ratio: 1; max-width: min(340px, 60vw); width: 100%; margin: 0 auto; border-radius: 32px; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); display: grid; place-items: center; color: #fff; overflow: hidden; }
        .about-art b { font-family: var(--font-display); font-size: clamp(6rem, 22vw, 9rem); line-height: 1; opacity: .95; }
        .about-art::before, .about-art::after { content: ''; position: absolute; border-radius: 50%; border: 2px solid rgba(255,255,255,.25); }
        .about-art::before { width: 70%; height: 70%; top: -18%; right: -18%; }
        .about-art::after { width: 50%; height: 50%; bottom: -12%; left: -12%; background: rgba(255,255,255,.08); }
        .about-text h2 { font-size: clamp(1.6rem, 4.5vw, 2.3rem); margin-bottom: 14px; }
        .about-text p { color: var(--muted); font-size: 1.08rem; }
        @media (min-width: 820px) { .about { grid-template-columns: 1fr 1.2fr; gap: 56px; } }

        .faq { max-width: 760px; margin: 0 auto; display: grid; gap: 12px; }
        .faq details { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 0 20px; }
        .faq summary { list-style: none; cursor: pointer; font-weight: 700; min-height: 60px; display: flex; align-items: center; justify-content: space-between; gap: 14px; }
        .faq summary::-webkit-details-marker { display: none; }
        .faq summary::after { content: '+'; flex: none; width: 30px; height: 30px; border-radius: 50%; display: grid; place-items: center; background: var(--primary-soft); color: var(--primary); font-size: 1.2rem; transition: transform .2s; }
        .faq details[open] summary::after { transform: rotate(45deg); }
        .faq details p { color: var(--muted); padding-bottom: 18px; }

        .band { position: relative; overflow: hidden; border-radius: 28px; padding: 44px 24px; text-align: center; color: #fff; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
        .band::before { content: ''; position: absolute; width: 320px; height: 320px; border-radius: 50%; right: -90px; top: -120px; background: rgba(255,255,255,.1); }
        .band::after { content: ''; position: absolute; width: 220px; height: 220px; border-radius: 50%; left: -70px; bottom: -110px; border: 2px solid rgba(255,255,255,.2); }
        .band h2 { position: relative; font-size: clamp(1.5rem, 4.4vw, 2.2rem); margin-bottom: 22px; }
        .band .hero-actions { position: relative; justify-content: center; }

        .contacts { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .contact { display: flex; align-items: center; gap: 14px; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 18px; text-decoration: none; min-height: 76px; }
        .contact .ico { margin: 0; width: 48px; height: 48px; font-size: 1.35rem; flex: none; }
        .contact small { display: block; color: var(--muted); font-size: .82rem; }
        .contact b { overflow-wrap: anywhere; }

        /* Footer */
        footer { background: #0f1117; color: #c8ccd6; padding: 56px 0 calc(28px + env(safe-area-inset-bottom)); }
        .foot { display: grid; gap: 32px; }
        .foot .brand { color: #fff; margin-bottom: 12px; }
        .foot p { color: #9aa0ad; font-size: .95rem; max-width: 340px; }
        .foot h4 { color: #fff; font-size: .82rem; letter-spacing: .1em; text-transform: uppercase; margin-bottom: 12px; }
        .foot ul { list-style: none; display: grid; gap: 8px; }
        .foot a { text-decoration: none; color: #c8ccd6; }
        .foot a:hover { color: #fff; }
        .legal { margin-top: 40px; padding-top: 20px; border-top: 1px solid #262a35; display: flex; flex-wrap: wrap; gap: 8px 18px; justify-content: space-between; font-size: .85rem; color: #8a90a0; }
        .legal a { color: #c8ccd6; }
        .made { display: inline-flex; align-items: center; gap: 6px; }
        .made b { color: #fff; }
        @media (min-width: 760px) { .foot { grid-template-columns: 1.4fr 1fr 1fr; } }

        /* Logo, foto, galleria, recensioni, dove e quando */
        .mono.logo { object-fit: contain; background: #fff; padding: 3px; border: 1px solid var(--line); }
        .about-art.photo { background: var(--soft); }
        .about-art.photo::before, .about-art.photo::after { display: none; }
        .about-art.photo img { width: 100%; height: 100%; object-fit: cover; }
        .gallery { display: grid; gap: 10px; grid-template-columns: repeat(2, 1fr); }
        .shot { border: 0; padding: 0; background: none; cursor: zoom-in; border-radius: var(--radius); overflow: hidden; aspect-ratio: 4/3; }
        .shot img { width: 100%; height: 100%; object-fit: cover; transition: transform .4s; }
        .shot:hover img { transform: scale(1.05); }
        @media (min-width: 760px) { .gallery { grid-template-columns: repeat(3, 1fr); gap: 14px; } }
        .reviews { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(min(280px, 100%), 1fr)); }
        .review { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 24px; position: relative; }
        .review::before { content: '“'; position: absolute; right: 18px; top: -6px; font-size: 5rem; line-height: 1; color: var(--primary-soft); font-family: Georgia, serif; }
        .stars { color: #f5b301; letter-spacing: 2px; margin-bottom: 8px; }
        .review blockquote { font-size: 1.05rem; }
        .review figcaption { margin-top: 10px; color: var(--muted); font-weight: 700; font-size: .92rem; }
        .where { display: grid; gap: 16px; margin-top: 22px; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 20px; }
        .where h3 { font-size: 1.2rem; margin-bottom: 8px; }
        .where p { color: var(--muted); margin-top: 4px; }
        .map { min-height: 220px; border-radius: calc(var(--radius) - 6px); background: linear-gradient(135deg, var(--primary-soft), var(--soft)); display: grid; place-items: center; text-align: center; gap: 8px; padding: 16px; overflow: hidden; }
        .map small { color: var(--muted); font-size: .8rem; }
        .map iframe { width: 100%; height: 100%; min-height: 260px; border: 0; }
        .map.on { padding: 0; }
        @media (min-width: 760px) { .where { grid-template-columns: 1fr 1.3fr; align-items: center; } }
        .socials { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
        .socials a { border: 1px solid #343a48; border-radius: 99px; padding: 8px 14px; font-size: .88rem; min-height: 40px; display: inline-flex; align-items: center; }
        .lightbox { position: fixed; inset: 0; z-index: 60; background: rgba(5,6,10,.92); display: none; place-items: center; padding: 16px; }
        .lightbox.on { display: grid; }
        .lightbox img { max-width: 100%; max-height: 86vh; border-radius: 14px; }
        .lightbox button { position: absolute; top: calc(12px + env(safe-area-inset-top)); right: 12px; width: 48px; height: 48px; border-radius: 50%; border: 0; background: rgba(255,255,255,.15); color: #fff; font-size: 1.4rem; cursor: pointer; }

        /* Offerte e acquisti (promo e servizi già pubblicati su Hub Core) */
        /* Schede di misura fissa e centrate: anche con una sola offerta non diventano enormi. */
        .cards { display: grid; gap: 14px; grid-template-columns: repeat(auto-fit, minmax(min(250px, 100%), 320px)); justify-content: center; }
        .card { display: flex; flex-direction: column; background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); overflow: hidden; text-decoration: none; transition: transform .2s, box-shadow .2s; }
        .card:hover { transform: translateY(-3px); box-shadow: 0 16px 34px rgba(16,18,24,.08); }
        .card-img { position: relative; aspect-ratio: 4/3; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); display: grid; place-items: center; color: #fff; font-weight: 800; text-align: center; padding: 0; }
        .card-img span { padding: 16px; font-family: var(--font-display); font-size: 1.2rem; }
        .card-img img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; }
        .card-img em { position: absolute; top: 10px; left: 10px; font-style: normal; background: #fff; color: var(--primary-dark); font-weight: 800; font-size: .75rem; padding: 5px 10px; border-radius: 99px; }
        .card-body { padding: 16px 18px 18px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
        .card-body h3 { font-size: 1.05rem; }
        .card-body p { color: var(--muted); font-size: .92rem; }
        .card-body .price { color: var(--ink); font-weight: 800; font-size: 1.15rem; }
        .card-cta { margin-top: auto; padding-top: 8px; color: var(--primary); font-weight: 800; }
        .more { text-align: center; margin-top: 18px; }
        .more a { display: inline-flex; align-items: center; min-height: 48px; font-weight: 700; color: var(--primary); }
        .open-now { display: inline-flex; align-items: center; gap: 8px; font-weight: 700; }
        .open-now::before { content: ''; width: 10px; height: 10px; border-radius: 50%; background: #9aa0ad; }
        .open-now.is-open::before { background: #22c55e; box-shadow: 0 0 0 4px rgba(34,197,94,.25); }
        .open-now:empty { display: none; }
        .hero-open { background: rgba(255,255,255,.14); border: 1px solid rgba(255,255,255,.3); padding: 7px 14px; border-radius: 99px; font-size: .85rem; margin: 0 0 18px 8px; }

        /* Barra fissa su telefono */
        .dock { position: fixed; left: 0; right: 0; bottom: 0; z-index: 40; display: flex; gap: 10px; padding: 10px 16px calc(10px + env(safe-area-inset-bottom)); background: color-mix(in srgb, var(--paper) 92%, transparent); backdrop-filter: blur(10px); border-top: 1px solid var(--line); }
        .dock .btn { flex: 1; min-height: 50px; padding: 0 12px; box-shadow: none; }
        body.has-dock { padding-bottom: 76px; }
        @media (min-width: 760px) { .dock { display: none; } body.has-dock { padding-bottom: 0; } }

        /* Comparsa morbida scorrendo (solo con JavaScript e senza «riduci movimento») */
        .js .rv { opacity: 0; transform: translateY(22px); transition: opacity .6s ease, transform .6s ease; }
        .js .rv.in { opacity: 1; transform: none; }
        @media (prefers-reduced-motion: reduce) { .js .rv { opacity: 1; transform: none; transition: none; } html { scroll-behavior: auto; } }
    </style>
    <script>document.documentElement.classList.add('js');</script>
@include('layouts.partials.ui')
</head>
<body class="{{ $tel ? 'has-dock' : '' }}" @if ($schedule) data-schedule='@json($schedule)' @endif>

<header class="top">
    <div class="wrap">
        <a class="brand" href="#top">@if ($logo)<img class="mono logo" src="{{ $logo }}" alt="">@else<span class="mono">{{ $initial }}</span>@endif<span>{{ $tenant->name }}</span></a>
        <nav aria-label="Sezioni">
            @if (count($services))<a href="#servizi">Servizi</a>@endif
            @if ($about)<a href="#chi-siamo">Chi siamo</a>@endif
            @if ($promos->isNotEmpty())<a href="#offerte">Offerte</a>@endif
            @if ($shop->isNotEmpty())<a href="#acquista">Acquista</a>@endif
            @if (count($photos) > 1)<a href="#foto">Foto</a>@endif
            @if (count($faq))<a href="#domande">Domande</a>@endif
            @if ($hasContacts)<a href="#contatti">Contatti</a>@endif
        </nav>
        @if ($hasContacts)<a class="btn" href="{{ $ctaUrl }}">{{ \Illuminate\Support\Str::limit($ctaLabel, 22) }}</a>@endif
    </div>
</header>

<section class="hero" id="top">
    @if ($heroVisual)
        <img class="hero-bg" src="{{ $heroVisual }}" alt="">
    @endif
    <div class="hero-scrim"></div>
    <div class="wrap hero-inner">
        <span class="eyebrow">{{ $tenant->name }}</span>
        @if ($schedule)<span class="open-now hero-open" data-open-now></span>@endif
        <h1>{{ $tagline }}</h1>
        @if ($lead)<p class="lead">{{ $lead }}</p>@endif
        <div class="hero-actions">
            @if ($hasContacts)<a class="btn" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a>@endif
            @if ($waUrl && $ctaUrl !== $waUrl)<a class="btn ghost" href="{{ $waUrl }}" target="_blank" rel="noopener">WhatsApp</a>@endif
            @if (count($services))<a class="btn ghost" href="#servizi">Cosa offriamo</a>@endif
        </div>
    </div>
    <svg class="wave" viewBox="0 0 1440 70" preserveAspectRatio="none" aria-hidden="true"><path fill="{{ count($strengths) ? ($dark ? '#0d0f14' : '#ffffff') : ($dark ? '#0d0f14' : '#ffffff') }}" d="M0,40 C240,80 480,0 720,24 C960,48 1200,70 1440,30 L1440,70 L0,70 Z"/></svg>
</section>

@if (count($strengths))
<section class="section" style="padding-top:40px" aria-label="Perché sceglierci">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Perché sceglierci</span></div>
        <div class="strengths">
            @foreach ($strengths as $s)
                <div class="strength rv"><div class="ico" aria-hidden="true">{{ $s['icon'] }}</div><h3>{{ $s['title'] }}</h3><p>{{ $s['text'] }}</p></div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if (count($services))
<section class="section soft" id="servizi">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Servizi</span><h2>Cosa offriamo</h2></div>
        <div class="services">
            @foreach ($services as $service)
                <div class="service rv">
                    <div class="ico" aria-hidden="true">{{ $service['icon'] }}</div>
                    <div><h3>{{ $service['name'] }}</h3>@if ($service['line'])<p>{{ $service['line'] }}</p>@endif</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($about)
<section class="section" id="chi-siamo">
    <div class="wrap about">
        @if ($aboutPhoto)<div class="about-art photo rv"><img src="{{ $aboutPhoto }}" alt="{{ $tenant->name }}" loading="lazy"></div>@else<div class="about-art rv" aria-hidden="true"><b>{{ $initial }}</b></div>@endif
        <div class="about-text rv"><span class="kicker">Chi siamo</span><h2>{{ $tenant->name }}</h2><p>{{ $about }}</p></div>
    </div>
</section>
@endif

@if ($promos->isNotEmpty())
<section class="section soft" id="offerte">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Offerte del momento</span><h2>Approfittane adesso</h2></div>
        <div class="cards">
            @foreach ($promos as $promo)
                <a class="card rv" href="{{ route('promo.show', [$tenant, $promo]) }}">
                    <div class="card-img">@if ($promo->imageUrl())<img src="{{ $promo->imageUrl() }}" alt="{{ $promo->title }}" loading="lazy">@else<span>{{ \Illuminate\Support\Str::limit($promo->title, 40) }}</span>@endif<em>Promo</em></div>
                    <div class="card-body">
                        <h3>{{ $promo->title }}</h3>
                        @if (! $promo->always_active && $promo->ends_at)<p>Fino al {{ $promo->ends_at->format('d/m') }}</p>@endif
                        <span class="card-cta">Scopri →</span>
                    </div>
                </a>
            @endforeach
        </div>
        <p class="more rv"><a href="{{ route('promo.archive', $tenant) }}">Tutte le offerte</a></p>
    </div>
</section>
@endif

@if ($shop->isNotEmpty())
<section class="section" id="acquista">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Acquista online</span><h2>Paghi subito, in sicurezza</h2></div>
        <div class="cards">
            @foreach ($shop as $item)
                <a class="card rv" href="{{ route('services.public.show', [$tenant, $item]) }}">
                    <div class="card-img">@if ($item->coverImageUrl())<img src="{{ $item->coverImageUrl() }}" alt="{{ $item->title }}" loading="lazy">@else<span>{{ \Illuminate\Support\Str::limit($item->title, 40) }}</span>@endif @if ($item->onPromo())<em>In promo</em>@endif</div>
                    <div class="card-body">
                        <h3>{{ $item->title }}</h3>
                        <p class="price">{{ $item->amountEuros() }} €</p>
                        <span class="card-cta">{{ $item->type === 'product' ? 'Compra' : 'Prenota e paga' }} →</span>
                    </div>
                </a>
            @endforeach
        </div>
        <p class="more rv"><a href="{{ route('services.public.archive', $tenant) }}">Vedi tutto</a></p>
    </div>
</section>
@endif

@if (count($photos) > 1)
<section class="section soft" id="foto">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Foto</span><h2>Dai un'occhiata</h2></div>
        <div class="gallery">
            @foreach ($photos as $i => $p)
                <button type="button" class="shot rv" data-full="{{ $p }}" aria-label="Apri foto {{ $i + 1 }}"><img src="{{ $p }}" alt="{{ $tenant->name }} — foto {{ $i + 1 }}" loading="lazy"></button>
            @endforeach
        </div>
    </div>
</section>
@endif

@if (count($reviews))
<section class="section" aria-label="Cosa dicono i clienti">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Dicono di noi</span><h2>Parola ai clienti</h2></div>
        <div class="reviews">
            @foreach ($reviews as $r)
                <figure class="review rv"><div class="stars" aria-hidden="true">★★★★★</div><blockquote>«{{ $r['text'] }}»</blockquote>@if ($r['name'])<figcaption>{{ $r['name'] }}</figcaption>@endif</figure>
            @endforeach
        </div>
    </div>
</section>
@endif

@if (count($faq))
<section class="section soft" id="domande">
    <div class="wrap">
        <div class="head rv"><span class="kicker">Domande frequenti</span><h2>Le risposte che cerchi</h2></div>
        <div class="faq">
            @foreach ($faq as $i => $f)
                <details class="rv" @if ($i === 0) open @endif><summary>{{ $f['q'] }}</summary><p>{{ $f['a'] }}</p></details>
            @endforeach
        </div>
    </div>
</section>
@endif

@if ($hasContacts)
<section class="section" id="contatti">
    <div class="wrap">
        <div class="band rv">
            <h2>{{ $closing ?: 'Scrivici o chiamaci: ti rispondiamo presto' }}</h2>
            <div class="hero-actions">
                @if ($waUrl)<a class="btn light" href="{{ $waUrl }}" target="_blank" rel="noopener">Scrivici su WhatsApp</a>@endif
                @if ($tel)<a class="btn ghost" href="tel:{{ $tel }}">Chiama {{ $tenant->phone }}</a>@endif
            </div>
        </div>

        @if ($tenant->address || $hoursText)
        <div class="where rv">
            <div class="where-text">
                <h3>Dove e quando</h3>
                @if ($tenant->address)<p>📍 {{ $tenant->address }}</p>@endif
                @if ($schedule)<p class="open-now" data-open-now></p>@endif
                @if ($hoursText)<p>🕒 {{ $hoursText }}</p>@endif
            </div>
            @if ($tenant->address)
                <div class="map" id="map" data-src="https://www.google.com/maps?q={{ rawurlencode($tenant->address) }}&output=embed">
                    <button type="button" class="btn light" id="map-btn">🗺️ Mostra la mappa</button>
                    <small>La mappa è di Google: si carica solo se la apri.</small>
                </div>
            @endif
        </div>
        @endif

        <div class="contacts" style="margin-top:22px">
            @if ($tel)<a class="contact rv" href="tel:{{ $tel }}"><span class="ico" aria-hidden="true">📞</span><span><small>Telefono</small><b>{{ $tenant->phone }}</b></span></a>@endif
            @if ($waUrl)<a class="contact rv" href="{{ $waUrl }}" target="_blank" rel="noopener"><span class="ico" aria-hidden="true">💬</span><span><small>WhatsApp</small><b>Scrivici ora</b></span></a>@endif
            @if ($tenant->address)<a class="contact rv" href="{{ $mapsUrl }}" target="_blank" rel="noopener"><span class="ico" aria-hidden="true">📍</span><span><small>Dove siamo</small><b>{{ $tenant->address }}</b></span></a>@endif
        </div>
    </div>
</section>
@endif

@include('layouts.partials.share-bar', ['shareTitle' => $tenant->name, 'shareUrl' => $siteUrl, 'shareSection' => true, 'shareLabel' => 'Condividi questo sito'])

<footer>
    <div class="wrap">
        <div class="foot">
            <div>
                <a class="brand" href="#top">@if ($logo)<img class="mono logo" src="{{ $logo }}" alt="">@else<span class="mono">{{ $initial }}</span>@endif<span>{{ $tenant->name }}</span></a>
                <p>{{ $tagline }}</p>
            </div>
            @if ($hasContacts || $hoursText || $socials)
            <div>
                <h4>Contatti</h4>
                <ul>
                    @if ($tel)<li><a href="tel:{{ $tel }}">{{ $tenant->phone }}</a></li>@endif
                    @if ($waUrl)<li><a href="{{ $waUrl }}" target="_blank" rel="noopener">WhatsApp</a></li>@endif
                    @if ($tenant->address)<li><a href="{{ $mapsUrl }}" target="_blank" rel="noopener">{{ $tenant->address }}</a></li>@endif
                    @if ($hoursText)<li>{{ $hoursText }}</li>@endif
                </ul>
                @if ($socials)<div class="socials">@foreach ($socials as $label => $url)<a href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>@endforeach</div>@endif
            </div>
            @endif
            <div>
                <h4>Pagina</h4>
                <ul>
                    <li><a href="#top">Inizio</a></li>
                    @if (count($services))<li><a href="#servizi">Servizi</a></li>@endif
                    @if ($about)<li><a href="#chi-siamo">Chi siamo</a></li>@endif
                    @if (count($faq))<li><a href="#domande">Domande frequenti</a></li>@endif
                    @if ($hasContacts)<li><a href="#contatti">Contatti</a></li>@endif
                </ul>
            </div>
        </div>
        <div class="legal">
            <span>© {{ now()->year }} {{ $tenant->name }}@if ($vat) · P.IVA {{ $vat }}@endif · <a href="{{ route('privacy') }}">Privacy</a></span>
            <a class="made" href="{{ route('site.start', ['ref' => 'sito-'.$tenant->slug]) }}">Sito creato con l'IA di <b>Hub Core</b> · crea il tuo</a>
        </div>
    </div>
</footer>

@if ($tel)
<div class="dock">
    <a class="btn" href="tel:{{ $tel }}">📞 Chiama</a>
    @if ($waUrl)<a class="btn wa" href="{{ $waUrl }}" target="_blank" rel="noopener">💬 WhatsApp</a>@endif
</div>
@endif

<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Foto"><button type="button" aria-label="Chiudi">✕</button><img alt=""></div>

<script>
(function () {
    // «Aperto ora / Chiuso»: ora italiana, fasce anche oltre mezzanotte.
    var raw = document.body.getAttribute('data-schedule');
    if (!raw) return;
    var sch; try { sch = JSON.parse(raw); } catch (e) { return; }
    var toMin = function (t) { var p = t.split(':'); return (+p[0]) * 60 + (+p[1]); };
    var fmt = function (m) { m = m % 1440; return Math.floor(m / 60) + ':' + ('0' + (m % 60)).slice(-2); };
    var names = ['', 'lunedì', 'martedì', 'mercoledì', 'giovedì', 'venerdì', 'sabato', 'domenica'];
    var parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Rome', weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date());
    var get = function (t) { return (parts.find(function (p) { return p.type === t; }) || {}).value; };
    var day = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 }[get('weekday')];
    var now = (+get('hour')) * 60 + (+get('minute'));
    // Intervalli della settimana in minuti dal lunedì 00:00.
    var spans = [];
    sch.days.forEach(function (d) {
        sch.ranges.forEach(function (r) {
            var o = toMin(r[0]), c = toMin(r[1]);
            if (c <= o) c += 1440;
            spans.push([(d - 1) * 1440 + o, (d - 1) * 1440 + c]);
        });
    });
    var t = (day - 1) * 1440 + now, week = 7 * 1440, text, open = false;
    var current = spans.find(function (s) { return (t >= s[0] && t < s[1]) || (t + week >= s[0] && t + week < s[1]); });
    if (current) {
        open = true;
        text = 'Aperto ora · chiude alle ' + fmt(current[1]);
    } else {
        var next = spans.map(function (s) { return s[0] > t ? s[0] : s[0] + week; }).sort(function (a, b) { return a - b; })[0];
        if (next !== undefined) {
            var nd = Math.floor((next % week) / 1440) + 1, diff = Math.floor(next / 1440) - Math.floor(t / 1440);
            text = 'Chiuso · apre ' + (diff === 0 ? 'alle ' : diff === 1 ? 'domani alle ' : names[nd] + ' alle ') + fmt(next % 1440);
        }
    }
    document.querySelectorAll('[data-open-now]').forEach(function (el) { el.textContent = text || ''; el.classList.toggle('is-open', open); });
})();
(function () {
    // Foto a schermo intero.
    var box = document.getElementById('lightbox');
    document.querySelectorAll('.shot').forEach(function (b) {
        b.addEventListener('click', function () { box.querySelector('img').src = b.dataset.full; box.classList.add('on'); });
    });
    box.addEventListener('click', function () { box.classList.remove('on'); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') box.classList.remove('on'); });

    // Mappa di Google solo se la persona la apre (niente cookie di terzi prima).
    var map = document.getElementById('map');
    if (map) {
        document.getElementById('map-btn').addEventListener('click', function () {
            map.innerHTML = '<iframe src="' + map.dataset.src + '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Mappa"></iframe>';
            map.classList.add('on');
        });
    }
})();
(function () {
    var items = document.querySelectorAll('.rv');
    var showAll = function () { items.forEach(function (el) { el.classList.add('in'); }); };
    // Sicurezza: nessuna sezione deve mai restare nascosta (vecchi browser, anteprime, stampa).
    setTimeout(showAll, 1500);
    window.addEventListener('beforeprint', showAll);
    if (!('IntersectionObserver' in window)) { showAll(); return; }
    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('in'); io.unobserve(e.target); } });
    }, { rootMargin: '0px 0px -8% 0px' });
    items.forEach(function (el) { io.observe(el); });
})();
</script>
</body>
</html>
