{{-- Riquadro grande in cima alla home dell'app: «Sito web a 1 €» (o «Il tuo sito» se c'è già).
     $siteUrl: dove porta; $hasSite: il sito esiste già; $launchEur: prezzo di partenza. --}}
<a class="ah-hero" href="{{ $siteUrl }}">
    <span class="ah-hero__ic">@include('layouts.partials.icon', ['name' => 'globe', 'size' => 30])</span>
    <span class="ah-hero__t">
        @if ($hasSite)
            <b>Il tuo sito</b>
            <small>Modificalo quando vuoi</small>
        @else
            <b>Sito web a {{ $launchEur }} €</b>
            <small>Pronto subito, lo scrive l'IA</small>
        @endif
    </span>
    <span class="ah-hero__go">{{ $hasSite ? 'Apri' : 'Crealo' }} @include('layouts.partials.icon', ['name' => 'arrow', 'size' => 18])</span>
</a>
