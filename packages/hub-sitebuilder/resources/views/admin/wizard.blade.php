@extends('layouts.admin')

@section('title', 'Crea il tuo sito web — '.$tenant->name)

@section('content')
@php
    $answers = $site->answers ?? [];
    $servicesValue = implode(', ', $answers['services'] ?? []);
@endphp

@if (session('upgrade_sent'))
    <div class="card" style="max-width:640px;margin-bottom:16px;background:#e8f5e9">
        <p style="margin:0;color:#2e7d32">Richiesta inviata! Ti contattiamo per parlare del tuo sito Pro con dominio tuo.</p>
    </div>
@endif

<div class="card" style="max-width:640px;margin-bottom:16px;background:linear-gradient(135deg,#eef2ff,#fdf4ff)">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px">
        <div>
            <strong>🚀 Vuoi qualcosa di più?</strong>
            <p style="margin:4px 0 0;font-size:13px;color:#555">Passa a un sito Pro: più pagine, dominio tuo, su misura.</p>
        </div>
        <form method="POST" action="{{ route('admin.tickets.store', $tenant) }}">
            @csrf
            <input type="hidden" name="context_type" value="site-upgrade">
            <input type="hidden" name="context_label" value="Richiesta sito Pro">
            <input type="hidden" name="message" value="Vorrei passare a un sito Pro con dominio mio, partendo dal sito semplice già creato con Max.">
            <button type="submit" class="btn btn-secondary">Passa a un sito Pro</button>
        </form>
    </div>
</div>

<div class="card" style="max-width:640px">
    <h1 style="margin:0 0 8px">🪄 Crea il tuo sito web con Max</h1>
    <p style="color:#666;margin:0 0 20px">Rispondi a qualche domanda veloce — uso già nome, colori e contatti dal tuo profilo. Alla fine premi "Crea il sito".</p>

    @if ($site && $site->isPublished())
        <div style="background:#e8f5e9;border-radius:12px;padding:16px;margin-bottom:20px">
            <p style="margin:0 0 10px;color:#2e7d32;font-weight:600">Il tuo sito è pubblicato!</p>
            <a href="{{ route('site.public.show', $tenant) }}" target="_blank" class="btn btn-secondary">Vedi il sito →</a>
            <p style="margin:12px 0 0;font-size:13px;color:#666">Puoi rispondere di nuovo alle domande qui sotto per rigenerarlo quando vuoi.</p>
        </div>
    @endif

    @error('tagline')<p style="color:#c62828;font-size:13px">{{ $message }}</p>@enderror
    @error('services')<p style="color:#c62828;font-size:13px">{{ $message }}</p>@enderror

    <form method="POST" action="{{ route('admin.sitebuilder.generate', $tenant) }}" id="sb-form">
        @csrf

        <div class="sb-step" data-step="tagline">
            <p style="font-weight:600;margin-bottom:8px">Qual è la frase che vuoi in prima pagina — quella che colpisce subito chi arriva?</p>
            <input type="text" name="tagline" maxlength="200" required value="{{ old('tagline', $answers['tagline'] ?? '') }}"
                   placeholder="Es. Il tuo prossimo taglio, come lo hai sempre immaginato."
                   style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px">
            <button type="button" class="btn sb-next">Avanti</button>
        </div>

        <div class="sb-step" data-step="services" hidden>
            <p style="font-weight:600;margin-bottom:8px">Quali sono i servizi o prodotti principali da mostrare? (separati da virgola)</p>
            <textarea name="services" rows="2" maxlength="1000" required
                      placeholder="Es. Taglio e piega, Colore, Trattamenti viso"
                      style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px">{{ old('services', $servicesValue) }}</textarea>
            <button type="button" class="btn sb-next">Avanti</button>
        </div>

        <div class="sb-step" data-step="cta" hidden>
            <p style="font-weight:600;margin-bottom:8px">Cosa vuoi che faccia chi visita il sito?</p>
            <input type="text" name="cta_label" maxlength="60" value="{{ old('cta_label', $answers['cta_label'] ?? 'Contattaci') }}"
                   placeholder="Es. Chiamaci ora, Scrivici su WhatsApp, Richiedi un preventivo"
                   style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px">
            <button type="button" class="btn sb-next">Avanti</button>
        </div>

        <div class="sb-step" data-step="extra" hidden>
            <p style="font-weight:600;margin-bottom:8px">C'è altro che vuoi assolutamente far sapere? (facoltativo)</p>
            <textarea name="extra" rows="2" maxlength="1000" placeholder="Facoltativo"
                      style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:12px">{{ old('extra', $answers['extra'] ?? '') }}</textarea>
            <button type="submit" class="btn">✨ Crea il sito</button>
        </div>
    </form>
</div>

<script>
(function () {
    const steps = Array.from(document.querySelectorAll('#sb-form .sb-step'));

    function showStep(name) {
        steps.forEach(s => { s.hidden = s.dataset.step !== name; });
    }

    document.querySelectorAll('#sb-form .sb-next').forEach(btn => {
        btn.addEventListener('click', () => {
            const current = btn.closest('.sb-step');
            const idx = steps.indexOf(current);
            const next = steps[idx + 1];
            if (next) showStep(next.dataset.step);
        });
    });
})();
</script>
@endsection
