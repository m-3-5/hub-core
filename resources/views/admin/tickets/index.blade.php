@extends('layouts.admin')

@section('title', 'Ticket clienti')

@section('content')
<div class="card">
    <h1 style="margin:0 0 8px">Ticket clienti</h1>
    <p style="color:#666;margin:0 0 20px">Messaggi ricevuti da Max — rispondi entro 24 ore dalla creazione.</p>

    @if ($tickets->isEmpty())
        <p style="color:#666">Nessun ticket per ora.</p>
    @endif

    @foreach ($tickets as $ticket)
        @php
            $pastSla = $ticket->isPastSla();
            $statusColor = $ticket->isAnswered() ? '#2e7d32' : ($pastSla ? '#c62828' : '#e65100');
            $statusLabel = $ticket->isAnswered() ? 'Risposto' : ($pastSla ? 'Scaduto (>24h)' : 'In attesa');
        @endphp
        <div style="border:1px solid #eee;border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;margin-bottom:8px">
                <strong>{{ $ticket->tenant->name }}</strong>
                <span style="font-size:12px;font-weight:700;padding:4px 10px;border-radius:999px;background:{{ $statusColor }}20;color:{{ $statusColor }}">
                    {{ $statusLabel }}
                </span>
            </div>
            <p style="color:#444;margin:0 0 8px">{{ $ticket->message }}</p>
            @if ($ticket->context_label)
                <p style="font-size:12px;color:#888;margin:0 0 10px">📎 {{ $ticket->context_label }}</p>
            @elseif ($ticket->context_type)
                <p style="font-size:12px;color:#888;margin:0 0 10px">Contesto: {{ $ticket->context_type }} #{{ $ticket->context_id }}</p>
            @endif
            <p style="font-size:12px;color:#888;margin:0 0 12px">{{ $ticket->created_at->format('d/m/Y H:i') }} ({{ $ticket->hoursOld() }}h fa)</p>

            @if ($ticket->isAnswered())
                <div style="background:#f0f9ff;border-radius:8px;padding:10px 12px;font-size:14px">
                    <strong>Risposta:</strong> {{ $ticket->response }}
                </div>
            @else
                <form method="POST" action="{{ route('admin.tickets.respond', $ticket) }}">
                    @csrf
                    <textarea name="response" rows="2" maxlength="2000" required placeholder="Scrivi la risposta..." style="width:100%;padding:10px;border:1px solid #e2e8f0;border-radius:8px;font-family:inherit"></textarea>
                    <button type="submit" class="btn" style="margin-top:8px">Invia risposta</button>
                </form>
            @endif
        </div>
    @endforeach
</div>
@endsection
