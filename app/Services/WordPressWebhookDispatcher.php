<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use App\Support\PromoPublicPresenter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use M35\HubPayments\Models\PayableService;

class WordPressWebhookDispatcher
{
    public function promoPublished(Tenant $tenant, Promo $promo): void
    {
        $promo->loadMissing('tenant');

        $this->dispatch('promo.published', $tenant, [
            'promos_index_url' => route('api.promos.index', ['tenantSlug' => $tenant->slug]),
            'promo' => PromoPublicPresenter::promo($promo),
            'featured' => PromoPublicPresenter::promo($promo),
        ]);
    }

    public function promosSync(Tenant $tenant): void
    {
        $promo = $tenant->activePromo();

        $this->dispatch('promos.sync', $tenant, [
            'promos_index_url' => route('api.promos.index', ['tenantSlug' => $tenant->slug]),
            ...($promo ? [
                'promo' => PromoPublicPresenter::promo($promo),
                'featured' => PromoPublicPresenter::promo($promo),
            ] : []),
        ]);
    }

    public function servicePublished(Tenant $tenant, PayableService $service): void
    {
        $this->dispatch('service.published', $tenant, [
            'services_index_url' => route('api.services.index', ['tenantSlug' => $tenant->slug]),
        ], config('services.hub.services_webhook_url'));
    }

    public function servicesSync(Tenant $tenant): void
    {
        $this->dispatch('services.sync', $tenant, [
            'services_index_url' => route('api.services.index', ['tenantSlug' => $tenant->slug]),
        ], config('services.hub.services_webhook_url'));
    }

    /** @param  array<string, mixed>  $extra */
    private function dispatch(string $event, Tenant $tenant, array $extra = [], ?string $urlOverride = null): void
    {
        $url = $urlOverride ?? config('services.hub.webhook_url');
        $secret = config('services.hub.webhook_secret');

        if (! $url || ! $secret) {
            return;
        }

        $payload = array_merge([
            'event' => $event,
            'tenant' => PromoPublicPresenter::tenant($tenant),
            'synced_at' => now()->toIso8601String(),
        ], $extra);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $body, $secret);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Hub-Signature' => 'sha256='.$signature,
                    'X-Hub-Event' => $event,
                ])
                ->withBody($body, 'application/json')
                ->post($url);

            if (! $response->successful()) {
                Log::warning('Hub webhook failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Hub webhook exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
