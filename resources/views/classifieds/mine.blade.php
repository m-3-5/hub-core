<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>I miei annunci · inm35.it</title>
    @include('layouts.partials.favicon')
    <meta name="robots" content="noindex">
    <meta name="theme-color" content="#4f46e5">
    @include('classifieds.partials.mobile-css')
@include('layouts.partials.ui')
</head>
<body>
@include('layouts.partials.public-nav')
<main class="m-wrap">
    <h1>I miei annunci</h1>
    @if (session('success'))<div class="m-ok">{{ session('success') }}</div>@endif

    @if ($ads->isEmpty())
        <div class="m-center" style="padding-top:20px">
            <div class="m-big">📭</div>
            <p class="m-muted">Nessun annuncio, per ora.</p>
        </div>
    @else
        <ul class="m-list">
            @foreach ($ads as $ad)
                @php
                    $chip = match (true) {
                        $ad->status === 'published' && $ad->isExpired() => 'expired',
                        $ad->status === 'published' => 'online',
                        default => $ad->status,
                    };
                    $thumb = $ad->thumbUrl();
                @endphp
                <li class="m-ad">
                    @if ($thumb)
                        <div class="m-ad__img" style="background-image:url('{{ $thumb }}')"></div>
                    @else
                        <div class="m-ad__img">{{ $ad->categoryIcon() }}</div>
                    @endif
                    <div class="m-ad__body">
                        <div class="m-ad__title">{{ $ad->title }}</div>
                        <div class="m-muted m-small">{{ $ad->zone }}{{ $ad->priceLabel() ? ' · '.$ad->priceLabel() : '' }}</div>
                        <span class="m-chip m-chip--{{ $chip }}">{{ $ad->statusLabel() }}</span>
                        @if ($chip === 'online' && $ad->expires_at)
                            <span class="m-muted m-small">fino al {{ $ad->expires_at->timezone(config('app.timezone'))->format('d/m') }}</span>
                        @endif
                        <div class="m-actions">
                            @if ($chip === 'online')
                                <a class="m-btn m-btn--ghost m-btn--sm" href="{{ $ad->publicUrl() }}">Vedi</a>
                            @endif
                            @if (in_array($chip, ['online', 'expired'], true) && $ad->expires_at)
                                <form method="POST" action="{{ route('classifieds.private.renew', $ad) }}">@csrf
                                    <button class="m-btn m-btn--sm" type="submit">+{{ \App\Models\ClassifiedAd::PRIVATE_DAYS }} giorni</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('classifieds.private.remove', $ad) }}" data-confirm="Togliere l'annuncio?">@csrf
                                <button class="m-link" type="submit">Togli</button>
                            </form>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="m-bar"><a class="m-btn" href="{{ route('classifieds.private.create') }}">+ Nuovo annuncio</a></div>
</main>
<script>
document.querySelectorAll('form[data-confirm]').forEach((f) => f.addEventListener('submit', (e) => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));
</script>
</body>
</html>
