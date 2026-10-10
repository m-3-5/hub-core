{{-- Max: chat aperta in basso a destra per chi visita inm35.it (anche non registrato). Richiede --accent e --muted. --}}
<style>
    :root { --pmax-lift: {{ (int) ($lift ?? 0) }}px; }
    .pmax-fab { position: fixed; right: 16px; bottom: calc(18px + var(--pmax-lift) + env(safe-area-inset-bottom)); width: 60px; border: 0; background: none; padding: 0; cursor: pointer; z-index: 80; filter: drop-shadow(0 10px 22px rgba(15, 23, 42, .3)); }
    .pmax-fab .pmax-dot { position: absolute; top: -2px; right: -2px; width: 16px; height: 16px; border-radius: 50%; background: #ef4444; border: 2px solid #fff; }
    .max-mascot-body { animation: max-bounce 2.6s ease-in-out infinite; transform-origin: center; }
    @keyframes max-bounce { 0%, 100% { transform: translateY(0) rotate(0deg); } 50% { transform: translateY(-6px) rotate(-2.5deg); } }
    .max-mascot-lid { animation: max-mascot-blink 4.6s ease-in-out .1s infinite; transform-origin: center; }
    @keyframes max-mascot-blink { 0%, 90%, 100% { transform: scaleY(1); } 93% { transform: scaleY(.08); } }
    @media (prefers-reduced-motion: reduce) { .max-mascot-body, .max-mascot-lid { animation: none !important; } }

    .pmax-panel { position: fixed; right: 16px; bottom: calc(92px + var(--pmax-lift) + env(safe-area-inset-bottom)); width: min(370px, calc(100vw - 24px)); height: min(560px, calc(100dvh - 120px)); background: #fff; border-radius: 22px; box-shadow: 0 24px 70px rgba(15, 23, 42, .3); z-index: 81; display: flex; flex-direction: column; overflow: hidden; animation: pmax-in .22s ease; }
    .pmax-panel[hidden] { display: none; }
    @keyframes pmax-in { from { opacity: 0; transform: translateY(14px) scale(.98); } to { opacity: 1; transform: none; } }
    .pmax-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px; background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 70%, #1e1b4b)); color: #fff; }
    .pmax-head .pmax-ava { display: flex; background: #fff; border-radius: 50%; padding: 3px; }
    .pmax-head b { display: block; line-height: 1.1; }
    .pmax-head small { opacity: .85; font-size: .75rem; }
    .pmax-head .pmax-title { flex: 1; }
    .pmax-head button { background: rgba(255,255,255,.2); border: 0; color: #fff; width: 30px; height: 30px; border-radius: 50%; cursor: pointer; font-size: .95rem; }
    .pmax-log { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 10px; background: #f6f7fb; }
    .pmax-msg { max-width: 86%; padding: 9px 13px; border-radius: 16px; font-size: .93rem; line-height: 1.4; white-space: pre-wrap; overflow-wrap: anywhere; }
    .pmax-msg.max { align-self: flex-start; background: #fff; border-bottom-left-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,.06); }
    .pmax-msg.me { align-self: flex-end; background: var(--accent); color: #fff; border-bottom-right-radius: 4px; }
    .pmax-typing { align-self: flex-start; color: var(--muted); font-size: .85rem; padding: 4px 8px; }
    .pmax-chips { display: flex; flex-wrap: wrap; gap: 8px; }
    .pmax-chip { border: 1px solid color-mix(in srgb, var(--accent) 35%, #fff); background: color-mix(in srgb, var(--accent) 10%, #fff); color: #1e1b4b; border-radius: 99px; padding: 8px 13px; font: inherit; font-size: .86rem; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
    .pmax-chip:hover { background: color-mix(in srgb, var(--accent) 18%, #fff); }
    .pmax-form { display: flex; gap: 8px; padding: 10px; border-top: 1px solid #eceef3; background: #fff; }
    .pmax-form input[type=text] { flex: 1; min-width: 0; font: inherit; font-size: 1rem; padding: 11px 14px; border: 2px solid #e6e7ee; border-radius: 99px; outline: none; }
    .pmax-form input[type=text]:focus { border-color: var(--accent); }
    .pmax-form button { border: 0; border-radius: 50%; width: 44px; height: 44px; background: var(--accent); color: #fff; font-size: 1.2rem; cursor: pointer; flex: none; }
    .pmax-form button:disabled { opacity: .5; }
    .pmax-guest { display: flex; flex-direction: column; gap: 8px; align-self: stretch; }
    .pmax-guest input[type=text] { font: inherit; font-size: 1rem; padding: 11px 14px; border: 2px solid #e6e7ee; border-radius: 14px; outline: none; }
    .pmax-guest .pmax-go { border: 0; border-radius: 14px; padding: 12px; background: var(--accent); color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
    @media (max-width: 520px) { .pmax-panel { right: 8px; left: 8px; width: auto; bottom: calc(86px + var(--pmax-lift) + env(safe-area-inset-bottom)); } }
</style>

<button type="button" class="pmax-fab" id="pmax-fab" aria-label="Chiedi aiuto a Max">
    @include('app.partials.max-avatar', ['size' => 60, 'animated' => true])
    <span class="pmax-dot" id="pmax-dot" hidden></span>
</button>

<div class="pmax-panel" id="pmax-panel" role="dialog" aria-label="Chat con Max" hidden>
    <div class="pmax-head">
        <span class="pmax-ava">@include('app.partials.max-avatar', ['size' => 30, 'animated' => false])</span>
        <span class="pmax-title"><b>Max</b><small>Chiedimi pure, ti aiuto io</small></span>
        <button type="button" id="pmax-close" aria-label="Chiudi">✕</button>
    </div>
    <div class="pmax-log" id="pmax-log" aria-live="polite"></div>
    <form class="pmax-form" id="pmax-form" autocomplete="off">
        <input type="text" id="pmax-input" maxlength="500" placeholder="Scrivi qui la tua domanda…" aria-label="Scrivi a Max">
        <button type="submit" id="pmax-send" aria-label="Invia">➤</button>
    </form>
</div>

<form method="POST" action="{{ route('guest.start') }}" id="pmax-guest-form" hidden>
    @csrf
    <input type="hidden" name="type" id="pmax-g-type">
    <input type="hidden" name="company_name" id="pmax-g-name">
</form>

<script>
(function () {
    const URL_CHAT = @json(route('max.chat'));
    const CSRF = @json(csrf_token());
    const URL_LOGIN = @json(route('admin.login'));
    const URL_REGISTER = @json(route('registration.create'));
    const URL_PRICES = @json(route('pricing.show'));
    const URL_WEB = @json(route('landing.web'));
    const $ = (id) => document.getElementById(id);
    const fab = $('pmax-fab'), panel = $('pmax-panel'), log = $('pmax-log'), input = $('pmax-input'), form = $('pmax-form'), send = $('pmax-send');
    const history = [];
    let started = false, busy = false;

    function scroll() { log.scrollTop = log.scrollHeight; }

    function bubble(text, who) {
        const d = document.createElement('div');
        d.className = 'pmax-msg ' + who;
        d.textContent = text;
        log.appendChild(d);
        scroll();
        return d;
    }

    function chips(items) {
        const wrap = document.createElement('div');
        wrap.className = 'pmax-chips';
        items.forEach((it) => {
            const el = document.createElement(it.url ? 'a' : 'button');
            el.className = 'pmax-chip';
            el.textContent = it.label;
            if (it.url) { el.href = it.url; if (/^https?:/.test(it.url) && !it.url.startsWith(location.origin)) { el.target = '_blank'; el.rel = 'noopener'; } }
            else { el.type = 'button'; el.addEventListener('click', () => it.run ? it.run() : act(it)); }
            wrap.appendChild(el);
        });
        log.appendChild(wrap);
        scroll();
    }

    function act(it) { if (it.key === 'guest') startGuest(); }

    function startGuest() {
        bubble('👋 Provalo subito, senza registrarti: ti preparo uno spazio e crei la tua prima promo. Sei un\'azienda, un privato o un ente?', 'max');
        chips([
            { label: '🏢 Azienda', run: () => guestName('azienda') },
            { label: '👤 Privato', run: () => guestName('privato') },
            { label: '🏛️ Ente', run: () => guestName('ente') },
        ]);
    }

    function guestName(type) {
        $('pmax-g-type').value = type;
        bubble(type === 'privato' ? 'Come ti chiami?' : 'Come si chiama la tua attività?', 'max');
        const box = document.createElement('div');
        box.className = 'pmax-guest';
        box.innerHTML = '<input type="text" maxlength="120" id="pmax-name" placeholder="' + (type === 'privato' ? 'Es. Mario Rossi' : 'Es. Salone Anna') + '"><button type="button" class="pmax-go">Inizia →</button>';
        log.appendChild(box);
        scroll();
        const field = box.querySelector('input');
        const go = () => {
            const v = field.value.trim();
            if (v.length < 2) { field.focus(); return; }
            $('pmax-g-name').value = v;
            $('pmax-guest-form').submit();
        };
        box.querySelector('button').addEventListener('click', go);
        field.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); go(); } });
        setTimeout(() => field.focus(), 50);
    }

    function greet() {
        if (started) return;
        started = true;
        bubble('Ciao! Sono Max 👋 Ti aiuto a orientarti su Hub Core. Cosa vuoi fare?', 'max');
        chips([
            { label: '👋 Prova come ospite', run: startGuest },
            { label: '📝 Registrati', url: URL_REGISTER },
            { label: '🔑 Accedi', url: URL_LOGIN },
            { label: '💶 Quanto costa?', run: () => ask('Quanto costa Hub Core?') },
            { label: '🌐 Siti web e app', url: URL_WEB },
        ]);
    }

    async function ask(text) {
        if (busy || !text.trim()) return;
        busy = true; send.disabled = true;
        bubble(text, 'me');
        const wait = document.createElement('div');
        wait.className = 'pmax-typing';
        wait.textContent = 'Max sta scrivendo…';
        log.appendChild(wait); scroll();
        try {
            const res = await fetch(URL_CHAT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ message: text, history: history.slice(-6) }),
            });
            const data = await res.json();
            wait.remove();
            bubble(data.reply || 'Scusa, non ho capito.', 'max');
            history.push({ role: 'user', text: text }, { role: 'max', text: data.reply || '' });
            if (data.actions && data.actions.length) chips(data.actions);
        } catch (e) {
            wait.remove();
            bubble('Ops, non riesco a rispondere ora. Riprova tra poco!', 'max');
        }
        busy = false; send.disabled = false; input.focus();
    }

    function open() { panel.hidden = false; $('pmax-dot').hidden = true; greet(); setTimeout(() => input.focus({ preventScroll: true }), 250); }
    function close() { panel.hidden = true; try { sessionStorage.setItem('pmaxDismissed', '1'); } catch (e) {} }

    fab.addEventListener('click', () => (panel.hidden ? open() : close()));
    $('pmax-close').addEventListener('click', close);
    form.addEventListener('submit', (e) => { e.preventDefault(); const v = input.value; input.value = ''; ask(v); });

    let dismissed = false;
    try { dismissed = !!sessionStorage.getItem('pmaxDismissed'); } catch (e) {}
    const AUTO = @json($autoOpen ?? true);
    if (!dismissed && AUTO) setTimeout(open, 1500); else $('pmax-dot').hidden = false;
})();
</script>
