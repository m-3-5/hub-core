@php
    $color = $tenant->primary_color ?: '#6366f1';
    $answers = $site->answers ?? [];
    $published = $site && $site->isPublished();
    $siteUrl = route('site.public.show', $tenant);
    // Dopo un errore del server si riparte dal riepilogo, con quello che aveva scritto.
    $hasOld = old('tagline') !== null;
    $start = $hasOld ? 'review' : ($published && ! request()->boolean('rifai') ? 'done' : 'describe');
    $ctaChoices = ['Chiamaci', 'Scrivici su WhatsApp', 'Prenota ora', 'Richiedi un preventivo', 'Vieni a trovarci'];
@endphp
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $color }}">
    <meta name="robots" content="noindex">
    <title>Il tuo sito con l'IA — {{ $tenant->name }}</title>
    @include('layouts.partials.favicon')
    @include('layouts.partials.wizard-css', ['color' => $color])
    <style>
        .wz-stage { padding-left: 16px; padding-right: 16px; }
        .wz-top { padding-left: 16px; padding-right: 16px; }
        .btn { min-height: 52px; }
        .ai-btn { width: 100%; margin-top: 14px; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .linkish { background: none; border: 0; color: var(--c-dark); font: inherit; font-weight: 700; padding: 14px 4px; min-height: 48px; cursor: pointer; text-decoration: underline; }
        .city { font-size: 1.1rem; margin-top: 12px; }
        .chips .chip { min-height: 48px; }
        .pv { border-radius: 22px; overflow: hidden; border: 2px solid #eceef3; }
        .pv-hero { background: linear-gradient(135deg, var(--c), var(--c-dark)); color: #fff; padding: 28px 20px; }
        .pv-hero small { opacity: .8; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; font-size: .75rem; }
        .pv-hero h2 { margin: 6px 0 14px; font-size: 1.45rem; line-height: 1.2; overflow-wrap: anywhere; }
        .pv-hero span { display: inline-block; background: #fff; color: var(--c-dark); font-weight: 700; padding: 10px 16px; border-radius: 12px; }
        .pv-body { padding: 16px 20px; }
        .pv-body .chips { gap: 8px; }
        .pv-body .chips span { background: var(--c-soft); color: var(--c-dark); font-weight: 600; padding: 8px 12px; border-radius: 99px; font-size: .92rem; }
        .pv-body p { color: #444; margin: 12px 0 0; line-height: 1.5; }
        .pv-more { display: grid; gap: 6px; margin-top: 12px; font-size: .92rem; }
        .pv-more:empty { display: none; }
        .pv-more b { display: block; margin-top: 6px; }
        .pv-more span { display: block; color: var(--muted); }
        .pv-more i { color: var(--c-dark); }
        .done-ico { width: 88px; height: 88px; border-radius: 50%; background: #e8f5e9; display: grid; place-items: center; font-size: 2.6rem; margin: 18px 0 4px; }
        .url { display: block; background: #f6f6fa; border-radius: 14px; padding: 14px 16px; font-weight: 600; overflow-wrap: anywhere; color: var(--ink); text-decoration: none; margin: 10px 0 16px; }
        .stack { display: grid; gap: 12px; }
        .stack .btn, .stack a.btn { display: flex; align-items: center; justify-content: center; text-decoration: none; text-align: center; }
        .box { border: 2px solid #eceef3; border-radius: 18px; padding: 16px; margin-top: 18px; }
        .box p { margin: 0 0 10px; color: #444; font-size: .95rem; line-height: 1.45; }
        .ok { background: #e8f5e9; color: #2e7d32; padding: 12px 16px; border-radius: 14px; margin: 6px 0 10px; font-size: .95rem; }
        @media (prefers-color-scheme: dark) {
            body { background: #121218; color: #ececf1; }
            :root { --ink: #ececf1; --muted: #a3a3b2; }
            .field, .choice, .chip { background: #1b1b24; color: #ececf1; border-color: #2c2c3a; }
            .wz-x, .btn.ghost { background: #23232e; color: #ececf1; }
            .wz-bar { background: linear-gradient(to top, #121218 70%, rgba(18,18,24,0)); }
            .url { background: #1b1b24; color: #ececf1; }
            .pv, .box { border-color: #2c2c3a; }
            .pv-body p, .box p { color: #c9c9d4; }
            .veil { background: rgba(18,18,24,.95); }
        }
    </style>
    @include('layouts.partials.ui')
</head>
<body>
<div class="wz">
    <div class="wz-top">
        <a class="wz-x" href="{{ route('app.home', $tenant) }}" aria-label="Esci">✕</a>
        <div class="wz-progress"><i id="wz-bar"></i></div>
        <div class="wz-count" id="wz-count"></div>
    </div>

    <form id="wz-form" class="wz-stage" method="POST" action="{{ route('admin.sitebuilder.generate', $tenant) }}" novalidate>
        @csrf
        @if ($errors->any())
            <div class="banner bad">{{ $errors->first() }}</div>
        @endif
        @php
            // «Sito creato! Eccolo qui: URL — nota»: la pagina finale mostra già il link, qui resta solo la nota.
            $okMessage = session('success');
            if ($okMessage && str_starts_with($okMessage, 'Sito creato')) {
                $okMessage = str_contains($okMessage, ' — ') ? 'Sito creato! '.ucfirst(\Illuminate\Support\Str::after($okMessage, ' — ')) : null;
            }
        @endphp
        @if ($okMessage)
            <div class="ok">{{ $okMessage }}</div>
        @endif
        @if (session('upgrade_sent'))
            <div class="ok">Richiesta inviata! Ti contattiamo per il sito Pro.</div>
        @endif

        <input type="hidden" name="tagline" id="f-tagline" value="{{ old('tagline', $answers['tagline'] ?? '') }}">
        <input type="hidden" name="services" id="f-services" value="{{ old('services', implode(', ', $answers['services'] ?? [])) }}">
        <input type="hidden" name="cta_label" id="f-cta" value="{{ old('cta_label', $answers['cta_label'] ?? '') }}">
        <input type="hidden" name="extra" id="f-extra" value="{{ old('extra', $answers['extra'] ?? '') }}">
        <input type="hidden" name="phone" id="f-phone" value="{{ old('phone', $tenant->phone) }}">
        <input type="hidden" name="extras" id="f-extras" value="{{ old('extras', json_encode($answers['extras'] ?? new \stdClass)) }}">

        {{-- Sito già online --}}
        <section class="wz-step" data-step="done">
            <div class="done-ico">🎉</div>
            <h1>Il tuo sito è online</h1>
            @if ($tenant->isGuestPending())
                <p class="sub">Lo vedi solo tu finché non confermi la tua email.</p>
            @else
                <p class="sub">Mandalo ai clienti, mettilo su WhatsApp e sui social.</p>
            @endif
            <a class="url" href="{{ $siteUrl }}" target="_blank" rel="noopener">{{ $siteUrl }}</a>
            <div class="stack">
                <a class="btn main" href="{{ $siteUrl }}" target="_blank" rel="noopener">Apri il sito</a>
                <button type="button" class="btn alt" id="b-copy">Copia il link</button>
                <a class="btn ghost" href="https://wa.me/?text={{ rawurlencode($tenant->name.' — '.$siteUrl) }}" target="_blank" rel="noopener">Invia su WhatsApp</a>
                <button type="button" class="btn ghost" data-goto="describe">✨ Rifallo con l'IA</button>
            </div>
            <div class="box">
                <p><b>Cambiare i testi dal telefono, senza password:</b> ti mandiamo un link segreto per email.</p>
                @if ($hasRealEmail)
                    <button type="submit" class="btn ghost" style="width:100%" form="f-link">Mandami il link</button>
                @else
                    <p style="color:#b45309">Serve una email vera del titolare: aggiungila dal Profilo.</p>
                @endif
            </div>
            <div class="box">
                <p><b>Vuoi di più?</b> Più pagine, dominio tuo, fatto su misura da noi.</p>
                <button type="submit" class="btn ghost" style="width:100%" form="f-pro">Chiedi un sito Pro</button>
            </div>
        </section>

        <section class="wz-step" data-step="describe">
            <span class="ai">✨ Scrive l'IA</span>
            <h1>Cosa fa {{ $tenant->name }}?</h1>
            <p class="sub">Poche parole, come le diresti a un amico. Al resto pensa l'IA.</p>
            <textarea class="field" id="in-describe" maxlength="600" style="min-height:140px" placeholder="Es. Parrucchiera per donna a Rossano: tagli, colore, sposa. Prezzi onesti, su appuntamento."></textarea>
            <input class="field city" id="in-city" type="text" maxlength="80" placeholder="Città (facoltativo)" autocomplete="address-level2">
            <div class="err" id="err-describe"></div>
            <button type="button" class="btn main ai-btn" id="b-ai">✨ Scrivi il sito con l'IA</button>
            <div style="text-align:center"><button type="button" class="linkish" data-goto="tagline">Preferisco scrivere io</button></div>
        </section>

        <section class="wz-step" data-step="tagline">
            <span class="ai" id="ai-tag" hidden>✨ Proposta dell'IA: cambiala come vuoi</span>
            <h1>La frase in prima pagina</h1>
            <p class="sub">La prima cosa che legge chi arriva sul sito.</p>
            <textarea class="field" id="in-tagline" maxlength="200" style="min-height:110px" placeholder="Es. Il tuo prossimo taglio, come lo hai sempre immaginato"></textarea>
            <div class="err" id="err-tagline"></div>
        </section>

        <section class="wz-step" data-step="services">
            <h1>Cosa offri?</h1>
            <p class="sub">Servizi o prodotti principali: uno per riga.</p>
            <textarea class="field" id="in-services" maxlength="1000" placeholder="Taglio&#10;Colore&#10;Acconciatura sposa"></textarea>
            <div class="err" id="err-services"></div>
        </section>

        <section class="wz-step" data-step="about">
            <h1>Chi siete</h1>
            <p class="sub">Due righe per presentarvi. Facoltativo.</p>
            <textarea class="field" id="in-about" maxlength="1000" style="min-height:150px" placeholder="Facoltativo"></textarea>
            <div class="err" id="err-about"></div>
        </section>

        <section class="wz-step" data-step="cta">
            <h1>Cosa deve fare chi visita il sito?</h1>
            <p class="sub">È il pulsante grande in prima pagina.</p>
            <div class="chips" id="cta-chips">
                @foreach ($ctaChoices as $choice)
                    <button type="button" class="chip" data-cta="{{ $choice }}">{{ $choice }}</button>
                @endforeach
            </div>
            <input class="field city" id="in-cta" type="text" maxlength="60" placeholder="Oppure scrivi tu">
            <div class="err" id="err-cta"></div>
        </section>

        <section class="wz-step" data-step="phone">
            <h1>Il numero per i clienti</h1>
            <p class="sub">Serve al pulsante «Chiamaci» e a WhatsApp. Facoltativo.</p>
            <input class="field" id="in-phone" type="tel" inputmode="tel" maxlength="30" autocomplete="tel" placeholder="es. 333 1234567">
            <div class="err" id="err-phone"></div>
        </section>

        <section class="wz-step" data-step="review">
            <h1>Ecco il tuo sito</h1>
            <p class="sub">Controlla e premi «Metti online».</p>
            <div class="pv">
                <div class="pv-hero"><small>{{ $tenant->name }}</small><h2 id="pv-tagline"></h2><span id="pv-cta"></span></div>
                <div class="pv-body"><div class="chips" id="pv-services"></div><p id="pv-about"></p><div class="pv-more" id="pv-more"></div></div>
            </div>
            <div class="rows" id="sum-rows"></div>
            <p class="hint">Prima di andare online l'IA controlla che i testi siano seri (niente spam o cose vietate).</p>
        </section>
    </form>
    <form id="f-link" method="POST" action="{{ route('admin.sitebuilder.edit-link', $tenant) }}" hidden>@csrf</form>
    <form id="f-pro" method="POST" action="{{ route('admin.tickets.store', $tenant) }}" hidden>
        @csrf
        <input type="hidden" name="context_type" value="site-upgrade">
        <input type="hidden" name="context_label" value="Richiesta sito Pro">
        <input type="hidden" name="message" value="Vorrei passare a un sito Pro con dominio mio, partendo dal sito semplice già creato con Max.">
    </form>

    <div class="wz-bar" id="wz-bar-actions">
        <div class="wz-bar-in">
            <button type="button" class="btn ghost" id="b-back" hidden>Indietro</button>
            <button type="button" class="btn main" id="b-next">Avanti</button>
        </div>
    </div>
</div>

<div class="veil" id="veil"><div><div class="spin"></div><b style="font-size:1.2rem" id="veil-title">Un attimo…</b><p style="color:var(--muted);margin:8px 0 0" id="veil-text"></p></div></div>

<script>
(function () {
    const $ = (id) => document.getElementById(id);
    const form = $('wz-form');
    const csrf = form.querySelector('input[name=_token]').value;
    const SUGGEST = @json(route('admin.sitebuilder.suggest', $tenant));
    const SITE_URL = @json($siteUrl);
    const sections = Object.fromEntries([...document.querySelectorAll('[data-step]')].map(s => [s.dataset.step, s]));
    const ORDER = ['describe', 'tagline', 'services', 'about', 'cta', 'phone', 'review'];
    const esc = (t) => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    const state = {
        tagline: $('f-tagline').value,
        services: $('f-services').value.split(',').map(s => s.trim()).filter(Boolean),
        about: $('f-extra').value,
        cta: $('f-cta').value || 'Chiamaci',
        phone: $('f-phone').value,
        extras: (() => { try { return JSON.parse($('f-extras').value || '{}') || {}; } catch (e) { return {}; } })(),
    };
    let current = @json($start);
    let busy = false;

    const err = (k, msg) => { const e = $('err-' + k); if (e) e.textContent = msg || ''; return !msg; };

    function show(id, back) {
        current = id;
        Object.values(sections).forEach(s => s.classList.remove('on', 'back'));
        sections[id].classList.add('on');
        if (back) sections[id].classList.add('back');
        const i = ORDER.indexOf(id);
        $('wz-bar').style.width = id === 'done' ? '100%' : ((i + 1) / ORDER.length * 100) + '%';
        $('wz-count').textContent = id === 'done' ? '' : (i + 1) + '/' + ORDER.length;
        // Sul primo passo e sulla pagina finale i tasti sono dentro la schermata.
        $('wz-bar-actions').hidden = id === 'done' || id === 'describe';
        $('b-back').hidden = i <= 0;
        $('b-next').textContent = id === 'review' ? 'Metti online' : 'Avanti';
        if (id === 'tagline') $('in-tagline').value = state.tagline;
        if (id === 'services') $('in-services').value = state.services.join('\n');
        if (id === 'about') $('in-about').value = state.about;
        if (id === 'cta') { $('in-cta').value = state.cta; markCta(); }
        if (id === 'phone') $('in-phone').value = state.phone;
        if (id === 'review') renderReview();
        window.scrollTo({ top: 0 });
        const f = { tagline: 'in-tagline', services: 'in-services', phone: 'in-phone' }[id];
        if (f) setTimeout(() => $(f).focus({ preventScroll: true }), 320);
    }

    function go(delta) {
        const i = ORDER.indexOf(current);
        show(ORDER[Math.max(0, Math.min(i + delta, ORDER.length - 1))], delta < 0);
    }

    function markCta() {
        document.querySelectorAll('[data-cta]').forEach(c => c.classList.toggle('on', c.dataset.cta === state.cta));
    }

    function validate(id) {
        if (id === 'tagline') {
            state.tagline = $('in-tagline').value.replace(/\s+/g, ' ').trim();
            return err('tagline', state.tagline.length < 3 ? 'Scrivi una frase (almeno 3 lettere).' : '');
        }
        if (id === 'services') {
            state.services = $('in-services').value.split(/\n|,/).map(s => s.trim()).filter(Boolean).slice(0, 20);
            return err('services', state.services.length ? '' : 'Scrivi almeno un servizio o prodotto.');
        }
        if (id === 'about') { state.about = $('in-about').value.trim(); return true; }
        if (id === 'cta') {
            state.cta = $('in-cta').value.trim() || 'Contattaci';
            return true;
        }
        if (id === 'phone') {
            state.phone = $('in-phone').value.trim();
            return err('phone', state.phone && !/^[0-9+()\s.\-]{6,30}$/.test(state.phone) ? 'Controlla il numero: usa solo cifre.' : '');
        }
        return true;
    }

    function renderReview() {
        $('pv-tagline').textContent = state.tagline;
        $('pv-cta').textContent = state.cta;
        $('pv-services').innerHTML = state.services.map(s => `<span>${esc(s)}</span>`).join('');
        $('pv-about').textContent = state.about;
        // Quello che l'IA ha aggiunto in più: punti di forza e domande frequenti.
        const ex = state.extras || {};
        const more = [];
        if ((ex.strengths || []).length) more.push('<b>Perché sceglierci</b>' + ex.strengths.map(s => `<span>${esc(s.icon)} ${esc(s.title)}</span>`).join(''));
        if ((ex.faq || []).length) more.push(`<b>${ex.faq.length} domande frequenti</b>`);
        if (ex.closing) more.push(`<i>${esc(ex.closing)}</i>`);
        $('pv-more').innerHTML = more.join('');
        const rows = [];
        const row = (label, value, step) => rows.push(`<div class="row"><span>${label}</span><b>${esc(value || '—')}</b><a data-go="${step}">Modifica</a></div>`);
        row('Frase', state.tagline, 'tagline');
        row('Offri', state.services.join(', '), 'services');
        row('Chi siete', state.about ? state.about.slice(0, 60) + (state.about.length > 60 ? '…' : '') : '', 'about');
        row('Pulsante', state.cta, 'cta');
        row('Telefono', state.phone, 'phone');
        $('sum-rows').innerHTML = rows.join('');
        $('sum-rows').querySelectorAll('[data-go]').forEach(a => a.addEventListener('click', () => show(a.dataset.go)));
    }

    function veil(on, title, text) {
        $('veil-title').textContent = title || 'Un attimo…';
        $('veil-text').textContent = text || '';
        $('veil').classList.toggle('on', on);
    }

    async function askAi() {
        const description = $('in-describe').value.trim();
        if (description.length < 10) return err('describe', 'Scrivi qualche parola in più (almeno 10 lettere).');
        err('describe', '');
        busy = true;
        veil(true, 'L\'IA sta scrivendo…', 'Ci vogliono pochi secondi.');
        try {
            const res = await fetch(SUGGEST, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ description, city: $('in-city').value.trim() }),
            });
            const data = await res.json().catch(() => ({}));
            if (!data.ok) { err('describe', data.message || 'L\'IA non risponde: scrivi tu i testi, oppure riprova.'); return; }
            state.tagline = data.tagline;
            state.services = data.services;
            state.about = data.about || '';
            state.cta = data.cta_label || state.cta;
            state.extras = data.extras || {};
            $('ai-tag').hidden = false;
            show('tagline');
        } catch (e) {
            err('describe', 'Connessione assente: riprova tra poco, oppure scrivi tu i testi.');
        } finally {
            busy = false;
            veil(false);
        }
    }

    function submit() {
        busy = true;
        $('f-tagline').value = state.tagline;
        $('f-services').value = state.services.map(s => s.replace(/,/g, ' ')).join(', ');
        $('f-extra').value = state.about;
        $('f-cta').value = state.cta;
        $('f-phone').value = state.phone;
        $('f-extras').value = JSON.stringify(state.extras || {});
        veil(true, 'Sto creando il tuo sito…', 'Controllo i testi e disegno la grafica: fino a un minuto.');
        form.submit();
    }

    function next() {
        if (busy || !validate(current)) return;
        if (current === 'review') return submit();
        go(1);
    }

    document.querySelectorAll('[data-cta]').forEach(c => c.addEventListener('click', () => {
        state.cta = c.dataset.cta; $('in-cta').value = state.cta; markCta();
    }));
    document.querySelectorAll('[data-goto]').forEach(b => b.addEventListener('click', () => show(b.dataset.goto)));
    $('b-ai').addEventListener('click', () => { if (!busy) askAi(); });
    $('b-next').addEventListener('click', next);
    $('b-back').addEventListener('click', () => { if (!busy) go(-1); });
    $('b-copy').addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(SITE_URL); $('b-copy').textContent = 'Link copiato ✓'; }
        catch (e) { window.prompt('Copia il link:', SITE_URL); }
    });
    form.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') { e.preventDefault(); current === 'describe' ? askAi() : next(); }
    });

    show(current);
})();
</script>
</body>
</html>
