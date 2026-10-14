/*
 * Condivisione fluttuante e discreta, per tutte le pagine pubbliche.
 * - Compare (piccola e semitrasparente) quando chi legge si ferma per un paio di secondi.
 * - Se il mouse si avvicina si apre: icone dei social a colori e piena opacità.
 * - Da telefono: tocco sul pulsante = finestra di condivisione del sistema (o elenco delle icone).
 * Non fa nulla nelle iframe, nelle pagine «noindex» (private) o dopo che l'utente l'ha chiusa.
 */
(function () {
    'use strict';
    if (window.top !== window.self || window.__floatingShare) { return; }
    window.__floatingShare = true;

    var robots = document.querySelector('meta[name="robots"]');
    if (robots && /noindex/i.test(robots.content)) { return; }
    try { if (sessionStorage.getItem('fs-closed') === '1') { return; } } catch (e) {}

    var canonical = document.querySelector('link[rel="canonical"]');
    var url = (canonical && canonical.href) || location.href.split('#')[0];
    var title = document.title || '';
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var coarse = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

    var enc = encodeURIComponent;
    var ICONS = {
        share: '<path d="M18 16.1c-.8 0-1.5.3-2 .8l-7.1-4.2a3.3 3.3 0 0 0 0-1.4l7-4.1c.6.5 1.3.8 2.1.8a3 3 0 1 0-3-3c0 .2 0 .5.1.7L8 9.8a3 3 0 1 0 0 4.4l7.2 4.2a2.8 2.8 0 0 0-.1.6 2.9 2.9 0 1 0 2.9-2.9z"/>',
        wa: '<path d="M20.5 3.5A11 11 0 0 0 3.2 17.1L2 22l5-1.3A11 11 0 0 0 20.5 3.5zM12 20.2a9.1 9.1 0 0 1-4.6-1.3l-.3-.2-3 .8.8-2.9-.2-.3A9.1 9.1 0 1 1 12 20.2zm5-6.8c-.3-.1-1.6-.8-1.9-.9s-.5-.1-.6.1-.7.9-.9 1.1-.3.2-.6.1a7.4 7.4 0 0 1-3.7-3.2c-.3-.5.3-.5.8-1.5.1-.2 0-.4 0-.5l-.9-2.1c-.2-.5-.5-.5-.6-.5h-.5a1 1 0 0 0-.8.4 3.1 3.1 0 0 0-1 2.3 5.4 5.4 0 0 0 1.1 2.9 12.4 12.4 0 0 0 4.8 4.2c1.8.8 2.5.8 3.4.7a2.9 2.9 0 0 0 1.9-1.3 2.4 2.4 0 0 0 .2-1.3c-.1-.1-.3-.2-.6-.3z"/>',
        fb: '<path d="M13.5 21v-8h2.7l.4-3.2h-3.1V7.8c0-.9.3-1.5 1.6-1.5h1.7V3.4a22 22 0 0 0-2.5-.1c-2.4 0-4.1 1.5-4.1 4.2v2.3H7.4V13h2.8v8h3.3z"/>',
        x: '<path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.9L17.8 3zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5z"/>',
        tg: '<path d="M21.9 4.2 18.7 19.4c-.2 1-.9 1.3-1.7.8l-4.8-3.6-2.3 2.2c-.3.3-.5.5-1 .5l.3-4.9 8.9-8c.4-.3-.1-.5-.6-.2L6.5 13 1.8 11.5c-1-.3-1-1 .2-1.5L20.3 3c.9-.3 1.6.2 1.6 1.2z"/>',
        copy: '<path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H10V7h9v14z"/>',
        ok: '<path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/>',
        close: '<path d="M18.3 5.7 12 12l6.3 6.3-1.4 1.4L10.6 13.4 4.3 19.7 2.9 18.3 9.2 12 2.9 5.7 4.3 4.3l6.3 6.3 6.3-6.3z"/>'
    };
    function svg(name) { return '<svg viewBox="0 0 24 24" aria-hidden="true">' + ICONS[name] + '</svg>'; }

    var css = ''
        + '.fs-root{position:fixed;right:10px;top:58%;z-index:2147483000;display:flex;flex-direction:column;align-items:center;gap:8px;'
        + 'opacity:0;pointer-events:none;transform:translateX(14px);transition:opacity .45s ease,transform .45s ease;font-family:system-ui,sans-serif}'
        + '.fs-root.fs-on{opacity:.55;pointer-events:auto;transform:none}'
        + '.fs-root.fs-open{opacity:1}'
        + '.fs-btn{width:38px;height:38px;border-radius:50%;border:0;padding:0;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;'
        + 'background:rgba(40,40,56,.78);box-shadow:0 2px 10px rgba(0,0,0,.22);transition:transform .2s ease,background .2s ease,opacity .2s ease,width .2s,height .2s}'
        + '.fs-btn svg{width:20px;height:20px;fill:currentColor;display:block}'
        + '.fs-list{display:flex;flex-direction:column;gap:8px;max-height:0;overflow:hidden;opacity:0;transition:max-height .3s ease,opacity .25s ease;padding:2px}'
        + '.fs-root.fs-open .fs-list{max-height:340px;opacity:1}'
        + '.fs-list .fs-btn{width:44px;height:44px}'
        + '.fs-list .fs-btn svg{width:23px;height:23px}'
        + '.fs-list .fs-btn:hover{transform:scale(1.12)}'
        + '.fs-wa{background:#25D366}.fs-fb{background:#1877F2}.fs-x{background:#111}.fs-tg{background:#229ED9}.fs-cp{background:#fff;color:#333}.fs-cp.fs-ok{background:#16a34a;color:#fff}'
        + '.fs-nat{background:#4f46e5}'
        + '.fs-x2{width:22px!important;height:22px!important;background:rgba(40,40,56,.55)!important;opacity:.0}'
        + '.fs-root.fs-open .fs-x2{opacity:.8}'
        + '.fs-x2 svg{width:11px!important;height:11px!important}'
        + '.fs-main{order:99}'
        + '@media (max-width:640px){.fs-root{top:auto;bottom:calc(150px + env(safe-area-inset-bottom));right:8px}}'
        + '@media (prefers-reduced-motion:reduce){.fs-root,.fs-list,.fs-btn{transition:none}}'
        + '@media print{.fs-root{display:none}}';
    var st = document.createElement('style');
    st.textContent = css;
    document.head.appendChild(st);

    var root = document.createElement('div');
    root.className = 'fs-root';
    root.setAttribute('role', 'complementary');
    root.setAttribute('aria-label', 'Condividi questa pagina');

    function link(cls, href, label, icon) {
        var a = document.createElement('a');
        a.className = 'fs-btn ' + cls; a.href = href; a.target = '_blank'; a.rel = 'noopener';
        a.title = label; a.setAttribute('aria-label', label); a.innerHTML = svg(icon);
        return a;
    }

    var list = document.createElement('div');
    list.className = 'fs-list';
    list.appendChild(link('fs-wa', 'https://wa.me/?text=' + enc(title + ' ' + url), 'Condividi su WhatsApp', 'wa'));
    list.appendChild(link('fs-fb', 'https://www.facebook.com/sharer/sharer.php?u=' + enc(url), 'Condividi su Facebook', 'fb'));
    list.appendChild(link('fs-x', 'https://twitter.com/intent/tweet?url=' + enc(url) + '&text=' + enc(title), 'Condividi su X', 'x'));
    list.appendChild(link('fs-tg', 'https://t.me/share/url?url=' + enc(url) + '&text=' + enc(title), 'Condividi su Telegram', 'tg'));

    var copy = document.createElement('button');
    copy.type = 'button'; copy.className = 'fs-btn fs-cp'; copy.title = 'Copia link'; copy.setAttribute('aria-label', 'Copia il link');
    copy.innerHTML = svg('copy');
    copy.addEventListener('click', function () {
        function done() {
            copy.classList.add('fs-ok'); copy.innerHTML = svg('ok');
            setTimeout(function () { copy.classList.remove('fs-ok'); copy.innerHTML = svg('copy'); }, 1800);
        }
        function legacy() {
            var t = document.createElement('textarea');
            t.value = url; t.setAttribute('readonly', ''); t.style.cssText = 'position:fixed;opacity:0';
            document.body.appendChild(t); t.select();
            var ok = false; try { ok = document.execCommand('copy'); } catch (e) {}
            t.remove(); if (ok) { done(); }
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done, legacy);
        } else { legacy(); }
    });
    list.appendChild(copy);

    var close = document.createElement('button');
    close.type = 'button'; close.className = 'fs-btn fs-x2'; close.title = 'Nascondi'; close.setAttribute('aria-label', 'Nascondi la condivisione');
    close.innerHTML = svg('close');
    close.addEventListener('click', function () {
        try { sessionStorage.setItem('fs-closed', '1'); } catch (e) {}
        root.remove();
    });
    list.appendChild(close);

    var main = document.createElement('button');
    main.type = 'button'; main.className = 'fs-btn fs-main'; main.title = 'Condividi'; main.setAttribute('aria-label', 'Condividi questa pagina');
    main.innerHTML = svg('share');
    main.addEventListener('click', function () {
        if (coarse && navigator.share) {
            navigator.share({ title: title, url: url }).catch(function () {});
            return;
        }
        root.classList.toggle('fs-open');
    });

    root.appendChild(list);
    root.appendChild(main);
    document.body.appendChild(root);

    // --- quando mostrarlo ---
    var idleTimer = null;
    var IDLE_MS = 2500;
    var near = false;

    function show() { root.classList.add('fs-on'); }
    function hide() {
        if (near || root.classList.contains('fs-open') && coarse) { return; }
        root.classList.remove('fs-on'); root.classList.remove('fs-open');
    }
    function activity() {
        if (!near) { root.classList.remove('fs-on'); if (!coarse) { root.classList.remove('fs-open'); } }
        clearTimeout(idleTimer);
        idleTimer = setTimeout(show, IDLE_MS);
    }

    ['scroll', 'keydown', 'touchstart', 'wheel'].forEach(function (ev) {
        window.addEventListener(ev, activity, { passive: true });
    });

    // Il mouse: ogni movimento reale e «lontano» è attività; avvicinandosi al widget lo si apre e lo si tiene visibile.
    // I mousemove sintetici (stesse coordinate, o spostamenti minimi dopo scroll/layout) non contano.
    var lastX = null, lastY = null, travelled = 0;
    window.addEventListener('mousemove', function (e) {
        if (lastX === null) { lastX = e.clientX; lastY = e.clientY; return; }
        travelled += Math.abs(e.clientX - lastX) + Math.abs(e.clientY - lastY);
        lastX = e.clientX; lastY = e.clientY;
        if (travelled < 24) { return; }
        travelled = 0;
        var r = root.getBoundingClientRect();
        var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
        var d = Math.hypot(e.clientX - cx, Math.max(0, Math.abs(e.clientY - cy) - r.height / 2));
        var wasNear = near;
        near = root.classList.contains('fs-on') && d < 130;
        if (near) {
            clearTimeout(idleTimer);
            show();
            root.classList.add('fs-open');
        } else {
            if (wasNear) { root.classList.remove('fs-open'); }
            activity();
        }
    }, { passive: true });

    root.addEventListener('mouseleave', function () { near = false; root.classList.remove('fs-open'); idleTimer = setTimeout(show, IDLE_MS); });

    activity();
})();
