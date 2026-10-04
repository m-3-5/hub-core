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

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:16px;margin-bottom:16px">
            @foreach (\App\Models\ClassifiedAd::FEATURE_LABELS as $key => $label)
                <div>
                    <label for="f_{{ $key }}">{{ $label }}</label>
                    <input id="f_{{ $key }}" name="features[{{ $key }}]" type="number" min="0" style="{{ $field }}" value="{{ $feature($key) }}">
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
@endsection
