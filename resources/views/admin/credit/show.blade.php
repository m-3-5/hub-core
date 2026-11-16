@extends('layouts.admin')

@section('title', 'Pagamenti — '.$tenant->name)

@php $eur = fn (int $c) => \App\Support\PayPerUse::euro($c); @endphp

@section('content')
<style>
    .cr { max-width: 560px; margin: 0 auto; }
    .cr-hero { text-align: center; padding: 22px 16px; border-radius: 22px; color: #fff; background: linear-gradient(135deg, #4f46e5, #a855f7); margin-bottom: 18px; }
    .cr-hero b { display: block; font-size: 1.6rem; line-height: 1.2; }
    .cr-hero small { display: block; opacity: .9; margin-top: 6px; font-weight: 600; }
    .cr-wait { background: #fff7e6; border: 1px solid #fde4b0; border-radius: 16px; padding: 14px 16px; margin-bottom: 18px; }
    .cr-wait p { margin: 0; }
    .cr-cta { width: 100%; min-height: 56px; font-size: 1.05rem; }
    .cr-card { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-radius: 16px; background: #f0fdf4; color: #166534; font-weight: 700; margin-bottom: 18px; }
    .cr-prices { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 18px; text-align: center; }
    .cr-prices div { background: #f6f7fb; border-radius: 14px; padding: 10px 4px; }
    .cr-prices small { display: block; color: #64748b; font-size: .78rem; }
    .cr-prices b { font-size: 1.05rem; }
    .cr-log { list-style: none; margin: 0; padding: 0; }
    .cr-log li { display: flex; justify-content: space-between; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: .92rem; }
    .cr-log b { white-space: nowrap; }
    @media (max-width: 420px) { .cr-prices { grid-template-columns: repeat(2, 1fr); } }
</style>

<div class="card cr">
    <h1 style="margin:0 0 14px">Pagamenti</h1>

    @if (session('status'))<p class="alert">{{ session('status') }}</p>@endif
    @if (session('success'))<p class="alert">{{ session('success') }}</p>@endif
    @error('credit')<p class="error">{{ $message }}</p>@enderror

    @if ($pending)
        <div class="cr-wait">
            <p>Per pubblicare <strong>«{{ $pending['title'] }}»</strong>: <strong>{{ $eur($pendingPrice) }}</strong>, lo paghi tra {{ $billDays }} giorni.</p>
            @if ($hasCard)
                <form method="POST" action="{{ route('admin.credit.publish', $tenant) }}" style="margin-top:10px">@csrf
                    <button class="btn cr-cta" type="submit" data-busy="Pubblico…">🚀 Pubblica ora</button>
                </form>
            @endif
        </div>
    @endif

    @if ($hasCard)
        <div class="cr-card"><span>💳 {{ ucfirst($card['brand'] ?? 'Carta') }} •••• {{ $card['last4'] ?? '' }}</span><span>✓</span></div>
        @if ($toBill > 0)
            <div class="cr-hero"><b>{{ $eur($toBill) }}</b><small>da pagare il {{ \Illuminate\Support\Carbon::parse($nextBill)->timezone(config('app.timezone'))->format('d/m/Y') }}</small></div>
        @endif
        <form method="POST" action="{{ route('admin.credit.checkout', $tenant) }}" style="margin-bottom:18px">@csrf
            <button type="submit" class="btn btn-secondary" style="width:100%;min-height:48px" data-busy="Apro…">Cambia carta</button>
        </form>
    @else
        <div class="cr-hero"><b>Non paghi niente adesso</b><small>Il primo addebito tra {{ $billDays }} giorni</small></div>
        @if (! empty($card['failed']))
            <p class="error">L'ultimo addebito non è andato a buon fine: aggiungi un'altra carta.</p>
        @endif
        <form method="POST" action="{{ route('admin.credit.checkout', $tenant) }}" style="margin-bottom:18px">@csrf
            <button type="submit" class="btn cr-cta" data-busy="Apro la pagina sicura…">💳 Aggiungi la carta</button>
        </form>
    @endif

    <div class="cr-prices" aria-label="Quanto costa pubblicare adesso">
        <div><small>Annuncio</small><b>Gratis</b></div>
        @foreach (\App\Support\PayPerUse::KINDS as $kind => $label)
            <div><small>{{ ucfirst($label) }}</small><b>{{ $prices[$kind] === 0 ? 'Incluso' : $eur($prices[$kind]) }}</b></div>
        @endforeach
    </div>

    <p style="margin:0 0 18px">@include('layouts.partials.costs-link')</p>

    @if ($entries->isNotEmpty())
        <h2 style="font-size:1rem;margin:0 0 6px">Movimenti</h2>
        <ul class="cr-log">
            @foreach ($entries as $e)
                <li>
                    <span>{{ $e->description }}<br><small style="color:#94a3b8">{{ $e->created_at->timezone(config('app.timezone'))->format('d/m/Y') }}</small></span>
                    <b>@if ($e->reason === 'included')Incluso@elseif ($e->status === 'billed')✓ {{ $eur($e->amount_cents) }}@else{{ $eur($e->amount_cents) }}@endif</b>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
