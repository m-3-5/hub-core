@php
    $isExpired = $expired ?? $promo->isExpired();
    $flyer = $promo->variantUrl('flyer') ?? $promo->imageUrl();
    $expiry = $promo->expiryLabel();
@endphp
<article class="promo-card {{ $isExpired ? 'promo-card--expired' : 'promo-card--active' }}">
    @if ($flyer)
        <a class="promo-card__media" href="{{ route('promo.show', [$tenant, $promo]) }}">
            <img src="{{ $flyer }}" alt="{{ $promo->title }}" loading="lazy">
        </a>
    @endif
    <div class="promo-card__body">
        @if ($expiry)
            <span class="promo-card__badge {{ $isExpired ? 'promo-card__badge--expired' : 'promo-card__badge--active' }}">
                {{ $expiry }}
            </span>
        @endif
        <h3><a href="{{ route('promo.show', [$tenant, $promo]) }}">{{ $promo->title }}</a></h3>
        @if ($promo->description)
            <p>{{ \Illuminate\Support\Str::limit($promo->description, 120) }}</p>
        @endif
        <a class="promo-card__cta" href="{{ route('promo.show', [$tenant, $promo]) }}">
            {{ $isExpired ? 'Vedi dettagli' : ($promo->cta_label ?? 'Scopri l\'offerta') }}
        </a>
    </div>
</article>
