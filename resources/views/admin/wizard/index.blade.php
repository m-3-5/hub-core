<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="{{ $color }}">
    <title>{{ ['promo' => 'Nuova promo', 'product' => 'Nuovo prodotto', 'service' => 'Nuovo servizio'][$kind] }} — {{ $tenant->name }}</title>
    @include('layouts.partials.wizard-css', ['color' => $color])
</head>
<body>
@php
    $steps = match ($kind) {
        'promo' => ['photo', 'title', 'description', 'validity', 'price', 'review'],
        'product' => ['photo', 'title', 'description', 'price', 'promo', 'review'],
        default => ['photo', 'title', 'description', 'price', 'duration', 'promo', 'review'],
    };
    if ($guestPending) {
        $steps[] = 'register';
    }
    $noun = ['promo' => 'la promo', 'product' => 'il prodotto', 'service' => 'il servizio'][$kind];
    $f = $meta['fields'];
@endphp
<div class="wz">
    <div class="wz-top">
        <a class="wz-x" href="{{ $meta['exit'] }}" id="wz-exit" aria-label="Esci">✕</a>
        <div class="wz-progress"><i id="wz-bar"></i></div>
        <div class="wz-count" id="wz-count"></div>
    </div>

    <form id="wz-form" class="wz-stage" method="POST" action="{{ $meta['action'] }}" enctype="multipart/form-data" novalidate>
        @csrf
        @if ($errors->any())
            <div class="banner bad">{{ $errors->first() }}<br><small>Ricontrolla i passi: la foto va scelta di nuovo.</small></div>
        @endif
        @if ($overQuota)
            <div class="banner">Hai usato tutte le promo incluse: questa verrà registrata come extra a pagamento.</div>
        @endif

        {{-- campi tecnici --}}
        <input type="hidden" name="wizard" value="1">
        @if ($kind === 'promo')
            <input type="hidden" name="visual_tier" value="base">
            <input type="hidden" name="promo_source" id="f-source" value="upload">
            <input type="hidden" name="promo_hint" id="f-hint">
            <input type="hidden" name="skip_ai" value="1">
            <input type="hidden" name="ai_payload" id="f-payload">
            <input type="hidden" name="always_active" id="f-always" value="0">
            <input type="hidden" name="starts_at" id="f-start" value="{{ $today }}">
            <input type="hidden" name="ends_at" id="f-end" value="{{ $defaultEnd }}">
            <input type="hidden" name="price" id="f-price-text">
            <input type="hidden" name="publish_now" id="f-publish" value="1">
        @else
            <input type="hidden" name="auto_cover" id="f-auto" value="0">
            <input type="hidden" name="amount" id="f-amount">
            <input type="hidden" name="duration_minutes" id="f-duration">
            <input type="hidden" name="promo_label" id="f-promo-on" value="0">
            <input type="hidden" name="promo_until" id="f-promo-until">
        @endif
        <input type="file" name="{{ $f['file'] }}" id="wz-file" accept="image/*" class="sr" tabindex="-1">
        <input type="hidden" name="{{ $f['title'] }}" id="f-title" value="{{ old($f['title']) }}">
        <input type="hidden" name="{{ $f['description'] }}" id="f-desc" value="{{ old($f['description']) }}">

        {{-- 1. foto o grafica --}}
        <section class="wz-step" data-step="photo">
            <h1>Come vuoi cominciare?</h1>
            <p class="sub">Parti da una foto oppure lascia che la grafica la creiamo noi.</p>
            <div class="choices" id="photo-choices">
                <label class="choice" for="wz-file"><span class="ico">📷</span><span><b>Carica una foto</b><small>Dalla galleria o scattala ora. Ti propongo io titolo e testo.</small></span></label>
                <button type="button" class="choice" id="pick-graphic"><span class="ico">✨</span><span><b>Crea tu la grafica</b><small>Scrivi titolo e descrizione, la grafica la disegna l'IA.</small></span></button>
            </div>
            <div id="photo-ready" hidden>
                <div class="preview-photo"><img id="photo-img" alt="La tua foto"></div>
                <p class="hint"><a href="#" id="photo-change" style="color:var(--c-dark);font-weight:700">Cambia foto</a></p>
            </div>
            <div class="err" id="err-photo"></div>
            <a class="adv" href="{{ $meta['advanced'] }}">Preferisci il modulo completo? Vai alla modalità avanzata</a>
        </section>

        {{-- 2. titolo --}}
        <section class="wz-step" data-step="title">
            <div class="ai" id="ai-title" hidden>✨ Proposto dall'IA — modificalo come vuoi</div>
            <h1 id="h-title">Come si chiama {{ $noun }}?</h1>
            <p class="sub" id="sub-title">Un titolo breve e chiaro.</p>
            <p class="banner" id="note-title" hidden></p>
            <input class="field" id="in-title" type="text" maxlength="{{ $kind === 'promo' ? 120 : 100 }}" autocomplete="off" placeholder="Scrivi il titolo">
            <div class="err" id="err-title"></div>
        </section>

        {{-- 3. descrizione --}}
        <section class="wz-step" data-step="description">
            <div class="ai" id="ai-desc" hidden>✨ Proposta dall'IA — modificala come vuoi</div>
            <h1 id="h-desc">Due righe di descrizione</h1>
            <p class="sub" id="sub-desc">Cosa deve sapere chi legge.</p>
            <textarea class="field" id="in-desc" maxlength="{{ $kind === 'promo' ? 1500 : 1800 }}" placeholder="Scrivi qui…"></textarea>
            <div class="err" id="err-desc"></div>
        </section>

        @if ($kind === 'promo')
        {{-- 4. scadenza --}}
        <section class="wz-step" data-step="validity">
            <h1>La promo ha una scadenza?</h1>
            <p class="sub">Dopo quella data sparisce da sola.</p>
            <div class="yesno">
                <button type="button" class="choice on" data-expire="1">Sì, scade</button>
                <button type="button" class="choice" data-expire="0">No, sempre attiva</button>
            </div>
            <div id="expire-box">
                <label for="in-end" class="sub" style="display:block;margin-bottom:8px">Valida fino al</label>
                <input class="field" id="in-end" type="date" min="{{ $today }}" value="{{ $defaultEnd }}">
                <p class="hint">Di solito un mese: puoi cambiarla.</p>
            </div>
            <div class="err" id="err-validity"></div>
        </section>
        @endif

        {{-- prezzo --}}
        <section class="wz-step" data-step="price">
            @if ($kind === 'promo')
                <h1>C'è un prezzo?</h1>
                <p class="sub" id="sub-price">Se vuoi mostrarlo nella promo.</p>
                <div class="yesno">
                    <button type="button" class="choice" data-price="1">Sì</button>
                    <button type="button" class="choice on" data-price="0">No</button>
                </div>
                <div id="price-box" hidden>
            @else
                <h1>Quanto costa?</h1>
                <p class="sub" id="sub-price">Il prezzo che pagherà il cliente.</p>
                <div id="price-box">
            @endif
                    <div class="money"><input class="field" id="in-price" type="text" inputmode="decimal" autocomplete="off" placeholder="0,00"><span>€</span></div>
                    <div class="err" id="err-price"></div>
                </div>
        </section>

        @if ($kind === 'service')
        <section class="wz-step" data-step="duration">
            <h1>Quanto dura?</h1>
            <p class="sub">Aiuta chi prenota a organizzarsi (facoltativo).</p>
            <div class="chips" id="dur-chips">
                @foreach ([15, 30, 45, 60, 90, 120] as $m)
                    <button type="button" class="chip" data-min="{{ $m }}">{{ $m < 60 ? $m.' min' : ($m % 60 ? intdiv($m, 60).' h '.($m % 60).' min' : intdiv($m, 60).' h') }}</button>
                @endforeach
                <button type="button" class="chip on" data-min="0">Non serve</button>
            </div>
            <div style="margin-top:18px">
                <label class="sub" for="in-dur" style="display:block;margin-bottom:8px">Oppure scrivi i minuti</label>
                <input class="field" id="in-dur" type="number" min="5" max="1440" inputmode="numeric" placeholder="es. 75" style="max-width:220px">
            </div>
            <div class="err" id="err-duration"></div>
        </section>
        @endif

        @if ($kind !== 'promo')
        <section class="wz-step" data-step="promo">
            <h1>Lo metti in promo?</h1>
            <p class="sub">Compare l'etichetta «In promo» fino alla data che scegli, poi sparisce da sola.</p>
            <div class="yesno">
                <button type="button" class="choice" data-promo="1">Sì, in promo</button>
                <button type="button" class="choice on" data-promo="0">No</button>
            </div>
            <div id="promo-box" hidden>
                <label for="in-promo-end" class="sub" style="display:block;margin-bottom:8px">In promo fino al</label>
                <input class="field" id="in-promo-end" type="date" min="{{ $today }}" value="{{ $defaultEnd }}">
            </div>
            <div class="err" id="err-promo"></div>
        </section>
        @endif

        {{-- riepilogo --}}
        <section class="wz-step" data-step="review">
            <h1>Tutto pronto?</h1>
            <p class="sub">Ricontrolla e poi pubblica. Puoi cambiare ogni cosa toccando «Modifica».</p>
            <div class="sum">
                <div class="sum-img" id="sum-img"></div>
                <div class="sum-body">
                    <h2 id="sum-title"></h2>
                    <p id="sum-desc"></p>
                    <div class="rows" id="sum-rows"></div>
                </div>
            </div>
            @if ($kind !== 'promo')
                <label class="toggle"><input type="checkbox" name="published_to_site" value="1" id="in-visible" checked> Mostralo sul tuo sito e su inm35.it</label>
            @endif
            <p class="hint" id="sum-note"></p>
        </section>

        @if ($guestPending)
            <section class="wz-step" data-step="register">
                <span class="ai">🛡️ Ultimo passo</span>
                <h1>Registrati per pubblicare</h1>
                <p class="sub">Prima di andare online la nostra IA controlla che la promo sia adatta a tutti (niente volgarità, violenza o contenuti pericolosi). Poi ti mandiamo un'email: un clic e la tua promo è su inm35.it.</p>
                <input class="field" type="email" name="guest_email" id="in-guest-email" inputmode="email" autocomplete="email" placeholder="la-tua-email@esempio.it" maxlength="190" value="{{ old('guest_email') }}">
                <label class="toggle"><input type="checkbox" id="in-guest-consent"> Accetto che la promo sia controllata dall'IA e che la mia email sia usata per registrarmi.</label>
                <div class="err" id="err-register"></div>
                <p class="hint">Non ti chiediamo nessuna carta. Se l'IA non ritiene adatta la promo ti spieghiamo cosa cambiare.</p>
            </section>
        @endif
    </form>

    <div class="wz-bar">
        <div class="wz-bar-in">
            <button type="button" class="btn ghost" id="b-back" hidden>Indietro</button>
            <button type="button" class="btn alt" id="b-draft" hidden>{{ $kind === 'promo' ? 'Salva bozza' : 'Non mostrare' }}</button>
            <button type="button" class="btn main" id="b-next">Avanti</button>
        </div>
    </div>
