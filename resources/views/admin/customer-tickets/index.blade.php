@extends('layouts.admin')

@section('title', 'Messaggi clienti — '.$tenant->name)

@section('content')
<div class="card">
    <h1 style="margin:0 0 8px">Messaggi clienti — {{ $tenant->name }}</h1>
    <p style="color:#666;margin:0 0 20px">Messaggi ricevuti dal modulo "Scrivici" delle tue promo. Rispondi direttamente al cliente via email o telefono.</p>

    @if ($customerTickets->isEmpty())
        <p style="color:#666">Nessun messaggio per ora.</p>
    @endif

    @foreach ($customerTickets as $customerTicket)
        @php
            $isNew = $customerTicket->isNew();
            $statusColor = $isNew ? '#e65100' : '#2e7d32';
            $statusLabel = $isNew ? 'Da gestire' : 'Gestito';
        @endphp
        <div style="border:1px solid #eee;border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;margin-bottom:8px">
                <strong>{{ $customerTicket->name }}</strong>
                <span style="font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;background:{{ $statusColor }}20;color:{{ $statusColor }}">
                    {{ $statusLabel }}
                </span>
            </div>
            <p style="color:#444;margin:0 0 8px">{{ $customerTicket->message }}</p>
            <p style="font-size:13px;color:#666;margin:0 0 8px">
                @if ($customerTicket->email)<a href="mailto:{{ $customerTicket->email }}">✉️ {{ $customerTicket->email }}</a>@endif
                @if ($customerTicket->email && $customerTicket->phone) &nbsp;·&nbsp; @endif
                @if ($customerTicket->phone)<a href="tel:{{ $customerTicket->phone }}">📞 {{ $customerTicket->phone }}</a>@endif
            </p>
            @if ($customerTicket->promo)
                <p style="font-size:12px;color:#888;margin:0 0 10px">📎 {{ $customerTicket->promo->title }}</p>
            @endif
            <p style="font-size:12px;color:#888;margin:0 0 12px">{{ $customerTicket->created_at->format('d/m/Y H:i') }}</p>

            <form method="POST" action="{{ route('admin.customer-tickets.toggle-status', [$tenant, $customerTicket]) }}">
                @csrf
                <button type="submit" class="btn btn-secondary">
                    {{ $isNew ? 'Segna come gestito' : 'Segna come da gestire' }}
                </button>
            </form>
        </div>
    @endforeach
</div>
@endsection
