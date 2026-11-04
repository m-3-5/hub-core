/* Tasto «Installa l'app» di inm35.it (vedi layouts/partials/install-app.blade.php). */
(function () {
    'use strict';
    var KEY = 'hub-install-closed';
    var deferred = null;

    function installed() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
    }
    function closedRecently() {
        try { var t = parseInt(localStorage.getItem(KEY) || '0', 10); return t && Date.now() - t < 1000 * 60 * 60 * 24 * 14; } catch (e) { return false; }
    }
    function isIos() {
        var ua = navigator.userAgent || '';
        return /iphone|ipad|ipod/i.test(ua) || (/macintosh/i.test(ua) && navigator.maxTouchPoints > 1);
    }
    function boxes() { return Array.prototype.slice.call(document.querySelectorAll('[data-install]')); }

    function show(mode) {
        if (installed() || closedRecently()) { return; }
        boxes().forEach(function (box) {
            box.hidden = false;
            box.classList.toggle('is-ios', mode === 'ios');
            var ios = box.querySelector('[data-install-ios]');
            var sub = box.querySelector('[data-install-sub]');
            var go = box.querySelector('[data-install-go]');
            if (ios) { ios.hidden = mode !== 'ios'; }
            if (sub) { sub.hidden = mode === 'ios'; }
            if (go) { go.hidden = mode === 'ios'; }
        });
    }
    function hideAll() { boxes().forEach(function (box) { box.hidden = true; }); }

    // Android / Chrome / Edge: il telefono dice che si può installare
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferred = e;
        show('prompt');
    });
    window.addEventListener('appinstalled', function () { deferred = null; hideAll(); });

    document.addEventListener('click', function (e) {
        var go = e.target.closest && e.target.closest('[data-install-go]');
        if (go && deferred) {
            deferred.prompt();
            deferred.userChoice.then(function (choice) {
                if (choice && choice.outcome === 'accepted') { hideAll(); }
                deferred = null;
            });
            return;
        }
        if (e.target.closest && e.target.closest('[data-install-close]')) {
            try { localStorage.setItem(KEY, String(Date.now())); } catch (err) { /* niente */ }
            hideAll();
        }
    });

    // iPhone / iPad: niente richiesta automatica, si spiega come fare a mano
    document.addEventListener('DOMContentLoaded', function () {
        if (deferred) { show('prompt'); } else if (isIos() && !installed()) { show('ios'); }
    });

    window.HubInstall = { show: show };
})();
