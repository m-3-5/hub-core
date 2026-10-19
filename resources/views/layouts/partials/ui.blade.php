{{-- Stile condiviso di tutte le pagine (foto uguali nelle card, navigazione sempre visibile). Va incluso per ultimo nell'<head>. --}}
<link rel="stylesheet" href="{{ asset('css/hub-ui.css') }}?v={{ @filemtime(public_path('css/hub-ui.css')) }}">
