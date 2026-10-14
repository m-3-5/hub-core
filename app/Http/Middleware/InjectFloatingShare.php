<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aggiunge a ogni pagina pubblica (HTML, 200, indicizzabile) lo script della condivisione fluttuante.
 * Vale per le pagine di oggi e per quelle future, senza toccare le singole viste. Salta admin, app, pagamenti,
 * risposte non HTML e qualunque pagina «noindex» (private).
 */
class InjectFloatingShare
{
    private const SKIP_PREFIXES = ['admin', 'app', 'api', 'ordine', 'login', 'registrati', 'auth', 'stripe', 'embed', 'client', 'cerca', 'storage', 'up'];

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
            || str_contains($html, 'floating-share.js')
            || preg_match('~<meta[^>]+name=["\']robots["\'][^>]+noindex~i', $html)) {
            return $response;
        }

        $tag = '<script src="'.asset('js/floating-share.js').'" defer></script>';
        $response->setContent(preg_replace('~</body>~i', $tag."\n</body>", $html, 1));

        return $response;
    }
}
