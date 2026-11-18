<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\HubBillingService;
use RuntimeException;

/** Ricarica pagata su Stripe: la stessa logica dal ritorno della pagina e dal webhook (vale una volta sola). */
class CreditTopUp
{
    public static function fromStripe(string $sessionId, ?Tenant $expected = null): ?Tenant
    {
        $secretKey = config('services.hub_billing.secret_key');
        if ($sessionId === '' || ! $secretKey) {
            return null;
        }

        try {
            $paid = (new HubBillingService($secretKey))->finishTopUp($sessionId);
        } catch (RuntimeException $e) {
            report($e);

            return null;
        }

        if (! $paid || ! ctype_digit($paid['tenant_id'])) {
            return null;
        }

        $tenant = Tenant::find((int) $paid['tenant_id']);
        if (! $tenant || ($expected && $expected->id !== $tenant->id)) {
            return null;
        }

        PayPerUse::topUp($tenant, $paid['amount'], 'carta', $paid['session']);

        return $tenant;
    }
}
