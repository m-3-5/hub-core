{{-- inm35.it come app (PWA): manifest, icone, service worker con pagina «Sei offline».
     Sulle pagine col marchio di un cliente (promo, servizi, sito, ordini) niente manifest: lì non si installa Hub Core. --}}
@php
    $pwaClientPage = request()->routeIs('promo.show', 'promo.archive', 'services.public.*', 'site.public.*', 'site.edit.*', 'client.*', 'protected.order.show', 'embed.*');
@endphp
@unless ($pwaClientPage)
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
<meta name="theme-color" content="#6366f1">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Hub Core">
@endunless
@unless (request()->routeIs('client.*'))
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {}); });
    }
</script>
@endunless
