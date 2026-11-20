<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Pubblica un annuncio · inm35.it</title>
    @include('layouts.partials.favicon')
    <meta name="description" content="Vendo, cerco, lavoro: pubblica gratis il tuo annuncio nel tuo paese.">
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#4f46e5">
    @include('classifieds.partials.mobile-css')
@include('layouts.partials.ui')
</head>
<body>
@include('layouts.partials.public-nav')

@if ($sent)
    <main class="m-wrap m-center">
        <div class="m-big">📩</div>
        <h1>Controlla la tua email</h1>
        <p class="m-muted">Abbiamo scritto a <b>{{ $sent === 'ok' ? 'te' : $sent }}</b>.<br>Tocca il link per confermare l'annuncio.</p>
        <p class="m-muted" style="margin-top:18px">Non arriva? Guarda nella posta indesiderata.</p>
        <a class="m-btn m-btn--ghost" href="{{ route('classifieds.board') }}" style="margin-top:24px">Vedi gli annunci</a>
    </main>
@else
<form class="m-wrap" method="POST" action="{{ route('classifieds.private.store') }}" enctype="multipart/form-data" id="ad-form">
    @csrf
    <input type="hidden" name="ref" value="{{ old('ref', $prefill['ref']) }}">
    <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="m-trap" aria-hidden="true">

    <h1>Nuovo annuncio</h1>

    @if ($errors->any())
        <div class="m-error">{{ $errors->first() }}</div>
    @endif

    <div class="m-types" role="radiogroup" aria-label="Tipo di annuncio">
        @foreach (\App\Models\ClassifiedAd::PRIVATE_TYPES as $key => $type)
            <label class="m-type">
                <input type="radio" name="tipo" value="{{ $key }}" @checked(old('tipo', $prefill['tipo']) === $key)>
                <span><i>{{ $type['icon'] }}</i>{{ $type['label'] }}</span>
            </label>
        @endforeach
    </div>

    <div class="m-photos" id="photos">
        <label class="m-add" for="images-pick"><i>📷</i><span>Foto</span></label>
    </div>
    <input type="file" id="images-pick" accept="image/*" multiple hidden>
    <input type="file" name="images[]" id="images" multiple hidden>

    <label class="m-field"><span>Titolo</span>
        <input type="text" name="title" maxlength="100" required value="{{ old('title', $prefill['titolo']) }}" placeholder="Es. Bici da bambino">
    </label>
    <label class="m-field"><span>Descrizione</span>
        <textarea name="description" rows="4" maxlength="3000" required placeholder="Due righe: com'è, dove si ritira…">{{ old('description', $prefill['testo']) }}</textarea>
    </label>
    <div class="m-row">
        <label class="m-field"><span>Prezzo €</span>
            <input type="number" name="price" min="0" step="1" inputmode="decimal" value="{{ old('price', $prefill['prezzo']) }}" placeholder="Facoltativo">
        </label>
        <label class="m-field"><span>Paese</span>
            <input type="text" name="zone" maxlength="80" required value="{{ old('zone', $prefill['zona']) }}" placeholder="Es. Senise">
        </label>
    </div>
    <label class="m-field"><span>Telefono <small>(facoltativo, si vede nell'annuncio)</small></span>
        <input type="tel" name="phone" maxlength="30" inputmode="tel" value="{{ old('phone') }}" placeholder="333 123 4567">
    </label>

    @guest
        <div class="m-row">
            <label class="m-field"><span>Il tuo nome</span>
                <input type="text" name="name" maxlength="80" required value="{{ old('name') }}" autocomplete="given-name">
            </label>
            <label class="m-field"><span>Email</span>
                <input type="email" name="email" maxlength="190" required value="{{ old('email') }}" autocomplete="email" inputmode="email">
            </label>
        </div>
        <p class="m-muted m-small">🔒 Niente password: ti mandiamo un link per confermare. L'email non si vede nell'annuncio.</p>
    @else
        <p class="m-muted m-small">Pubblichi come <b>{{ $user->name }}</b> · <a href="{{ route('classifieds.private.mine') }}">I miei annunci</a></p>
    @endguest
    <p class="m-muted m-small">✅ Gratis · lo controlliamo prima di pubblicarlo · resta online {{ \App\Models\ClassifiedAd::PRIVATE_DAYS }} giorni</p>

    <div class="m-bar">
        <button type="submit" class="m-btn" id="ad-submit">Pubblica annuncio</button>
    </div>
</form>

<script>
(() => {
    const MAX = {{ \App\Http\Controllers\PrivateAdController::MAX_IMAGES }}, SIDE = 1600;
    const pick = document.getElementById('images-pick'), real = document.getElementById('images');
    const box = document.getElementById('photos'), add = box.querySelector('.m-add');
    const form = document.getElementById('ad-form'), btn = document.getElementById('ad-submit');
    let files = [], busy = 0;

    // Foto ridotte nel telefono (lato lungo 1600 px, JPEG): si caricano in un attimo anche col 4G.
    const shrink = (file) => new Promise((done) => {
        const img = new Image(), url = URL.createObjectURL(file);
        img.onload = () => {
            const k = Math.min(1, SIDE / Math.max(img.naturalWidth, img.naturalHeight));
            const c = document.createElement('canvas');
            c.width = Math.round(img.naturalWidth * k); c.height = Math.round(img.naturalHeight * k);
            const g = c.getContext('2d'); g.fillStyle = '#fff'; g.fillRect(0, 0, c.width, c.height);
            g.drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            c.toBlob((b) => done(b ? new File([b], (file.name || 'foto').replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file), 'image/jpeg', 0.8);
        };
        img.onerror = () => { URL.revokeObjectURL(url); done(file); };
        img.src = url;
    });

    const sync = () => {
        const dt = new DataTransfer();
        files.forEach((f) => dt.items.add(f));
        real.files = dt.files;
        box.querySelectorAll('.m-thumb').forEach((n) => n.remove());
        files.forEach((f, i) => {
            const t = document.createElement('button');
            t.type = 'button'; t.className = 'm-thumb'; t.title = 'Togli';
            t.style.backgroundImage = 'url(' + URL.createObjectURL(f) + ')';
            t.innerHTML = '<span>✕</span>';
            t.onclick = () => { files.splice(i, 1); sync(); };
            box.insertBefore(t, add);
        });
        add.style.display = files.length >= MAX ? 'none' : '';
    };

    pick.addEventListener('change', async () => {
        const chosen = Array.from(pick.files).slice(0, MAX - files.length);
        pick.value = '';
        busy++; btn.disabled = true;
        for (const f of chosen) files.push(await shrink(f));
        busy--; btn.disabled = busy > 0;
        sync();
    });

    form.addEventListener('submit', (e) => {
        if (busy > 0) { e.preventDefault(); return; }
        btn.disabled = true; btn.textContent = 'Invio…';
    });
})();
</script>
@endif
</body>
</html>
