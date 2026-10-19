@extends('layouts.admin')

@section('title', $tenant->name.' — scheda cliente')

@section('content')
@php
    $typeLabel = ['azienda' => 'Azienda', 'privato' => 'Privato', 'ente' => 'Ente'];
    $type = $tenant->type ?: 'azienda';
    $isPrivate = $type === 'privato';
    $logo = app(\App\Services\TenantBrandManager::class)->logoUrl($tenant);
    $money = fn (int $c) => \App\Support\TenantOverview::money($c);
    $payoutLabel = ['held' => '🛡️ Trattenuto', 'released' => '✓ Liberato', 'frozen' => '⏸ Bloccato', 'refunded' => '↩ Rimborsato'];
@endphp

<div class="card">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <span class="avatar" style="--av: {{ $tenant->primary_color ?: '#6366f1' }};width:64px;height:64px;font-size:1.6rem">
            @if ($logo)<img src="{{ $logo }}" alt="">@else{{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}@endif
        </span>
        <div style="flex:1;min-width:200px">
            <h1 style="margin:0">{{ $tenant->name }}</h1>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                <span class="chip">{{ $typeLabel[$type] ?? 'Azienda' }}</span>
                <span class="chip {{ $s['badge']['tone'] }}">{{ $s['badge']['label'] }}</span>
                @if ($s['connect'] === 'ready')<span class="chip ok">🛡️ Pagamenti protetti collegati</span>
                @elseif ($s['connect'] === 'incomplete')<span class="chip warn">🛡️ Collegamento da completare</span>
                @else<span class="chip off">🛡️ Pagamenti protetti non collegati</span>@endif
            </div>
            @if ($tenant->website)<p style="margin:6px 0 0;color:#666;font-size:.9rem">{{ $tenant->website }}</p>@endif
        </div>
    </div>

    <div class="fab-bar" style="margin-top:16px">
        <a class="btn" href="{{ route('app.home', $tenant) }}">Apri l'app di {{ $tenant->name }}</a>
    </div>
    <div class="chips" style="margin-top:12px">
        <a class="btn btn-secondary" href="{{ route('admin.wizard.show', [$tenant, 'promo']) }}">＋ Nuova promo</a>
        <a class="btn btn-secondary" href="{{ route('admin.billing.show', $tenant) }}">Abbonamento</a>
        @unless ($isPrivate)<a class="btn btn-secondary" href="{{ route('admin.module-billing.show', $tenant) }}">Costi e pagamenti</a>@endunless
        <a class="btn btn-secondary" href="{{ route('admin.profile.edit', $tenant) }}">Profilo</a>
        <a class="btn btn-secondary" href="{{ route('classifieds.tenant-board', $tenant) }}" target="_blank">Bacheca pubblica ↗</a>
    </div>

    <details class="ah-other" style="margin-top:16px">
        <summary>Vetrina sul sito e sito web</summary>
        <form method="POST" action="{{ route('admin.tenants.directory', $tenant) }}" style="display:grid;gap:10px;margin-top:10px;max-width:460px">
            @csrf
            <label style="display:flex;gap:10px;align-items:center;font-weight:600"><input type="checkbox" name="show" value="1" @checked($tenant->settings['directory']['show'] ?? false)> Mostra tra i clienti sul sito (serve il sito web nel profilo)</label>
            <label style="font-weight:600">Città da mostrare <input type="text" name="city" maxlength="80" value="{{ $tenant->settings['directory']['city'] ?? '' }}" placeholder="es. Castrovillari" style="width:100%;padding:10px;border:1px solid #d9dcf0;border-radius:10px"></label>
            <label style="display:flex;gap:10px;align-items:center;font-weight:600"><input type="checkbox" name="keep_site" value="1" @checked($tenant->settings['keep_site'] ?? false)> Non rimuovere mai il suo sito creato con Max</label>
            <button type="submit" class="btn" style="width:fit-content">Salva</button>
        </form>
    </details>

    <div class="section-title"><h2>In breve</h2></div>
    @include('admin.partials.tenant-kpis', ['tenant' => $tenant, 's' => $s])

    <div class="section-title">
        <h2>✨ Promozioni</h2>
        <a href="{{ route('admin.promos.index', $tenant) }}">Vedi tutte →</a>
    </div>
    @if ($promos->isEmpty())
        <p class="empty-note">Nessuna promozione ancora.</p>
    @else
        <div class="rowcards">
            @foreach ($promos as $promo)
                <a class="rc" href="{{ route('admin.promos.show', [$tenant, $promo]) }}">
                    @if ($promo->imageUrl())<img class="im" src="{{ $promo->imageUrl() }}" alt="" loading="lazy">@else<div class="ph">✨</div>@endif
                    <div class="tx">
                        <b>{{ $promo->title }}</b>
                        <span class="chip {{ $promo->isPublished() ? ($promo->isExpired() ? 'off' : 'ok') : 'warn' }}" style="width:fit-content">
                            {{ $promo->isPublished() ? ($promo->isExpired() ? 'Scaduta' : 'Attiva') : 'Bozza' }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @unless ($isPrivate)
        <div class="section-title">
            <h2>🛍️ Servizi e prodotti</h2>
            <span style="display:flex;gap:12px"><a href="{{ route('admin.services.index', $tenant) }}">Servizi →</a><a href="{{ route('admin.products.index', $tenant) }}">Prodotti →</a></span>
        </div>
        @if ($offers->isEmpty())
            <p class="empty-note">Nessun servizio o prodotto ancora.</p>
        @else
            <div class="rowcards">
                @foreach ($offers as $offer)
                    <a class="rc" href="{{ route('admin.'.($offer->type === 'product' ? 'products' : 'services').'.show', [$tenant, $offer]) }}">
                        @if ($offer->coverImageUrl())<img class="im" src="{{ $offer->coverImageUrl() }}" alt="" loading="lazy">@else<div class="ph">{{ $offer->type === 'product' ? '🛍️' : '💆' }}</div>@endif
                        <div class="tx">
                            <b>{{ $offer->title }}</b>
                            <small>{{ $offer->type === 'product' ? 'Prodotto' : 'Servizio' }} · {{ $offer->amountEuros() }} €</small>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @endunless

    @if ($isPrivate || $ads->isNotEmpty())
        <div class="section-title">
            <h2>🏷️ Annunci</h2>
            <a href="{{ route('admin.classifieds.index', $tenant) }}">Vedi tutti →</a>
        </div>
        @if ($ads->isEmpty())
            <p class="empty-note">Nessun annuncio ancora.</p>
        @else
            <div class="rowcards">
                @foreach ($ads as $ad)
                    <a class="rc" href="{{ route('admin.classifieds.edit', [$tenant, $ad]) }}">
                        @if ($ad->coverUrl())<img class="im" src="{{ $ad->coverUrl() }}" alt="" loading="lazy">@else<div class="ph">🏷️</div>@endif
                        <div class="tx">
                            <b>{{ $ad->title }}</b>
                            <small>{{ $ad->priceLabel() }}</small>
                            <span class="chip {{ $ad->isSold() || $ad->isPublished() ? 'ok' : 'warn' }}" style="width:fit-content">{{ $ad->isSold() ? 'Venduto' : ($ad->isPublished() ? 'Online' : 'Bozza') }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    @endif

    <div class="section-title">
        <h2>🧾 Ultimi ordini</h2>
        <a href="{{ route('admin.orders.index', $tenant) }}">Vedi tutti →</a>
    </div>
    @if ($orders->isEmpty())
        <p class="empty-note">Nessun ordine pagato ancora.</p>
    @else
        <div style="display:grid;gap:10px">
            @foreach ($orders as $order)
                <a class="kpi" href="{{ route('admin.orders.index', $tenant) }}" style="flex-direction:row;align-items:center;gap:12px;flex-wrap:wrap">
                    <span class="n" style="font-size:1.1rem;min-width:84px">{{ $money($order->amount_cents) }}</span>
                    <span style="flex:1;min-width:140px">
                        <b style="font-size:.92rem">{{ collect($order->items)->pluck('title')->filter()->implode(', ') ?: 'Ordine' }}</b><br>
                        <small style="color:#6b7280">{{ $order->customer_name ?: $order->customer_email ?: '—' }} · {{ $order->paid_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</small>
                    </span>
                    @if ($order->isProtected())<span class="chip info">{{ $payoutLabel[$order->payout_status] ?? 'Protetto' }}</span>@endif
                </a>
            @endforeach
        </div>
    @endif

    <div class="section-title"><h2>👤 Chi lo usa</h2></div>
    @if ($users->isEmpty())
        <p class="empty-note">Nessun utente collegato.</p>
    @else
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @foreach ($users as $u)
                <span class="chip info" title="{{ $u->email }}">{{ $u->name }} · {{ $u->email }}</span>
            @endforeach
        </div>
    @endif

    <div class="section-title"><h2>Zona delicata</h2></div>
    <form method="POST" action="{{ route('admin.tenants.destroy', $tenant) }}"
          onsubmit="return confirm('Eliminare definitivamente {{ $tenant->name }}? Verranno cancellati anche servizi, promo, registro pagamenti e gli utenti collegati solo a questo tenant. Azione irreversibile.')">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn-danger">Elimina {{ $tenant->name }}</button>
    </form>
    <p style="margin:18px 0 0"><a href="{{ route('admin.dashboard') }}">← Tutti i clienti</a></p>
</div>
@endsection
