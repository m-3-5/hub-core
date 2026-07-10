@php
    $size = $size ?? 56;
    $animated = $animated ?? true;
    $height = round($size * 132 / 120);
    $uid = 'maxg'.uniqid();
@endphp
<svg width="{{ $size }}" height="{{ $height }}" viewBox="0 0 120 132" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
    <defs>
        <linearGradient id="{{ $uid }}" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="var(--accent)"/><stop offset="100%" stop-color="color-mix(in srgb, var(--accent) 65%, #4a0d2b)"/>
        </linearGradient>
    </defs>
    <g class="{{ $animated ? 'max-mascot-body' : '' }}">
        <rect x="50" y="2" width="20" height="10" rx="5" fill="url(#{{ $uid }})"/>
        <circle cx="60" cy="66" r="54" fill="url(#{{ $uid }})"/>
        <circle cx="60" cy="66" r="46" fill="none" stroke="#fff" stroke-opacity=".18" stroke-width="2"/>
        <circle cx="34" cy="86" r="7.5" fill="#fff" opacity=".22"/>
        <circle cx="86" cy="86" r="7.5" fill="#fff" opacity=".22"/>

        <g class="{{ $animated ? 'max-mascot-lid' : '' }}">
            <ellipse cx="42" cy="62" rx="10" ry="11.5" fill="#fff"/>
            <g class="max-mascot-pupil {{ $animated ? 'max-mascot-intro' : '' }}">
                <circle cx="42" cy="62" r="5.4" fill="#1f1a24"/>
                <circle cx="44" cy="59.5" r="1.7" fill="#fff"/>
            </g>
        </g>
        <g class="{{ $animated ? 'max-mascot-lid' : '' }}">
            <ellipse cx="78" cy="62" rx="10" ry="11.5" fill="#fff"/>
            <g class="max-mascot-pupil {{ $animated ? 'max-mascot-intro' : '' }}">
                <circle cx="78" cy="62" r="5.4" fill="#1f1a24"/>
                <circle cx="80" cy="59.5" r="1.7" fill="#fff"/>
            </g>
        </g>

        <path d="M40 90 Q60 106 80 90" stroke="#1f1a24" stroke-width="4.5" fill="none" stroke-linecap="round"/>
    </g>
</svg>
