<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\Tenant;
use App\Support\PromoPublicPresenter;
use App\Support\TenantSiteSync;
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
        ], catalog: true);
    }

    public function servicesSync(Tenant $tenant): void
    {
        $this->dispatch('services.sync', $tenant, [
            'services_index_url' => route('api.services.index', ['tenantSlug' => $tenant->slug]),
            'products_index_url' => route('api.products.index', ['tenantSlug' => $tenant->slug]),
        ], catalog: true);
    }

    /**
     * Invia un avviso di prova all'indirizzo del tenant e restituisce lo stato HTTP (null se non raggiungibile).
     *
     * @return array{url: string, status: ?int, error: ?string}|null null se il tenant non ha un indirizzo
     */
    public function ping(Tenant $tenant): ?array
    {
        $url = TenantSiteSync::url($tenant);

        if (! $url) {
            return null;
        }

        $result = $this->send($url, TenantSiteSync::secret($tenant) ?? config('services.hub.webhook_secret'), 'site.ping', $tenant, [
            'services_index_url' => route('api.services.index', ['tenantSlug' => $tenant->slug]),
            'products_index_url' => route('api.products.index', ['tenantSlug' => $tenant->slug]),
        ]);

        return ['url' => $url] + $result;
    }

    /**
     * Indirizzi che ricevono l'evento.
     * - catalogo (servizi/prodotti): indirizzo del tenant; se manca, i vecchi indirizzi globali del .env;
     * - promo: vecchio indirizzo globale (finché il vecchio sito convive) e indirizzo del tenant.
     *
     * @return array<int, array{0: string, 1: ?string}> coppie [indirizzo, segreto di firma]
     */
    private function targets(Tenant $tenant, bool $catalog): array
    {
        $global = config('services.hub.webhook_secret');
        $own = TenantSiteSync::url($tenant);
        $ownTarget = $own ? [$own, TenantSiteSync::secret($tenant) ?? $global] : null;

        $targets = [];

        if ($catalog) {
            if ($ownTarget) {
                $targets[] = $ownTarget;
            } elseif ($fallback = config('services.hub.services_webhook_url') ?: config('services.hub.webhook_url')) {
                $targets[] = [$fallback, $global];
            }
        } else {
            $legacy = config('services.hub.webhook_url');

            if ($legacy && (! $own || TenantSiteSync::sendsLegacyPromos($tenant))) {
                $targets[] = [$legacy, $global];
            }

            if ($ownTarget && TenantSiteSync::sendsPromos($tenant)) {
                $targets[] = $ownTarget;
            }
        }

        $seen = [];

        return array_values(array_filter($targets, function (array $target) use (&$seen) {
            if (! $target[0] || ! $target[1] || isset($seen[$target[0]])) {
                return false;
            }

            return $seen[$target[0]] = true;
        }));
    }

    /** @param  array<string, mixed>  $extra */
    private function dispatch(string $event, Tenant $tenant, array $extra = [], bool $catalog = false): void
    {
        if (! $tenant->hasAutoSiteSync()) {
            return;
        }

        foreach ($this->targets($tenant, $catalog) as [$url, $secret]) {
            $this->send($url, $secret, $event, $tenant, $extra);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{status: ?int, error: ?string}
     */
    private function send(string $url, ?string $secret, string $event, Tenant $tenant, array $extra): array
    {
        if (! $secret) {
            return ['status' => null, 'error' => 'Segreto di firma mancante (HUB_WEBHOOK_SECRET).'];
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

            return ['status' => $response->status(), 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('Hub webhook exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return ['status' => null, 'error' => $e->getMessage()];
        }
    }
}
