@extends('layouts.admin')

@section('title', 'Nuovo servizio — '.$tenant->name)

@section('content')
<div class="card" style="max-width:640px">
    <h1 style="margin:0 0 8px">Nuovo servizio a pagamento</h1>
    <p style="margin:0 0 20px;color:#666">Genera un link Stripe con carta e Klarna (se attivo sul conto del salone).</p>

    <div class="alert" style="background:#eef2ff;color:#312e81;margin-bottom:20px">
        Servizi demo rimasti: <strong>{{ $quota['remaining'] }}</strong> su {{ $quota['included'] }}.
    </div>

    @error('quota')
        <p class="alert alert-warning">{{ $message }}</p>
    @enderror
    @error('stripe')
        <p class="error">{{ $message }}</p>
    @enderror

    <form method="POST" action="{{ route('admin.services.store', $tenant) }}">
        @csrf
        <label for="title">Titolo servizio *</label>
        <input type="text" name="title" id="title" value="{{ old('title') }}" required maxlength="120"
               placeholder="es. Piega + trattamento ricostruzione" style="width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:8px">

        <label for="description">Descrizione</label>
        <textarea name="description" id="description" rows="4" maxlength="2000" placeholder="Breve descrizione per il cliente"
                  style="width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:8px">{{ old('description') }}</textarea>

        <label for="amount">Prezzo (€) *</label>
        <input type="number" name="amount" id="amount" value="{{ old('amount') }}" required min="0.5" step="0.01"
               placeholder="45.00" style="width:100%;padding:10px;margin-bottom:16px;border:1px solid #ddd;border-radius:8px">

        <label style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-weight:500">
            <input type="checkbox" name="published_to_site" value="1" @checked(old('published_to_site'))>
            Mostra sul sito (API / beautyofimage.com — quando collegato)
        </label>

        <button type="submit" class="btn">Crea link di pagamento</button>
        <a href="{{ route('admin.services.index', $tenant) }}" class="btn btn-secondary" style="margin-left:8px">Annulla</a>
    </form>
</div>
@endsection
