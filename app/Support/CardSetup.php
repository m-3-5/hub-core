<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\HubBillingService;
use RuntimeException;

/** Carta salvata su Stripe (sessione «setup»): la stessa logica dal ritorno della pagina e dal webhook. */
class CardSetup
{
    /** @return Tenant|null l'attività a cui è stata salvata la carta */
    public static function fromStripe(string $sessionId, ?Tenant $expected = null): ?Tenant
    {
        $secretKey = config('services.hub_billing.secret_key');
        if ($sessionId === '' || ! $secretKey) {
            return null;
        }

        try {
            $card = (new HubBillingService($secretKey))->finishCardSetup($sessionId);
        } catch (RuntimeException $e) {
            report($e);

            return null;
        }

        if (! $card || ! ctype_digit($card['tenant_id'])) {
            return null;
        }

        $tenant = Tenant::find((int) $card['tenant_id']);
        // la sessione deve essere di questa attività (mai fidarsi dell'indirizzo di ritorno)
        if (! $tenant || ($expected && $expected->id !== $tenant->id)) {
            return null;
        }

        PayPerUse::saveCard($tenant, $card['customer'], $card);

        return $tenant;
    }
}
