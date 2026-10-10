{{--
    Scheda con immagine grande, usata nelle liste dell'area admin (promo, annunci…).
    Parametri: $title, $href (apre il dettaglio), $image (url o null), $meta (testo piccolo), $badge + $tone (ok|warn|off),
    $editUrl (matita), $deleteUrl + $deleteConfirm (X), $openUrl (anteprima pubblica), $muted (scheda attenuata)
--}}
<article class="mcard {{ ($muted ?? false) ? 'mcard--muted' : '' }}">
    <a class="mcard__media" href="{{ $href }}" aria-label="{{ $title }}">
        @if (! empty($image))
            <img src="{{ $image }}" alt="{{ $title }}" loading="lazy">
        @else
            <span class="mcard__ph" aria-hidden="true">🖼️<small>Nessuna immagine</small></span>
        @endif
        @if (! empty($badge))<span class="mcard__badge mcard__badge--{{ $tone ?? 'off' }}">{{ $badge }}</span>@endif
    </a>

    <div class="mcard__tools">
        @if (! empty($editUrl))
            <a class="mcard__tool" href="{{ $editUrl }}" title="Modifica" aria-label="Modifica {{ $title }}">✎</a>
        @endif
        @if (! empty($deleteUrl))
            <form method="POST" action="{{ $deleteUrl }}" onsubmit="return confirm(@js($deleteConfirm ?? 'Eliminare definitivamente?'))">
                @csrf
                @method('DELETE')
                <button type="submit" class="mcard__tool mcard__tool--del" title="Elimina" aria-label="Elimina {{ $title }}">✕</button>
            </form>
        @endif
    </div>

    <div class="mcard__body">
        <h3><a href="{{ $href }}">{{ $title }}</a></h3>
        @if (! empty($meta))<p>{{ $meta }}</p>@endif
        @if (! empty($openUrl))<a class="mcard__open" href="{{ $openUrl }}" target="_blank" rel="noopener">Vedi pagina ↗</a>@endif
        @if (! empty($actionUrl))
            <form method="POST" action="{{ $actionUrl }}" style="margin:6px 0 0">@csrf<button type="submit" class="mcard__action">{{ $actionLabel ?? 'Fatto' }}</button></form>
        @endif
    </div>
</article>
