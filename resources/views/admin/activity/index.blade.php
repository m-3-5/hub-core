@extends('layouts.admin')

@section('title', 'Attività clienti')

@section('content')
<div class="card">
    <h1 style="margin:0 0 8px">Attività clienti</h1>
    <p style="color:#666;margin:0 0 20px">Cosa scrivono e cosa esce fuori quando i clienti creano o modificano una promo — utile per capire le stranezze senza dover ripetere il test.</p>

    @if ($logs->isEmpty())
        <p style="color:#666">Nessuna attività registrata ancora.</p>
    @endif

    @foreach ($logs as $log)
        <div style="border:1px solid #eee;border-radius:12px;padding:16px;margin-bottom:14px">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:10px;margin-bottom:10px">
                <div>
                    <strong>{{ $log->tenant->name }}</strong>
                    <span style="font-size:12px;font-weight:700;padding:3px 10px;border-radius:999px;background:#eef2ff;color:#4338ca;margin-left:6px">{{ $log->event }}</span>
                </div>
                <span style="font-size:12px;color:#888">{{ $log->created_at->format('d/m/Y H:i:s') }}</span>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div>
                    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#888;margin:0 0 6px">Cosa ha scritto/fatto</p>
                    <pre style="background:#f6f7fb;padding:10px;border-radius:8px;overflow:auto;font-size:12px;margin:0">{{ json_encode($log->input, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
                <div>
                    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#888;margin:0 0 6px">Cosa è uscito</p>
                    <pre style="background:#f6f7fb;padding:10px;border-radius:8px;overflow:auto;font-size:12px;margin:0">{{ json_encode($log->output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                </div>
            </div>

            @if ($log->subject_type && $log->subject_id)
                <p style="font-size:12px;color:#888;margin:10px 0 0">📎 {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</p>
            @endif
        </div>
    @endforeach
</div>
@endsection
