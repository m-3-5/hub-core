<?php

namespace M35\HubPayments\Support;

use App\Models\Tenant;

/**
 * Stato del collegamento Stripe Connect di un'attività (pagamenti protetti da Hub Core).
 * Convive con le chiavi Stripe proprie (TenantStripeConfig): vendite dirette sul proprio sito restano com'erano.
 */
class TenantConnect
{
    public static function accountId(Tenant $tenant): ?string
    {
        $id = $tenant->settings['connect']['account_id'] ?? null;

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** Può incassare e ricevere bonifici: solo allora le vendite sul canale hub passano dai pagamenti protetti. */
    public static function isReady(Tenant $tenant): bool
    {
        return self::accountId($tenant) !== null
            && ! empty($tenant->settings['connect']['charges_enabled'])
            && ! empty($tenant->settings['connect']['payouts_enabled']);
    }

    /** 'none' (non iniziato) | 'incomplete' (mancano dati per Stripe) | 'ready'. */
    public static function state(Tenant $tenant): string
    {
        if (self::accountId($tenant) === null) {
            return 'none';
        }

        return self::isReady($tenant) ? 'ready' : 'incomplete';
    }

    /** @return array<int, string> cosa chiede ancora Stripe (testi tecnici di Stripe, per assistenza) */
    public static function missing(Tenant $tenant): array
    {
        return array_values((array) ($tenant->settings['connect']['currently_due'] ?? []));
    }

    public static function setAccount(Tenant $tenant, string $accountId): void
    {
        $settings = $tenant->settings ?? [];
        $settings['connect'] = array_merge($settings['connect'] ?? [], ['account_id' => $accountId]);

        $tenant->forceFill(['settings' => $settings])->save();
    }

    /** @param  array<string, mixed>  $account  oggetto Account di Stripe */
    public static function sync(Tenant $tenant, array $account): void
    {
        $settings = $tenant->settings ?? [];
        $settings['connect'] = array_merge($settings['connect'] ?? [], [
            'account_id' => $account['id'] ?? self::accountId($tenant),
            'charges_enabled' => (bool) ($account['charges_enabled'] ?? false),
            'payouts_enabled' => (bool) ($account['payouts_enabled'] ?? false),
            'details_submitted' => (bool) ($account['details_submitted'] ?? false),
            'currently_due' => array_values((array) ($account['requirements']['currently_due'] ?? [])),
            'synced_at' => now()->toIso8601String(),
        ]);

        $tenant->forceFill(['settings' => $settings])->save();
    }
}
