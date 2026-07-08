@extends('layouts.admin')

@section('title', 'Modifica servizio — '.$tenant->name)

@section('content')
<div class="card" style="max-width:640px">
    <h1 style="margin:0 0 8px">Modifica servizio</h1>
    <p style="margin:0 0 20px;color:#666">Aggiorna titolo, descrizione, prezzo e foto. Le modifiche vengono sincronizzate su Stripe.</p>

    @error('stripe')
        <p class="error">{{ $message }}</p>
    @enderror

    @if ($service->coverImageUrl())
        <img src="{{ $service->coverImageUrl() }}" alt="{{ $service->title }}"
             style="width:100%;max-width:280px;aspect-ratio:4/3;object-fit:cover;border-radius:12px;margin-bottom:16px;display:block">
    @else
        <div style="width:100%;max-width:280px;aspect-ratio:4/3;border-radius:12px;margin-bottom:16px;background:linear-gradient(135deg,#fdf2f8,#fff);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:#b8879e">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 7a2 2 0 0 1 2-2h2l1.5-2h7L17 5h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>
                <circle cx="12" cy="13" r="3.5"/>
            </svg>
            <span style="font-size:.8rem">Nessuna foto — verrà usata quella di default</span>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.services.update', [$tenant, $service]) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <label for="title">Titolo servizio *</label>
        <input type="text" name="title" id="title" value="{{ old('title', $service->title) }}" required maxlength="120"
               style="width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:8px">

        <label for="description">Descrizione</label>
        <textarea name="description" id="description" rows="4" maxlength="2000"
                  style="width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:8px">{{ old('description', $service->description) }}</textarea>

        <label for="amount">Prezzo (€) *</label>
        <input type="number" name="amount" id="amount" value="{{ old('amount', number_format($service->amount_cents / 100, 2, '.', '')) }}" required min="0.5" step="0.01"
               style="width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:8px">

        <label for="cover_image">Foto servizio</label>
        <input type="file" name="cover_image" id="cover_image" accept="image/*" style="margin-bottom:12px">
        @if ($service->cover_image_path)
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:16px;font-weight:500">
                <input type="checkbox" name="remove_cover_image" value="1" @checked(old('remove_cover_image'))>
                Rimuovi foto attuale
            </label>
        @endif

        <label style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-weight:500">
            <input type="checkbox" name="published_to_site" value="1" @checked(old('published_to_site', $service->published_to_site))>
            Mostra sul sito (API / beautyofimage.com)
        </label>

        <button type="submit" class="btn">Salva modifiche</button>
        <a href="{{ route('admin.services.show', [$tenant, $service]) }}" class="btn btn-secondary" style="margin-left:8px">Annulla</a>
    </form>
</div>
@endsection
