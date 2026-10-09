@extends('layouts.admin')

@section('title', 'Commissioni vendite')

@section('content')
@php $eur = fn (int $cents) => number_format($cents / 100, 2, ',', '.').' €'; @endphp
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1 style="margin:0 0 6px">Commissioni sulle vendite inm35.it</h1>
            <p style="margin:0;color:#666">
                Riguarda solo gli acquisti fatti dalle pagine pubbliche di inm35.it (canale «hub»). Non cambia i prezzi pagati dal cliente:
                la commissione è a carico dell'azienda: si registra sull'ordine, si porta nel registro «Costi e pagamenti» a fine mese (<code>hub:charge-commissions</code>) e resta «da incassare» finché non la segni pagata. Non viene mai addebitata in automatico sulla carta.
                Con 0% e 0 € non si applica nulla.
            </p>
        </div>
        <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">← Dashboard</a>
    </div>

    @if (session('status'))
        <p class="alert">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <p class="error">{{ $errors->first() }}</p>
    @endif

    <h2 style="margin:0 0 10px;font-size:1.05rem">Riepilogo per azienda</h2>
    @if ($rows->isEmpty())
        <p style="color:#666">Nessun ordine pagato ancora.</p>
    @else
        <div style="overflow-x:auto;margin-bottom:28px">
            <table style="width:100%;border-collapse:collapse;font-size:.92rem">
                <thead>
                    <tr style="text-align:left;border-bottom:2px solid #eee">
                        <th style="padding:8px">Azienda</th>
                        <th style="padding:8px;text-align:right">Vendite sito</th>
                        <th style="padding:8px;text-align:right">Vendite inm35.it</th>
                        <th style="padding:8px">Commissione</th>
                        <th style="padding:8px;text-align:right">Maturata</th>
                        <th style="padding:8px;text-align:right">Da incassare</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr style="border-bottom:1px solid #f0f0f0">
                        <td style="padding:8px"><a href="{{ route('admin.services.index', $row['tenant']) }}">{{ $row['tenant']->name }}</a></td>
                        <td style="padding:8px;text-align:right">{{ $eur($row['summary']['site']['total_cents']) }} <span style="color:#888">({{ $row['summary']['site']['count'] }})</span></td>
                        <td style="padding:8px;text-align:right">{{ $eur($row['summary']['hub']['total_cents']) }} <span style="color:#888">({{ $row['summary']['hub']['count'] }})</span></td>
                        <td style="padding:8px">{{ rtrim(rtrim(number_format($row['percent'], 2, ',', ''), '0'), ',') }}% + {{ $eur($row['fixed_cents']) }}</td>
                        <td style="padding:8px;text-align:right">{{ $eur($row['summary']['hub']['commission_cents']) }}</td>
                        <td style="padding:8px;text-align:right">{{ $eur($row['summary']['hub']['unpaid_cents']) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 style="margin:0 0 10px;font-size:1.05rem">Imposta la commissione di un'azienda</h2>
    <form method="POST" id="commission-form" style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));align-items:end;max-width:720px"
          onsubmit="this.action='{{ url('admin/commissions') }}/'+this.tenant.value">
        @csrf
        @method('PUT')
        <div>
            <label for="tenant">Azienda</label>
            <select name="tenant" id="tenant" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">
                @foreach ($tenants as $t)
                    <option value="{{ $t->slug }}">{{ $t->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="percent">Percentuale (%)</label>
            <input type="number" name="percent" id="percent" value="0" min="0" max="100" step="0.01" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">
        </div>
        <div>
            <label for="fixed">Fisso per ordine (€)</label>
            <input type="number" name="fixed" id="fixed" value="0" min="0" max="1000" step="0.01" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">
        </div>
        <button type="submit" class="btn">Salva</button>
    </form>
<script>
    const current = @json($tenants->mapWithKeys(fn ($t) => [$t->slug => ['percent' => \M35\HubPayments\Support\TenantCommission::percent($t), 'fixed' => \M35\HubPayments\Support\TenantCommission::fixedCents($t) / 100]]));
    const form = document.getElementById('commission-form');
    const fill = () => { const c = current[form.tenant.value] || {percent: 0, fixed: 0}; form.percent.value = c.percent; form.fixed.value = c.fixed; };
    form.tenant.addEventListener('change', fill);
    fill();
</script>
</div>
@endsection
