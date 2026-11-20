<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassifiedAd;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Elenco pubblico degli annunci online di un paese, per piramide35.com (chat «Il tuo paese»).
 * GET /api/annunci?zona=Senise&tipo=vendo|cerco|lavoro&limit=12
 * Cache 5 minuti; dati pubblici, leggibili da piramide35.com (CORS aperto come tutte le /api).
 */
class ClassifiedFeedController extends Controller
{
    public const CACHE_SECONDS = 300;

    public function index(Request $request): JsonResponse
    {
        $zone = mb_substr(trim((string) $request->query('zona')), 0, 80);
        $type = array_key_exists((string) $request->query('tipo'), ClassifiedAd::PRIVATE_TYPES) ? (string) $request->query('tipo') : null;
        $limit = max(1, min(50, (int) ($request->query('limit') ?: 12)));

        $payload = Cache::remember('api-annunci:'.md5(mb_strtolower($zone).'|'.$type.'|'.$limit), self::CACHE_SECONDS, function () use ($zone, $type, $limit) {
            $query = ClassifiedAd::published()->with('tenant')->latest('published_at');

            if ($zone !== '') {
                // «Senise» trova anche «Senise (PZ)»; % e _ tolti (nessun paese li ha nel nome)
                $like = str_replace(['%', '_'], '', mb_strtolower($zone)).'%';
                $query->whereRaw('LOWER(zone) LIKE ?', [$like]);
            }
            if ($type) {
                $query->whereIn('category', ClassifiedAd::PRIVATE_TYPES[$type]['categories']);
            }

            return [
                'zona' => $zone ?: null,
                'tipo' => $type,
                'annunci' => $query->limit($limit)->get()->map(fn (ClassifiedAd $ad) => [
                    'id' => $ad->id,
                    'titolo' => $ad->title,
                    'tipo' => $ad->privateType(),
                    'categoria' => $ad->categoryLabel(),
                    'prezzo' => $ad->priceLabel(),
                    'prezzo_centesimi' => $ad->price !== null ? $ad->priceCents() : null,
                    'zona' => $ad->zone,
                    'foto' => ($url = $ad->thumbUrl()) ? url($url) : null,
                    'url' => $ad->publicUrl(),
                    'data' => $ad->published_at?->toIso8601String(),
                ])->values()->all(),
                'nuovo_url' => route('classifieds.private.create', array_filter(['zona' => $zone, 'tipo' => $type ?? 'vendo', 'ref' => 'piramide35'])),
            ];
        });

        // CORS: le rotte /api/* sono già aperte a tutti i siti (Laravel HandleCors), quindi anche a piramide35.com.
        return response()->json($payload)->header('Cache-Control', 'public, max-age='.self::CACHE_SECONDS);
    }
}
