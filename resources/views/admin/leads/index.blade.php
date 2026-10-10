@extends('layouts.admin')

@section('title', 'Richieste sito')

@section('content')
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:20px">
        <div>
            <h1 style="margin:0 0 6px">Richieste sito</h1>
            <p style="margin:0;color:#666">Preventivi richiesti dalla pagina «Siti web e app a Corigliano-Rossano». Nuove da gestire: <strong>{{ $newCount }}</strong></p>
        </div>
        <a class="btn btn-secondary" href="{{ route('admin.dashboard') }}">← Dashboard</a>
    </div>

    @if ($leads->isEmpty())
        <p style="color:#666">Nessuna richiesta ancora.</p>
    @else
        <div style="overflow-x:auto">
            <table style="width:100%;border-collapse:collapse;font-size:.92rem">
                <thead>
                    <tr style="text-align:left;border-bottom:2px solid #eee">
                        <th style="padding:8px">Data</th><th style="padding:8px">Chi</th><th style="padding:8px">Contatti</th>
                        <th style="padding:8px">Interessato a</th><th style="padding:8px">Messaggio</th><th style="padding:8px"></th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($leads as $lead)
                    <tr style="border-bottom:1px solid #f0f0f0;vertical-align:top;{{ $lead->status === 'new' ? 'background:#fffbea' : '' }}">
                        <td style="padding:8px;white-space:nowrap">{{ $lead->created_at->timezone(config('app.timezone'))->format('d/m H:i') }}</td>
                        <td style="padding:8px"><strong>{{ $lead->name }}</strong></td>
                        <td style="padding:8px">
                            <a href="tel:+{{ $lead->dialNumber() }}">{{ $lead->phone }}</a>
                            · <a href="https://wa.me/{{ $lead->dialNumber() }}" target="_blank" rel="noopener">WhatsApp</a>
                            @if ($lead->email)<div><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a></div>@endif
                        </td>
                        <td style="padding:8px">{{ $lead->packageLabel() }}</td>
                        <td style="padding:8px;max-width:320px">{{ $lead->message ?: '—' }}</td>
                        <td style="padding:8px;text-align:right">
                            <form method="POST" action="{{ route('admin.leads.toggle', $lead) }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary" style="border:0;cursor:pointer">{{ $lead->status === 'new' ? 'Segna gestita' : 'Rimetti da gestire' }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
