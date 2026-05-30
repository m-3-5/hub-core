@extends('layouts.admin')

@section('title', 'Nuova promo — '.$tenant->name)

@section('content')
<div class="card">
    <h1>Nuova promo — {{ $tenant->name }}</h1>
    <p style="color:#666">Carica il volantino. Gemini genera i testi; la promo resta in <strong>bozza</strong> finché non la pubblichi.</p>

    <form method="POST" action="{{ route('admin.promos.store', $tenant) }}" enctype="multipart/form-data" style="margin-top:24px">
        @csrf
        <label for="image">Immagine promo (JPG, PNG, WebP)</label>
        <input type="file" name="image" id="image" accept="image/*" required>

        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-bottom:12px">
            <input type="checkbox" name="always_active" value="1" checked>
            Promo sempre attiva (popup + pagina senza scadenza)
        </label>

        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-bottom:24px">
            <input type="checkbox" name="skip_ai" value="1">
            Crea senza IA (solo upload immagine, testi predefiniti)
        </label>

        @error('image')
            <p class="error" style="color:#c62828;margin-bottom:16px">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn">Crea bozza e anteprima</button>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary" style="margin-left:8px">Annulla</a>
    </form>
</div>
@endsection
