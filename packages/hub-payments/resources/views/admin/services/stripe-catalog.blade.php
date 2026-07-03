@extends('layouts.admin')

@section('title', 'Catalogo Stripe — '.$tenant->name)

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1 style="margin:0 0 6px">Catalogo Stripe</h1>
            <p style="margin:0;color:#666">Tutti i prodotti presenti sul conto Stripe del salone (creati dall'hub o a mano su Stripe).</p>
        </div>
        <a href="{{ route('admin.services.index', $tenant) }}" class="btn btn-secondary">← Servizi a pagamento</a>
    </div>

    @if (session('status'))
        <p class="alert">{{ session('status') }}</p>
    @endif
    @error('stripe')
        <p class="error">{{ $message }}</p>
    @enderror

    @if (empty($products))
        <p style="color:#666">Nessun prodotto trovato su Stripe.</p>
    @else
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="text-align:left;border-bottom:2px solid #eee">
                    <th style="padding:10px 8px"></th>
                    <th style="padding:10px 8px">Prodotto</th>
                    <th style="padding:10px 8px">Prezzo</th>
                    <th style="padding:10px 8px">Origine</th>
                    <th style="padding:10px 8px">Stato</th>
                    <th style="padding:10px 8px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $product)
                    <tr style="border-bottom:1px solid #f0f0f0">
                        <td style="padding:8px">
                            @if (! empty($product['images'][0]))
                                <img src="{{ $product['images'][0] }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px">
                            @endif
                        </td>
                        <td style="padding:8px">
                            <a href="{{ $dashboardBase }}/products/{{ $product['id'] }}" target="_blank" rel="noopener" style="color:#1a1a2e;font-weight:600;text-decoration:none">
                                {{ $product['name'] ?? $product['id'] }}
                            </a>
                        </td>
                        <td style="padding:8px">
                            @if (! empty($product['default_price']['unit_amount']))
                                {{ number_format($product['default_price']['unit_amount'] / 100, 2, ',', '.') }} {{ strtoupper($product['default_price']['currency'] ?? '') }}
                            @else
                                —
                            @endif
                        </td>
                        <td style="padding:8px">
                            @if (in_array($product['id'], $linkedProductIds, true))
                                <span style="color:#2e7d32">Gestito dall'hub</span>
                            @else
                                <span style="color:#666">Esterno / manuale</span>
                            @endif
                        </td>
                        <td style="padding:8px">
                            {{ ($product['active'] ?? false) ? 'Attivo' : 'Archiviato' }}
                        </td>
                        <td style="padding:8px;text-align:right">
                            @if ($product['active'] ?? false)
                                <form method="POST" action="{{ route('admin.services.stripe-catalog.archive', [$tenant, $product['id']]) }}"
                                      onsubmit="return confirm('Archiviare questo prodotto su Stripe?')" style="display:inline">
                                    @csrf
                                    <button type="submit" class="btn btn-danger">Archivia</button>
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
