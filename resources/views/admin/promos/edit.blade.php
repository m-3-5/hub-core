@extends('layouts.admin')

@section('title', 'Modifica — '.$promo->title)

@section('content')
<div class="card">
    <h1>Modifica promo</h1>
    <p style="color:#666">{{ $tenant->name }} · {{ $promo->isDraft() ? 'Bozza' : 'Pubblicata' }}</p>

    <form method="POST" action="{{ route('admin.promos.update', [$tenant, $promo]) }}" enctype="multipart/form-data" style="margin-top:24px">
        @csrf
        @method('PUT')

        <label>Foto / volantino</label>
        <div style="margin-bottom:16px">
            @if ($promo->imageUrl())
                <img src="{{ $promo->imageUrl() }}" alt="" style="max-width:200px;display:block;border-radius:8px;margin-bottom:8px">
            @endif
            <input type="file" name="image" accept="image/*" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">
            <p style="color:#666;font-size:13px;margin:6px 0 0">Lascia vuoto per mantenere quella attuale. Caricandone una nuova, le illustrazioni decorative si rigenerano da questa.</p>
            @error('image')<p class="error" style="color:#c62828;font-size:13px;margin-top:4px">{{ $message }}</p>@enderror
        </div>

        <label for="title">Titolo promo</label>
        <input type="text" name="title" id="title" value="{{ old('title', $promo->title) }}" required style="width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:8px">

        <label for="description">Descrizione</label>
        <textarea name="description" id="description" rows="4" style="width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:8px">{{ old('description', $promo->description) }}</textarea>

        <h3 style="margin:24px 0 12px">Offerte</h3>
        @php
            $offers = old('offers', $promo->offers ?? []);
            $slots = max(4, count($offers));
        @endphp
        @for ($i = 0; $i < $slots; $i++)
            @php $offer = $offers[$i] ?? ['name' => '', 'price' => '', 'detail' => '']; @endphp
            <div style="background:#f9f9f9;padding:16px;border-radius:8px;margin-bottom:12px">
                <label>Nome offerta {{ $i + 1 }}</label>
                <input type="text" name="offers[{{ $i }}][name]" value="{{ $offer['name'] ?? '' }}" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid #ddd;border-radius:6px">
                <label>Prezzo</label>
                <input type="text" name="offers[{{ $i }}][price]" value="{{ $offer['price'] ?? '' }}" placeholder="es. 10€" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid #ddd;border-radius:6px">
                <label>Dettaglio</label>
                <input type="text" name="offers[{{ $i }}][detail]" value="{{ $offer['detail'] ?? '' }}" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:6px">
            </div>
        @endfor

        <h3 style="margin:24px 0 12px">Validità</h3>
        <label style="display:flex;align-items:center;gap:8px;font-weight:normal;margin-bottom:16px">
            <input type="checkbox" name="always_active" value="1" @checked(old('always_active', $promo->always_active)) id="always_active">
            Promo sempre attiva (nessuna scadenza)
        </label>

        <div id="date-fields" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px">
            <div>
                <label for="starts_at">Inizio</label>
                <input type="datetime-local" name="starts_at" id="starts_at"
                    value="{{ old('starts_at', $promo->starts_at?->format('Y-m-d\TH:i')) }}"
                    style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px">
            </div>
            <div>
                <label for="ends_at">Scadenza</label>
                <input type="datetime-local" name="ends_at" id="ends_at"
                    value="{{ old('ends_at', $promo->ends_at?->format('Y-m-d\TH:i')) }}"
                    style="width:100%;padding:10px;border:1px solid #ddd;border-radius:8px">
            </div>
        </div>

        <button type="submit" class="btn">Salva modifiche</button>
        <a href="{{ route('admin.promos.show', [$tenant, $promo]) }}" class="btn btn-secondary" style="margin-left:8px">Annulla</a>
    </form>
</div>

<script>
document.getElementById('always_active')?.addEventListener('change', function () {
    document.getElementById('date-fields').style.display = this.checked ? 'none' : 'grid';
});
document.getElementById('always_active')?.dispatchEvent(new Event('change'));
</script>
@endsection
