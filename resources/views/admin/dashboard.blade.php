@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="card">
    <h1>Clienti</h1>
    <p style="color:#666">Seleziona un cliente per creare una nuova promo da immagine (Gemini AI).</p>
    <ul style="list-style:none;padding:0;margin:24px 0 0">
        @foreach ($tenants as $tenant)
            <li style="padding:16px 0;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center">
                <div>
                    <strong>{{ $tenant->name }}</strong>
                    <div style="color:#888;font-size:14px">{{ $tenant->website }} · {{ $tenant->promos_count }} promo</div>
                </div>
                <a class="btn" href="{{ route('admin.promos.create', $tenant) }}">Nuova promo</a>
            </li>
        @endforeach
    </ul>
</div>
@endsection
