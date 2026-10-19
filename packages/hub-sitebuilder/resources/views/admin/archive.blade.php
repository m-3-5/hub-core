@extends('layouts.admin')

@section('title', 'Archivio siti')

@section('content')
<div class="card">
    <h1 style="margin:0 0 4px">Archivio siti</h1>
    <p style="color:#666;margin:0 0 16px">Siti creati con Max e poi rimossi (non pagavano, non li usavano o l'email non è mai stata confermata). Qui restano solo i testi, riservati a te.</p>

    @if ($sites->isEmpty())
        <p class="empty-note">Nessun sito archiviato.</p>
    @else
        <table class="rtable" style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="text-align:left;color:#667085;font-size:.8rem">
                    <th style="padding:8px">Attività</th><th style="padding:8px">Email</th><th style="padding:8px">Motivo</th><th style="padding:8px">Rimosso il</th><th style="padding:8px">Testi</th><th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sites as $s)
                    <tr style="border-top:1px solid #eee;vertical-align:top">
                        <td data-label="Attività" style="padding:10px 8px"><b>{{ $s->tenant_name }}</b><br><small style="color:#667085">{{ $s->tenant_slug }}</small></td>
                        <td data-label="Email" style="padding:10px 8px">{{ $s->owner_email ?: '—' }}</td>
                        <td data-label="Motivo" style="padding:10px 8px">{{ ucfirst($s->reason_label ?? $s->reason) }}</td>
                        <td data-label="Rimosso il" style="padding:10px 8px">{{ $s->archived_at?->format('d/m/Y') }}</td>
                        <td data-label="Testi" style="padding:10px 8px;max-width:340px">
                            <details><summary style="cursor:pointer;color:#4f46e5;font-weight:700">Vedi</summary>
                                <p style="margin:6px 0 0;font-size:.88rem"><b>{{ $s->answers['tagline'] ?? '' }}</b><br>
                                {{ implode(', ', $s->answers['services'] ?? []) }}<br>
                                @if (! empty($s->answers['extra']))<i>{{ $s->answers['extra'] }}</i>@endif</p>
                            </details>
                        </td>
                        <td style="padding:10px 8px">
                            <form method="POST" action="{{ route('admin.site-archive.destroy', $s) }}" onsubmit="return confirm('Eliminare per sempre questo sito dall\'archivio?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger" style="padding:6px 12px">Elimina</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:14px">{{ $sites->links() }}</div>
    @endif
</div>
@endsection
