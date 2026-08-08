@extends('layouts.admin')

@section('title', 'Feedback clienti')

@section('content')
<div class="card">
    <h1 style="margin:0 0 8px">Feedback clienti</h1>
    <p style="color:#666;margin:0 0 20px">Risposte raccolte dalle richieste di feedback inviate via email.</p>

    @if ($responses->isEmpty())
        <p style="color:#666">Nessuna risposta ancora.</p>
    @endif

    @foreach ($responses as $response)
        <div style="border:1px solid #eee;border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;margin-bottom:10px">
                <strong>{{ $response->tenant->name }}</strong>
                <span style="font-size:12px;color:#888">{{ $response->campaign }} · {{ $response->created_at->format('d/m/Y H:i') }}</span>
            </div>
            @foreach ($response->answers as $key => $value)
                <div style="margin-bottom:8px;font-size:14px">
                    <strong style="color:#555">{{ $labels[$key] ?? $key }}</strong>
                    <div style="color:#333">{{ $value }}</div>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endsection
