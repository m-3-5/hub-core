{{-- Icona a linea (SVG inline, stile app nativa). Uso: @include('layouts.partials.icon', ['name' => 'home', 'size' => 22]) --}}
@php
    $icons = [
        'home' => 'M3 11.5 12 4l9 7.5V20a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z',
        'promo' => 'M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z M19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z',
        'services' => 'M3 8h18v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1z M9 8V6a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2 M3 13h18',
        'products' => 'M12 3 3 7.5v9L12 21l9-4.5v-9z M3 7.5l9 4.5 9-4.5 M12 12v9',
        'quotes' => 'M6 3h9l4 4v14H6z M15 3v4h4 M9 12h7 M9 16h7',
        'classifieds' => 'M3 12.5V4h8.5L21 13.5 13.5 21z M8 8.5h.01',
        'orders' => 'M3 4h2l2.2 11h10.3L19.5 8H6.2 M9.5 19.5h.01 M17 19.5h.01',
        'messages' => 'M4 5h16v11H10l-4.5 4V16H4z',
        'user' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z M4 20c.5-3.5 4-5 8-5s7.5 1.5 8 5',
        'card' => 'M3 6h18v12H3z M3 10h18 M7 15h3',
        'globe' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M3 12h18 M12 3c2.5 2.5 3.5 5.5 3.5 9s-1 6.5-3.5 9c-2.5-2.5-3.5-5.5-3.5-9S9.5 5.5 12 3z',
        'calendar' => 'M4 6h16v14H4z M4 10h16 M8 3v4 M16 3v4',
        'shield' => 'M12 3 4.5 6v5.5c0 4.5 3 8 7.5 9.5 4.5-1.5 7.5-5 7.5-9.5V6z M9 12l2.2 2.2L15.5 10',
        'users' => 'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7z M2.5 20c.5-3 3.2-4.5 6.5-4.5s6 1.5 6.5 4.5 M16 4.5a3.5 3.5 0 0 1 0 6.5 M18.5 15.7c1.8.6 2.8 2 3 4.3',
        'chart' => 'M5 20V10 M12 20V4 M19 20v-7 M3 20h18',
        'search' => 'M11 18a7 7 0 1 0 0-14 7 7 0 0 0 0 14z M20 20l-4-4',
        'book' => 'M5 4.5A1.5 1.5 0 0 1 6.5 3H19v15H6.5A1.5 1.5 0 0 0 5 19.5z M5 19.5A1.5 1.5 0 0 0 6.5 21H19',
        'mail' => 'M3 5h18v14H3z M3 6l9 7 9-7',
        'coins' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z M14.5 9.5c-.5-.8-1.4-1.2-2.5-1.2-1.4 0-2.5.7-2.5 1.8 0 2.7 5 1.3 5 4 0 1.1-1.1 1.8-2.5 1.8-1.1 0-2-.4-2.5-1.2',
        'logout' => 'M10 4H5v16h5 M15 8l4 4-4 4 M19 12H9',
        'external' => 'M7 17 17 7 M8 7h9v9',
        'arrow' => 'M5 12h14 M13 6l6 6-6 6',
        'check' => 'M5 12.5l4.5 4.5L19 7.5',
        'back' => 'M9 14 4 9l5-5 M4 9h10a6 6 0 0 1 0 12h-3',
        'gift' => 'M4 9h16v4H4z M5 13v8h14v-8 M12 9v12 M12 9C9.5 9 8 7.5 8 6.2S9.2 4 10.4 4C11.7 4 12 6 12 9z M12 9c2.5 0 4-1.5 4-2.8S14.8 4 13.6 4C12.3 4 12 6 12 9z',
        'tag' => 'M3 12.5V4h8.5L21 13.5 13.5 21z M8 8.5h.01',
        'plus' => 'M12 5v14 M5 12h14',
        'archive' => 'M3 4h18v4H3z M5 8v12h14V8 M10 12h4',
        'bell' => 'M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15z M10 20.5h4',
    ];
    $d = $icons[$name ?? 'home'] ?? $icons['home'];
    $s = (int) ($size ?? 22);
@endphp
<svg class="ui-icon" width="{{ $s }}" height="{{ $s }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="{{ $d }}"/></svg>
