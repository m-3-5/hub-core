@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
@php
    $typeLabel = ['azienda' => 'Azienda', 'privato' => 'Privato', 'ente' => 'Ente'];
    $logos = app(\App\Services\TenantBrandManager::class);
@endphp
<div class="card">
    <h1 style="margin:0 0 4px">Clienti</h1>
    <p style="color:#666;margin:0">Tocca un cliente per vedere tutto il suo lavoro: promo, servizi, prodotti, annunci, ordini e messaggi.</p>
    <div class="tiles">
        <a class="tile" href="{{ route('admin.tickets.index') }}"><span class="ti">🎫</span>Ticket clienti
            @if ($openTicketsCount > 0)<span class="tb">{{ $openTicketsCount }}</span>@endif
        </a>
        <a class="tile" href="{{ route('admin.activity.index') }}"><span class="ti">📊</span>Attività</a>
        <a class="tile" href="{{ route('admin.feedback.index') }}"><span class="ti">💬</span>Feedback</a>
        <a class="tile" href="{{ route('admin.commissions.index') }}"><span class="ti">💶</span>Commissioni</a>
        <a class="tile" href="{{ route('admin.protected-orders.index') }}"><span class="ti">🛡️</span>Pagamenti protetti</a>
        <a class="tile" href="{{ route('admin.leads.index') }}"><span class="ti">📩</span>Richieste sito</a>
        <a class="tile" href="{{ route('pricing.show') }}" target="_blank"><span class="ti">🏷️</span>Listino prezzi</a>
    </div>

    <div class="section-title"><h2>I tuoi clienti ({{ $tenants->count() }})</h2></div>

    @if ($tenants->isEmpty())
        <p class="empty-note">Nessun cliente ancora.</p>
    @else
        <div class="cgrid" style="margin-top:0">
            @foreach ($tenants as $t)
                @php
                    $s = $summaries[$t->id];
                    $logo = $logos->logoUrl($t);
                @endphp
                <a class="ccard" href="{{ route('admin.tenants.show', $t) }}">
                    <div class="head">
                        <span class="avatar" style="--av: {{ $t->primary_color ?: '#6366f1' }}">
                            @if ($logo)<img src="{{ $logo }}" alt="">@else{{ mb_strtoupper(mb_substr($t->name, 0, 1)) }}@endif
                        </span>
                        <div style="min-width:0">
                            <b>{{ $t->name }}</b>
                            <small>{{ $typeLabel[$t->type ?: 'azienda'] ?? 'Azienda' }}@if ($t->website) · {{ preg_replace('~^https?://~', '', $t->website) }}@endif</small>
                        </div>
                        <span class="go">›</span>
                    </div>

                    <div style="display:flex;gap:6px;flex-wrap:wrap">
                        <span class="chip {{ $s['badge']['tone'] }}">{{ $s['badge']['label'] }}</span>
                        @if ($s['connect'] === 'ready')<span class="chip ok">🛡️ Pagamenti protetti</span>@elseif ($s['connect'] === 'incomplete')<span class="chip warn">🛡️ Da completare</span>@endif
                        @if ($s['messages_new'] > 0)<span class="chip warn">📨 {{ $s['messages_new'] }} {{ $s['messages_new'] === 1 ? 'nuovo' : 'nuovi' }}</span>@endif
                        @if ($s['frozen_count'] > 0)<span class="chip warn">⏸ {{ $s['frozen_count'] }} {{ $s['frozen_count'] === 1 ? 'bloccato' : 'bloccati' }}</span>@endif
                    </div>

                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;text-align:center">
                        <div title="Promo attive"><div style="font-size:1.25rem;font-weight:800">{{ $s['promos_active'] }}</div><div style="font-size:.72rem;color:#6b7280">✨ Promo</div></div>
                        <div title="Servizi e prodotti"><div style="font-size:1.25rem;font-weight:800">{{ $s['services'] + $s['products'] }}</div><div style="font-size:.72rem;color:#6b7280">🛍️ Offerte</div></div>
                        <div title="Annunci online"><div style="font-size:1.25rem;font-weight:800">{{ $s['ads_published'] }}</div><div style="font-size:.72rem;color:#6b7280">🏷️ Annunci</div></div>
                        <div title="Ordini pagati"><div style="font-size:1.25rem;font-weight:800">{{ $s['orders_count'] }}</div><div style="font-size:.72rem;color:#6b7280">🧾 Ordini</div></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
