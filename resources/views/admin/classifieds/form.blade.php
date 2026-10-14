@extends('layouts.admin')

@section('title', ($ad->exists ? 'Modifica annuncio' : 'Nuovo annuncio').' — '.$tenant->name)

@section('content')
@php
    $isEdit = $ad->exists;
    $field = 'width:100%;padding:10px;border:1px solid #ddd;border-radius:8px;font-family:inherit;font-size:1rem';
    $val = fn ($key, $default = '') => old($key, $ad->{$key} ?? $default);
    $feature = fn ($key) => old('features.'.$key, ($ad->features ?? [])[$key] ?? '');
@endphp
<div class="card">
    <h1 style="margin:0 0 16px">{{ $isEdit ? 'Modifica annuncio' : 'Nuovo annuncio' }}</h1>

    @if (session('success'))
        <div style="background:#e8f5e9;color:#1b5e20;border-radius:10px;padding:10px 14px;margin-bottom:16px">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div style="background:#ffebee;color:#c62828;border-radius:10px;padding:10px 14px;margin-bottom:16px">
            @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $isEdit ? route('admin.classifieds.update', [$tenant, $ad]) : route('admin.classifieds.store', $tenant) }}">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:16px">
            <div>
                <label for="category">Tipo di annuncio</label>
                <select id="category" name="category" style="{{ $field }}" required>
                    @foreach (\App\Models\ClassifiedAd::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected($val('category') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="zone">Zona / località</label>
                <input id="zone" name="zone" style="{{ $field }}" required maxlength="120" value="{{ $val('zone') }}" placeholder="Es. Desenzano del Garda (BS)">
            </div>
        </div>

        <div style="margin-bottom:16px">
            <label for="title">Titolo</label>
            <input id="title" name="title" style="{{ $field }}" required maxlength="140" value="{{ $val('title') }}" placeholder="Es. Bilocale con vista lago a 300 m dalla spiaggia">
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:16px">
            <div>
                <label for="price">Prezzo (€)</label>
                <input id="price" name="price" type="number" step="0.01" min="0" style="{{ $field }}" value="{{ $val('price') }}" placeholder="Vuoto = trattabile">
            </div>
            <div>
                <label for="price_unit">Tipo di prezzo</label>
                <select id="price_unit" name="price_unit" style="{{ $field }}">
                    @foreach (\App\Models\ClassifiedAd::PRICE_UNITS as $key => $label)
                        <option value="{{ $key }}" @selected((string) $val('price_unit') === (string) $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div id="protected-box" style="margin-bottom:16px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:12px 14px">
            <label style="display:flex;gap:10px;align-items:flex-start;cursor:pointer;font-weight:600">
                <input type="checkbox" name="protected_sale" value="1" @checked(old('protected_sale', $ad->protected_sale)) style="margin-top:4px;width:18px;height:18px;flex:none">
                <span>🛡️ Vendi con pagamento protetto <span style="font-weight:400;color:#4b5563;display:block;font-size:.9rem">Solo per «Oggetti in vendita» a prezzo fisso. Chi compra paga online, i soldi restano custoditi da Hub Core e li ricevi dopo la consegna (conferma o 7 giorni). Devi prima collegare i pagamenti protetti dalla pagina Annunci.</span></span>
            </label>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:16px;margin-bottom:16px">
            @foreach (\App\Models\ClassifiedAd::FEATURE_LABELS as $key => $label)
                <div data-feature="{{ $key }}">
                    <label for="f_{{ $key }}">{{ $label }}</label>
                    <input id="f_{{ $key }}" name="features[{{ $key }}]" type="number" min="0" style="{{ $field }}" value="{{ $feature($key) }}">
                </div>
            @endforeach
            @foreach (\App\Models\ClassifiedAd::TEXT_FEATURES as $key => $meta)
                <div data-feature="{{ $key }}">
                    <label for="f_{{ $key }}">{{ $meta['label'] }}</label>
                    <select id="f_{{ $key }}" name="features[{{ $key }}]" style="{{ $field }}">
                        <option value="">—</option>
                        @foreach ($meta['options'] as $optKey => $optLabel)
                            <option value="{{ $optKey }}" @selected((string) $feature($key) === (string) $optKey)>{{ $optLabel }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            @foreach (\App\Models\ClassifiedAd::INPUT_FEATURES as $key => $meta)
                <div data-feature="{{ $key }}" style="{{ $meta['type'] === 'url' ? 'grid-column:1 / -1' : '' }}">
                    <label for="f_{{ $key }}">{{ $meta['label'] }}</label>
                    <input id="f_{{ $key }}" name="features[{{ $key }}]" type="{{ $meta['type'] }}" maxlength="{{ $meta['max'] }}" style="{{ $field }}" value="{{ $feature($key) }}" placeholder="{{ $meta['placeholder'] }}">
                </div>
            @endforeach
        </div>

        <div style="margin-bottom:16px">
            <label for="description">Descrizione</label>
            <textarea id="description" name="description" rows="8" required maxlength="5000" style="{{ $field }}" placeholder="Descrivi l'immobile: servizi, distanza dal lago, periodo disponibile, regole...">{{ $val('description') }}</textarea>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:16px">
            <div>
                <label for="contact_name">Nome referente</label>
                <input id="contact_name" name="contact_name" style="{{ $field }}" maxlength="120" value="{{ $val('contact_name') }}">
            </div>
            <div>
                <label for="contact_phone">Telefono / WhatsApp</label>
                <input id="contact_phone" name="contact_phone" style="{{ $field }}" maxlength="30" value="{{ $val('contact_phone') }}" placeholder="+39 ...">
            </div>
            <div>
                <label for="contact_email">Email (non mostrata)</label>
                <input id="contact_email" name="contact_email" type="email" style="{{ $field }}" maxlength="190" value="{{ $val('contact_email') }}">
            </div>
        </div>

        <div style="margin-bottom:20px">
            <label>Foto (fino a 8, la prima è la copertina)</label>
            @if ($isEdit && $ad->images)
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin:8px 0">
                    @foreach ($ad->images as $i => $path)
                        <label style="width:110px;font-size:12px;font-weight:normal;cursor:pointer">
                            <img src="{{ $ad->imageUrls()[$i] }}" alt="" style="width:110px;height:80px;object-fit:cover;border-radius:8px;display:block">
                            <input type="checkbox" name="remove_images[]" value="{{ $path }}"> Rimuovi
                        </label>
                    @endforeach
                </div>
            @endif
            <input type="file" name="images[]" accept="image/*" multiple>
        </div>

        <div style="margin-bottom:20px">
            <label>Video (fino a 3, MP4/MOV/WebM, max 40 MB l'uno)</label>
            @if ($isEdit && $ad->videos)
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin:8px 0">
                    @foreach ($ad->videos as $i => $path)
                        <label style="font-size:12px;font-weight:normal;cursor:pointer">
                            <video src="{{ $ad->videoUrls()[$i] }}" preload="metadata" muted style="width:130px;height:90px;object-fit:cover;border-radius:8px;display:block;background:#000"></video>
                            <input type="checkbox" name="remove_videos[]" value="{{ $path }}"> Rimuovi
                        </label>
                    @endforeach
                </div>
            @endif
            <input type="file" name="videos[]" accept="video/mp4,video/quicktime,video/webm" multiple>
        </div>

        <button type="submit" class="btn">{{ $isEdit ? 'Salva modifiche' : 'Salva come bozza' }}</button>
        <a href="{{ route('admin.classifieds.index', $tenant) }}" class="btn btn-secondary" style="margin-left:8px">← Annunci</a>
    </form>

    @if ($isEdit)
        <form method="POST" action="{{ route('admin.classifieds.destroy', [$tenant, $ad]) }}" style="margin-top:24px"
              onsubmit="return confirm('Eliminare definitivamente questo annuncio?')">
            @csrf @method('DELETE')
            <button type="submit" class="btn" style="background:#c62828">Elimina annuncio</button>
        </form>
    @endif
</div>

<script>
(function () {
    var META = @json(\App\Models\ClassifiedAd::CATEGORY_META);
    var sel = document.getElementById('category');
    var unit = document.getElementById('price_unit');
    var desc = document.getElementById('description');
    var box = document.getElementById('protected-box');

    function apply(initial) {
        var m = META[sel.value] || {fields: [], units: [''], hint: ''};
        document.querySelectorAll('[data-feature]').forEach(function (w) {
            var on = m.fields.indexOf(w.getAttribute('data-feature')) !== -1;
            w.hidden = !on;
            w.querySelectorAll('input,select').forEach(function (i) { i.disabled = !on; });
        });
        var current = unit.value;
        Array.prototype.forEach.call(unit.options, function (o) {
            var ok = m.units.indexOf(o.value) !== -1 || (initial && o.value === current);
            o.hidden = !ok; o.disabled = !ok;
        });
        if (unit.selectedOptions[0] && unit.selectedOptions[0].disabled) { unit.value = ''; }
        if (box) { box.hidden = sel.value !== 'oggetti'; }
        if (desc && m.hint) { desc.placeholder = m.hint; }
    }

    sel.addEventListener('change', function () { apply(false); });
    apply(true);
})();
</script>
@endsection
