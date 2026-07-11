@extends('layouts.admin')

@section('title', $promo->title)

@section('content')
@php
    $previewUrl = route('admin.promos.preview', [$tenant, $promo]);
    $statusLabel = $promo->isPublished() ? 'Pubblicata' : 'Bozza';
    $statusColor = $promo->isPublished() ? '#2e7d32' : '#e65100';
    $ownSiteTicket = $promo->isPublished() && ! $tenant->hasAutoSiteSync()
        ? $tenant->tickets()->where('context_type', 'site-integration')->where('context_id', $promo->id)->latest()->first()
        : null;
@endphp

<div class="card" style="margin-bottom:20px">
    <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:16px">
        <div>
            <h1 style="margin:0 0 8px">{{ $promo->title }}</h1>
            <p style="color:#666;margin:0">{{ $tenant->name }}</p>
            <span style="display:inline-block;margin-top:10px;padding:6px 12px;border-radius:999px;font-size:13px;font-weight:600;background:{{ $statusColor }}20;color:{{ $statusColor }}">
                {{ $statusLabel }}
            </span>
            @if ($promo->always_active)
                <span style="font-size:13px;color:#888;margin-left:8px">· Sempre attiva</span>
            @elseif ($promo->ends_at)
                <span style="font-size:13px;color:#888;margin-left:8px">· Scade {{ $promo->ends_at->format('d/m/Y H:i') }}</span>
            @endif
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px">
            @if ($promo->isDraft() && $tenant->isGuestPending())
                <form method="POST" action="{{ route('admin.promos.publish', [$tenant, $promo]) }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start">
                    @csrf
                    <div>
                        <input type="email" name="guest_email" required placeholder="la-tua-email@esempio.it"
                               style="padding:10px 12px;border:1px solid #e2e8f0;border-radius:8px;min-width:220px">
                        @error('guest_email')<div class="error" style="color:#c62828;font-size:13px;margin-top:4px">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn">Conferma email e pubblica</button>
                </form>
            @elseif ($promo->isDraft())
                <form method="POST" action="{{ route('admin.promos.publish', [$tenant, $promo]) }}">
                    @csrf
                    <button type="submit" class="btn">Pubblica su {{ $tenant->name }}</button>
                </form>
            @elseif ($tenant->hasAutoSiteSync())
                <form method="POST" action="{{ route('admin.promos.publish', [$tenant, $promo]) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary">Risincronizza WordPress</button>
                </form>
            @endif
            <a class="btn btn-secondary" href="{{ route('admin.promos.edit', [$tenant, $promo]) }}">Modifica</a>
            <a class="btn btn-secondary" href="{{ $previewUrl }}" target="_blank">Anteprima a schermo intero</a>
            <form method="POST" action="{{ route('admin.promos.destroy', [$tenant, $promo]) }}" onsubmit="return confirm('Eliminare questa promo?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Elimina</button>
            </form>
        </div>
    </div>
</div>

@if ($promo->isPublished() && ! $tenant->hasAutoSiteSync())
    <div class="card" style="margin-bottom:20px">
        <h3 style="margin:0 0 8px;font-size:1rem">🌐 Vuoi questa promo anche sul tuo sito web?</h3>
        @if ($ownSiteTicket && ! $ownSiteTicket->isAnswered())
            <p style="color:#666;font-size:14px;margin:0">Richiesta già inviata il {{ $ownSiteTicket->created_at->format('d/m/Y') }} — ti rispondiamo entro 24 ore.</p>
        @elseif ($ownSiteTicket && $ownSiteTicket->isAnswered())
            <p style="color:#2e7d32;font-size:14px;margin:0 0 10px">Risposta ricevuta: {{ $ownSiteTicket->response }}</p>
        @else
            <p style="color:#666;font-size:14px;margin:0 0 14px">Qui su {{ $tenant->name }} è già pubblicata. Se vuoi mostrarla anche sul tuo sito personale, dicci come procedere.</p>
            <div style="display:flex;flex-wrap:wrap;gap:10px">
                <form method="POST" action="{{ route('admin.tickets.store', $tenant) }}">
                    @csrf
                    <input type="hidden" name="context_type" value="site-integration">
                    <input type="hidden" name="context_id" value="{{ $promo->id }}">
                    <input type="hidden" name="context_label" value="Promo: {{ $promo->title }}">
                    <input type="hidden" name="message" value="Ho già un sito web e vorrei integrare la promo «{{ $promo->title }}». Contattatemi per procedere.">
                    <button type="submit" class="btn btn-secondary">Ho già un sito — chiedi a Max di integrarla</button>
                </form>
                <a class="btn btn-secondary" href="{{ route('admin.sitebuilder.show', $tenant) }}">Non ho un sito — crealo con Max</a>
            </div>
        @endif
    </div>
