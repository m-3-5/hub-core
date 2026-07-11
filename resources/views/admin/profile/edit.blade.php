@extends('layouts.admin')

@section('title', 'Profilo — '.$tenant->name)

@section('content')
<style>
    .profile-wrap { display: grid; grid-template-columns: 1fr 320px; gap: 24px; align-items: start; }
    @media (max-width: 900px) { .profile-wrap { grid-template-columns: 1fr; } }

    .profile-header {
        display: flex; align-items: center; gap: 16px;
        margin-bottom: 24px;
    }
    .profile-avatar {
        width: 64px; height: 64px; border-radius: 20px;
        background: linear-gradient(145deg, var(--pv-color, #6366f1), color-mix(in srgb, var(--pv-color, #6366f1) 60%, #1e1b4b));
        display: grid; place-items: center; overflow: hidden;
        box-shadow: 0 10px 24px rgba(15,23,42,.15); flex-shrink: 0;
    }
    .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .profile-avatar span { color: #fff; font-size: 1.6rem; font-weight: 800; }
    .profile-header h1 { margin: 0 0 4px; font-size: 1.5rem; }
    .profile-header p { margin: 0; color: #666; font-size: .92rem; }

    .pcard { background: #fff; border-radius: 20px; padding: 26px 24px; box-shadow: 0 2px 16px rgba(15,23,42,.06); margin-bottom: 20px; }
    .pcard-head { display: flex; align-items: center; gap: 10px; margin-bottom: 18px; }
    .pcard-icon { font-size: 1.3rem; }
    .pcard-head h2 { margin: 0; font-size: 1.05rem; }
    .pcard-head p { margin: 2px 0 0; color: #888; font-size: .82rem; }

    .pfield { margin-bottom: 16px; }
    .pfield label { display: block; font-weight: 600; font-size: .85rem; margin-bottom: 6px; }
    .pfield input[type=text], .pfield input[type=tel], .pfield input[type=url] {
        width: 100%; padding: 11px 12px; border: 1px solid #e2e8f0; border-radius: 10px; font-family: inherit; font-size: .92rem;
    }
    .pfield input:focus { outline: 2px solid color-mix(in srgb, var(--pv-color, #6366f1) 50%, transparent); outline-offset: 1px; border-color: transparent; }
    .prow { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    @media (max-width: 560px) { .prow { grid-template-columns: 1fr; } }

    .color-row { display: flex; align-items: center; gap: 12px; }
    .color-row input[type=color] { width: 52px; height: 44px; border: 0; border-radius: 12px; padding: 0; cursor: pointer; }

    .logo-row { display: flex; align-items: center; gap: 16px; }
    .logo-preview { width: 64px; height: 64px; border-radius: 14px; object-fit: cover; background: #f1f5f9; flex-shrink: 0; }
    .logo-placeholder { width: 64px; height: 64px; border-radius: 14px; background: #f1f5f9; display: grid; place-items: center; font-size: 1.4rem; color: #94a3b8; flex-shrink: 0; }

    .font-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
    .font-option {
        border: 2px solid #e2e8f0; border-radius: 14px; padding: 14px;
        cursor: pointer; transition: border-color .15s ease;
    }
    .font-option:has(input:checked) { border-color: var(--pv-color, #6366f1); background: color-mix(in srgb, var(--pv-color, #6366f1) 6%, #fff); }
    .font-option input { margin-right: 8px; }
    .font-option strong { font-size: 1rem; }
    .font-option p { margin: 4px 0 0; font-size: .78rem; color: #888; line-height: 1.4; }

    .psubmit {
        width: 100%; padding: 13px; border: 0; border-radius: 12px;
        background: var(--pv-color, #6366f1); color: #fff; font-weight: 700; font-size: .95rem; cursor: pointer;
    }

    .preview-sticky { position: sticky; top: 20px; }
    .preview-card {
        border-radius: 22px; overflow: hidden; box-shadow: 0 20px 50px rgba(15,23,42,.15);
    }
    .preview-hero {
        background: linear-gradient(145deg, var(--pv-color, #6366f1), color-mix(in srgb, var(--pv-color, #6366f1) 55%, #1e1b4b));
        padding: 32px 22px 26px; color: #fff; text-align: center;
    }
    .preview-hero .plogo { width: 56px; height: 56px; border-radius: 14px; margin: 0 auto 14px; background: rgba(255,255,255,.18); display: grid; place-items: center; overflow: hidden; }
    .preview-hero .plogo img { width: 100%; height: 100%; object-fit: cover; }
    .preview-hero h3 { font-family: var(--pv-display); margin: 0 0 6px; font-size: 1.5rem; }
    .preview-hero p { font-family: var(--pv-body); margin: 0; opacity: .9; font-size: .85rem; }
    .preview-body { background: #fff; padding: 20px; }
    .preview-body .pbtn {
        display: block; text-align: center; padding: 11px; border-radius: 10px;
        background: var(--pv-color, #6366f1); color: #fff; font-family: var(--pv-body); font-weight: 600; font-size: .88rem; margin-bottom: 10px;
    }
    .preview-body p { font-family: var(--pv-body); color: #667; font-size: .82rem; line-height: 1.5; margin: 0; }
    .preview-label { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #999; margin-bottom: 10px; }
</style>

@php
    $fontLink = 'https://fonts.googleapis.com/css2?'.($fontPresets[$brandFont]['google_fonts'] ?? '').'&display=swap';
@endphp
<link rel="stylesheet" href="{{ $fontLink }}" id="font-link">

<div class="profile-header" style="--pv-color: {{ $brandColor }}">
    <div class="profile-avatar">
        @if ($brandLogoUrl)
            <img src="{{ $brandLogoUrl }}" alt="">
        @else
            <span>{{ mb_substr($tenant->name, 0, 1) }}</span>
        @endif
    </div>
    <div>
        <h1>Profilo — {{ $tenant->name }}</h1>
        <p>Identità e brand di questa attività: come si presenta ai clienti su tutte le pagine pubbliche.</p>
    </div>
</div>

<form method="POST" action="{{ route('admin.profile.update', $tenant) }}" enctype="multipart/form-data" id="profile-form" style="--pv-color: {{ $brandColor }}">
    @csrf
    @method('PUT')

    <div class="profile-wrap">
        <div>
            <div class="pcard">
                <div class="pcard-head">
                    <span class="pcard-icon">🪪</span>
                    <div>
                        <h2>Identità</h2>
                        <p>I dati di base della tua attività</p>
                    </div>
                </div>
                <div class="pfield">
                    <label for="name">Nome attività</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $tenant->name) }}" required>
                </div>
                <div class="pfield">
                    <label for="address">Indirizzo</label>
                    <input type="text" name="address" id="address" value="{{ old('address', $tenant->address) }}">
                </div>
                <div class="prow">
                    <div class="pfield">
                        <label for="phone">Telefono</label>
                        <input type="tel" name="phone" id="phone" value="{{ old('phone', $tenant->phone) }}">
                    </div>
                    <div class="pfield">
                        <label for="website">Sito web</label>
                        <input type="url" name="website" id="website" value="{{ old('website', $tenant->website) }}" placeholder="https://">
                    </div>
                </div>
            </div>

            <div class="pcard">
                <div class="pcard-head">
                    <span class="pcard-icon">🎨</span>
                    <div>
                        <h2>Brand</h2>
                        <p>Colore, logo e carattere — usati su promo, servizi e tutte le pagine pubbliche</p>
                    </div>
                </div>

                <div class="pfield">
                    <label>Colore principale</label>
                    <div class="color-row">
                        <input type="color" name="brand_color" id="brand_color" value="{{ old('brand_color', $brandColor) }}">
                        <span style="font-size:.85rem;color:#888">Usato per bottoni, sfondi e i volantini generati</span>
                    </div>
                </div>

                <div class="pfield">
                    <label>Logo</label>
                    <div class="logo-row">
                        @if ($brandLogoUrl)
                            <img src="{{ $brandLogoUrl }}" alt="" class="logo-preview" id="logo-preview">
                        @else
                            <div class="logo-placeholder" id="logo-placeholder">🖼️</div>
                        @endif
                        <input type="file" name="logo" accept="image/*" id="logo-input">
                    </div>
                </div>

                <div class="pfield">
                    <label>Carattere</label>
                    <div class="font-grid">
                        @foreach ($fontPresets as $key => $preset)
                            <label class="font-option">
                                <input type="radio" name="brand_font" value="{{ $key }}" data-display="{{ $preset['display'] }}" data-body="{{ $preset['body'] }}" data-fonts="{{ $preset['google_fonts'] }}" @checked(old('brand_font', $brandFont) === $key)>
                                <strong>{{ $preset['label'] }}</strong>
                                <p>{{ $preset['description'] }}</p>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <button type="submit" class="psubmit">Salva profilo</button>
        </div>

        <div class="preview-sticky">
            <p class="preview-label">Anteprima live</p>
            <div class="preview-card" style="--pv-color: {{ $brandColor }}; --pv-display: {{ $fontPresets[$brandFont]['display'] }}; --pv-body: {{ $fontPresets[$brandFont]['body'] }}" id="preview-card">
                <div class="preview-hero">
                    <div class="plogo" id="preview-logo">
                        @if ($brandLogoUrl)
                            <img src="{{ $brandLogoUrl }}" alt="">
                        @else
                            🏠
                        @endif
                    </div>
                    <h3 id="preview-name">{{ $tenant->name }}</h3>
                    <p>Promozioni e servizi</p>
                </div>
                <div class="preview-body">
                    <span class="pbtn">Scopri l'offerta</span>
                    <p>Così apparirà il tuo brand su promo e pagine pubbliche.</p>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    const colorInput = document.getElementById('brand_color');
    const nameInput = document.getElementById('name');
    const previewCard = document.getElementById('preview-card');
    const previewName = document.getElementById('preview-name');
    const profileHeader = document.querySelector('.profile-header');
    const profileForm = document.getElementById('profile-form');
    const fontRadios = document.querySelectorAll('input[name="brand_font"]');
    const fontLink = document.getElementById('font-link');
    const logoInput = document.getElementById('logo-input');
    const previewLogo = document.getElementById('preview-logo');

    colorInput.addEventListener('input', () => {
        previewCard.style.setProperty('--pv-color', colorInput.value);
        profileHeader.style.setProperty('--pv-color', colorInput.value);
        profileForm.style.setProperty('--pv-color', colorInput.value);
    });

    nameInput.addEventListener('input', () => {
        previewName.textContent = nameInput.value || 'La tua attività';
    });

    fontRadios.forEach(radio => {
        radio.addEventListener('change', () => {
            if (!radio.checked) return;
            previewCard.style.setProperty('--pv-display', radio.dataset.display);
            previewCard.style.setProperty('--pv-body', radio.dataset.body);
            fontLink.href = 'https://fonts.googleapis.com/css2?' + radio.dataset.fonts + '&display=swap';
        });
    });

    if (logoInput) {
        logoInput.addEventListener('change', () => {
            const file = logoInput.files[0];
            if (!file) return;
            const url = URL.createObjectURL(file);
            previewLogo.innerHTML = '<img src="' + url + '" alt="">';
        });
    }
})();
</script>
@endsection
