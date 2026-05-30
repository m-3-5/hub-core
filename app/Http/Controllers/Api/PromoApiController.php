<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Tenant;
use App\Support\PromoLinks;
use App\Support\PromoPublicPresenter;
use Illuminate\Http\JsonResponse;

class PromoApiController extends Controller
{
    public function index(string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $promos = $tenant->promos()
            ->active()
            ->latest('published_at')
            ->get();

        $featured = $promos->first();

        return response()->json([
            'tenant' => PromoPublicPresenter::tenant($tenant),
            'featured' => $featured ? PromoPublicPresenter::promo($featured) : null,
            'promos' => $promos->map(fn (Promo $promo) => PromoPublicPresenter::promo($promo))->values(),
            'meta' => [
                'count' => $promos->count(),
                'embed_script' => route('embed.script', ['tenantSlug' => $tenant->slug]),
                'promos_page_url' => PromoLinks::promosPageUrl($tenant),
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function show(string $tenantSlug, string $promoSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $promo = $tenant->promos()
            ->where('slug', $promoSlug)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json([
            'tenant' => PromoPublicPresenter::tenant($tenant),
            'promo' => PromoPublicPresenter::promo($promo),
        ]);
    }
}