@endif

<div class="admin-grid">
    <div class="card">
        <h2 style="margin:0 0 12px;font-size:1.1rem">Anteprima cliente</h2>
        <p style="color:#666;font-size:14px;margin:0 0 16px">Così vedrà la promo su hub-core. Su WordPress comparirà nelle card dopo <strong>Pubblica</strong>.</p>
        <iframe src="{{ $previewUrl }}" title="Anteprima promo" class="preview-frame"></iframe>
    </div>

    <div class="card">
        <h2 style="margin:0 0 16px;font-size:1.1rem">Contenuto</h2>
        @if ($promo->imageUrl())
            <img src="{{ $promo->imageUrl() }}" alt="" style="max-width:100%;border-radius:8px;margin-bottom:8px">
            <details style="margin-bottom:16px">
                <summary style="cursor:pointer;color:#666;font-size:13px">Il volantino non ti convince? Scrivi a Max</summary>
                <form method="POST" action="{{ route('admin.tickets.store', $tenant) }}" style="margin-top:10px">
                    @csrf
                    <input type="hidden" name="context_type" value="promo">
                    <input type="hidden" name="context_id" value="{{ $promo->id }}">
                    <input type="hidden" name="context_label" value="Promo: {{ $promo->title }}">
                    <textarea name="message" rows="3" maxlength="2000" required placeholder="Cosa vorresti cambiare?" style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-family:inherit"></textarea>
                    <button type="submit" class="btn btn-secondary" style="margin-top:8px">Invia a Max — risposta entro 24 ore</button>
                </form>
            </details>
        @endif

        @if ($promo->flyerSvgPath())
            <div style="background:#f6f7fb;border-radius:12px;padding:16px;margin-bottom:16px">
                <h3 style="font-size:.95rem;margin:0 0 8px">🎬 Video per Instagram/WhatsApp</h3>
                @error('video')<p style="color:#c62828;font-size:13px;margin:0 0 10px">{{ $message }}</p>@enderror
                @if ($promo->variantUrl('video'))
                    <img src="{{ $promo->variantUrl('video') }}" alt="Video promo" style="max-width:280px;border-radius:8px;display:block;margin-bottom:10px">
                    <p style="color:#666;font-size:13px;margin:0 0 10px">Questo video è già quello mostrato al posto del volantino statico sulla pagina pubblica.</p>
                    <a href="{{ $promo->variantUrl('video') }}" download class="btn btn-secondary" style="margin-right:8px">Scarica</a>
                @else
                    <p style="color:#666;font-size:13px;margin:0 0 10px">Nessun video creato (il server potrebbe non supportarlo, oppure non è ancora stato generato) — la promo mostra il volantino statico.</p>
                @endif
                <form method="POST" action="{{ route('admin.promos.generate-video', [$tenant, $promo]) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-secondary">{{ $promo->variantUrl('video') ? 'Rigenera video' : 'Genera video' }}</button>
                </form>
            </div>

            <div style="background:#f6f7fb;border-radius:12px;padding:16px;margin-bottom:16px">
                <h3 style="font-size:.95rem;margin:0 0 8px">🖼️ Pack immagini per i social</h3>
                @error('image_pack')<p style="color:#c62828;font-size:13px;margin:0 0 10px">{{ $message }}</p>@enderror
                @if ($promo->image_variants['pack'] ?? null)
                    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:10px">
                        @foreach ($promo->image_variants['pack'] as $item)
                            <div style="text-align:center">
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['path']) }}" alt="{{ $item['label'] }}" style="max-width:120px;border-radius:8px;display:block;margin-bottom:4px">
                                <a href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($item['path']) }}" download style="font-size:12px">{{ $item['label'] }}</a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p style="color:#666;font-size:13px;margin:0 0 10px">Se preferisci delle immagini statiche invece del video (quadrata per il feed, verticale per le storie), crea qui il pack pronto da postare.</p>
                @endif
                <form method="POST" action="{{ route('admin.promos.generate-image-pack', [$tenant, $promo]) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="btn btn-secondary">{{ ($promo->image_variants['pack'] ?? null) ? 'Rigenera pack immagini' : 'Crea pack immagini' }}</button>
                </form>
            </div>
        @endif
        <p style="color:#444;line-height:1.6">{{ $promo->description }}</p>

        @if ($promo->offers)
            <h3 style="font-size:1rem;margin:20px 0 10px">Offerte</h3>
            <ul style="padding-left:18px;line-height:1.7">
                @foreach ($promo->offers as $offer)
                    <li>
                        <strong>{{ $offer['name'] ?? '' }}</strong>
                        @if (!empty($offer['price'])) — {{ $offer['price'] }} @endif
                        @if (!empty($offer['detail']))<br><span style="color:#666;font-size:14px">{{ $offer['detail'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($promo->isPublished())
            <p style="margin-top:20px;font-size:14px">
                <a href="{{ $promo->publicUrl() }}" target="_blank">Apri pagina pubblica →</a>
            </p>
        @else
            <p style="margin-top:20px;font-size:14px;color:#e65100;background:#fff3e0;padding:12px;border-radius:8px">
                In bozza: non visibile su {{ $tenant->website ? preg_replace('#^https?://#', '', $tenant->website) : $tenant->name }} finché non clicchi <strong>Pubblica</strong>.
            </p>
        @endif
    </div>
</div>

@if (auth()->user()->isSuperAdmin())
    <div class="card" style="margin-top:20px">
        <details>
            <summary style="cursor:pointer;font-weight:700">🔍 Dati diagnostici (solo super admin)</summary>
            <div style="margin-top:14px;font-size:13px;color:#444;line-height:1.7">
                <p><strong>Creata:</strong> {{ $promo->created_at->format('d/m/Y H:i:s') }} · <strong>Ultima modifica:</strong> {{ $promo->updated_at->format('d/m/Y H:i:s') }}</p>
                <p><strong>Slug:</strong> {{ $promo->slug }} · <strong>Immagine:</strong> {{ $promo->image_path }}</p>
                <p><strong>Origine (promo_source):</strong> {{ $promo->ai_metadata['promo_source'] ?? 'n/d' }}
                    @if (!empty($promo->ai_metadata['generated_without_ai'])) · <span style="color:#c62828">generata senza IA (fallback)</span>@endif
                    @if (!empty($promo->ai_metadata['gemini_error'])) · <span style="color:#c62828">errore Gemini: {{ $promo->ai_metadata['gemini_error'] }}</span>@endif
                </p>
                <p><strong>Hashtag:</strong> {{ implode(' ', $promo->ai_metadata['hashtags'] ?? []) ?: 'n/d' }}</p>
                <p><strong>Offers (grezzo):</strong></p>
                <pre style="background:#f6f7fb;padding:10px;border-radius:8px;overflow:auto;font-size:12px">{{ json_encode($promo->offers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                <p><strong>ai_metadata (grezzo):</strong></p>
                <pre style="background:#f6f7fb;padding:10px;border-radius:8px;overflow:auto;font-size:12px">{{ json_encode($promo->ai_metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                <p><strong>image_variants (grezzo):</strong></p>
                <pre style="background:#f6f7fb;padding:10px;border-radius:8px;overflow:auto;font-size:12px">{{ json_encode($promo->image_variants, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </details>
    </div>
@endif

<div class="card" style="margin-top:20px">
    <h3>Integrazione WordPress</h3>
    <p style="font-size:14px;color:#666">Popup footer:</p>
    <pre style="background:#1a1a2e;color:#fff;padding:12px;border-radius:8px;overflow:auto;font-size:13px">&lt;script src="{{ route('embed.script', ['tenantSlug' => $tenant->slug]) }}" defer&gt;&lt;/script&gt;</pre>
    <p style="font-size:14px;color:#666;margin-top:16px">API sync: <code>{{ route('api.promos.index', ['tenantSlug' => $tenant->slug]) }}</code></p>
</div>
@endsection
