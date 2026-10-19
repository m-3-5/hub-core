{{-- Barra in alto delle pagine pubbliche di inm35.it: tutte le sezioni sempre in vista (stili in public/css/hub-ui.css). --}}
@php
    $pubItems = [
        ['Promo', '✨', route('promo.hub-archive'), request()->routeIs('promo.hub-archive')],
        ['Annunci', '📋', route('classifieds.board'), request()->routeIs('classifieds.board')],
        ['Siti web', '🌐', route('landing.web'), request()->routeIs('landing.web')],
        ['Prezzi', '🏷️', route('pricing.show'), request()->routeIs('pricing.show')],
        ['Guide', '📘', route('guides.index'), request()->routeIs('guides.*') || request()->routeIs('terms.*')],
        ['Cerca', '🔎', route('search'), request()->routeIs('search')],
    ];
@endphp
<nav class="ui-pub" aria-label="Menu del sito">
    <a class="ui-pub__brand" href="{{ route('welcome') }}" aria-label="Hub Core - Home">
        <span class="ui-pub__logo">H</span><span class="ui-pub__name">Hub Core</span>
    </a>
    <div class="ui-pub__links">
        @foreach ($pubItems as $it)
            <a href="{{ $it[2] }}" class="{{ $it[3] ? 'is-active' : '' }} {{ $it[0] === 'Guide' ? 'ui-pub__opt' : '' }}" @if($it[3]) aria-current="page" @endif><span class="ui-pub__ico">{{ $it[1] }}</span><span>{{ $it[0] }}</span></a>
        @endforeach
    </div>
    <a class="ui-pub__cta" href="{{ route('admin.login') }}">Accedi</a>
</nav>