</div>

<div class="veil" id="veil"><div><div class="spin"></div><b id="veil-t" style="font-size:1.2rem">Un attimo…</b><p id="veil-s" style="color:#6b6b7b;margin:8px 0 0"></p></div></div>

<script>
(function () {
    const KIND = @json($kind);
    const STEPS = @json($steps);
    const GUEST = @json((bool) $guestPending);
    const LAST = STEPS[STEPS.length - 1];
    const NOUN = @json($noun);
    const SUGGEST_URL = @json(route('admin.wizard.suggest', $tenant));
    const CSRF = @json(csrf_token());
    const TODAY = @json($today);
    const MAX_FILE = KIND === 'promo' ? 10 : 5; // MB

    const $ = (id) => document.getElementById(id);
    const form = $('wz-form'), file = $('wz-file');
    const sections = Object.fromEntries([...document.querySelectorAll('[data-step]')].map(s => [s.dataset.step, s]));
    const state = {
        mode: null,            // 'photo' | 'graphic'
        title: @json(old($f['title'], '')), desc: @json(old($f['description'], '')),
        titleAi: false, descAi: false, touchedTitle: false, touchedDesc: false,
        expire: true, end: @json($defaultEnd),
        hasPrice: false, price: '',
        duration: 0,
        promo: false, promoEnd: @json($defaultEnd),
        photoUrl: null, payload: null,
    };
    let idx = 0, busy = false;

    const money = (v) => { const n = parseFloat(String(v).replace(',', '.')); return isNaN(n) ? null : n; };
    const fmt = (n) => n.toLocaleString('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dateIt = (iso) => { const [y, m, d] = iso.split('-'); return `${d}/${m}/${y}`; };
    const esc = (t) => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function show(i, back) {
        idx = Math.max(0, Math.min(i, STEPS.length - 1));
        const id = STEPS[idx];
        Object.values(sections).forEach(s => s.classList.remove('on', 'back'));
        sections[id].classList.add('on');
        if (back) sections[id].classList.add('back');
        $('wz-bar').style.width = ((idx + 1) / STEPS.length * 100) + '%';
        $('wz-count').textContent = (idx + 1) + '/' + STEPS.length;
        $('b-back').hidden = idx === 0;
        $('b-draft').hidden = id !== 'review';
        $('b-next').textContent = id === LAST ? (GUEST ? 'Registrati e pubblica' : 'Pubblica') : 'Avanti';
        $('b-next').style.display = (id === 'photo' && !state.mode) ? 'none' : '';
        prepare(id);
        window.scrollTo({ top: 0 });
        const focusEl = { title: 'in-title', description: 'in-desc', price: 'in-price' }[id];
        if (focusEl && (id !== 'price' || state.hasPrice || KIND !== 'promo')) setTimeout(() => $(focusEl).focus({ preventScroll: true }), 320);
    }

    function prepare(id) {
        if (id === 'title') {
            $('in-title').value = state.title;
            $('ai-title').hidden = !state.titleAi;
            $('h-title').textContent = state.titleAi ? 'Ti propongo questo titolo' : (state.mode === 'graphic' ? 'Come si chiama ' + NOUN + '?' : 'Che titolo vuoi dare?');
            $('sub-title').textContent = state.mode === 'graphic' ? 'Da questo creo la grafica: breve e chiaro.' : 'Un titolo breve e chiaro.';
            $('note-title').hidden = !state.note || state.titleAi;
            $('note-title').textContent = state.note || '';
        }
        if (id === 'description') {
            $('in-desc').value = state.desc;
            $('ai-desc').hidden = !state.descAi;
            $('h-desc').textContent = state.descAi ? 'E questa è la descrizione' : 'Due righe di descrizione';
            $('sub-desc').textContent = state.mode === 'graphic' ? 'Anche da qui prendo spunto per la grafica.' : 'Cosa deve sapere chi legge.';
        }
        if (id === 'price') {
            $('in-price').value = state.price;
            if (KIND === 'promo') syncPriceBox();
        }
        if (id === 'review') renderReview();
    }

    // ---- validazione e avanzamento ----
    function validate(id) {
        const err = (k, msg) => { const e = $('err-' + k); if (e) e.textContent = msg || ''; return !msg; };
        if (id === 'photo') return err('photo', state.mode ? '' : 'Scegli come vuoi cominciare.');
        if (id === 'title') {
            state.title = $('in-title').value.trim();
            return err('title', state.title.length < 2 ? 'Scrivi un titolo (almeno 2 lettere).' : '');
        }
        if (id === 'description') {
            state.desc = $('in-desc').value.trim();
            return err('desc', '');
        }
        if (id === 'validity') {
            if (state.expire) {
                state.end = $('in-end').value;
                if (!state.end || state.end < TODAY) return err('validity', 'Scegli una data da oggi in poi.');
            }
            return err('validity', '');
        }
        if (id === 'price') {
            state.price = $('in-price').value.trim();
            if (KIND === 'promo' && !state.hasPrice) return err('price', '');
            const n = money(state.price);
            if (n === null || n < (KIND === 'promo' ? 0 : 0.5)) return err('price', KIND === 'promo' ? 'Scrivi il prezzo oppure scegli «No».' : 'Scrivi un prezzo (almeno 0,50 €).');
            if (n > 99999) return err('price', 'Il prezzo è troppo alto.');
            return err('price', '');
        }
        if (id === 'duration') {
            const v = $('in-dur').value.trim();
            if (v) { const n = parseInt(v, 10); if (isNaN(n) || n < 5 || n > 1440) return err('duration', 'Scrivi i minuti, da 5 a 1440.'); state.duration = n; }
            return err('duration', '');
        }
        if (id === 'promo') {
            if (state.promo) {
                state.promoEnd = $('in-promo-end').value;
                if (!state.promoEnd || state.promoEnd < TODAY) return err('promo', 'Scegli una data da oggi in poi.');
            }
            return err('promo', '');
        }
        if (id === 'register') {
            const email = $('in-guest-email').value.trim();
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) return err('register', 'Scrivi un indirizzo email valido.');
            if (!$('in-guest-consent').checked) return err('register', 'Serve il tuo consenso per continuare.');
            return err('register', '');
        }
        return true;
    }

    async function next() {
        if (busy) return;
        const id = STEPS[idx];
        if (!validate(id)) return;
        if (id === LAST) return submit(true);
        if (id === 'photo' && state.mode === 'photo' && !state.suggested) { await suggest(); }
        show(idx + 1);
    }

    // ---- foto + suggerimenti IA ----
    function chooseGraphic() {
        state.mode = 'graphic'; state.suggested = false; state.titleAi = state.descAi = false; state.payload = null;
        file.value = ''; state.photoUrl = null;
        $('photo-ready').hidden = true;
        show(idx + 1);
    }

    function onFile() {
        const f = file.files[0];
        if (!f) return;
        if (!/^image\//.test(f.type)) { $('err-photo').textContent = 'Scegli un file immagine.'; file.value = ''; return; }
        if (f.size > MAX_FILE * 1024 * 1024) { $('err-photo').textContent = 'La foto è troppo pesante (massimo ' + MAX_FILE + ' MB).'; file.value = ''; return; }
        $('err-photo').textContent = '';
        state.mode = 'photo'; state.suggested = false; state.titleAi = state.descAi = false;
        if (state.photoUrl) URL.revokeObjectURL(state.photoUrl);
        state.photoUrl = URL.createObjectURL(f);
        $('photo-img').src = state.photoUrl;
        $('photo-ready').hidden = false;
        $('photo-choices').hidden = true;
        $('b-next').style.display = '';
        next();
    }

    async function suggest() {
        busy = true;
        veil(true, 'Sto guardando la tua foto…', 'Preparo titolo e descrizione');
        try {
            const fd = new FormData();
            fd.append('image', file.files[0]); fd.append('kind', KIND); fd.append('_token', CSRF);
            const res = await fetch(SUGGEST_URL, { method: 'POST', body: fd, headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            if (data.ok) {
                if (!state.touchedTitle && data.title) { state.title = data.title; state.titleAi = true; }
                if (!state.touchedDesc && data.description) { state.desc = data.description; state.descAi = true; }
                if (data.price && !state.price) { state.price = String(data.price).replace('.', ','); state.hasPrice = true; state.aiPrice = true; }
                state.payload = data.payload || null;
            } else if (data.message) {
                $('err-title').textContent = '';
                state.note = data.message;
            }
        } catch (e) {
            state.note = 'Non sono riuscito a leggere la foto: scrivi tu il titolo, va benissimo.';
        }
        state.suggested = true;
        busy = false;
        veil(false);
    }

    function veil(on, t, s) { $('veil').classList.toggle('on', on); if (t) $('veil-t').textContent = t; $('veil-s').textContent = s || ''; }

    // ---- riepilogo ----
    function renderReview() {
        const rows = [];
        const row = (label, value, step) => rows.push(`<div class="row"><span>${label}</span><b>${esc(value)}</b><a data-go="${step}">Modifica</a></div>`);
        row('Titolo', state.title, 'title');
        row('Descrizione', state.desc ? (state.desc.length > 34 ? state.desc.slice(0, 34) + '…' : state.desc) : 'nessuna', 'description');
        if (KIND === 'promo') {
            row('Validità', state.expire ? 'fino al ' + dateIt(state.end) : 'sempre attiva', 'validity');
            row('Prezzo', state.hasPrice && money(state.price) !== null ? fmt(money(state.price)) + ' €' : 'nessuno', 'price');
        } else {
            row('Prezzo', fmt(money(state.price)) + ' €', 'price');
            if (KIND === 'service') row('Durata', state.duration ? state.duration + ' min' : 'non indicata', 'duration');
            row('Etichetta', state.promo ? 'In promo fino al ' + dateIt(state.promoEnd) : 'nessuna', 'promo');
        }
        row('Immagine', state.mode === 'photo' ? 'la tua foto' : 'grafica creata dall\'IA', 'photo');
        $('sum-rows').innerHTML = rows.join('');
        $('sum-title').textContent = state.title;
        $('sum-desc').textContent = state.desc;
        const withPhoto = state.mode === 'photo' && state.photoUrl;
        $('sum-img').style.padding = withPhoto ? '0' : '';
        $('sum-img').innerHTML = withPhoto ? `<img src="${state.photoUrl}" alt="">` : esc(state.title);
        $('sum-note').textContent = state.mode === 'graphic' ? 'La grafica la disegno quando pubblichi: ci vuole qualche secondo.' : '';
        $('sum-rows').querySelectorAll('[data-go]').forEach(a => a.addEventListener('click', () => show(STEPS.indexOf(a.dataset.go))));
    }

    // ---- invio ----
    function submit(publish) {
        if (busy) return;
        busy = true;
        $('f-title').value = state.title;
        $('f-desc').value = state.desc;
        if (KIND === 'promo') {
            $('f-source').value = state.mode === 'photo' ? 'upload' : 'svg';
            $('f-hint').value = state.title;
            $('f-payload').value = state.mode === 'photo' && state.payload ? JSON.stringify(state.payload) : '';
            $('f-always').value = state.expire ? '0' : '1';
            $('f-end').value = state.expire ? state.end : '';
            $('f-start').value = state.expire ? TODAY : '';
            $('f-price-text').value = state.hasPrice && money(state.price) !== null ? fmt(money(state.price)) + ' €' : '';
            $('f-publish').value = publish ? '1' : '0';
        } else {
            $('f-auto').value = state.mode === 'graphic' ? '1' : '0';
            $('f-amount').value = String(money(state.price));
            $('f-duration').value = state.duration || '';
            $('f-promo-on').value = state.promo ? '1' : '0';
            $('f-promo-until').value = state.promo ? state.promoEnd : '';
            if (!publish) $('in-visible').checked = false;
        }
        if (state.mode !== 'photo') file.value = '';
        // Salvando solo la bozza l'ospite non invia l'email: la chiederà quando vuole pubblicare.
        if (GUEST && !publish) $('in-guest-email').disabled = true;
        veil(true, publish ? (GUEST ? 'Controllo la tua promo…' : 'Sto pubblicando…') : 'Sto salvando…', state.mode === 'graphic' ? 'Sto disegnando la grafica, ci vuole qualche secondo.' : 'Ancora un attimo.');
        form.submit();
    }

    // ---- eventi ----
    $('b-next').addEventListener('click', next);
    $('b-back').addEventListener('click', () => { if (!busy) show(idx - 1, true); });
    $('b-draft').addEventListener('click', () => { if (validate('review')) submit(false); });
    $('pick-graphic').addEventListener('click', chooseGraphic);
    file.addEventListener('change', onFile);
    $('photo-change').addEventListener('click', (e) => { e.preventDefault(); $('photo-choices').hidden = false; $('photo-ready').hidden = true; state.mode = null; $('b-next').style.display = 'none'; file.value = ''; });
    $('in-title').addEventListener('input', () => { state.touchedTitle = true; state.titleAi = false; $('ai-title').hidden = true; });
    $('in-desc').addEventListener('input', () => { state.touchedDesc = true; state.descAi = false; $('ai-desc').hidden = true; });
    form.addEventListener('keydown', (e) => { if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') { e.preventDefault(); next(); } });
    $('wz-exit').addEventListener('click', (e) => { if ((state.mode || state.title) && !confirm('Vuoi uscire? Quello che hai inserito andrà perso.')) e.preventDefault(); });

    // scadenza promo
    document.querySelectorAll('[data-expire]').forEach(b => b.addEventListener('click', () => {
        state.expire = b.dataset.expire === '1';
        document.querySelectorAll('[data-expire]').forEach(x => x.classList.toggle('on', x === b));
        $('expire-box').hidden = !state.expire;
    }));
    // prezzo promo (sì/no)
    function syncPriceBox() {
        document.querySelectorAll('[data-price]').forEach(x => x.classList.toggle('on', (x.dataset.price === '1') === state.hasPrice));
        $('price-box').hidden = !state.hasPrice;
        if (state.aiPrice) $('sub-price').textContent = 'L\'ho letto dalla tua foto: controlla che sia giusto.';
    }
    document.querySelectorAll('[data-price]').forEach(b => b.addEventListener('click', () => { state.hasPrice = b.dataset.price === '1'; syncPriceBox(); if (state.hasPrice) $('in-price').focus(); }));
    // durata
    document.querySelectorAll('#dur-chips .chip').forEach(c => c.addEventListener('click', () => {
        state.duration = parseInt(c.dataset.min, 10);
        document.querySelectorAll('#dur-chips .chip').forEach(x => x.classList.toggle('on', x === c));
        if ($('in-dur')) $('in-dur').value = state.duration ? '' : '';
    }));
    // etichetta promo
    document.querySelectorAll('[data-promo]').forEach(b => b.addEventListener('click', () => {
        state.promo = b.dataset.promo === '1';
        document.querySelectorAll('[data-promo]').forEach(x => x.classList.toggle('on', x === b));
        $('promo-box').hidden = !state.promo;
    }));

    show(0);
})();
</script>
</body>
</html>
