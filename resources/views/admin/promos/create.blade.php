@extends('layouts.admin')

@section('title', 'Nuova promo — '.$tenant->name)

@section('content')
<div class="card">
    <h1>Nuova promo — {{ $tenant->name }}</h1>
    <p style="color:#666;margin-bottom:24px">
        Scegli se hai già il volantino oppure se vuoi che l'IA lo crei dal tuo logo e brand.
        Gemini genera testi e <strong>immagini decorative</strong> per la landing (niente più icone SVG).
    </p>

    <form method="POST" action="{{ route('admin.promos.store', $tenant) }}" enctype="multipart/form-data" id="promo-form">
        @csrf

        <fieldset style="border:0;padding:0;margin:0 0 24px">
            <legend style="font-weight:700;margin-bottom:12px">1. Da dove partiamo?</legend>

            <label style="display:flex;gap:10px;align-items:flex-start;margin-bottom:12px;font-weight:500;cursor:pointer">
                <input type="radio" name="promo_source" value="upload" checked data-promo-source>
                <span>
                    <strong>Ho già l'immagine promo</strong><br>
                    <small style="color:#666">Carico il volantino → l'IA legge testi e crea immagini correlate per la pagina</small>
                </span>
            </label>

            <label style="display:flex;gap:10px;align-items:flex-start;font-weight:500;cursor:pointer">
                <input type="radio" name="promo_source" value="generate" data-promo-source>
                <span>
                    <strong>Crea promo con logo e brand</strong><br>
                    <small style="color:#666">Per chi non ha ancora un volantino: generiamo tutto dal tuo marchio</small>
                </span>
            </label>
        </fieldset>

        <div id="panel-upload" style="margin-bottom:24px">
            <label for="image">Immagine promo / volantino</label>
            <input type="file" name="image" id="image" accept="image/*">
        </div>

        <div id="panel-generate" style="display:none;margin-bottom:24px">
            <fieldset style="border:1px solid #eee;border-radius:12px;padding:16px;margin:0 0 16px">
                <legend style="font-weight:700;padding:0 8px">Logo e brand</legend>

                @if ($hasBrandLogo)
                    <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px;font-weight:500">
                        <input type="radio" name="brand_mode" value="tenant" checked data-brand-mode>
                        Usa logo salvato
                        @if ($brandLogoUrl)
                            <img src="{{ $brandLogoUrl }}" alt="Logo" style="height:36px;border-radius:6px;margin-left:8px">
                        @endif
                    </label>
                @endif

                <label style="display:flex;gap:8px;align-items:center;margin-bottom:10px;font-weight:500">
                    <input type="radio" name="brand_mode" value="once" @checked(! $hasBrandLogo) data-brand-mode>
                    Carica logo solo per questa promo
                </label>

                <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px;font-weight:500">
                    <input type="radio" name="brand_mode" value="save" data-brand-mode>
                    Carica logo e <strong>ricorda</strong> per le prossime promo
                </label>

                <div id="logo-upload-wrap" style="{{ $hasBrandLogo ? 'display:none' : '' }}">
                    <label for="logo">File logo (PNG, JPG, SVG)</label>
                    <input type="file" name="logo" id="logo" accept="image/*">
                </div>
            </fieldset>

            <label for="promo_hint">Suggerimento per la promo (opzionale)</label>
            <textarea name="promo_hint" id="promo_hint" rows="2" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;margin-bottom:8px" placeholder="Es. Promo primavera: piega + trattamento corpo a prezzo speciale"></textarea>
        </div>

        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-bottom:12px">
            <input type="checkbox" name="always_active" value="1" checked>
            Promo sempre attiva (popup + pagina senza scadenza)
        </label>

        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-bottom:24px">
            <input type="checkbox" name="skip_ai" value="1">
            Crea senza IA (solo immagine, testi predefiniti — niente immagini decorative)
        </label>

        @error('image')
            <p class="error" style="color:#c62828;margin-bottom:16px">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn">Crea bozza e anteprima</button>
        <a href="{{ route('app.home', $tenant) }}" class="btn btn-secondary" style="margin-left:8px">← Home</a>
    </form>
</div>

<script>
(function () {
    const uploadPanel = document.getElementById('panel-upload');
    const generatePanel = document.getElementById('panel-generate');
    const imageInput = document.getElementById('image');
    const logoWrap = document.getElementById('logo-upload-wrap');
    const sourceRadios = document.querySelectorAll('[data-promo-source]');
    const brandRadios = document.querySelectorAll('[data-brand-mode]');

    function syncSource() {
        const generate = document.querySelector('[data-promo-source]:checked')?.value === 'generate';
        uploadPanel.style.display = generate ? 'none' : 'block';
        generatePanel.style.display = generate ? 'block' : 'none';
        imageInput.required = !generate;
        syncBrandMode();
    }

    function syncBrandMode() {
        const generate = document.querySelector('[data-promo-source]:checked')?.value === 'generate';
        if (!generate) return;
        const mode = document.querySelector('[data-brand-mode]:checked')?.value;
        const needsLogo = mode === 'once' || mode === 'save';
        logoWrap.style.display = needsLogo ? 'block' : 'none';
        document.getElementById('logo').required = needsLogo;
    }

    sourceRadios.forEach(r => r.addEventListener('change', syncSource));
    brandRadios.forEach(r => r.addEventListener('change', syncBrandMode));
    syncSource();
})();
</script>
@endsection
