@extends('layouts.admin')

@section('title', $service->title)

@section('content')
<div class="card">
    @if (session('status'))
        <p class="alert">{{ session('status') }}</p>
    @endif
    @error('stripe')
        <p class="error">{{ $message }}</p>
    @enderror

    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1 style="margin:0 0 6px">{{ $service->title }}</h1>
            <p style="margin:0;color:#666">{{ $service->amountEuros() }} € · Stripe Payment Link</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <a href="{{ route('admin.services.edit', [$tenant, $service]) }}" class="btn btn-secondary">Modifica</a>
            <form method="POST" action="{{ route('admin.services.refresh-payment-methods', [$tenant, $service]) }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-secondary">Rigenera link</button>
            </form>
        </div>
    </div>

    @if ($service->coverImageUrl())
        <img src="{{ $service->coverImageUrl() }}" alt="{{ $service->title }}"
             style="width:100%;max-width:420px;aspect-ratio:4/3;object-fit:cover;border-radius:12px;margin-bottom:20px;display:block">
    @else
        <div style="width:100%;max-width:420px;aspect-ratio:4/3;border-radius:12px;margin-bottom:20px;background:linear-gradient(135deg,#fdf2f8,#fff);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:#b8879e">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M3 7a2 2 0 0 1 2-2h2l1.5-2h7L17 5h2a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/>
                <circle cx="12" cy="13" r="3.5"/>
            </svg>
            <span style="font-size:.85rem">Nessuna foto</span>
        </div>
    @endif

    @if ($service->description)
        <p style="line-height:1.5">{{ $service->description }}</p>
    @endif

    <div style="background:#f6f7fb;border-radius:12px;padding:20px;margin:24px 0">
        <label style="font-size:.85rem;color:#666">Link da inviare al cliente</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">
            <input type="text" readonly value="{{ $service->payment_url }}" id="payment-url"
                   style="flex:1;min-width:200px;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:.95rem">
            <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('payment-url').value);this.textContent='Copiato!'">Copia link</button>
            @if (($tenant->settings['whatsapp'] ?? null))
                <a class="btn btn-secondary" target="_blank" rel="noopener"
                   href="https://wa.me/{{ $tenant->settings['whatsapp'] }}?text={{ rawurlencode('Ciao! Ecco il link per il servizio «'.$service->title.'»: '.$service->payment_url) }}">WhatsApp</a>
            @endif
        </div>
        <p style="margin:12px 0 0;font-size:.85rem;color:#666">Il cliente può pagare con carta o con gli altri metodi attivi sul vostro Stripe (Klarna, Scalapay, ecc.).</p>
    </div>

    @if ($service->published_to_site)
        <div style="background:#f6f7fb;border-radius:12px;padding:20px;margin:24px 0">
            <label style="font-size:.85rem;color:#666">Pubblicato sul sito</label>
            <p style="margin:8px 0 12px;font-size:.9rem;color:#666">Visibile su inm35.it e sincronizzato automaticamente su {{ $tenant->website ? preg_replace('#^https?://#', '', $tenant->website) : 'il tuo sito' }} (nessuna azione manuale necessaria).</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ route('services.public.show', [$tenant, $service]) }}">Vedi pagina pubblica</a>
                <a class="btn btn-secondary" target="_blank" rel="noopener" href="{{ route('client.services.iframe', [$tenant, $service]) }}">Apri snippet da incollare sul sito</a>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.services.publish', [$tenant, $service]) }}" style="margin-bottom:20px;display:inline">
        @csrf
        <button type="submit" class="btn btn-secondary">
            {{ $service->published_to_site ? 'Nascondi dal sito' : 'Pubblica sul sito' }}
        </button>
    </form>

    <form method="POST" action="{{ route('admin.services.destroy', [$tenant, $service]) }}"
          onsubmit="return confirm('Archiviare questo servizio e disattivare il link Stripe?')" style="display:inline;margin-left:8px">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">Elimina</button>
    </form>

    <p style="margin-top:24px"><a href="{{ route('admin.services.index', $tenant) }}">← Tutti i servizi</a></p>
</div>
@endsection
