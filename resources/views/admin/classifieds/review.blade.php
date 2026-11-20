@extends('layouts.admin')

@section('title', 'Annunci da controllare')

@section('content')
<div class="card" style="max-width:720px;margin:0 auto">
    <h1 style="margin:0 0 6px">Annunci da controllare</h1>
    <p style="color:#666;margin:0 0 14px">Annunci dei privati. Approvati restano online {{ \App\Models\ClassifiedAd::PRIVATE_DAYS }} giorni.</p>
    @if (session('success'))<p class="alert">{{ session('success') }}</p>@endif

    @forelse ($ads as $ad)
        <div style="border:1px solid #e5e5ee;border-radius:16px;padding:14px;margin-bottom:14px">
            @if ($ad->imageUrls())
                <div style="display:flex;gap:8px;overflow-x:auto;margin-bottom:10px">
                    @foreach ($ad->imageUrls() as $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener"><img src="{{ $url }}" alt="" style="height:110px;border-radius:10px"></a>
                    @endforeach
                </div>
            @endif
            <strong style="font-size:1.05rem">{{ $ad->categoryIcon() }} {{ $ad->title }}</strong>
            <div style="color:#666;font-size:.9rem">{{ $ad->categoryLabel() }} · {{ $ad->zone }}{{ $ad->priceLabel() ? ' · '.$ad->priceLabel() : '' }}{{ $ad->source ? ' · da '.$ad->source : '' }}</div>
            <p style="white-space:pre-line;margin:8px 0">{{ $ad->description }}</p>
            <div style="color:#666;font-size:.85rem">{{ $ad->contact_name }} · {{ $ad->contact_email }}{{ $ad->contact_phone ? ' · '.$ad->contact_phone : '' }} · {{ $ad->created_at->timezone(config('app.timezone'))->format('d/m H:i') }}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
                <form method="POST" action="{{ route('admin.classified-review.approve', $ad) }}">@csrf
                    <button class="btn" type="submit">✅ Approva</button>
                </form>
                <form method="POST" action="{{ route('admin.classified-review.reject', $ad) }}" style="display:flex;gap:6px;flex:1">@csrf
                    <input type="text" name="reason" maxlength="300" placeholder="Motivo (facoltativo)" style="flex:1;min-width:0">
                    <button class="btn btn-secondary" type="submit">Rifiuta</button>
                </form>
            </div>
        </div>
    @empty
        <p style="color:#666">Niente da controllare. 👍</p>
    @endforelse
</div>
@endsection
