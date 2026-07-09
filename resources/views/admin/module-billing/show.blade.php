@extends('layouts.admin')

@section('title', 'Costi e pagamenti — '.$tenant->name)

@section('content')
<div class="card">
    <h1 style="margin:0 0 8px">Costi e pagamenti — {{ $tenant->name }}</h1>
    <p style="margin:0 0 24px;color:#666">Cosa è attivo, quanto costa, e registro di cosa è stato pagato. Tracciamento manuale per ora (fattura/bonifico) — verrà collegato a Stripe M 3.5 più avanti.</p>

    @if (session('status'))
        <p class="alert">{{ session('status') }}</p>
    @endif

    <div style="display:grid;gap:16px;grid-template-columns:1fr 1fr;margin-bottom:28px">
        @foreach ($pricing as $key => $module)
            @continue($key === 'iva_percent')
            <div class="card" style="background:#fafafa;padding:20px">
                <h2 style="margin:0 0 10px;font-size:1.05rem">{{ $module['label'] }}</h2>
                <table style="width:100%;font-size:.9rem;border-collapse:collapse">
                    <tr><td style="padding:4px 0;color:#666">Attivazione una tantum</td><td style="text-align:right;font-weight:600">€{{ number_format($module['activation_cents']/100,2,',','.') }}</td></tr>
                    <tr><td style="padding:4px 0;color:#666">Canone mensile</td><td style="text-align:right;font-weight:600">€{{ number_format($module['monthly_cents']/100,2,',','.') }}</td></tr>
                    <tr><td style="padding:4px 0;color:#666">Incluso al mese</td><td style="text-align:right;font-weight:600">{{ $module['included_per_month'] }}</td></tr>
                    <tr><td style="padding:4px 0;color:#666">Extra creato dallo staff M 3.5</td><td style="text-align:right;font-weight:600">€{{ number_format($module['extra_staff_cents']/100,2,',','.') }}</td></tr>
                    <tr><td style="padding:4px 0;color:#666">Extra creato dal cliente</td><td style="text-align:right;font-weight:600">€{{ number_format($module['extra_self_cents']/100,2,',','.') }}</td></tr>
                </table>
            </div>
        @endforeach
    </div>

    <div class="alert {{ $unpaidTotalCents > 0 ? 'alert-warning' : '' }}" style="margin-bottom:24px">
        <strong>Totale non pagato: €{{ number_format($unpaidTotalCents/100,2,',','.') }}</strong>
    </div>

    <div class="card" style="background:#fafafa;padding:20px;margin-bottom:24px">
        <h2 style="margin:0 0 12px;font-size:1.05rem">Aggiungi voce al registro</h2>
        <form method="POST" action="{{ route('admin.module-billing.store', $tenant) }}" style="display:grid;gap:12px;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));align-items:end">
            @csrf
            <div>
                <label for="module">Modulo</label>
                <select name="module" id="module" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">
                    <option value="servizi">Servizi</option>
                    <option value="promo">Promo</option>
                </select>
            </div>
            <div>
                <label for="charge_type">Tipo</label>
                <select name="charge_type" id="charge_type" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">
                    <option value="activation">Attivazione</option>
                    <option value="monthly">Canone mensile</option>
                    <option value="extra_item">Extra (oltre incluso)</option>
                </select>
            </div>
            <div>
                <label for="period">Periodo (es. 2026-07)</label>
                <input type="text" name="period" id="period" maxlength="7" placeholder="2026-07" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">
            </div>
            <div>
                <label for="amount">Importo (€)</label>
                <input type="number" name="amount" id="amount" step="0.01" min="0" required style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">
            </div>
            <div style="grid-column:1/-1">
                <label for="description">Descrizione (opzionale)</label>
                <input type="text" name="description" id="description" maxlength="190" style="width:100%;padding:8px;border:1px solid #ddd;border-radius:8px">
            </div>
            <label style="display:flex;align-items:center;gap:8px;font-weight:500">
                <input type="checkbox" name="paid" value="1">
                Già pagato
            </label>
            <button type="submit" class="btn" style="border:0;cursor:pointer">Aggiungi</button>
        </form>
    </div>

    @if ($charges->isEmpty())
        <p style="color:#666">Nessuna voce nel registro ancora.</p>
    @else
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="text-align:left;border-bottom:2px solid #eee">
                    <th style="padding:8px">Periodo</th>
                    <th style="padding:8px">Modulo</th>
                    <th style="padding:8px">Tipo</th>
                    <th style="padding:8px">Descrizione</th>
                    <th style="padding:8px">Importo</th>
                    <th style="padding:8px">Stato</th>
                    <th style="padding:8px"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($charges as $charge)
                    <tr style="border-bottom:1px solid #f0f0f0">
                        <td style="padding:8px">{{ $charge->period ?? '—' }}</td>
                        <td style="padding:8px">{{ ucfirst($charge->module) }}</td>
                        <td style="padding:8px">{{ match($charge->charge_type) { 'activation' => 'Attivazione', 'monthly' => 'Mensile', 'extra_item' => 'Extra', default => $charge->charge_type } }}</td>
                        <td style="padding:8px;color:#666">{{ $charge->description ?? '—' }}</td>
                        <td style="padding:8px;font-weight:600">€{{ $charge->amountEuros() }}</td>
                        <td style="padding:8px">
                            <span style="color:{{ $charge->paid ? '#2e7d32' : '#c62828' }};font-weight:600">
                                {{ $charge->paid ? '✓ Pagato' : 'Non pagato' }}
                            </span>
                        </td>
                        <td style="padding:8px;text-align:right;white-space:nowrap">
                            <form method="POST" action="{{ route('admin.module-billing.toggle-paid', [$tenant, $charge]) }}" style="display:inline">
                                @csrf
                                <button type="submit" class="btn btn-secondary">{{ $charge->paid ? 'Segna non pagato' : 'Segna pagato' }}</button>
                            </form>
                            <form method="POST" action="{{ route('admin.module-billing.destroy', [$tenant, $charge]) }}"
                                  onsubmit="return confirm('Eliminare questa voce dal registro?')" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger">Elimina</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
