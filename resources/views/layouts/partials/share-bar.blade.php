{{-- Condivisione con le icone dei social. Da telefono apre la finestra di condivisione del sistema.
     Parametri: $shareTitle, $shareUrl, opzionale $shareColor. --}}
@php
    $shareColor = $shareColor ?? 'var(--primary, #4f46e5)';
    $u = urlencode($shareUrl);
    $t = urlencode($shareTitle);
@endphp
<div class="share-row" aria-label="Condividi">
    <style>
        .share-row { display: flex; flex-wrap: wrap; gap: 10px; margin: 14px 0 4px; align-items: center; }
        .share-row .share-label { font-size: .85rem; font-weight: 700; color: {{ ($shareDark ?? false) ? 'rgba(255,255,255,.9)' : '#6b6b7b' }}; margin-right: 2px; }
        .share-row .sb { width: 46px; height: 46px; border-radius: 50%; border: 0; display: inline-flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; text-decoration: none; padding: 0; box-shadow: 0 2px 8px rgba(0,0,0,.12); transition: transform .12s; }
        .share-row .sb:hover { transform: translateY(-2px); }
        .share-row .sb svg { width: 24px; height: 24px; fill: currentColor; display: block; }
        .share-row .sb.wa { background: #25D366; }
        .share-row .sb.fb { background: #1877F2; }
        .share-row .sb.xx { background: #000; }
        .share-row .sb.tg { background: #229ED9; }
        .share-row .sb.nat { background: {{ $shareColor }}; }
        .share-row .sb.cp { background: #fff; color: #333; border: 1.5px solid #d9d9e3; box-shadow: none; }
        .share-row .sb.ok { background: #16a34a; color: #fff; border-color: #16a34a; }
    </style>
    <span class="share-label">Condividi</span>
    <button type="button" class="sb nat" data-native-share data-title="{{ $shareTitle }}" data-url="{{ $shareUrl }}" aria-label="Condividi" title="Condividi" hidden>
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 16.1c-.8 0-1.5.3-2 .8l-7.1-4.2a3.3 3.3 0 0 0 0-1.4l7-4.1c.6.5 1.3.8 2.1.8a3 3 0 1 0-3-3c0 .2 0 .5.1.7L8 9.8a3 3 0 1 0 0 4.4l7.2 4.2a2.8 2.8 0 0 0-.1.6 2.9 2.9 0 1 0 2.9-2.9z"/></svg>
    </button>
    <a class="sb wa" href="https://wa.me/?text={{ $t }}%20{{ $u }}" target="_blank" rel="noopener" aria-label="Condividi su WhatsApp" title="WhatsApp">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.5 3.5A11 11 0 0 0 3.2 17.1L2 22l5-1.3A11 11 0 0 0 20.5 3.5zM12 20.2a9.1 9.1 0 0 1-4.6-1.3l-.3-.2-3 .8.8-2.9-.2-.3A9.1 9.1 0 1 1 12 20.2zm5-6.8c-.3-.1-1.6-.8-1.9-.9s-.5-.1-.6.1-.7.9-.9 1.1-.3.2-.6.1a7.4 7.4 0 0 1-3.7-3.2c-.3-.5.3-.5.8-1.5.1-.2 0-.4 0-.5l-.9-2.1c-.2-.5-.5-.5-.6-.5h-.5a1 1 0 0 0-.8.4 3.1 3.1 0 0 0-1 2.3 5.4 5.4 0 0 0 1.1 2.9 12.4 12.4 0 0 0 4.8 4.2c1.8.8 2.5.8 3.4.7a2.9 2.9 0 0 0 1.9-1.3 2.4 2.4 0 0 0 .2-1.3c-.1-.1-.3-.2-.6-.3z"/></svg>
    </a>
    <a class="sb fb" href="https://www.facebook.com/sharer/sharer.php?u={{ $u }}" target="_blank" rel="noopener" aria-label="Condividi su Facebook" title="Facebook">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.5 21v-8h2.7l.4-3.2h-3.1V7.8c0-.9.3-1.5 1.6-1.5h1.7V3.4a22 22 0 0 0-2.5-.1c-2.4 0-4.1 1.5-4.1 4.2v2.3H7.4V13h2.8v8h3.3z"/></svg>
    </a>
    <a class="sb xx" href="https://twitter.com/intent/tweet?url={{ $u }}&text={{ $t }}" target="_blank" rel="noopener" aria-label="Condividi su X" title="X">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.8 3h3.1l-6.8 7.7L22 21h-6.2l-4.9-6.4L5.3 21H2.2l7.3-8.3L2 3h6.4l4.4 5.9L17.8 3zm-1.1 16.2h1.7L7.4 4.7H5.6l11.1 14.5z"/></svg>
    </a>
    <a class="sb tg" href="https://t.me/share/url?url={{ $u }}&text={{ $t }}" target="_blank" rel="noopener" aria-label="Condividi su Telegram" title="Telegram">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21.9 4.2 18.7 19.4c-.2 1-.9 1.3-1.7.8l-4.8-3.6-2.3 2.2c-.3.3-.5.5-1 .5l.3-4.9 8.9-8c.4-.3-.1-.5-.6-.2L6.5 13 1.8 11.5c-1-.3-1-1 .2-1.5L20.3 3c.9-.3 1.6.2 1.6 1.2z"/></svg>
    </a>
    <button type="button" class="sb cp" data-copy="{{ $shareUrl }}" aria-label="Copia il link" title="Copia link">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16 1H6a2 2 0 0 0-2 2v12h2V3h10V1zm3 4H10a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2zm0 16H10V7h9v14z"/></svg>
    </button>
    <script>
    (function () {
        var root = document.currentScript.parentNode;
        function legacyCopy(url) {
            var t = document.createElement('textarea');
            t.value = url; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
            document.body.appendChild(t); t.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) {}
            t.remove();
            return ok;
        }
        root.querySelectorAll('[data-copy]').forEach(function (btn) {
            var original = btn.innerHTML;
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-copy');
                var done = function () {
                    btn.classList.add('ok'); btn.title = 'Link copiato!';
                    btn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"/></svg>';
                    setTimeout(function () { btn.classList.remove('ok'); btn.title = 'Copia link'; btn.innerHTML = original; }, 2000);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(done, function () { if (legacyCopy(url)) { done(); } });
                } else if (legacyCopy(url)) { done(); }
            });
        });
        root.querySelectorAll('[data-native-share]').forEach(function (btn) {
            if (!navigator.share) { return; }
            btn.hidden = false;
            btn.addEventListener('click', function () {
                navigator.share({ title: btn.getAttribute('data-title'), url: btn.getAttribute('data-url') }).catch(function () {});
            });
        });
    })();
    </script>
</div>
