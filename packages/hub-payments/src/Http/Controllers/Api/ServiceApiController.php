<?php

namespace M35\HubPayments\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use M35\HubPayments\Models\PayableService;

class ServiceApiController extends Controller
{
    public function index(string $tenantSlug): JsonResponse
    {
        return $this->catalog($tenantSlug, 'service', 'services');
    }

    public function products(string $tenantSlug): JsonResponse
    {
        return $this->catalog($tenantSlug, 'product', 'products');
    }

    private function catalog(string $tenantSlug, string $type, string $key): JsonResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $items = PayableService::query()
            ->where('tenant_id', $tenant->id)
            ->where('type', $type)
            ->where('status', 'active')
            ->where('published_to_site', true)
            ->latest()
            ->get();

        return response()->json([
            'tenant' => [
                'slug' => $tenant->slug,
                'name' => $tenant->name,
                'primary_color' => $tenant->primary_color,
            ],
            $key => $items->map(fn (PayableService $s) => [
                'id' => $s->id,
                'title' => $s->title,
                'slug' => $s->slug,
                'description' => $s->description,
                'cover_image_url' => $s->coverImageUrl(),
                'amount_cents' => $s->amount_cents,
                'amount_label' => $s->amountEuros().' €',
                'currency' => $s->currency,
                'payment_url' => $s->payment_url,
                'public_url' => route('services.public.show', [$tenant, $s]),
                'status' => $s->status,
            ])->values(),
            'meta' => [
                'count' => $items->count(),
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
