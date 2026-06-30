@extends('layouts.admin')

@section('title', $service->title)

@section('content')
<div class="card">
    @if (session('status'))
        <p class="alert">{{ session('status') }}</p>
    @endif

    <h1 style="margin:0 0 6px">{{ $service->title }}</h1>
    <p style="margin:0 0 20px;color:#666">{{ $service->amountEuros() }} € · Stripe Payment Link</p>

    @if ($service->description)
        <p style="line-height:1.5">{{ $service->description }}</p>
    @endif

    <div style="background:#f6f7fb;border-radius:12px;padding:20px;margin:24px 0">
        <label style="font-size:.85rem;color:#666">Link da inviare al cliente</label>
        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">
            <input type="text" readonly value="{{ $service->payment_url }}" id="payment-url"
                   style="flex:1;min-width:200px;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:.95rem">
            <button type="button" class="btn" onclick="navigator.clipboard.writeText(document.getElementById('payment-url').value);this.textContent='Copiato!'">Copia link</button>
            @if ($tenant->settings['whatsapp'] ?? null)
                <a class="btn btn-secondary" target="_blank" rel="noopener"
                   href="https://wa.me/{{ $tenant->settings['whatsapp'] }}?text={{ rawurlencode('Ciao! Ecco il link per il servizio «'.$service->title.'»: '.$service->payment_url) }}">WhatsApp</a>
            @endif
        </div>
        <p style="margin:12px 0 0;font-size:.85rem;color:#666">Il cliente può pagare con carta o Klarna (se abilitato sul vostro Stripe).</p>
    </div>

    <form method="POST" action="{{ route('admin.services.publish', [$tenant, $service]) }}" style="margin-bottom:20px">
        @csrf
        <button type="submit" class="btn btn-secondary">
            {{ $service->published_to_site ? 'Nascondi dal sito' : 'Pubblica sul sito' }}
        </button>
    </form>

    <a href="{{ route('admin.services.index', $tenant) }}">← Tutti i servizi</a>
</div>
@endsection
