@extends('layouts.admin')

@section('title', 'Pagamenti protetti')

@section('content')
@php
    $eur = fn (?int $cents) => number_format(((int) $cents) / 100, 2, ',', '.').' €';
    $label = ['held' => '🛡️ Trattenuto', 'released' => '✓ Liberato', 'frozen' => '⏸ Bloccato', 'refunded' => '↩ Rimborsato'];
@endphp
<div class="card">
    <div class="ph-row">
        <div>
            <h1 style="margin:0 0 6px">🛡️ Pagamenti protetti</h1>
            <p style="margin:0;color:#666">{{ $eur($heldCents) }} trattenuti · {{ $frozenCount }} {{ $frozenCount === 1 ? 'ordine bloccato' : 'ordini bloccati' }}</p>
        </div>
        <div class="ph-act"><div class="chips"><a class="btn btn-secondary" href="{{ route('admin.commissions.index') }}">← Commissioni</a></div></div>
    </div>

    @if (session('status'))<p style="color:#166534;font-weight:600">{{ session('status') }}</p>@endif
    @error('order')<p class="error">{{ $message }}</p>@enderror

    @if ($orders->isEmpty())
        <p style="color:#666">Nessun pagamento protetto ancora.</p>
    @else
        <div style="overflow-x:auto">
            <table class="rtable" style="width:100%;border-collapse:collapse;font-size:.9rem">
                <thead><tr style="text-align:left;border-bottom:2px solid #eee">
                    <th style="padding:8px">#</th><th style="padding:8px">Venditore</th><th style="padding:8px">Cosa / cliente</th>
                    <th style="padding:8px;text-align:right">Importo</th><th style="padding:8px">Stato</th><th style="padding:8px">Azioni</th>
                </tr></thead>
                <tbody>
                @foreach ($orders as $order)
                    <tr style="border-bottom:1px solid #f0f0f0;vertical-align:top">
                        <td data-label="Ordine" style="padding:8px">#{{ $order->id }}</td>
                        <td data-label="Venditore" style="padding:8px">{{ $order->tenant?->name }}</td>
                        <td data-label="Cosa / cliente" style="padding:8px">
                            @foreach ($order->items as $item)<div>{{ $item['title'] ?? 'Voce' }}</div>@endforeach
                            <div style="color:#666;font-size:.82rem">{{ $order->customer_name }} {{ $order->customer_email }}</div>
                            @if ($order->problem_message)<div style="color:#b45309;font-size:.82rem;margin-top:4px">⚠ {{ $order->problem_message }}</div>@endif
                        </td>
                        <td data-label="Importo" style="padding:8px;text-align:right;white-space:nowrap">{{ $eur($order->amount_cents) }}
                            @if ($order->payout_status === 'released')<div style="font-size:.78rem;color:#6b7280">al venditore {{ $eur($order->payout_cents) }} (Stripe −{{ $eur($order->stripe_fee_cents) }})</div>@endif
                        </td>
                        <td data-label="Stato" style="padding:8px;white-space:nowrap">{{ $label[$order->payout_status] ?? $order->payout_status }}
                            @if ($order->isHeld() && $order->release_at)<div style="font-size:.78rem;color:#6b7280">fino al {{ $order->release_at->timezone(config('app.timezone'))->format('d/m H:i') }}</div>@endif
                        </td>
                        <td style="padding:8px">
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px">
                                @if (in_array($order->payout_status, ['held'], true))
                                    <form method="POST" action="{{ route('admin.protected-orders.release', $order) }}" onsubmit="return confirm('Rilasciare ora i soldi al venditore?')">@csrf<button class="btn" type="submit" style="border:0;cursor:pointer">Rilascia ora</button></form>
                                @endif
                                @if ($order->isFrozen())
                                    <form method="POST" action="{{ route('admin.protected-orders.resume', $order) }}" onsubmit="return confirm('Il problema è chiarito: riprendere il rilascio?')">@csrf<button class="btn btn-secondary" type="submit" style="border:0;cursor:pointer">Problema risolto</button></form>
                                @endif
                                @if (in_array($order->payout_status, ['held', 'frozen', 'released'], true))
                                    <form method="POST" action="{{ route('admin.protected-orders.refund', $order) }}" onsubmit="return confirm('Rimborsare TUTTO al compratore? Se i soldi erano già stati girati, vengono richiamati dal venditore.')">@csrf<button class="btn btn-secondary" type="submit" style="border:0;cursor:pointer;color:#b91c1c">Rimborsa</button></form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
