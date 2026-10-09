<?php

namespace M35\HubPayments\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use M35\HubPayments\Services\StripePaymentLinkService;
use M35\HubPayments\Support\TenantStripeConfig;
use M35\HubPayments\Support\TenantStripeWebhook;
use RuntimeException;

/** Collega il webhook "pagato" al conto Stripe del tenant: in automatico o incollando il segreto di firma. */
class StripeWebhookSettingsController extends Controller
{
    public function create(Tenant $tenant): RedirectResponse
    {
        $secretKey = TenantStripeConfig::secretKey($tenant);

        if (! $secretKey) {
            return back()->withErrors(['stripe' => 'Salva prima la Secret key Stripe.']);
        }

        $stripe = new StripePaymentLinkService($secretKey);
        $oldEndpoint = TenantStripeWebhook::endpointId($tenant);

        try {
            $endpoint = $stripe->createWebhookEndpoint(
                TenantStripeWebhook::url($tenant),
                TenantStripeWebhook::EVENTS,
                'Hub Core: notifica pagamenti',
            );
        } catch (RuntimeException $e) {
            Log::warning('Creazione webhook Stripe fallita', ['tenant' => $tenant->slug, 'message' => $e->getMessage()]);

            return back()
                ->with('webhook_manual', true)
                ->withErrors(['webhook' => 'Non sono riuscito a creare il webhook in automatico: probabilmente la chiave Stripe non ha il permesso "Webhook endpoints: scrittura". Segui i passi manuali qui sotto (ci vuole un minuto).']);
        }

        TenantStripeWebhook::store($tenant, $endpoint['secret'], $endpoint['id']);

        // Se c'era già un webhook creato da noi, lo toglie per non ricevere ogni evento due volte.
        if ($oldEndpoint && $oldEndpoint !== $endpoint['id']) {
            try {
                $stripe->deleteWebhookEndpoint($oldEndpoint);
            } catch (RuntimeException) {
                // Già rimosso o chiave senza permesso: non è un problema.
            }
        }

        return back()->with('status', 'Webhook collegato al conto Stripe: da ora i pagamenti risultano "pagati" e ricevi l\'email.');
    }

    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'webhook_secret' => ['required', 'string', 'regex:/^whsec_[A-Za-z0-9]{16,}$/'],
        ], [
            'webhook_secret.regex' => 'Il segreto di firma inizia con "whsec_" (lo trovi nel webhook su Stripe, "Segreto di firma").',
        ]);

        TenantStripeWebhook::store($tenant, $data['webhook_secret']);

        return back()->with('status', 'Segreto di firma salvato. Per provare: fai un pagamento di prova o usa "Invia evento di test" su Stripe.');
    }
}
