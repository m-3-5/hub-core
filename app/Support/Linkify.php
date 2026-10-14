<?php

namespace App\Support;

/**
 * Rende cliccabili gli indirizzi web (solo http/https) nei testi inseriti dagli utenti, senza ammettere HTML:
 * prima si esegue l'escape di tutto, poi si trasformano i soli indirizzi in link con rel="nofollow ugc noopener".
 */
class Linkify
{
    public static function html(?string $text): string
    {
        $escaped = e((string) $text);

        return preg_replace_callback(
            '~\bhttps?://[^\s<>"\']+~i',
            function (array $m): string {
                $url = rtrim($m[0], '.,;:!?)');
                $trail = substr($m[0], strlen($url));
                $host = parse_url(html_entity_decode($url), PHP_URL_HOST) ?: $url;

                return '<a href="'.$url.'" target="_blank" rel="nofollow ugc noopener">'.e(preg_replace('~^www\.~i', '', (string) $host)).' ↗</a>'.$trail;
            },
            $escaped,
        ) ?? $escaped;
    }
}
