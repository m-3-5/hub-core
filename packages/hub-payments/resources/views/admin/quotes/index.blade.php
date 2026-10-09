@extends('layouts.admin')

@section('title', 'Preventivi — '.$tenant->name)

@section('content')
<style>
    .q-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; padding: 14px 0; border-top: 1px solid #eee; }
    .q-row:first-child { border-top: 0; }
    .q-badge { display: inline-block; font-size: .72rem; font-weight: 700; padding: 3px 10px; border-radius: 999px; }
    .q-badge--pending { background: #fff7e0; color: #8a6100; }
    .q-badge--paid { background: #e8f5e9; color: #2e7d32; }
    .q-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .q-btn { background: #f4f4f8; color: #333; border: 0; border-radius: 999px; padding: 7px 12px; font-size: .82rem; font-weight: 600; cursor: pointer; text-decoration: none; }
    .q-btn--danger { background: #fdecea; color: #c62828; }
    .q-form { display: grid; gap: 12px; max-width: 520px; }
    .q-form input, .q-form textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font: inherit; }
</style>

<div class="card">
    <div style="margin-bottom:20px">
        <h1 style="margin:0 0 6px">Preventivi</h1>
        <p style="margin:0;color:#666">
            Scrivi un importo a piacere e ottieni subito un link di pagamento Stripe da mandare al cliente. Ogni link si può pagare <strong>una volta sola</strong>.
            I preventivi non compaiono sul sito e non contano nei servizi inclusi.
        </p>
    </div>

    @if (session('status'))
        <p class="alert">{{ session('status') }}</p>
    @endif
    @error('stripe')
        <p class="error">{{ $message }}</p>
    @enderror

    @if (! $stripeConfigured)
        <p class="error">Prima collega Stripe: <a href="{{ route('admin.services.index', $tenant) }}">imposta le chiavi nella sezione Servizi</a>.</p>
    @else
        @unless ($webhookConfigured)
            <p class="alert" style="background:#fff7e0;color:#8a6100">
                Il webhook dei pagamenti non è ancora collegato: i preventivi pagati resterebbero "in attesa".
                <a href="{{ route('admin.services.index', $tenant) }}">Collegalo dalla sezione Servizi</a>.
            </p>
        @endunless

        <h2 style="margin:0 0 12px;font-size:1.1rem">Nuovo preventivo</h2>
        <form method="POST" action="{{ route('admin.quotes.store', $tenant) }}" class="q-form" style="margin-bottom:28px">
            @csrf
            <div>
                <label for="title">Titolo</label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" maxlength="120" required placeholder="es. Pacchetto sposa">
                @error('title')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="amount">Importo totale (€)</label>
                <input type="number" name="amount" id="amount" value="{{ old('amount') }}" step="0.01" min="0.5" max="99999" required>
                @error('amount')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="customer_name">Cliente (opzionale)</label>
                <input type="text" name="customer_name" id="customer_name" value="{{ old('customer_name') }}" maxlength="120">
            </div>
            <div>
                <label for="description">Nota (opzionale)</label>
                <textarea name="description" id="description" rows="3" maxlength="2000">{{ old('description') }}</textarea>
            </div>
            <button type="submit" class="btn">Crea link di pagamento</button>
        </form>
    @endif

    <h2 style="margin:0 0 4px;font-size:1.1rem">I tuoi preventivi</h2>
    @if ($quotes->isEmpty())
        <p style="color:#666">Nessun preventivo ancora.</p>
    @else
        @foreach ($quotes as $quote)
            @php $paid = $quote->isPaid(); @endphp
            <div class="q-row">
                <div>
                    <strong>{{ $quote->title }}</strong>
                    <span class="q-badge {{ $paid ? 'q-badge--paid' : 'q-badge--pending' }}">{{ $paid ? 'Pagato' : 'In attesa' }}</span>
                    <div style="color:#555;margin-top:4px">
                        {{ $quote->amountEuros() }} €
                        @if (! empty($quote->metadata['customer_name'])) · {{ $quote->metadata['customer_name'] }} @endif
                        · creato il {{ $quote->created_at->timezone(config('app.timezone'))->format('d/m/Y') }}
                        @if ($paid && $quote->paid_at) · pagato il {{ $quote->paid_at->timezone(config('app.timezone'))->format('d/m/Y H:i') }} @endif
                    </div>
                    @if ($quote->description)
                        <div style="color:#777;font-size:.88rem;margin-top:2px">{{ $quote->description }}</div>
                    @endif
                </div>
                <div class="q-actions">
                    @unless ($paid)
                        <button type="button" class="q-btn" onclick="navigator.clipboard.writeText('{{ $quote->payment_url }}');this.textContent='✓ Copiato!';setTimeout(()=>this.textContent='🔗 Copia link',1500)">🔗 Copia link</button>
                        <form method="POST" action="{{ route('admin.quotes.destroy', [$tenant, $quote]) }}" onsubmit="return confirm('Annullare questo preventivo e disattivare il link?')" style="display:contents">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="q-btn q-btn--danger">Annulla</button>
                        </form>
                    @endunless
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
