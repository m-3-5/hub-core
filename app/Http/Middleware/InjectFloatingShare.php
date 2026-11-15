<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aggiunge a ogni pagina pubblica (HTML, 200, indicizzabile) la sezione «Condividi» sopra il footer,
 * se la pagina non ce l'ha già. Niente più tasto fluttuante: icone ferme, sempre nello stesso posto.
 * Salta admin, app, pagamenti, risposte non HTML e qualunque pagina «noindex» (private).
 */
class InjectFloatingShare
{
    private const SKIP_PREFIXES = ['admin', 'app', 'api', 'ordine', 'login', 'registrati', 'auth', 'stripe', 'embed', 'client', 'cerca', 'storage', 'up', 'pubblica', 'ospite'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') || $request->ajax() || $response->getStatusCode() !== 200) {
            return $response;
        }

        $first = strtolower((string) $request->segment(1));
        if (in_array($first, self::SKIP_PREFIXES, true) || str_contains((string) $request->path(), '/acquista')) {
            return $response;
        }

        $type = (string) $response->headers->get('Content-Type', '');
        if (! str_contains($type, 'text/html')) {
            return $response;
        }

        $html = (string) $response->getContent();

        if (! str_contains($html, '</body>')
            || str_contains($html, 'share-row share-section')
            || preg_match('~<meta[^>]+name=["\']robots["\'][^>]+noindex~i', $html)) {
            return $response;
        }

        $url = preg_match('~<link[^>]+rel=["\']canonical["\'][^>]+href=["\']([^"\']+)~i', $html, $m)
            ? html_entity_decode($m[1]) : $request->url();
        $title = preg_match('~<title>(.*?)</title>~is', $html, $t)
            ? trim(html_entity_decode(strip_tags($t[1]))) : config('app.name');

        $section = view('layouts.partials.share-bar', [
            'shareTitle' => $title, 'shareUrl' => $url, 'shareSection' => true, 'shareLabel' => 'Condividi questa pagina',
        ])->render();

        // sopra il footer comune; se la pagina non lo usa, in fondo
        $pos = strripos($html, '<footer');
        $pos = $pos === false ? strripos($html, '</body>') : $pos;
        $response->setContent(substr($html, 0, $pos).$section."\n".substr($html, $pos));

        return $response;
    }
}
