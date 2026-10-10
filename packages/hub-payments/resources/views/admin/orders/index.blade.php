@extends('layouts.admin')

@section('title', 'Ordini — '.$tenant->name)

@section('content')
@php
    $eur = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €';
    $channelLabel = ['site' => 'Sito', 'hub' => 'inm35.it'];
    $heldCents = (int) $orders->filter->isHeld()->sum('amount_cents');
    $payoutLabel = ['held' => '🛡️ Trattenuto', 'released' => '✓ Liberato', 'frozen' => '⏸ Segnalazione', 'refunded' => '↩ Rimborsato'];
@endphp
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1 style="margin:0 0 6px">Ordini</h1>
            <p style="margin:0;color:#666">Pagamenti ricevuti da carrello, preventivi e link di pagamento, divisi per dove ha comprato il cliente.</p>
        </div>
        <a class="btn btn-secondary" href="{{ route('admin.services.index', $tenant) }}">← Servizi</a>
    </div>

    <div style="display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));margin-bottom:28px">
        @foreach (['site' => 'Dal tuo sito', 'hub' => 'Da inm35.it'] as $channel => $label)
            <div class="card" style="background:#fafafa;padding:18px">
                <h2 style="margin:0 0 8px;font-size:1rem">{{ $label }}</h2>
                <div style="font-size:1.5rem;font-weight:700">{{ $eur($summary[$channel]['total_cents']) }}</div>
                <div style="color:#666;font-size:.88rem">{{ $summary[$channel]['count'] }} ordini in tutto · questo mese {{ $eur($thisMonth[$channel]['total_cents']) }} ({{ $thisMonth[$channel]['count'] }})</div>
                @if ($channel === 'hub' && $commissionActive)
                    <div style="margin-top:8px;font-size:.88rem;color:#8a6100">
                        Commissione maturata: <strong>{{ $eur($summary['hub']['commission_cents']) }}</strong>
                        · ancora da pagare: {{ $eur($summary['hub']['unpaid_cents']) }}
                        <div style="color:#888;font-size:.82rem">A carico dell'azienda: il prezzo per il cliente non cambia. Si somma a fine mese nel registro «Costi e pagamenti».</div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    @if ($heldCents > 0)
        <div style="background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:12px 14px;margin-bottom:20px;font-size:.92rem;color:#3730a3">
            🛡️ <strong>{{ $eur($heldCents) }}</strong> di pagamenti protetti sono custoditi da Hub Core: ti arrivano quando il cliente conferma, o alla data indicata se non ci sono segnalazioni (meno la commissione Stripe). <a href="{{ route('terms.economic') }}" target="_blank">Come funziona</a>.
        </div>
    @endif

    @if ($orders->isEmpty())
        <p style="color:#666">Nessun ordine pagato ancora.</p>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.92rem">
                <thead>
                    <tr style="text-align:left;border-bottom:2px solid #eee">
                        <th style="padding:8px">Data</th>
                        <th style="padding:8px">Cosa</th>
                        <th style="padding:8px">Cliente</th>
                        <th style="padding:8px">Canale</th>
                        <th style="padding:8px;text-align:right">Importo</th>
                        @if ($commissionActive)<th style="padding:8px;text-align:right">Commissione</th>@endif
                    </tr>
                </thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr style="border-bottom:1px solid #f0f0f0;vertical-align:top">
                        <td style="padding:8px;white-space:nowrap">{{ $order->paid_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                        <td style="padding:8px">
                            @foreach ($order->items as $item)
                                <div>{{ ($item['quantity'] ?? 1) > 1 ? $item['quantity'].' × ' : '' }}{{ $item['title'] ?? 'Voce' }}@if (($item['type'] ?? '') === 'quote') <span style="color:#888">(preventivo)</span>@endif</div>
                            @endforeach
                        </td>
                        <td style="padding:8px">
                            {{ $order->customer_name ?: '—' }}
                            @if ($order->customer_email)<div style="color:#666;font-size:.85rem">{{ $order->customer_email }}</div>@endif
                            @if ($order->customer_phone)<div style="color:#666;font-size:.85rem">{{ $order->customer_phone }}</div>@endif
                        </td>
                        <td style="padding:8px">{{ $channelLabel[$order->channel] ?? $order->channel }}</td>
                        <td style="padding:8px;text-align:right;white-space:nowrap">
                            {{ $eur($order->amount_cents) }}
                            @if ($order->isProtected())
                                <div style="font-size:.8rem;color:#4338ca">{{ $payoutLabel[$order->payout_status] ?? 'Protetto' }}@if ($order->isHeld() && $order->release_at) · fino al {{ $order->release_at->timezone(config('app.timezone'))->format('d/m') }}@endif</div>
                                <div style="font-size:.78rem;color:#6b7280">ricevi circa {{ $eur($order->amount_cents - $order->commission_cents - \M35\HubPayments\Support\StripeFees::estimateCents($order->amount_cents)) }}</div>
                            @endif
                        </td>
                        @if ($commissionActive)
                            <td style="padding:8px;text-align:right;white-space:nowrap">{{ $order->commission_cents ? $eur($order->commission_cents) : '—' }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
