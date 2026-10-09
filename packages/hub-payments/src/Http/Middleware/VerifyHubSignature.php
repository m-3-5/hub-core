<?php

namespace M35\HubPayments\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chiamate riservate dal sito del cliente verso l'hub (checkout, preventivi).
 *
 * X-Hub-Timestamp: <unix time>
 * X-Hub-Signature: sha256=HMAC_SHA256(timestamp + "." + corpo_grezzo, HUB_BRIDGE_SECRET)
 */
class VerifyHubSignature
{
    private const MAX_AGE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $secret = config('hub.bridge_secret');

        if (! $secret) {
            return $this->reject('Firma non configurata sul server.', 503);
        }

        $timestamp = (string) $request->header('X-Hub-Timestamp', '');
        $signature = (string) $request->header('X-Hub-Signature', '');

        if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::MAX_AGE_SECONDS) {
            return $this->reject('Timestamp mancante o scaduto.');
        }

        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        if (! hash_equals($expected, $signature)) {
            return $this->reject('Firma non valida.');
        }

        return $next($request);
    }

    private function reject(string $message, int $status = 401): Response
    {
        return response()->json(['message' => $message], $status);
    }
}
