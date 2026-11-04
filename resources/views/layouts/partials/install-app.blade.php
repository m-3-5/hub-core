{{-- Tasto «Installa l'app»: compare solo se il telefono può installarla e non è già installata.
     Android/Chrome: apre la richiesta di installazione. iPhone/iPad: due righe «Condividi → Aggiungi a Home».
     Logica in public/js/hub-pwa.js, stile in public/css/hub-ui.css (.ui-install). --}}
<div class="ui-install" data-install hidden>
    <img class="ui-install__ic" src="{{ asset('pwa/icon-192.png') }}" alt="" width="48" height="48">
    <div class="ui-install__t">
        <b>Installa l'app</b>
        <small data-install-sub>Hub Core sul telefono, come un'app vera</small>
        <ol class="ui-install__ios" data-install-ios hidden>
            <li>Tocca <b>Condividi</b> <span class="ui-install__share" aria-hidden="true"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 15V3"/><path d="m7 8 5-5 5 5"/><path d="M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/></svg></span> in basso</li>
            <li>Scegli <b>«Aggiungi a Home»</b></li>
        </ol>
    </div>
    <button type="button" class="ui-install__go" data-install-go data-no-busy>Installa</button>
    <button type="button" class="ui-install__x" data-install-close aria-label="Non ora">✕</button>
</div>
<script src="{{ asset('js/hub-pwa.js') }}?v={{ @filemtime(public_path('js/hub-pwa.js')) }}" defer></script>
