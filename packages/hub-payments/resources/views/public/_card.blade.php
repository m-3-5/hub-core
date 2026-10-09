<article class="service-card" style="background:#fff;border-radius:14px;overflow:hidden;border:1px solid rgba(0,0,0,.06);box-shadow:0 8px 24px rgba(0,0,0,.07)">
    <a href="{{ route('services.public.show', [$tenant, $service]) }}" style="display:block;background:linear-gradient(180deg,#fdf8fb,#fff)">
        @if ($service->coverImageUrl())
            <img src="{{ $service->coverImageUrl() }}" alt="{{ $service->title }}" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block">
        @else
            <div style="width:100%;aspect-ratio:4/3;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:.85rem">{{ $tenant->name }}</div>
        @endif
    </a>
    <div style="padding:14px 16px 16px">
        @if ($service->onPromo())
            <span style="display:inline-block;background:#fef3c7;color:#92400e;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px;margin-bottom:8px">In promo fino al {{ $service->promoUntil()->format('d/m') }}</span>
        @endif
        <h3 style="font-size:1.05rem;margin-bottom:6px">
            <a href="{{ route('services.public.show', [$tenant, $service]) }}" style="color:inherit;text-decoration:none">{{ $service->title }}</a>
        </h3>
        <p style="color:var(--muted);font-size:.95rem;margin-bottom:12px">{{ $service->amountEuros() }} €@if ($service->durationLabel()) · {{ $service->durationLabel() }}@endif</p>
        <a href="{{ route('services.public.show', [$tenant, $service]) }}" style="display:inline-block;background:var(--primary);color:#fff;text-decoration:none;padding:8px 14px;border-radius:999px;font-size:.82rem;font-weight:600">{{ $service->type === 'product' ? 'Vedi e acquista' : 'Vedi e prenota' }}</a>
    </div>
</article>
