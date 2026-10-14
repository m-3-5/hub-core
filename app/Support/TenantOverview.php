<?php

namespace App\Support;

use App\Models\ClassifiedAd;
use App\Models\CustomerTicket;
use App\Models\Promo;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use M35\HubPayments\Models\PayableOrder;
use M35\HubPayments\Models\PayableService;
use M35\HubPayments\Support\TenantConnect;

/**
 * Numeri «a colpo d'occhio» di una o più attività: cosa hanno attivo, cosa è da guardare, quanto hanno incassato.
 * Usato dalle card clienti del super admin, dalla scheda cliente e dalla home dell'attività.
 */
class TenantOverview
{
    /**
     * @param  Collection<int, Tenant>  $tenants
     * @return array<int, array<string, mixed>> indicizzato per id attività
     */
    public static function summaries(Collection $tenants): array
    {
        $ids = $tenants->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $count = fn ($query) => $query->whereIn('tenant_id', $ids)->selectRaw('tenant_id, count(*) as c')->groupBy('tenant_id')->pluck('c', 'tenant_id');

        $promosActive = $count(Promo::query()->active());
        $promosDraft = $count(Promo::query()->where('status', 'draft'));
        $services = $count(PayableService::query()->where('type', 'service')->where('status', '!=', 'archived'));
        $products = $count(PayableService::query()->where('type', 'product')->where('status', '!=', 'archived'));
        $adsPublished = $count(ClassifiedAd::query()->published());
        $adsSold = $count(ClassifiedAd::query()->where('status', 'sold'));
        $messages = $count(CustomerTicket::query()->where('status', 'new'));
        $frozen = $count(PayableOrder::query()->where('flow', 'protected')->where('payout_status', 'frozen'));

        $orders = PayableOrder::query()
            ->whereIn('tenant_id', $ids)
            ->where('status', 'paid')
            ->selectRaw("tenant_id, count(*) as c, sum(amount_cents) as total, sum(case when flow = 'protected' and payout_status = 'held' then amount_cents else 0 end) as held")
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id');

        return $tenants->mapWithKeys(function (Tenant $tenant) use ($promosActive, $promosDraft, $services, $products, $adsPublished, $adsSold, $messages, $frozen, $orders) {
            $order = $orders->get($tenant->id);

            return [$tenant->id => [
                'promos_active' => (int) ($promosActive[$tenant->id] ?? 0),
                'promos_draft' => (int) ($promosDraft[$tenant->id] ?? 0),
                'services' => (int) ($services[$tenant->id] ?? 0),
                'products' => (int) ($products[$tenant->id] ?? 0),
                'ads_published' => (int) ($adsPublished[$tenant->id] ?? 0),
                'ads_sold' => (int) ($adsSold[$tenant->id] ?? 0),
                'messages_new' => (int) ($messages[$tenant->id] ?? 0),
                'orders_count' => (int) ($order->c ?? 0),
                'orders_total_cents' => (int) ($order->total ?? 0),
                'held_cents' => (int) ($order->held ?? 0),
                'frozen_count' => (int) ($frozen[$tenant->id] ?? 0),
                'connect' => TenantConnect::state($tenant),
                'badge' => self::badge($tenant),
            ]];
        })->all();
    }

    /** @return array{label: string, tone: string} tone: ok | info | warn | off */
    public static function badge(Tenant $tenant): array
    {
        return match (true) {
            $tenant->subscription_status === 'free' => ['label' => 'Gratis', 'tone' => 'ok'],
            $tenant->subscription_status === 'active' => ['label' => 'Abbonamento attivo', 'tone' => 'ok'],
            $tenant->onTrial() => ['label' => 'In prova · '.$tenant->trialDaysRemaining().' gg', 'tone' => 'info'],
            $tenant->trialExpired() => ['label' => 'Prova scaduta', 'tone' => 'warn'],
            $tenant->subscription_status === 'trialing' => ['label' => 'In prova', 'tone' => 'info'],
            $tenant->subscription_status === 'pending_payment' => ['label' => 'Da attivare', 'tone' => 'warn'],
            $tenant->subscription_status === 'past_due' => ['label' => 'Pagamento in ritardo', 'tone' => 'warn'],
            $tenant->subscription_status === 'canceled' => ['label' => 'Annullato', 'tone' => 'off'],
            default => ['label' => ucfirst((string) $tenant->subscription_status), 'tone' => 'off'],
        };
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }
}
