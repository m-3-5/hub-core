<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#6366f1">
    <title>Registrati — Hub Core</title>
    <meta name="robots" content="noindex">
    @include('layouts.partials.favicon')
    @include('layouts.partials.wizard-css', ['color' => '#6366f1'])
</head>
<body>
@php
    $offer = (int) config('services.hub_billing.launch_offer_price_eur', 1);
    $monthly = (int) config('services.hub_billing.monthly_price_eur', 29);
@endphp
<div class="wz">
    <div class="wz-top">
        <a class="wz-x" href="{{ route('welcome') }}" id="wz-exit" aria-label="Esci">✕</a>
        <div class="wz-progress"><i id="wz-bar"></i></div>
        <div class="wz-count" id="wz-count"></div>
    </div>

    <form id="wz-form" class="wz-stage" method="POST" action="{{ route('registration.store') }}" novalidate>
        @csrf
        @if ($errors->any())
            <div class="banner bad">{{ $errors->first() }}</div>
        @endif

        <input type="hidden" name="type" id="f-type" value="{{ old('type') }}">
        <input type="hidden" name="first_module" id="f-module" value="{{ old('first_module') }}">
        <input type="hidden" name="company_name" id="f-company" value="{{ old('company_name') }}">
        <input type="hidden" name="contact_name" id="f-contact" value="{{ old('contact_name') }}">
        <input type="hidden" name="email" id="f-email" value="{{ old('email') }}">
        <input type="hidden" name="phone" id="f-phone" value="{{ old('phone') }}">

        <section class="wz-step" data-step="type">
            <h1>Come vuoi usare Hub Core?</h1>
            <p class="sub">Scegli: ti preparo lo spazio giusto.</p>
            <div class="choices">
                <button type="button" class="choice" data-type="azienda"><span class="ico">🏢</span><span><b>Azienda</b><small>Negozio, studio, attività con clienti</small></span></button>
                <button type="button" class="choice" data-type="privato"><span class="ico">👤</span><span><b>Privato</b><small>Sempre gratis: annunci, affitti, idee</small></span></button>
                <button type="button" class="choice" data-type="ente"><span class="ico">🏛️</span><span><b>Ente o associazione</b><small>Eventi, comunicazioni, iniziative</small></span></button>
            </div>
            <div class="err" id="err-type"></div>
        </section>

        <section class="wz-step" data-step="module">
            <h1>Da dove vuoi cominciare?</h1>
            <p class="sub">Attivo subito il primo modulo, gli altri li aggiungi quando vuoi.</p>
            <div class="choices">
                <button type="button" class="choice" data-module="promo"><span class="ico">✨</span><span><b>Promo</b><small>Volantini e offerte online in pochi tocchi</small></span></button>
                <button type="button" class="choice" data-module="services"><span class="ico">💆</span><span><b>Servizi e prodotti</b><small>Vendi con pagamento online</small></span></button>
            </div>
            <div class="err" id="err-module"></div>
        </section>

        <section class="wz-step" data-step="company">
            <h1 id="h-company">Come si chiama la tua attività?</h1>
            <p class="sub" id="sub-company">Lo useremo per personalizzare tutto quello che crei.</p>
            <input class="field" id="in-company" type="text" maxlength="120" autocomplete="organization" placeholder="Es. Salone Anna">
            <div class="err" id="err-company"></div>
        </section>

        <section class="wz-step" data-step="contact">
            <h1>E tu come ti chiami?</h1>
            <p class="sub">Così so come chiamarti.</p>
            <input class="field" id="in-contact" type="text" maxlength="120" autocomplete="name" placeholder="Es. Anna Rossi">
            <div class="err" id="err-contact"></div>
        </section>

        <section class="wz-step" data-step="email">
            <h1>Qual è la tua email?</h1>
            <p class="sub">Ti mando un link per confermare: serve per entrare e per recuperare l'account.</p>
            <input class="field" id="in-email" type="email" inputmode="email" maxlength="190" autocomplete="email" placeholder="la-tua-email@esempio.it">
            <div class="err" id="err-email"></div>
        </section>

        <section class="wz-step" data-step="phone">
            <h1>Vuoi lasciarmi un telefono?</h1>
            <p class="sub">Facoltativo: ti serve solo se vuoi essere richiamato.</p>
            <input class="field" id="in-phone" type="tel" inputmode="tel" maxlength="30" autocomplete="tel" placeholder="es. 333 1234567">
            <div class="err" id="err-phone"></div>
        </section>

        <section class="wz-step" data-step="review">
            <h1>Tutto pronto?</h1>
            <p class="sub" id="sub-review"></p>
            <div class="sum"><div class="sum-body"><div class="rows" id="sum-rows" style="margin-top:0;border-top:0"></div></div></div>
            <p class="hint" id="note-offer"></p>
            <p class="hint">Registrandoti accetti che usiamo i tuoi dati solo per gestire il tuo account. Puoi cancellarli quando vuoi.</p>
        </section>
    </form>

    <div class="wz-bar">
        <div class="wz-bar-in">
            <button type="button" class="btn ghost" id="b-back" hidden>Indietro</button>
            <button type="button" class="btn main" id="b-next">Avanti</button>
        </div>
    </div>
