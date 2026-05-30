<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use App\Support\PromoPublicPresenter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WordPressWebhookDispatcher
{
    public function promoPublished(Tenant $tenant, Promo $promo): void
    {
        $this->dispatch('promo.published', $tenant, $promo);
    }

    public function promosSync(Tenant $tenant): void
    {
        $this->dispatch('promos.sync', $tenant, $tenant->activePromo());
    }

    public function dispatch(string $event, Tenant $tenant, ?Promo $promo): void
    {
        $url = config('services.hub.webhook_url');
        $secret = config('services.hub.webhook_secret');

        if (! $url || ! $secret) {
            return;
        }

        $payload = [
            'event' => $event,
            'tenant' => PromoPublicPresenter::tenant($tenant),
            'promos_index_url' => route('api.promos.index', ['tenantSlug' => $tenant->slug]),
            'synced_at' => now()->toIso8601String(),
        ];

        if ($promo) {
            $promo->loadMissing('tenant');
            $payload['promo'] = PromoPublicPresenter::promo($promo);
            $payload['featured'] = PromoPublicPresenter::promo($promo);
        }

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
