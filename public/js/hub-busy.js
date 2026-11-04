/*
 * Rotellina con scritta mentre si aspetta — unica per tutto inm35.it.
 * Si attiva da sola:
 *   - invio di un modulo (anche form.submit() dei filtri): il tasto premuto mostra la rotellina e
 *     una scritta («Salvo…», «Ricerca in corso…», «Invio…»), e non si può premere due volte;
 *   - chiamate fetch/XHR che inviano dati (POST, PUT, …): pillola in alto «Un attimo…»
 *     (o «L'IA sta scrivendo…» se l'indirizzo parla di IA);
 *   - clic su un link interno che ci mette più di mezzo secondo: pillola «Carico…».
 * Scritta personalizzata: data-busy="Genero il sito…" sul tasto, sul modulo o sul link.
 * Per escludere un modulo o un link: data-no-busy.
 * Uso a mano: HubBusy.show('Testo…'), HubBusy.hide().
 */
(function () {
    'use strict';
    if (window.HubBusy) { return; }

    var SPIN = '<span class="hub-spin" aria-hidden="true"></span>';
    var SHORT = { 'Ricerca in corso…': 'Cerco…', "L'IA sta scrivendo…": 'Scrivo…', 'Un attimo…': 'Attendi…', 'Pubblico…': 'Attendi…', 'Nascondo…': 'Attendi…' };
    var pill = null, pillTimer = null, pending = 0, lastClick = null, lastClickAt = 0;

    function textFor(el, form) {
        var own = (el && el.getAttribute && el.getAttribute('data-busy')) || (form && form.getAttribute('data-busy'));
        if (own) { return own; }
        var label = ((el && (el.innerText || el.value)) || '').toLowerCase();
        var method = form ? (form.getAttribute('method') || 'get').toLowerCase() : '';
        var action = form ? (form.getAttribute('action') || '') : '';
        if (/\bia\b|gemini|genera|scrivi con|ai\//.test(label + ' ' + action.toLowerCase())) { return "L'IA sta scrivendo…"; }
        if (method === 'get' || /cerca|filtra|trova/.test(label)) { return 'Ricerca in corso…'; }
        if (/salva|aggiorna|modifica|conferma/.test(label)) { return 'Salvo…'; }
        if (/invia|manda|scrivi/.test(label)) { return 'Invio…'; }
        if (/pubblica/.test(label)) { return 'Pubblico…'; }
        if (/nascondi/.test(label)) { return 'Nascondo…'; }
        if (/elimina|cancella|annulla|disattiva|rimuovi/.test(label)) { return 'Un attimo…'; }
        if (/accedi|entra|login/.test(label)) { return 'Accesso…'; }
        if (/carica|importa/.test(label)) { return 'Carico…'; }
        if (/paga|acquista|compra|attiva/.test(label)) { return 'Un attimo…'; }
        return 'Un attimo…';
    }

    function busyButton(btn, text) {
        if (!btn || btn.classList.contains('is-busy')) { return; }
        btn.classList.add('is-busy');
        btn.setAttribute('aria-busy', 'true');
        if (btn.tagName === 'INPUT') {
            btn.dataset.busyOrig = btn.value;
            btn.value = text;
        } else {
            btn.dataset.busyOrig = btn.innerHTML;
            // la larghezza resta quella di prima, così la pagina non salta
            var w = btn.getBoundingClientRect().width;
            if (w) { btn.style.minWidth = Math.round(w) + 'px'; }
            // tasto stretto (es. «Cerca» accanto alla casella): scritta corta, così non spinge fuori niente
            if (w && w < 150 && SHORT[text]) { text = SHORT[text]; }
            btn.innerHTML = SPIN + '<span class="hub-spin__t">' + text + '</span>';
            btn.dataset.busySet = btn.innerHTML;
        }
    }

    function restoreAll() {
        document.querySelectorAll('.is-busy').forEach(function (btn) {
            if (btn.dataset.busyOrig !== undefined) {
                if (btn.tagName === 'INPUT') { btn.value = btn.dataset.busyOrig; } else { btn.innerHTML = btn.dataset.busyOrig; }
                delete btn.dataset.busyOrig;
            }
            btn.classList.remove('is-busy');
            btn.removeAttribute('aria-busy');
            btn.style.minWidth = '';
            btn.disabled = false;
            delete btn.dataset.busySet;
        });
        document.querySelectorAll('form.is-busy-form').forEach(function (f) { f.classList.remove('is-busy-form'); });
        hide(true);
    }

    function show(text, delay) {
        clearTimeout(pillTimer);
        pillTimer = setTimeout(function () {
            if (!pill) {
                pill = document.createElement('div');
                pill.className = 'hub-busy-pill';
                pill.setAttribute('role', 'status');
                pill.setAttribute('aria-live', 'polite');
                document.body.appendChild(pill);
            }
            pill.innerHTML = SPIN + '<span>' + (text || 'Un attimo…') + '</span>';
            pill.classList.add('is-on');
        }, delay || 0);
    }

    function hide(force) {
        if (!force && pending > 0) { return; }
        clearTimeout(pillTimer);
        if (pill) { pill.classList.remove('is-on'); }
    }

    function startForm(form, submitter) {
        if (!form || form.hasAttribute('data-no-busy') || form.classList.contains('is-busy-form')) { return; }
        var target = (form.getAttribute('target') || '').toLowerCase();
        if (target && target !== '_self') { return; }
        var btn = submitter || form.querySelector('button[type=submit], button:not([type]), input[type=submit]');
        var text = textFor(btn, form);
        form.classList.add('is-busy-form');
        // la pagina ha già messo la sua scritta d'attesa sul tasto: si lascia la sua
        if (btn && (btn.disabled || btn.getAttribute('aria-busy') === 'true')) { return; }
        if (btn && !btn.hasAttribute('data-no-busy') && btn.offsetParent !== null) {
            busyButton(btn, text);
            // disattivato DOPO l'invio, così il suo name/value parte lo stesso
            setTimeout(function () { btn.disabled = true; }, 0);
        } else {
            show(text);
        }
        // se la pagina non cambia (es. un file scaricato), dopo un po' tutto torna normale
        setTimeout(restoreAll, 20000);
    }

    document.addEventListener('click', function (e) {
        var el = e.target.closest && e.target.closest('button, input[type=submit], a');
        if (!el) { return; }
        lastClick = el; lastClickAt = Date.now();
        if (el.tagName !== 'A' || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) { return; }
        var href = el.getAttribute('href') || '';
        if (!href || href.charAt(0) === '#' || el.hasAttribute('download') || el.hasAttribute('data-no-busy')) { return; }
        if ((el.getAttribute('target') || '_self') !== '_self') { return; }
        if (/^(mailto|tel|sms|whatsapp|javascript):/i.test(href)) { return; }
        var url;
        try { url = new URL(href, location.href); } catch (err) { return; }
        if (url.origin !== location.origin) { return; }
        if (url.pathname === location.pathname && url.search === location.search && url.hash) { return; }
        var text = el.getAttribute('data-busy') || (url.pathname === location.pathname && url.search !== location.search ? 'Ricerca in corso…' : 'Carico…');
        // solo se ci mette: niente lampeggi sulle pagine veloci
        show(text, 500);
    });

    // dopo i gestori della pagina (onsubmit="return confirm(...)" compreso): parte solo se l'invio è vero
    document.addEventListener('submit', function (e) {
        if (e.defaultPrevented) { return; }
        startForm(e.target, e.submitter);
    });

    // form.submit() dei filtri (select che inviano da sole) non genera l'evento submit
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        try { startForm(this, null); } catch (err) { /* non bloccare mai l'invio */ }
        return nativeSubmit.apply(this, arguments);
    };

    function wantsBusy(method, url) {
        method = (method || 'GET').toUpperCase();
        if (method === 'GET' || method === 'HEAD' || method === 'OPTIONS') { return false; }
        return !/\/(ping|track|beacon|heartbeat)\b/i.test(url || '');
    }

    function begin(url) {
        pending++;
        var btn = (lastClick && Date.now() - lastClickAt < 1500 && lastClick.tagName !== 'A') ? lastClick : null;
        var text = (/\b(ai|ia|gemini|genera|generate|rewrite|chat)\b/i.test(url || '') ? "L'IA sta scrivendo…" : textFor(btn, btn && btn.form));
        // la pagina gestisce già il tasto da sola (disattivato o scritta cambiata): solo la pillola
        if (btn && (btn.disabled || btn.getAttribute('aria-busy') === 'true')) { btn = null; }
        if (btn && !btn.classList.contains('is-busy') && !btn.hasAttribute('data-no-busy')) {
            busyButton(btn, text);
            return btn;
        }
        show(text, 250);
        return null;
    }

    function end(btn) {
        pending = Math.max(0, pending - 1);
        if (btn && btn.dataset.busyOrig !== undefined) {
            // se nel frattempo la pagina ha cambiato il tasto, si lascia com'è
            if (btn.innerHTML === btn.dataset.busySet) { btn.innerHTML = btn.dataset.busyOrig; }
            delete btn.dataset.busySet;
            delete btn.dataset.busyOrig;
            btn.classList.remove('is-busy');
            btn.removeAttribute('aria-busy');
            btn.style.minWidth = '';
        }
        hide();
    }

    if (window.fetch) {
        var nativeFetch = window.fetch;
        window.fetch = function (input, init) {
            var method = (init && init.method) || (input && input.method) || 'GET';
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            if (!wantsBusy(method, url) || (init && init.busy === false)) { return nativeFetch.apply(this, arguments); }
            var btn = begin(url);
            return nativeFetch.apply(this, arguments).then(function (r) { end(btn); return r; }, function (err) { end(btn); throw err; });
        };
    }

    var xOpen = XMLHttpRequest.prototype.open, xSend = XMLHttpRequest.prototype.send;
    XMLHttpRequest.prototype.open = function (method, url) { this._hubBusy = wantsBusy(method, url) ? String(url) : null; return xOpen.apply(this, arguments); };
    XMLHttpRequest.prototype.send = function () {
        if (this._hubBusy !== null && this._hubBusy !== undefined) {
            var btn = begin(this._hubBusy), done = false;
            this.addEventListener('loadend', function () { if (!done) { done = true; end(btn); } });
        }
        return xSend.apply(this, arguments);
    };

    // tornando indietro col tasto del telefono la pagina può riapparire dalla memoria: niente rotelline rimaste
    window.addEventListener('pageshow', function () { pending = 0; restoreAll(); });

    window.HubBusy = { show: function (t) { show(t); }, hide: function () { hide(true); }, button: busyButton, restore: restoreAll };
})();
