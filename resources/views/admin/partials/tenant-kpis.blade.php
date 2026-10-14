{{-- Indicatori «a colpo d'occhio» di un'attività. Richiede $tenant e $s (App\Support\TenantOverview::summaries). --}}
@php
    $money = fn (int $c) => \App\Support\TenantOverview::money($c);
    $isPrivate = ($tenant->type ?: 'azienda') === 'privato';
@endphp
<div class="kpis">
    <a class="kpi {{ $s['promos_active'] > 0 ? 'good' : '' }}" href="{{ route('admin.promos.index', $tenant) }}">
        <span class="i">✨</span><span class="n">{{ $s['promos_active'] }}</span>
        <span class="l">Promo attive{{ $s['promos_draft'] > 0 ? ' · '.$s['promos_draft'].' in bozza' : '' }}</span>
    </a>

    @unless ($isPrivate)
        <a class="kpi {{ $s['services'] > 0 ? 'good' : '' }}" href="{{ route('admin.services.index', $tenant) }}">
            <span class="i">💆</span><span class="n">{{ $s['services'] }}</span><span class="l">Servizi</span>
        </a>
        <a class="kpi {{ $s['products'] > 0 ? 'good' : '' }}" href="{{ route('admin.products.index', $tenant) }}">
            <span class="i">🛍️</span><span class="n">{{ $s['products'] }}</span><span class="l">Prodotti</span>
        </a>
    @endunless

    @if ($isPrivate || $s['ads_published'] > 0 || $s['ads_sold'] > 0)
        <a class="kpi {{ $s['ads_published'] > 0 ? 'good' : '' }}" href="{{ route('admin.classifieds.index', $tenant) }}">
            <span class="i">🏷️</span><span class="n">{{ $s['ads_published'] }}</span>
            <span class="l">Annunci online{{ $s['ads_sold'] > 0 ? ' · '.$s['ads_sold'].' venduti' : '' }}</span>
        </a>
    @endif

    @if (! $isPrivate || $s['orders_count'] > 0)
        <a class="kpi" href="{{ route('admin.orders.index', $tenant) }}">
            <span class="i">🧾</span><span class="n">{{ $s['orders_count'] }}</span>
            <span class="l">Ordini pagati{{ $s['orders_total_cents'] > 0 ? ' · '.$money($s['orders_total_cents']) : '' }}</span>
        </a>
    @endif

    @if ($s['held_cents'] > 0)
        <a class="kpi" href="{{ route('admin.orders.index', $tenant) }}">
            <span class="i">🛡️</span><span class="n">{{ $money($s['held_cents']) }}</span><span class="l">Trattenuti fino alla consegna</span>
        </a>
    @endif

    @if ($s['frozen_count'] > 0)
        <a class="kpi alert" href="{{ route('admin.orders.index', $tenant) }}">
            <span class="i">⏸</span><span class="n">{{ $s['frozen_count'] }}</span><span class="l">{{ $s['frozen_count'] === 1 ? 'Pagamento bloccato' : 'Pagamenti bloccati' }} da un problema</span>
        </a>
    @endif

    <a class="kpi {{ $s['messages_new'] > 0 ? 'alert' : '' }}" href="{{ route('admin.customer-tickets.index', $tenant) }}">
        <span class="i">📨</span><span class="n">{{ $s['messages_new'] }}</span><span class="l">{{ $s['messages_new'] === 1 ? 'Messaggio nuovo' : 'Messaggi nuovi' }}</span>
    </a>
</div>
