{{-- Griglia «tutti i servizi» della home dell'app: solo icona e nome, niente prezzi o spiegazioni.
     $tiles: array di ['key', 'label', 'icon', 'color', 'url' => ?string, 'tag' => ?string, 'guest' => bool]
     - url: si apre il servizio; tag: piccola etichetta («Presto», «Premium»); guest: apre la scheda «inizia senza account». --}}
<div class="ah-grid">
    @foreach ($tiles as $tile)
        @if (! empty($tile['url']))
            <a class="ah-gi {{ ! empty($tile['tag']) ? 'is-off' : '' }}" href="{{ $tile['url'] }}" style="--c: {{ $tile['color'] }}">
        @elseif (! empty($tile['guest']))
            <button type="button" class="ah-gi" data-guest-start="{{ $tile['key'] }}" style="--c: {{ $tile['color'] }}">
        @elseif ($canRequest ?? true)
            <button type="button" class="ah-gi is-off" data-request-module="{{ $tile['label'] }}" style="--c: {{ $tile['color'] }}">
        @else
            <span class="ah-gi is-off" aria-disabled="true" style="--c: {{ $tile['color'] }}">
        @endif
            <span class="ah-ic">@include('layouts.partials.icon', ['name' => $tile['icon'], 'size' => 24])</span>
            <b>{{ $tile['label'] }}</b>
            @if (! empty($tile['tag']))<em>{{ $tile['tag'] }}</em>@endif
        @if (! empty($tile['url']))
            </a>
        @elseif (! empty($tile['guest']) || ($canRequest ?? true))
            </button>
        @else
            </span>
        @endif
    @endforeach
</div>
