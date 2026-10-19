{{-- Footer unico delle pagine pubbliche di inm35.it: compatto, in stile app (stili in public/css/hub-ui.css).
     Parametri facoltativi: $footExtra = [['Etichetta', 'url'], ...] link in più. --}}
<footer class="ui-foot">
    <div class="ui-foot__top">
        <a class="ui-foot__brand" href="{{ route('welcome') }}"><span class="ui-pub__logo">H</span><span><b>Hub Core</b><small>La tua attività in un'app</small></span></a>
        <nav class="ui-foot__links" aria-label="Link utili">
            <a href="{{ route('promo.hub-archive') }}">Promo</a>
            <a href="{{ route('classifieds.board') }}">Annunci</a>
            <a href="{{ route('landing.web') }}">Siti web</a>
            <a href="{{ route('pricing.show') }}">Prezzi</a>
            <a href="{{ route('guides.index') }}">Guide</a>
            <a href="{{ route('search') }}">Cerca</a>
            @if (config('landing.facebook_group.url'))
                <a href="{{ config('landing.facebook_group.url') }}" target="_blank" rel="noopener">Facebook</a>
            @endif
            @foreach (($footExtra ?? []) as $x)<a href="{{ $x[1] }}">{{ $x[0] }}</a>@endforeach
        </nav>
        <a class="ui-foot__cta" href="{{ route('admin.login') }}">Accedi</a>
    </div>
    <div class="ui-foot__legal">@include('layouts.partials.company-footer')</div>
</footer>
