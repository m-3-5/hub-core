<style>
    .max-fab {
        position: fixed;
        right: 16px;
        bottom: 24px;
        width: 56px; height: 56px;
        border-radius: 50%;
        background: linear-gradient(145deg, var(--accent), color-mix(in srgb, var(--accent) 70%, #1e1b4b));
        color: #fff;
        font-size: 1.6rem;
        border: 0;
        box-shadow: 0 10px 28px rgba(15, 23, 42, .22);
        cursor: pointer;
        z-index: 60;
        animation: max-bounce 2.6s ease-in-out infinite;
    }
    @media (max-width: 860px) {
        .max-fab { bottom: calc(64px + env(safe-area-inset-bottom) + 16px); }
    }
    @keyframes max-bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-6px); }
    }
    .max-panel {
        position: fixed;
        right: 16px;
        bottom: 92px;
        width: min(340px, calc(100vw - 32px));
        max-height: min(600px, calc(100dvh - 140px));
        background: var(--card, #fff);
        border-radius: 22px;
        box-shadow: 0 20px 60px rgba(15, 23, 42, .25);
        z-index: 61;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .max-panel[hidden] { display: none; }
    @media (max-width: 860px) {
        .max-panel { bottom: calc(64px + env(safe-area-inset-bottom) + 88px); }
    }
    .max-header {
        display: flex; align-items: center; gap: 10px;
        padding: 14px 16px;
        background: linear-gradient(135deg, var(--accent), color-mix(in srgb, var(--accent) 70%, #1e1b4b));
        color: #fff;
    }
    .max-header .max-avatar { font-size: 1.4rem; }
    .max-header strong { flex: 1; }
    .max-header button {
        background: rgba(255,255,255,.2); border: 0; color: #fff;
        width: 26px; height: 26px; border-radius: 50%; cursor: pointer; font-size: .85rem;
    }
    .max-body { padding: 16px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
    .max-step[hidden] { display: none; }
    .max-bubble {
        background: var(--bg, #f4f6fb);
        border-radius: 16px 16px 16px 4px;
        padding: 10px 14px;
        font-size: .92rem;
        line-height: 1.4;
    }
    .max-choices { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
    .max-chip {
        background: color-mix(in srgb, var(--accent) 12%, #fff);
        border: 1px solid color-mix(in srgb, var(--accent) 30%, #fff);
        color: var(--text, #0f172a);
        border-radius: 12px;
        padding: 10px 14px;
        font-weight: 600;
        font-size: .88rem;
        cursor: pointer;
        text-align: left;
    }
    .max-field { display: block; margin-top: 10px; font-size: .82rem; font-weight: 600; }
    .max-field small { font-weight: 400; color: var(--muted, #64748b); }
    .max-field input[type=text], .max-field textarea {
        width: 100%; margin-top: 4px; padding: 8px 10px;
        border: 1px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: .88rem;
    }
    .max-file { margin-top: 10px; width: 100%; font-size: .85rem; }
    .max-color-row { display: flex; align-items: center; gap: 10px; margin-top: 10px; }
    .max-color-row input[type=color] { width: 48px; height: 40px; border: 0; border-radius: 10px; padding: 0; cursor: pointer; }
    .max-next, .max-submit {
        margin-top: 12px; width: 100%; padding: 10px; border: 0; border-radius: 12px;
        background: var(--accent); color: #fff; font-weight: 700; cursor: pointer;
    }
    .max-next:disabled { opacity: .4; cursor: default; }
    .max-skip {
        margin-top: 8px; width: 100%; padding: 8px; border: 0; background: none;
        color: var(--muted, #64748b); font-size: .82rem; cursor: pointer; text-decoration: underline;
    }
    .max-radio-row { display: flex; flex-direction: column; gap: 6px; font-size: .88rem; margin-top: 8px; }
    .max-already {
        font-size: .82rem; color: var(--muted, #64748b); margin-top: 8px; font-style: italic;
    }
</style>

<button type="button" class="max-fab" id="max-fab" aria-label="Apri Max, il tuo assistente">🙂</button>

<div class="max-panel" id="max-panel" hidden>
    <div class="max-header">
        <span class="max-avatar">🙂</span>
        <strong>Max</strong>
        <button type="button" id="max-close" aria-label="Chiudi">✕</button>
    </div>
    <div class="max-body" id="max-body">
        <div class="max-step" data-step="greeting">
            <div class="max-bubble">Ciao! Cosa vuoi fare oggi?</div>
            <div class="max-choices">
                <button type="button" class="max-chip" data-next-target="what">✨ Pubblica una promo</button>
                @foreach ($ownModules as $module)
                    @continue($module['key'] === 'promo')
                    @if ($module['active'] && $module['url'])
                        <a href="{{ $module['url'] }}" class="max-chip" style="display:block;text-decoration:none">{{ $module['emoji'] }} {{ $module['label'] }}</a>
                    @else
                        <button type="button" class="max-chip" data-request-module="{{ $module['label'] }}">{{ $module['emoji'] }} Richiedi «{{ $module['label'] }}»</button>
                    @endif
                @endforeach
            </div>
        </div>

        <form method="POST" action="{{ route('admin.promos.store', $tenant) }}" enctype="multipart/form-data" id="max-promo-form">
            @csrf
            <input type="hidden" name="promo_source" value="upload" id="max-promo-source">
            <input type="hidden" name="visual_tier" value="base">

            <div class="max-step" data-step="what" hidden>
                <div class="max-bubble">Cosa vuoi promuovere?</div>
                <label class="max-field">
                    <input type="text" name="promo_hint" maxlength="500" placeholder="Es. sconto 20% su taglio e piega">
                </label>
                <button type="button" class="max-next" data-next>Avanti</button>
            </div>

            <div class="max-step" data-step="description" hidden>
                <div class="max-bubble">Vuoi aggiungere una descrizione più ampia? Se la lasci vuota ci pensa l'IA leggendo il volantino.</div>
                <label class="max-field">
                    <textarea name="manual_description" maxlength="2000" rows="2" placeholder="Facoltativo"></textarea>
                </label>
                <button type="button" class="max-next" data-next>Avanti</button>
            </div>

            <div class="max-step" data-step="image" hidden>
                <div class="max-bubble">Carica la foto o il volantino della promo — oppure, se non ne hai una, te lo creo io.</div>
                <input type="file" name="image" accept="image/*" required class="max-file" id="max-image-input">
                <button type="button" class="max-next" data-next id="max-next-image" disabled>Avanti</button>
                <button type="button" class="max-skip" id="max-generate-flyer">✨ Non ho una foto, generamela tu</button>
            </div>

            @unless ($brandHasColor)
                <div class="max-step" data-step="color" hidden>
                    <div class="max-bubble">Vuoi salvare i colori del tuo brand? Li riuseremo sempre da qui in poi.</div>
                    <div class="max-color-row">
                        <input type="color" name="brand_color" id="max-color-input" value="{{ $tenant->primary_color ?? '#6366f1' }}">
                        <span style="font-size:.82rem;color:var(--muted,#64748b)">Scegli il colore principale</span>
                    </div>
                    <button type="button" class="max-next" data-next>Salva e continua</button>
                    <button type="button" class="max-skip" data-next data-disable-field="max-color-input">Salta per ora</button>
                </div>
            @endunless

            @unless ($brandHasLogo)
                <div class="max-step" data-step="logo" hidden>
                    <div class="max-bubble">Vuoi aggiungere il logo della tua attività? Lo riuseremo per le prossime promo.</div>
                    <input type="file" name="logo" accept="image/*" class="max-file">
                    <button type="button" class="max-next" data-next>Salva e continua</button>
                    <button type="button" class="max-skip" data-next>Salta per ora</button>
                </div>
            @endunless

            <div class="max-step" data-step="active" hidden>
                <div class="max-bubble">La promo resta sempre attiva, senza scadenza?</div>
                <div class="max-radio-row">
                    <label><input type="radio" name="always_active" value="1" checked> Sì, sempre attiva</label>
                    <label><input type="radio" name="always_active" value="0"> No, la imposto dopo</label>
                </div>
                <button type="submit" class="max-submit">Crea la bozza →</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const fab = document.getElementById('max-fab');
    const panel = document.getElementById('max-panel');
    const close = document.getElementById('max-close');
    const body = document.getElementById('max-body');
    const steps = Array.from(body.querySelectorAll('.max-step'));
    const imageInput = document.getElementById('max-image-input');
    const nextImage = document.getElementById('max-next-image');
    const promoSource = document.getElementById('max-promo-source');
    const generateFlyerBtn = document.getElementById('max-generate-flyer');

    function showStep(name) {
        steps.forEach(s => { s.hidden = s.dataset.step !== name; });
    }

    function goNext(fromEl) {
        const currentStep = fromEl.closest('.max-step');
        const idx = steps.indexOf(currentStep);
        const next = steps[idx + 1];
        if (next) showStep(next.dataset.step);
    }

    fab.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        if (!panel.hidden) showStep('greeting');
    });
    close.addEventListener('click', () => { panel.hidden = true; });

    body.querySelectorAll('[data-next-target]').forEach(btn => {
        btn.addEventListener('click', () => showStep(btn.dataset.nextTarget));
    });

    body.querySelectorAll('[data-next]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (btn.dataset.disableField) {
                const field = document.getElementById(btn.dataset.disableField);
                if (field) field.disabled = true;
            }
            goNext(btn);
        });
    });

    if (generateFlyerBtn) {
        generateFlyerBtn.addEventListener('click', () => {
            promoSource.value = 'svg';
            imageInput.required = false;
            imageInput.disabled = true;
            goNext(generateFlyerBtn);
        });
    }

    if (imageInput) {
        imageInput.addEventListener('change', () => {
            nextImage.disabled = !imageInput.files.length;
        });
    }
})();
</script>
