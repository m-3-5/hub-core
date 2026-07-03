@extends('layouts.admin')

@section('title', 'Link di pagamento — '.$tenant->name)

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1 style="margin:0 0 6px">Link di pagamento</h1>
            <p style="margin:0;color:#666">Tutti i link di pagamento Stripe del salone, creati dall'hub o a mano su Stripe.</p>
        </div>
        <a href="{{ route('admin.services.index', $tenant) }}" class="btn btn-secondary">← Servizi a pagamento</a>
    </div>

    @if (session('status'))
        <p class="alert">{{ session('status') }}</p>
    @endif
    @error('stripe')
        <p class="error">{{ $message }}</p>
    @enderror

    @if (empty($paymentLinks))
        <p style="color:#666">Nessun link di pagamento trovato su Stripe.</p>
    @else
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="text-align:left;border-bottom:2px solid #eee">
                    <th style="padding:10px 8px">Prodotto</th>
                    <th style="padding:10px 8px">Prezzo</th>
                    <th style="padding:10px 8px">Origine</th>
                    <th style="padding:10px 8px">Stato</th>
                    <th style="padding:10px 8px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($paymentLinks as $link)
                    @php
                        $price = $link['line_items']['data'][0]['price'] ?? null;
                        $product = $price['product'] ?? null;
                    @endphp
                    <tr style="border-bottom:1px solid #f0f0f0">
                        <td style="padding:8px">
                            <a href="{{ $link['url'] }}" target="_blank" rel="noopener" style="color:#1a1a2e;font-weight:600;text-decoration:none">
                                {{ is_array($product) ? ($product['name'] ?? $link['id']) : $link['id'] }}
                            </a>
                        </td>
                        <td style="padding:8px">
                            @if (! empty($price['unit_amount']))
                                {{ number_format($price['unit_amount'] / 100, 2, ',', '.') }} {{ strtoupper($price['currency'] ?? '') }}
                            @else
                                —
                            @endif
                        </td>
                        <td style="padding:8px">
                            @if (in_array($link['id'], $linkedPaymentLinkIds, true))
                                <span style="color:#2e7d32">Gestito dall'hub</span>
                            @else
                                <span style="color:#666">Esterno / manuale</span>
                            @endif
                        </td>
                        <td style="padding:8px">
                            {{ ($link['active'] ?? false) ? 'Attivo' : 'Disattivato' }}
                        </td>
                        <td style="padding:8px;text-align:right">
                            @if ($link['active'] ?? false)
                                <form method="POST" action="{{ route('admin.services.payment-links.deactivate', [$tenant, $link['id']]) }}"
                                      onsubmit="return confirm('Disattivare questo link di pagamento?')" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Disattiva</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
