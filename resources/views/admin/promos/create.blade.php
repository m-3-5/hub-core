@extends('layouts.admin')

@section('title', 'Nuova promo — '.$tenant->name)

@section('content')
<div class="card">
    <h1>Nuova promo — {{ $tenant->name }}</h1>

    @if ($promoQuota['remaining'] <= 0)
        <p class="ui-note">Promo del mese finite. @include('layouts.partials.costs-link')</p>
    @endif

    <form method="POST" action="{{ route('admin.promos.store', $tenant) }}" enctype="multipart/form-data" id="promo-form">
        @csrf

        <fieldset style="border:0;padding:0;margin:0 0 24px">
            <legend style="font-weight:700;margin-bottom:12px">1. Tipo di promo</legend>

            <label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:12px;font-weight:500;cursor:pointer">
                <input type="radio" name="visual_tier" value="base" checked data-visual-tier>
                <span>
                    <strong>Promo base</strong><br>
                    <small style="color:#666">Carica il volantino</small>
                </span>
            </label>

            <label style="display:flex;gap:10px;align-items:flex-start;font-weight:500;cursor:pointer;opacity:.85">
                <input type="radio" name="visual_tier" value="ai_flyer" data-visual-tier>
                <span>
                    <strong>Volantino fatto dall'IA</strong><br>
                    <small style="color:#666">Presto</small>
                </span>
            </label>
        </fieldset>

        <fieldset style="border:0;padding:0;margin:0 0 24px" id="panel-base">
            <legend style="font-weight:700;margin-bottom:12px">2. Immagine volantino</legend>
            <input type="hidden" name="promo_source" value="upload" id="promo_source">
            <label for="image">Carica il volantino / immagine promo</label>
            <input type="file" name="image" id="image" accept="image/*" required>
        </fieldset>

        <div id="panel-ai-flyer" style="display:none;margin-bottom:24px;padding:16px;background:#fff7ed;border-radius:12px;border:1px solid #fed7aa;color:#9a3412">
            Arriva presto: per ora scegli <strong>Promo base</strong>.
        </div>

        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-bottom:12px">
            <input type="checkbox" name="always_active" value="1" checked id="always_active">
            Promo sempre attiva (senza data di scadenza)
        </label>

        <div id="date-fields" style="display:none;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
            <div>
                <label for="starts_at">Inizio</label>
                <input type="datetime-local" name="starts_at" id="starts_at"
                    style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px">
            </div>
            <div>
                <label for="ends_at">Scadenza</label>
                <input type="datetime-local" name="ends_at" id="ends_at"
                    style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px">
            </div>
        </div>

        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-bottom:24px">
            <input type="checkbox" name="skip_ai" value="1">
            Crea senza IA sui testi (solo immagine + testi predefiniti)
        </label>

        @error('visual_tier')<p class="error" style="color:#c62828;margin-bottom:12px">{{ $message }}</p>@enderror
        @error('promo_source')<p class="error" style="color:#c62828;margin-bottom:12px">{{ $message }}</p>@enderror
        @error('image')<p class="error" style="color:#c62828;margin-bottom:12px">{{ $message }}</p>@enderror
        @error('ends_at')<p class="error" style="color:#c62828;margin-bottom:12px">{{ $message }}</p>@enderror

        <button type="submit" class="btn" id="submit-btn">Crea bozza e anteprima</button>
        <a href="{{ route('admin.promos.index', $tenant) }}" class="btn btn-secondary" style="margin-left:8px">← Tutte le promo</a>
    </form>
</div>

<script>
(function () {
    const basePanel = document.getElementById('panel-base');
    const aiPanel = document.getElementById('panel-ai-flyer');
    const imageInput = document.getElementById('image');
    const submitBtn = document.getElementById('submit-btn');
    const tiers = document.querySelectorAll('[data-visual-tier]');

    function sync() {
        const ai = document.querySelector('[data-visual-tier]:checked')?.value === 'ai_flyer';
        basePanel.style.display = ai ? 'none' : 'block';
        aiPanel.style.display = ai ? 'block' : 'none';
        imageInput.required = !ai;
        submitBtn.disabled = ai;
        submitBtn.style.opacity = ai ? '0.5' : '1';
    }

    tiers.forEach(r => r.addEventListener('change', sync));
    sync();

    const alwaysActive = document.getElementById('always_active');
    const dateFields = document.getElementById('date-fields');
    alwaysActive?.addEventListener('change', function () {
        dateFields.style.display = this.checked ? 'none' : 'grid';
    });

    const form = document.getElementById('promo-form');
    form?.addEventListener('submit', function () {
        if (submitBtn.disabled) return;
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.6';
        submitBtn.textContent = 'Creazione in corso… (può richiedere qualche secondo)';
    });
})();
</script>
@endsection
