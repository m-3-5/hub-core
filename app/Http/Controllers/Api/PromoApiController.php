<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promo;
use App\Models\Tenant;
use App\Support\PromoLinks;
use App\Support\PromoPublicPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromoApiController extends Controller
{
    public function index(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();
        $includeExpired = $request->boolean('include_expired');

        $active = $tenant->promos()
            ->active()
            ->latest('published_at')
            ->get();

        $expired = $includeExpired
            ? $tenant->promos()->expired()->latest('ends_at')->get()
            : collect();

        $promos = $active->concat($expired);

        return response()->json([
            'tenant' => PromoPublicPresenter::tenant($tenant),
            'featured' => $active->first() ? PromoPublicPresenter::promo($active->first()) : null,
            'promos' => $promos->map(fn (Promo $promo) => PromoPublicPresenter::promo($promo))->values(),
            'active' => $active->map(fn (Promo $promo) => PromoPublicPresenter::promo($promo))->values(),
            'expired' => $expired->map(fn (Promo $promo) => PromoPublicPresenter::promo($promo))->values(),
            'meta' => [
                'count' => $promos->count(),
                'active_count' => $active->count(),
                'expired_count' => $expired->count(),
                'archive_url' => route('promo.archive', $tenant),
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