</div>

<div class="veil" id="veil"><div><div class="spin"></div><b style="font-size:1.2rem">Un attimo…</b><p style="color:#6b6b7b;margin:8px 0 0">Ti sto mandando l'email di conferma.</p></div></div>

<script>
(function () {
    const OFFER = @json($offer), MONTHLY = @json($monthly);
    const $ = (id) => document.getElementById(id);
    const form = $('wz-form');
    const sections = Object.fromEntries([...document.querySelectorAll('[data-step]')].map(s => [s.dataset.step, s]));
    const esc = (t) => String(t).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const TYPES = { azienda: 'Azienda', privato: 'Privato', ente: 'Ente' };
    const MODULES = { promo: 'Promo', services: 'Servizi e prodotti' };

    const state = {
        type: $('f-type').value, module: $('f-module').value, company: $('f-company').value,
        contact: $('f-contact').value, email: $('f-email').value, phone: $('f-phone').value,
    };
    // Il privato non sceglie il modulo e usa lo stesso nome per tutto.
    const steps = () => state.type === 'privato' ? ['type', 'company', 'email', 'phone', 'review'] : ['type', 'module', 'company', 'contact', 'email', 'phone', 'review'];
    let current = state.type && state.email ? 'review' : 'type';
    let busy = false;

    const err = (k, msg) => { const e = $('err-' + k); if (e) e.textContent = msg || ''; return !msg; };

    function show(id, back) {
        current = id;
        const list = steps(), i = list.indexOf(id);
        Object.values(sections).forEach(s => s.classList.remove('on', 'back'));
        sections[id].classList.add('on');
        if (back) sections[id].classList.add('back');
        $('wz-bar').style.width = ((i + 1) / list.length * 100) + '%';
        $('wz-count').textContent = (i + 1) + '/' + list.length;
        $('b-back').hidden = i === 0;
        $('b-next').textContent = id === 'review' ? 'Registrati' : 'Avanti';
        $('b-next').style.display = (id === 'type' || id === 'module') ? 'none' : '';
        if (id === 'company') {
            const p = state.type === 'privato';
            $('h-company').textContent = p ? 'Come ti chiami?' : (state.type === 'ente' ? 'Come si chiama il tuo ente?' : 'Come si chiama la tua attività?');
            $('sub-company').textContent = p ? 'Nome e cognome.' : 'Lo useremo per personalizzare tutto quello che crei.';
            $('in-company').placeholder = p ? 'Es. Mario Rossi' : 'Es. Salone Anna';
            $('in-company').value = state.company;
        }
        if (id === 'contact') $('in-contact').value = state.contact;
        if (id === 'email') $('in-email').value = state.email;
        if (id === 'phone') $('in-phone').value = state.phone;
        if (id === 'review') renderReview();
        window.scrollTo({ top: 0 });
        const f = { company: 'in-company', contact: 'in-contact', email: 'in-email', phone: 'in-phone' }[id];
        if (f) setTimeout(() => $(f).focus({ preventScroll: true }), 320);
    }

    function go(delta) {
        const list = steps();
        show(list[Math.max(0, Math.min(list.indexOf(current) + delta, list.length - 1))], delta < 0);
    }

    function validate(id) {
        if (id === 'company') {
            state.company = $('in-company').value.trim();
            if (state.type === 'privato') state.contact = state.company;
            return err('company', state.company.length < 2 ? 'Scrivi almeno 2 lettere.' : '');
        }
        if (id === 'contact') {
            state.contact = $('in-contact').value.trim();
            return err('contact', state.contact.length < 2 ? 'Scrivi il tuo nome.' : '');
        }
        if (id === 'email') {
            state.email = $('in-email').value.trim();
            return err('email', /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(state.email) ? '' : 'Scrivi un indirizzo email valido.');
        }
        if (id === 'phone') {
            state.phone = $('in-phone').value.trim();
            return err('phone', state.phone && !/^[0-9+()\s.\-]{6,30}$/.test(state.phone) ? 'Controlla il numero: usa solo cifre.' : '');
        }
        return true;
    }

    function renderReview() {
        const rows = [];
        const row = (label, value, step) => rows.push(`<div class="row"><span>${label}</span><b>${esc(value || '—')}</b><a data-go="${step}">Modifica</a></div>`);
        row('Tipo', TYPES[state.type], 'type');
        if (state.type !== 'privato') row('Primo modulo', MODULES[state.module], 'module');
        row(state.type === 'privato' ? 'Nome' : 'Attività', state.company, 'company');
        if (state.type !== 'privato') row('Tuo nome', state.contact, 'contact');
        row('Email', state.email, 'email');
        row('Telefono', state.phone, 'phone');
        $('sum-rows').innerHTML = rows.join('');
        $('sum-rows').querySelectorAll('[data-go]').forEach(a => a.addEventListener('click', () => show(a.dataset.go)));
        $('sub-review').textContent = 'Ti mando un\'email: un clic e il tuo spazio è attivo.';
        $('note-offer').textContent = state.type === 'privato'
            ? 'Per i privati Hub Core è sempre gratis.'
            : `La prima settimana è gratis, senza carta. Dopo, per continuare, basta ${OFFER} € (offerta di lancio, copre anche il primo modulo); il canone da ${MONTHLY} €/mese parte solo dopo altri giorni di prova. Se non attivi, non succede niente.`;
    }

    function next() {
        if (busy || !validate(current)) return;
        if (current === 'review') return submit();
        go(1);
    }

    function submit() {
        busy = true;
        $('f-type').value = state.type;
        $('f-module').value = state.type === 'privato' ? '' : state.module;
        $('f-company').value = state.company;
        $('f-contact').value = state.type === 'privato' ? state.company : state.contact;
        $('f-email').value = state.email;
        $('f-phone').value = state.phone;
        if (state.type === 'privato') $('f-module').disabled = true;
        $('veil').classList.add('on');
        form.submit();
    }

    document.querySelectorAll('[data-type]').forEach(b => b.addEventListener('click', () => {
        state.type = b.dataset.type;
        document.querySelectorAll('[data-type]').forEach(x => x.classList.toggle('on', x === b));
        if (state.type !== 'privato' && !state.module) { show('module'); return; }
        go(1);
    }));
    document.querySelectorAll('[data-module]').forEach(b => b.addEventListener('click', () => {
        state.module = b.dataset.module;
        document.querySelectorAll('[data-module]').forEach(x => x.classList.toggle('on', x === b));
        go(1);
    }));

    $('b-next').addEventListener('click', next);
    $('b-back').addEventListener('click', () => { if (!busy) go(-1); });
    form.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); next(); } });

    show(current);
})();
</script>
</body>
</html>
